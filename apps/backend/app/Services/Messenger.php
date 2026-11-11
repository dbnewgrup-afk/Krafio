<?php

namespace App\Services;

use App\Models\EmailTemplate;
use App\Models\MailLog;
use App\Models\User;
use App\Models\WaLog;
use App\Models\WaTemplate;
use App\Notifications\Notice;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

/**
 * Pengiriman pesan terpadu (PRD 45, 46, 47): email (SMTP dari Integrasi Admin, template dapat diedit),
 * WhatsApp Cloud API (template yang disetujui Meta), dan notifikasi in-app. Setiap pengiriman dicatat
 * di mail_logs / wa_logs. Kode OTP tidak pernah ditulis ke log.
 */
class Messenger
{
    /** Kategori wajib (keamanan, transaksi) selalu dikirim lewat email bila tersedia (Design Spec §19.8). */
    public const EVENTS = [
        'otp' => 'security', 'welcome' => 'subscription', 'password_changed' => 'security', 'topup_success' => 'transaction',
        'subscription_active' => 'subscription', 'subscription_expiring' => 'subscription', 'withdrawal_requested' => 'transaction',
        'withdrawal_paid' => 'transaction', 'withdrawal_rejected' => 'transaction', 'test' => 'system',
    ];

    /** Terapkan SMTP dari Integrasi Admin; tanpa integrasi aktif memakai mailer .env (log di lokal). */
    public static function applyMailConfig(): string
    {
        $c = Integrations::active('smtp');
        if (! $c) {
            return (string) config('mail.default');
        }
        config([
            'mail.default' => 'krafio_smtp',
            'mail.mailers.krafio_smtp' => [
                'transport' => 'smtp',
                'host' => $c['host'],
                'port' => (int) $c['port'],
                'scheme' => ($c['encryption'] ?? 'tls') === 'ssl' ? 'smtps' : null,
                'username' => $c['username'] ?? null,
                'password' => $c['password'] ?? null,
                'timeout' => 15,
            ],
            'mail.from' => ['address' => $c['from_address'], 'name' => $c['from_name']],
        ]);
        app('mail.manager')->purge('krafio_smtp');

        return 'krafio_smtp';
    }

    private static function render(string $text, array $vars): string
    {
        return preg_replace_callback('/\{\{\s*(\w+)\s*\}\}/', fn ($m) => (string) ($vars[$m[1]] ?? ''), $text);
    }

    public static function email(string $to, string $templateKey, array $vars = [], ?User $user = null, string $locale = 'id'): bool
    {
        $tpl = EmailTemplate::where('key', $templateKey)->where('is_active', true)->first();
        if (! $tpl) {
            MailLog::create(['user_id' => $user?->id, 'to' => $to, 'subject' => $templateKey, 'template' => $templateKey, 'mailer' => '-', 'status' => 'skipped', 'error' => 'Template email tidak aktif / tidak ada.']);

            return false;
        }
        $en = $locale === 'en' && $tpl->subject_en;
        $subject = self::render($en ? $tpl->subject_en : $tpl->subject_id, $vars);
        $body = self::render($en ? ($tpl->body_en ?: $tpl->body_id) : $tpl->body_id, $vars);
        $mailer = self::applyMailConfig();
        try {
            Mail::mailer($mailer)->raw($body, fn ($m) => $m->to($to)->subject($subject));
            MailLog::create(['user_id' => $user?->id, 'to' => $to, 'subject' => $subject, 'template' => $templateKey, 'mailer' => $mailer, 'status' => 'sent']);

            return true;
        } catch (\Throwable $e) {
            MailLog::create(['user_id' => $user?->id, 'to' => $to, 'subject' => $subject, 'template' => $templateKey, 'mailer' => $mailer, 'status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 480)]);

            return false;
        }
    }

    /** Kirim template WhatsApp untuk sebuah event. @return array{0:bool,1:string} */
    public static function whatsapp(string $to, string $event, array $vars = [], ?User $user = null): array
    {
        $c = Integrations::active('whatsapp');
        $tpl = WaTemplate::where('event', $event)->where('is_active', true)->first();
        $digits = preg_replace('/\D/', '', $to);
        if (! $c || ! $tpl || ! $digits) {
            $why = ! $c ? 'Integrasi WhatsApp belum aktif.' : (! $tpl ? 'Template WhatsApp untuk event ini belum diatur.' : 'Nomor tujuan kosong.');
            WaLog::create(['user_id' => $user?->id, 'to' => $digits ?: '-', 'event' => $event, 'template' => $tpl?->meta_name, 'status' => 'skipped', 'error' => $why]);

            return [false, $why];
        }
        $params = array_map(fn ($k) => ['type' => 'text', 'text' => (string) ($vars[$k] ?? '-')], $tpl->variables ?? []);
        $payload = ['messaging_product' => 'whatsapp', 'to' => $digits, 'type' => 'template', 'template' => ['name' => $tpl->meta_name, 'language' => ['code' => $tpl->language]]];
        if ($params) {
            $payload['template']['components'] = [['type' => 'body', 'parameters' => $params]];
            if ($event === 'otp') {
                // Template autentikasi Meta mewajibkan tombol salin kode berisi kode yang sama
                $payload['template']['components'][] = ['type' => 'button', 'sub_type' => 'url', 'index' => '0', 'parameters' => [['type' => 'text', 'text' => (string) ($vars['code'] ?? '')]]];
            }
        }
        try {
            $ver = ($c['api_version'] ?? '') ?: 'v21.0';
            $res = Http::timeout(20)->withToken((string) $c['access_token'])->acceptJson()->post("https://graph.facebook.com/{$ver}/{$c['phone_number_id']}/messages", $payload);
            if ($res->successful()) {
                WaLog::create(['user_id' => $user?->id, 'to' => $digits, 'event' => $event, 'template' => $tpl->meta_name, 'status' => 'sent', 'provider_message_id' => $res->json('messages.0.id')]);

                return [true, 'Terkirim.'];
            }
            $err = (string) ($res->json('error.message') ?? ('HTTP '.$res->status()));
            WaLog::create(['user_id' => $user?->id, 'to' => $digits, 'event' => $event, 'template' => $tpl->meta_name, 'status' => 'failed', 'error' => mb_substr($err, 0, 480)]);

            return [false, $err];
        } catch (\Throwable $e) {
            WaLog::create(['user_id' => $user?->id, 'to' => $digits, 'event' => $event, 'template' => $tpl->meta_name, 'status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 480)]);

            return [false, $e->getMessage()];
        }
    }

    /**
     * Notifikasi event ke pengguna: in-app + email (+ WhatsApp bila diizinkan preferensi).
     * Kategori security & transaction selalu email (wajib, tidak dapat dimatikan).
     */
    public static function notify(User $u, string $event, array $vars, string $title, string $body, ?string $link = null): void
    {
        $cat = self::EVENTS[$event] ?? 'system';
        $u->notify(new Notice($title, $body, $cat, $link));
        $prefs = $u->notification_prefs ?? [];
        $mandatory = in_array($cat, ['security', 'transaction'], true);
        $vars = $vars + ['name' => $u->name, 'app' => 'Krafio'];
        if ($mandatory || ($prefs[$cat]['email'] ?? true)) {
            self::email($u->email, $event, $vars, $u, $u->locale ?? 'id');
        }
        if ($u->phone && ($prefs[$cat]['whatsapp'] ?? false)) {
            self::whatsapp($u->phone, $event, $vars, $u);
        }
    }

    /** Sinkronkan status template dari WhatsApp Manager. @return array{0:bool,1:string} */
    public static function syncWaTemplates(): array
    {
        $c = Integrations::active('whatsapp');
        if (! $c) {
            return [false, 'Integrasi WhatsApp belum aktif.'];
        }
        $ver = ($c['api_version'] ?? '') ?: 'v21.0';
        $res = Http::timeout(20)->withToken((string) $c['access_token'])->get("https://graph.facebook.com/{$ver}/{$c['business_account_id']}/message_templates", ['fields' => 'name,status,language', 'limit' => 200]);
        if (! $res->successful()) {
            return [false, (string) ($res->json('error.message') ?? 'HTTP '.$res->status())];
        }
        $n = 0;
        foreach ((array) $res->json('data') as $row) {
            $n += WaTemplate::where('meta_name', $row['name'] ?? '')->update(['meta_status' => $row['status'] ?? null]);
        }

        return [true, "Sinkron selesai: {$n} template diperbarui dari ".count((array) $res->json('data')).' template Meta.'];
    }

    /** @return array{0:bool,1:string} */
    public static function testWhatsappConnection(array $c): array
    {
        try {
            $ver = ($c['api_version'] ?? '') ?: 'v21.0';
            $res = Http::timeout(15)->withToken((string) ($c['access_token'] ?? ''))->get("https://graph.facebook.com/{$ver}/".($c['phone_number_id'] ?? ''), ['fields' => 'display_phone_number,verified_name']);

            return $res->successful() ? [true, 'Terhubung: '.$res->json('verified_name').' ('.$res->json('display_phone_number').').'] : [false, (string) ($res->json('error.message') ?? 'HTTP '.$res->status())];
        } catch (\Throwable $e) {
            return [false, 'Tidak dapat terhubung: '.mb_substr($e->getMessage(), 0, 150)];
        }
    }
}
