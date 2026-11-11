<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Approval;
use App\Models\LegalDocument;
use App\Models\User;
use App\Models\UserConsent;
use App\Notifications\Notice;
use App\Services\Audit;
use App\Support\ApiException;
use App\Support\Present;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

class MeController extends Controller
{
    public function show(Request $r)
    {
        return ['data' => Present::me($r->user())];
    }

    public function update(Request $r)
    {
        $u = $r->user();
        $data = $r->validate(['name' => 'required|string|min:2|max:120', 'phone' => ['nullable', 'regex:/^\+\d{8,15}$/', 'unique:users,phone,'.$u->id]], ['phone.regex' => 'Gunakan format internasional, contoh +6281234567890.']);
        $u->update(['name' => $data['name'], 'phone' => $data['phone'] ?: null]);
        Audit::log('profile.updated', $u);

        return ['data' => Present::me($u)];
    }

    public function preferences(Request $r)
    {
        $data = $r->validate(['theme' => 'nullable|in:system,light,dark', 'locale' => 'nullable|in:id,en', 'loader' => 'nullable|in:auto,transparent,dark']);
        $r->user()->update(array_filter($data));

        return response()->noContent();
    }

    public function password(Request $r)
    {
        $u = $r->user();
        $r->validate(['current_password' => 'required', 'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers()]]);
        if (! Hash::check($r->input('current_password'), $u->password)) {
            throw new ApiException('validation_failed', 'Kata sandi saat ini salah.', 422, [], ['current_password' => ['Kata sandi saat ini salah.']]);
        }
        $u->update(['password' => $r->input('password')]);
        $u->forceFill(['must_change_password' => false])->save();
        DB::table('sessions')->where('user_id', $u->id)->where('id', '!=', $r->session()->getId())->delete();
        \App\Services\Messenger::notify($u, 'password_changed', ['time' => now()->format('d-m-Y H:i')], 'Kata sandi diubah', 'Kata sandi akun Anda baru saja diubah. Jika bukan Anda, segera hubungi support.');
        Audit::log('auth.password_changed', $u);

        return response()->noContent();
    }

    public function sessions(Request $r)
    {
        $cur = $r->session()->getId();

        return ['data' => DB::table('sessions')->where('user_id', $r->user()->id)->orderByDesc('last_activity')->get()->map(fn ($s) => [
            'id' => substr(hash('sha256', $s->id), 0, 16),
            'device' => Str::limit((string) $s->user_agent, 80) ?: 'Perangkat tidak dikenal',
            'ip' => $s->ip_address,
            'last_active' => date(DATE_ATOM, $s->last_activity),
            'current' => $s->id === $cur,
        ])];
    }

    public function revokeOthers(Request $r)
    {
        DB::table('sessions')->where('user_id', $r->user()->id)->where('id', '!=', $r->session()->getId())->delete();
        Audit::log('auth.sessions_revoked', $r->user());

        return response()->noContent();
    }

    public function consent(Request $r)
    {
        $data = $r->validate(['document' => 'required|in:terms,privacy,refund,affiliate_terms,cookies', 'version' => 'required|string|max:20']);
        $doc = LegalDocument::where('type', $data['document'])->where('version', $data['version'])->first();
        if (! $doc) {
            throw new ApiException('not_found', 'Dokumen tidak ditemukan.', 404);
        }
        UserConsent::create(['user_id' => $r->user()->id, 'document' => $doc->type, 'version' => $doc->version, 'ip_hash' => hash('sha256', (string) $r->ip()), 'device' => Str::limit((string) $r->userAgent(), 250), 'locale' => Present::lang(), 'agreed_at' => now()]);

        return response()->noContent();
    }

    public function cookieConsent(Request $r)
    {
        UserConsent::create(['user_id' => $r->user()->id, 'document' => 'cookies', 'version' => (string) $r->input('version', '1.0'), 'payload' => $r->only('analytics', 'functional', 'marketing'), 'ip_hash' => hash('sha256', (string) $r->ip()), 'locale' => Present::lang(), 'agreed_at' => now()]);

        return response()->noContent();
    }

    public function aiConsent(Request $r)
    {
        $u = $r->user();
        if (! $u->ai_consent_at) {
            $u->forceFill(['ai_consent_at' => now()])->save();
            UserConsent::create(['user_id' => $u->id, 'document' => 'ai_processing', 'version' => '1.0', 'ip_hash' => hash('sha256', (string) $r->ip()), 'locale' => Present::lang(), 'agreed_at' => now()]);
        }

        return response()->noContent();
    }

    public function notifications(Request $r)
    {
        $q = $r->user()->notifications();
        if ($r->boolean('unread')) {
            $q->whereNull('read_at');
        }

        return ['data' => $q->limit(min(100, (int) $r->query('per_page', 20)))->get()->map(fn ($n) => [
            'id' => $n->id, 'title' => $n->data['title'] ?? '', 'body' => $n->data['body'] ?? '', 'category' => $n->data['category'] ?? 'system',
            'link' => $n->data['link'] ?? null, 'read_at' => $n->read_at?->toIso8601String(), 'created_at' => $n->created_at->toIso8601String(),
        ])];
    }

    public function readNotification(Request $r, string $id)
    {
        $r->user()->notifications()->where('id', $id)->update(['read_at' => now()]);

        return response()->noContent();
    }

    public function readAll(Request $r)
    {
        $r->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->noContent();
    }

    public function notificationPrefs(Request $r)
    {
        $def = ['security' => ['email' => true, 'in_app' => true], 'transaction' => ['email' => true, 'in_app' => true], 'job' => ['in_app' => true], 'subscription' => ['email' => true, 'in_app' => true], 'affiliate' => ['in_app' => true], 'promo' => []];

        return ['data' => $r->user()->notification_prefs ?? $def];
    }

    public function saveNotificationPrefs(Request $r)
    {
        $prefs = $r->validate(['*' => 'array'])  ;
        // Kategori wajib (keamanan & transaksi) tidak dapat dimatikan (Design Spec §19.8)
        foreach (['security', 'transaction'] as $m) {
            $prefs[$m] = array_merge($prefs[$m] ?? [], ['email' => true, 'in_app' => true]);
        }
        $r->user()->update(['notification_prefs' => $prefs]);

        return response()->noContent();
    }

    public function revision(Request $r)
    {
        $u = $r->user();
        if ($u->status !== 'request_revision') {
            throw new ApiException('forbidden', 'Akun tidak dalam status revisi.', 403);
        }
        $data = $r->validate(['name' => 'required|string|min:2|max:120', 'phone' => ['nullable', 'regex:/^\+?\d{8,15}$/'], 'note' => 'nullable|string|max:1000']);
        $u->update(['name' => $data['name'], 'phone' => $data['phone'] ? '+'.ltrim($data['phone'], '+') : null]);
        $u->forceFill(['status' => 'pending_approval'])->save();
        Approval::create(['type' => 'account', 'subject_type' => 'User', 'subject_id' => $u->id, 'user_id' => $u->id, 'title' => 'Revisi data akun '.$u->email, 'payload' => $data]);
        Notification::send(User::where('role', 'admin')->get(), new Notice('Revisi akun dikirim', $u->email, 'approval', '/admin/approvals/accounts'));

        return ['data' => Present::me($u)];
    }

    public function deactivate(Request $r)
    {
        $u = $r->user();
        $u->forceFill(['status' => 'deactivated'])->save();
        Audit::log('account.deactivated', $u);
        auth()->guard('web')->logout();
        $r->session()->invalidate();

        return response()->noContent();
    }
}
