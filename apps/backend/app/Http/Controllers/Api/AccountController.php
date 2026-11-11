<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Audit;
use App\Services\Otp;
use App\Services\Settings;
use App\Support\ApiException;
use App\Support\Present;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

/**
 * Akun yang dibuat dari Admin Panel:
 *  - Admin membuat akun tipe User atau Admin, langsung aktif.
 *  - Kata sandi awal diisi Admin atau dibuat otomatis (ditampilkan sekali untuk disalin).
 *  - Akun ditandai "wajib ganti kata sandi" (pengingat, bukan paksaan) sampai pemilik menggantinya.
 *  - Akun tipe User wajib memverifikasi email ATAU nomor HP sebelum memakai User Panel.
 */
class AccountController extends Controller
{
    /* ---------------- Admin: buat akun ---------------- */
    public function store(Request $r)
    {
        // Nomor HP boleh diketik dengan spasi/tanda hubung (mis. 0812 3456 7890) — dinormalisasi ke E.164 sebelum divalidasi
        if ($r->filled('phone')) {
            $r->merge(['phone' => self::normalizePhone((string) $r->input('phone'))]);
        }
        $d = $r->validate([
            'type' => 'required|in:user,admin',
            'name' => 'required|string|min:2|max:120',
            'email' => 'required|email:rfc|max:190|unique:users,email',
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9]{8,15}$/', 'unique:users,phone'],
            'password' => ['nullable', 'string', PasswordRule::min(8)->letters()->numbers()],
        ]);
        $generated = empty($d['password']);
        $password = $generated ? self::generatePassword() : $d['password'];
        $phone = $d['phone'] ?? null;

        $u = new User(['name' => $d['name'], 'email' => mb_strtolower($d['email']), 'phone' => $phone, 'password' => $password]);
        $u->forceFill([
            'role' => $d['type'],
            'status' => 'active',
            'referral_code' => AuthController::newReferralCode(),
            'must_change_password' => true,
            'created_by' => $r->user()->id,
        ])->save();
        if ($d['type'] === 'user') {
            AuthController::grantFreePlan($u);
        }
        Audit::log('admin.account.created', $u, ['type' => $d['type'], 'password' => $generated ? 'generated' : 'manual']);

        return response()->json(['data' => [
            'id' => $u->id,
            'type' => $u->role,
            'name' => $u->name,
            'email' => $u->email,
            'phone' => $u->phone,
            'login_url' => $u->role === 'admin' ? '/admin/login' : '/login',
            // Ditampilkan sekali saja agar Admin bisa menyampaikannya ke pemilik akun; tidak disimpan dalam bentuk terbaca.
            'password' => $generated ? $password : null,
            'needs_verification' => $u->needsContactVerification(),
        ]], 201);
    }

    public static function generatePassword(): string
    {
        // 12 karakter, huruf besar/kecil + angka, tanpa karakter yang mudah tertukar (0/O, 1/l/I)
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
        do {
            $p = '';
            for ($i = 0; $i < 12; $i++) {
                $p .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (! preg_match('/[A-Za-z]/', $p) || ! preg_match('/\d/', $p));

        return $p;
    }

    public static function normalizePhone(string $p): string
    {
        $digits = preg_replace('/\D/', '', $p);
        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        }

        return '+'.$digits;
    }

    /* ---------------- User: verifikasi email atau nomor HP ---------------- */
    private function payload(User $u): array
    {
        $otp = Otp::current($u);

        return [
            'needs_verification' => $u->needsContactVerification(),
            'email' => Otp::mask($u->email),
            'phone' => $u->phone ? Otp::mask($u->phone) : null,
            'email_verified' => (bool) $u->email_verified_at,
            'phone_verified' => (bool) $u->phone_verified_at,
            'whatsapp_available' => (bool) Settings::get('whatsapp.enabled') && (bool) $u->phone,
            'channel' => $otp?->channel,
            'destination_masked' => $otp ? Otp::mask($otp->destination) : null,
            'expires_at' => $otp?->expires_at?->toIso8601String(),
            'resend_available_at' => $otp ? $otp->created_at->copy()->addSeconds((int) Settings::get('otp.resend_seconds'))->toIso8601String() : null,
            'attempts_left' => $otp ? max(0, (int) Settings::get('otp.max_attempts') - $otp->attempts) : null,
            // Hanya APP_ENV=local, agar alur dapat diuji tanpa SMTP/WhatsApp. Tidak pernah ada di production.
            'dev_code' => app()->environment('local') ? cache()->get("otp.dev.{$u->id}") : null,
        ];
    }

    public function verifyStatus(Request $r)
    {
        return ['data' => $this->payload($r->user())];
    }

    public function verifySend(Request $r)
    {
        $u = $r->user();
        $channel = $r->validate(['channel' => 'required|in:email,whatsapp'])['channel'];
        if ($channel === 'whatsapp' && ! $u->phone) {
            throw new ApiException('validation_failed', 'Nomor HP belum diisi.', 422, [], ['channel' => ['Tambahkan nomor HP di pengaturan akun terlebih dahulu.']]);
        }
        Otp::issue($u, $channel);

        return ['data' => $this->payload($u)];
    }

    public function verifyConfirm(Request $r)
    {
        $u = $r->user();
        $r->validate(['code' => 'required|digits:6']);
        $otp = Otp::current($u);
        Otp::verify($u, $r->input('code'));
        $u->forceFill($otp?->channel === 'whatsapp' ? ['phone_verified_at' => now()] : ['email_verified_at' => now()])->save();
        Audit::log('auth.contact_verified', $u, ['channel' => $otp?->channel ?? 'email'], $u->id);

        return ['data' => Present::me($u->fresh())];
    }

    /** Admin: kirim ulang instruksi / reset kata sandi akun yang dibuatnya (opsional, untuk kasus lupa). */
    public function resetPassword(Request $r, User $user)
    {
        if ($user->id === $r->user()->id) {
            throw new ApiException('forbidden', 'Gunakan menu ganti kata sandi untuk akun Anda sendiri.', 403);
        }
        $password = self::generatePassword();
        $user->forceFill(['password' => $password, 'must_change_password' => true, 'remember_token' => Str::random(60)])->save();
        \Illuminate\Support\Facades\DB::table('sessions')->where('user_id', $user->id)->delete();
        Audit::log('admin.account.password_reset', $user);

        return ['data' => ['password' => $password]];
    }
}
