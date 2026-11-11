<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Approval;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserConsent;
use App\Models\LegalDocument;
use App\Notifications\Notice;
use App\Services\Audit;
use App\Services\Otp;
use App\Services\Settings;
use App\Services\Totp;
use App\Support\ApiException;
use App\Support\Present;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

/** Autentikasi & verifikasi akun (PRD Bab 44, Design Spec §9). Pesan gagal login selalu generik. */
class AuthController extends Controller
{
    private function throttleKey(Request $r, string $login): string
    {
        return 'login:'.mb_strtolower($login).'|'.$r->ip();
    }

    private function verifyTurnstile(Request $r): void
    {
        $secret = Settings::get('security.turnstile_secret');
        if (! $secret) {
            return; // Turnstile belum dikonfigurasi Admin
        }
        $token = (string) $r->input('turnstile_token');
        if (! $token) {
            throw new ApiException('captcha_required', 'Verifikasi keamanan diperlukan.', 422);
        }
        $ok = Http::asForm()->timeout(8)->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', ['secret' => $secret, 'response' => $token, 'remoteip' => $r->ip()])->json('success');
        if (! $ok) {
            throw new ApiException('captcha_required', 'Verifikasi keamanan gagal.', 422);
        }
    }

    private function findByLogin(string $login): ?User
    {
        $login = trim($login);
        if (str_contains($login, '@')) {
            return User::where('email', mb_strtolower($login))->first();
        }
        $digits = preg_replace('/\D/', '', $login);
        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        }

        return User::where('phone', '+'.$digits)->first();
    }

    public function login(Request $r)
    {
        $r->validate(['login' => 'required|string|max:190', 'password' => 'required|string|max:1024']);
        $key = $this->throttleKey($r, $r->input('login'));
        $max = (int) Settings::get('security.login_max_attempts');
        if (RateLimiter::tooManyAttempts($key, $max)) {
            Audit::security('login_locked', null, 'warning', ['login' => Str::mask($r->input('login'), '*', 3)]);
            throw new ApiException('too_many_attempts', 'Terlalu banyak percobaan.', 429, ['retry_after' => RateLimiter::availableIn($key)]);
        }
        if (RateLimiter::attempts($key) >= 3) {
            $this->verifyTurnstile($r);
        }
        $user = $this->findByLogin($r->input('login'));
        if (! $user || ! Hash::check($r->input('password'), $user->password)) {
            RateLimiter::hit($key, 15 * 60);
            Audit::security('login_failed', $user?->id);
            throw new ApiException('invalid_credentials', 'Email atau kata sandi salah.', 422);
        }
        if ($user->isAdmin()) {
            RateLimiter::hit($key, 15 * 60);
            throw new ApiException('admin_use_admin_login', 'Gunakan halaman login Admin.', 422);
        }
        RateLimiter::clear($key);
        Auth::guard('web')->login($user, $r->boolean('remember'));
        $r->session()->regenerate();
        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $r->ip()])->save();
        Audit::log('auth.login', $user, [], $user->id);

        return ['data' => Present::me($user)];
    }

    public function register(Request $r)
    {
        $key = 'register:'.$r->ip();
        if (RateLimiter::tooManyAttempts($key, 10)) {
            throw new ApiException('too_many_attempts', 'Terlalu banyak percobaan.', 429, ['retry_after' => RateLimiter::availableIn($key)]);
        }
        RateLimiter::hit($key, 3600);
        $this->verifyTurnstile($r);
        $data = $r->validate([
            'name' => 'required|string|min:2|max:120',
            'email' => 'required|email:rfc|max:190|unique:users,email',
            'phone' => ['nullable', 'regex:/^\+\d{8,15}$/', 'unique:users,phone'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers()->uncompromised(0)],
            'agree_terms' => 'accepted',
            'referral' => 'nullable|string|max:16',
            'locale' => 'nullable|in:id,en',
        ], [
            'email.unique' => 'Email ini sudah terdaftar. Silakan masuk atau gunakan email lain.',
            'agree_terms.accepted' => 'Anda perlu menyetujui Syarat & Ketentuan.',
        ]);
        $low = mb_strtolower($data['password']);
        foreach ([explode('@', $data['email'])[0], $data['name']] as $p) {
            if (mb_strlen($p) >= 3 && str_contains($low, mb_strtolower($p))) {
                throw new ApiException('validation_failed', 'Kata sandi terlalu mirip dengan data pribadi.', 422, [], ['password' => ['Kata sandi tidak boleh memuat nama atau email Anda.']]);
            }
        }
        $referrer = ! empty($data['referral']) ? User::where('referral_code', strtoupper($data['referral']))->first() : null;
        $user = new User([
            'name' => $data['name'],
            'email' => mb_strtolower($data['email']),
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
            'locale' => $data['locale'] ?? 'id',
            'referred_by' => $referrer?->id,
        ]);
        $user->forceFill(['role' => 'user', 'status' => 'pending_verification', 'referral_code' => $this->newReferralCode()])->save();
        foreach (['terms', 'privacy'] as $docType) {
            $doc = LegalDocument::where('type', $docType)->where('is_active', true)->latest('effective_at')->first();
            UserConsent::create(['user_id' => $user->id, 'document' => $docType, 'version' => $doc?->version ?? '1.0', 'ip_hash' => hash('sha256', (string) $r->ip()), 'device' => Str::limit((string) $r->userAgent(), 250), 'locale' => $user->locale, 'agreed_at' => now()]);
        }
        Auth::guard('web')->login($user);
        $r->session()->regenerate();
        try {
            Otp::issue($user);
        } catch (ApiException) {
            // pengiriman bisa diulang dari layar OTP
        }
        Audit::log('auth.register', $user, ['referred' => (bool) $referrer], $user->id);

        return response()->json(['data' => Present::me($user->fresh())], 201);
    }

    private function newReferralCode(): string
    {
        do {
            $c = strtoupper(Str::random(8));
        } while (User::where('referral_code', $c)->exists());

        return $c;
    }

    public function logout(Request $r)
    {
        if ($u = $r->user()) {
            Audit::log('auth.logout', $u, [], $u->id);
        }
        Auth::guard('web')->logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return response()->noContent();
    }

    private function otpPayload(User $u): array
    {
        $otp = Otp::current($u);

        return [
            'destination_masked' => Otp::mask($otp?->destination ?? $u->email),
            'channel' => $otp?->channel ?? 'email',
            'expires_at' => $otp?->expires_at?->toIso8601String(),
            'resend_available_at' => $otp ? $otp->created_at->copy()->addSeconds((int) Settings::get('otp.resend_seconds'))->toIso8601String() : null,
            'attempts_left' => $otp ? max(0, (int) Settings::get('otp.max_attempts') - $otp->attempts) : 0,
            'whatsapp_available' => (bool) Settings::get('whatsapp.enabled') && (bool) $u->phone,
            // Hanya APP_ENV=local, agar alur dapat diuji tanpa SMTP. Tidak pernah ada di production.
            'dev_code' => app()->environment('local') ? cache()->get("otp.dev.{$u->id}") : null,
        ];
    }

    private function pendingUser(Request $r): User
    {
        $u = $r->user();
        if (! $u) {
            throw new ApiException('unauthenticated', 'Silakan masuk.', 401);
        }

        return $u;
    }

    public function otpStatus(Request $r)
    {
        $u = $this->pendingUser($r);
        if ($u->status === 'pending_verification' && ! Otp::current($u)) {
            try {
                Otp::issue($u);
            } catch (ApiException) {
            }
        }

        return ['data' => $this->otpPayload($u)];
    }

    public function otpResend(Request $r)
    {
        $u = $this->pendingUser($r);
        Otp::issue($u, $r->input('channel', 'email') === 'whatsapp' ? 'whatsapp' : 'email');

        return ['data' => $this->otpPayload($u)];
    }

    public function otpChangeEmail(Request $r)
    {
        $u = $this->pendingUser($r);
        if ($u->status !== 'pending_verification') {
            throw new ApiException('forbidden', 'Email sudah terverifikasi.', 403);
        }
        $data = $r->validate(['email' => 'required|email:rfc|max:190|unique:users,email,'.$u->id]);
        $u->forceFill(['email' => mb_strtolower($data['email'])])->save();
        Otp::issue($u);

        return ['data' => $this->otpPayload($u)];
    }

    public function otpVerify(Request $r)
    {
        $u = $this->pendingUser($r);
        $r->validate(['code' => 'required|digits:6']);
        Otp::verify($u, $r->input('code'));
        $manual = Settings::get('registration.mode') === 'manual';
        $u->forceFill(['email_verified_at' => now(), 'status' => $manual ? 'pending_approval' : 'active'])->save();
        if ($manual) {
            Approval::create(['type' => 'account', 'subject_type' => 'User', 'subject_id' => $u->id, 'user_id' => $u->id, 'title' => 'Persetujuan akun '.$u->email, 'payload' => ['name' => $u->name, 'email' => $u->email, 'phone' => $u->phone]]);
            Notification::send(User::where('role', 'admin')->get(), new Notice('Akun menunggu persetujuan', $u->email, 'approval', '/admin/approvals/accounts'));
        } else {
            self::grantFreePlan($u);
        }
        Audit::log('auth.verified', $u, [], $u->id);

        return ['data' => Present::me($u->fresh())];
    }

    public static function grantFreePlan(User $u, bool $force = false): void
    {
        $free = Plan::where('code', 'free')->where('is_active', true)->first();
        if (! $free || (! $force && $u->subscriptions()->exists())) {
            return;
        }
        Subscription::create([
            'user_id' => $u->id, 'source' => 'plan', 'plan_code' => $free->code, 'plan_name' => $free->name_id, 'cycle' => 'monthly', 'price' => 0,
            'snapshot' => ['features' => $free->features, 'quotas' => $free->quotas, 'limits' => $free->limits],
            'status' => 'active', 'starts_at' => now(), 'ends_at' => null,
        ]);
        \App\Services\Messenger::notify($u, 'welcome', ['plan' => $free->name_id], 'Selamat datang di Krafio', 'Akun Anda aktif dengan paket '.$free->name_id.'.', '/app');
    }

    public function forgot(Request $r)
    {
        $r->validate(['email' => 'required|email']);
        $key = 'forgot:'.$r->ip();
        if (! RateLimiter::tooManyAttempts($key, 5)) {
            RateLimiter::hit($key, 3600);
            try {
                Password::broker()->sendResetLink(['email' => mb_strtolower($r->input('email'))]);
            } catch (\Throwable) {
            }
        }

        // Respons netral, selalu sama (Design Spec §9.6)
        return ['data' => ['sent' => true]];
    }

    public function reset(Request $r)
    {
        $r->validate(['token' => 'required', 'email' => 'required|email', 'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers()]]);
        $status = Password::broker()->reset($r->only('email', 'password', 'password_confirmation', 'token'), function (User $u, string $pw) {
            $u->forceFill(['password' => $pw, 'remember_token' => Str::random(60)])->save();
            // Cabut semua sesi lain (§9.6 butir 3)
            \Illuminate\Support\Facades\DB::table('sessions')->where('user_id', $u->id)->delete();
            Audit::log('auth.password_reset', $u, [], $u->id);
        });
        if ($status !== Password::PASSWORD_RESET) {
            throw new ApiException('token_invalid', 'Tautan reset tidak valid atau kedaluwarsa.', 410);
        }

        return ['data' => ['reset' => true]];
    }

    /* ---------------- Admin: password → MFA TOTP wajib ---------------- */
    public function adminLogin(Request $r)
    {
        $r->validate(['email' => 'required|email', 'password' => 'required|string|max:1024']);
        $key = 'admin-login:'.$r->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw new ApiException('too_many_attempts', 'Terlalu banyak percobaan.', 429, ['retry_after' => RateLimiter::availableIn($key)]);
        }
        $u = User::where('email', mb_strtolower($r->input('email')))->where('role', 'admin')->first();
        if (! $u || ! Hash::check($r->input('password'), $u->password) || in_array($u->status, ['suspended', 'banned', 'deactivated'], true)) {
            RateLimiter::hit($key, 15 * 60);
            Audit::security('admin_login_failed', $u?->id, 'warning');
            throw new ApiException('invalid_credentials', 'Email atau kata sandi salah.', 422);
        }
        RateLimiter::clear($key);
        Auth::guard('web')->login($u);
        $r->session()->regenerate();
        session()->forget('admin_mfa_passed');
        if ($u->mfa_enabled && $u->mfa_secret) {
            return ['data' => ['mfa' => 'verify']];
        }
        $secret = Totp::generateSecret();
        session(['admin_mfa_setup' => $secret]);

        return ['data' => ['mfa' => 'setup', 'secret' => $secret, 'otpauth' => Totp::uri($secret, $u->email)]];
    }

    public function adminMfa(Request $r)
    {
        $u = $r->user();
        if (! $u || ! $u->isAdmin()) {
            throw new ApiException('unauthenticated', 'Silakan masuk.', 401);
        }
        $r->validate(['code' => 'required|string']);
        $key = 'admin-mfa:'.$u->id;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw new ApiException('too_many_attempts', 'Terlalu banyak percobaan.', 429, ['retry_after' => RateLimiter::availableIn($key)]);
        }
        $secret = $u->mfa_enabled ? $u->mfa_secret : session('admin_mfa_setup');
        if (! $secret || ! Totp::verify($secret, $r->input('code'))) {
            RateLimiter::hit($key, 300);
            Audit::security('admin_mfa_failed', $u->id, 'warning');
            throw new ApiException('mfa_invalid', 'Kode autentikator salah.', 422);
        }
        RateLimiter::clear($key);
        if (! $u->mfa_enabled) {
            $u->forceFill(['mfa_secret' => $secret, 'mfa_enabled' => true])->save();
            session()->forget('admin_mfa_setup');
        }
        session(['admin_mfa_passed' => true, 'admin_last_seen' => time()]);
        $u->forceFill(['last_login_at' => now(), 'last_login_ip' => $r->ip()])->save();
        Audit::log('admin.login', $u, [], $u->id);

        return ['data' => Present::me($u)];
    }
}
