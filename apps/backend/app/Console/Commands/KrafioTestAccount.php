<?php

namespace App\Console\Commands;

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AuthController;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\UsageCounter;
use App\Models\User;
use App\Services\Audit;
use App\Services\Credits;
use App\Services\Entitlements;
use App\Services\Wallet;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Akun user khusus QA: semua fitur aktif, kredit & kuota besar, saldo top-up untuk uji pembelian.
 * Dijalankan ulang = "isi ulang" (kredit di-top-up, kuota pemakaian direset). Tidak untuk production.
 */
class KrafioTestAccount extends Command
{
    protected $signature = 'krafio:test-account
        {--email=qa@krafio.test : Email akun tes}
        {--credits=100000 : Kredit bonus yang ditambahkan}
        {--balance=5000000 : Saldo top-up minimal (Rupiah) untuk uji pembelian}
        {--days=365 : Masa aktif paket QA}
        {--reset-password : Buat kata sandi baru}
        {--force : Izinkan di environment production}';

    protected $description = 'Buat/isi ulang akun user QA dengan semua fitur & kredit melimpah untuk pengujian';

    public function handle(): int
    {
        if (app()->environment('production') && ! $this->option('force')) {
            $this->error('Ditolak di production. Tambahkan --force bila benar-benar diperlukan.');

            return self::FAILURE;
        }
        $email = mb_strtolower((string) $this->option('email'));
        $credits = max(0, (int) $this->option('credits'));
        $balance = max(0, (int) $this->option('balance'));
        $days = max(1, (int) $this->option('days'));

        $password = null;
        $user = User::where('email', $email)->first();
        if ($user?->isAdmin()) {
            $this->error('Email tersebut milik akun admin. Akun admin adalah pengelola dan tidak bisa dijadikan akun QA.');

            return self::FAILURE;
        }

        DB::transaction(function () use (&$user, &$password, $email, $credits, $balance, $days) {
            if (! $user) {
                $password = AccountController::generatePassword();
                $user = new User(['name' => 'QA Krafio', 'email' => $email, 'password' => $password]);
                $user->forceFill(['role' => 'user', 'referral_code' => AuthController::newReferralCode()]);
            } elseif ($this->option('reset-password')) {
                $password = AccountController::generatePassword();
                $user->forceFill(['password' => $password]);
            }
            $user->forceFill(['status' => 'active', 'email_verified_at' => $user->email_verified_at ?? now(), 'must_change_password' => false, 'ai_consent_at' => $user->ai_consent_at ?? now()])->save();

            // Paket QA: semua fitur + kuota & batas tertinggi dari katalog, dikalikan agar tidak cepat habis.
            $plans = Plan::where('is_active', true)->get()->reject(fn ($p) => $p->limits['custom'] ?? false);
            $top = $plans->sortByDesc('sort')->first();
            $quotas = ['credits' => 0];
            foreach (array_keys(Entitlements::TEXT_KEYS) as $k) {
                $quotas[$k] = 100000;
            }
            $limits = array_merge($top?->limits ?? [], ['projects' => 1000, 'websites' => 100, 'social_accounts' => 50, 'storage_mb' => 512000, 'parallel_jobs' => 10, 'custom_domains' => 20]);
            Subscription::where('user_id', $user->id)->whereIn('status', ['active', 'past_due'])->update(['status' => 'cancelled', 'ends_at' => now()]);
            Subscription::create([
                'user_id' => $user->id, 'source' => 'admin', 'plan_code' => 'qa-all', 'plan_name' => 'QA — Semua fitur', 'cycle' => 'custom', 'price' => 0,
                'snapshot' => ['features' => Entitlements::KEYS, 'quotas' => $quotas, 'limits' => $limits, 'mode' => 'custom', 'reason' => 'Akun QA (krafio:test-account)'],
                'status' => 'active', 'starts_at' => now(), 'ends_at' => now()->addDays($days),
            ]);

            if ($credits > 0) {
                Credits::grant($user, 'bonus', $credits, now()->addDays($days), 'Kredit QA', 'admin', null);
            }
            $current = Wallet::balance($user->id, 'topup');
            if ($balance > $current) {
                Wallet::post($user->id, 'topup', 'credit', $balance - $current, 'Saldo uji QA', ['qa_seed', null], 'krafio:test-account');
            }
            UsageCounter::where('user_id', $user->id)->update(['used' => 0]);
            Audit::log('qa.test_account', $user, ['credits' => $credits, 'balance' => $balance, 'days' => $days], null);
        });

        $sum = Credits::summary($user->fresh());
        $this->info('Akun QA siap.');
        $this->table(['Item', 'Nilai'], [
            ['Email', $email],
            ['Kata sandi', $password ?? '(tidak diubah — pakai --reset-password untuk membuat baru)'],
            ['Paket', 'QA — Semua fitur ('.count(Entitlements::KEYS).' fitur, '.$days.' hari)'],
            ['Kredit tersisa', number_format($sum['remaining'], 0, ',', '.')],
            ['Saldo top-up', 'Rp'.number_format(Wallet::balance($user->id, 'topup'), 0, ',', '.')],
        ]);
        if ($password) {
            $this->warn('Kata sandi hanya ditampilkan sekali. Simpan di password manager tim.');
        }

        return self::SUCCESS;
    }
}
