<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Credits;
use App\Services\Entitlements;
use App\Services\Wallet;

class QaTestAccountTest extends KrafioTestCase
{
    public function test_command_creates_and_refills_qa_account(): void
    {
        $this->artisan('krafio:test-account', ['--email' => 'qa@uji.test', '--credits' => 20000, '--balance' => 1000000])->assertSuccessful();
        $u = User::where('email', 'qa@uji.test')->firstOrFail();
        $this->assertSame('user', $u->role);
        $all = Entitlements::forUser($u);
        foreach (Entitlements::KEYS as $k) {
            $this->assertTrue($all[$k]['enabled'], "Fitur {$k} aktif untuk QA");
        }
        $this->assertSame(20000, Credits::summary($u)['remaining']);
        $this->assertSame(1000000, Wallet::balance($u->id, 'topup'));
        Entitlements::consume($u, 'chatbot.basic', 5);

        // Jalankan ulang = isi ulang: kredit bertambah, kuota direset, saldo tidak dobel, hanya satu paket aktif
        $this->artisan('krafio:test-account', ['--email' => 'qa@uji.test', '--credits' => 20000, '--balance' => 1000000])->assertSuccessful();
        $u->refresh();
        $this->assertSame(40000, Credits::summary($u)['remaining']);
        $this->assertSame(1000000, Wallet::balance($u->id, 'topup'));
        $this->assertSame(0, Entitlements::forUser($u)['chatbot.basic']['used']);
        $this->assertSame(1, $u->subscriptions()->where('status', 'active')->count());
    }

    public function test_command_refuses_admin_email(): void
    {
        $admin = User::where('role', 'admin')->firstOrFail();
        $this->artisan('krafio:test-account', ['--email' => $admin->email])->assertFailed();
        $this->assertSame(0, $admin->subscriptions()->count());
    }
}
