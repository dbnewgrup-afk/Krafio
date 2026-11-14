<?php

namespace Tests\Feature;

/** Hasil audit: batas request API global, batas ganti kata sandi, header tanpa versi PHP, pagination aman. */
class SecurityHardeningTest extends KrafioTestCase
{
    public function test_global_api_rate_limit_per_user_and_guest(): void
    {
        config(['krafio.api_rate_user' => 12, 'krafio.api_rate_guest' => 10]);
        $this->actingUser($this->user());
        $codes = [];
        for ($i = 0; $i < 14; $i++) {
            $codes[] = $this->json_('GET', '/me')->status();
        }
        $this->assertSame(200, $codes[0]);
        $this->assertContains(429, $codes, 'User login dibatasi setelah melewati batas per menit');
        $this->json_('GET', '/me')->assertStatus(429)->assertJsonPath('code', 'too_many_attempts')->assertHeader('Retry-After');

        // Webhook gateway & media tidak terkena batas global
        $this->flushSession();
        $this->app['auth']->forgetGuards();
        for ($i = 0; $i < 12; $i++) {
            $this->assertNotSame(429, $this->postJson('/api/webhooks/payment/sim', ['reference' => 'X', 'status' => 'paid', 'amount' => 1])->status());
        }
    }

    public function test_password_change_is_throttled_and_headers_hide_php_version(): void
    {
        $this->actingUser($this->user());
        $codes = [];
        for ($i = 0; $i < 7; $i++) {
            $codes[] = $this->json_('PUT', '/me/password', ['current_password' => 'salah-'.$i, 'password' => 'BaruKuat123', 'password_confirmation' => 'BaruKuat123'])->status();
        }
        $this->assertSame(422, $codes[0]);
        $this->assertContains(429, $codes, 'Tebak kata sandi lama dibatasi 5×/menit');

        $res = $this->json_('GET', '/public/settings')->assertOk();
        $this->assertFalse($res->headers->has('X-Powered-By'));
        $res->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('X-Frame-Options', 'DENY');
    }

    public function test_negative_pagination_never_errors(): void
    {
        $this->actingUser($this->user());
        foreach (['/app/jobs', '/app/projects', '/app/assets', '/app/forum/threads', '/app/seo/audits'] as $uri) {
            $s = $this->getJson('/api'.$uri.'?per_page=-1&page=-5')->status();
            $this->assertNotSame(500, $s, $uri.' tidak boleh error 500');
        }
    }
}
