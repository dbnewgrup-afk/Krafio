<?php
// Daftar cek sebelum live: pengaturan yang aman di lokal tetapi berbahaya di produksi.
use App\Models\User;
use App\Services\Integrations;
use App\Services\Settings;

$items = [
    ['MFA admin wajib', (bool) Settings::get('security.admin_mfa_required'), 'Admin › Pengaturan › Keamanan admin → aktifkan'],
    ['APP_ENV=production', app()->environment('production'), '.env APP_ENV=production'],
    ['APP_DEBUG mati', ! config('app.debug'), '.env APP_DEBUG=false'],
    ['Mailer bukan log', config('mail.default') !== 'log' || (bool) Integrations::active('email'), 'Isi SMTP di Admin › Integrasi atau MAIL_MAILER'],
    ['Gateway pembayaran nyata', in_array(Integrations::active('payment')['driver'] ?? 'manual', ['midtrans', 'xendit', 'tripay', 'jualkirim'], true), 'Admin › Integrasi › Pembayaran'],
    ['Akun admin QA dihapus', ! User::where('email', 'qa-admin@krafio.test')->exists(), 'hapus qa-admin@krafio.test'],
    ['Akun QA dihapus', ! User::where('email', 'qa@krafio.test')->exists(), 'hapus qa@krafio.test (atau nonaktifkan)'],
    ['Secret webhook simulasi dihapus', (string) Settings::get('payment.webhook_secret.sim') === '', 'hapus setting payment.webhook_secret.sim'],
    ['Queue tidak inline', ! config('krafio.jobs_inline'), 'KRAFIO_JOBS_INLINE=false + worker supervisor'],
    ['Turnstile (anti-bot) diisi', (string) Settings::get('security.turnstile_site_key') !== '', 'Admin › Sistem › Cloudflare'],
];
$fail = 0;
foreach ($items as [$label, $ok, $fix]) {
    echo ($ok ? '✅ ' : '❌ ').str_pad($label, 34).($ok ? '' : '→ '.$fix)."\n";
    $fail += $ok ? 0 : 1;
}
echo "GOLIVE ".($fail ? "BELUM SIAP ({$fail} item)" : 'SIAP')."\n";
