<?php
// Akun admin QA khusus lokal (dipanggil oleh admin-account.sh lewat tinker). Tidak menyentuh akun admin utama.
use App\Models\User;
use App\Services\Settings;
use Illuminate\Support\Str;

if (! app()->environment('local')) {
    echo "TOLAK: hanya APP_ENV=local\n";

    return;
}
$email = getenv('KRAFIO_QA_ADMIN_EMAIL') ?: 'qa-admin@krafio.test';
$password = 'QaAdm-'.Str::random(20).'9';
$u = User::where('email', $email)->first();
if ($u && $u->role !== 'admin') {
    echo "TOLAK: {$email} sudah dipakai akun non-admin\n";

    return;
}
$u ??= new User(['name' => 'QA Admin', 'email' => $email]);
$u->password = $password;
$u->forceFill(['role' => 'admin', 'status' => 'active', 'email_verified_at' => $u->email_verified_at ?? now(), 'must_change_password' => false,
    'referral_code' => $u->referral_code ?: strtoupper(Str::random(8))])->save();
if (getenv('KRAFIO_MFA_OFF') === '1') {
    Settings::set('security.admin_mfa_required', false); // sampai live; nyalakan lagi di Admin › Pengaturan › Keamanan admin
}
file_put_contents(getenv('KRAFIO_OUT'), json_encode(['login' => $email, 'password' => $password]));
@chmod(getenv('KRAFIO_OUT'), 0600);
echo 'OK '.$email.' mfa_required='.var_export((bool) Settings::get('security.admin_mfa_required'), true)."\n";
