<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Menjaga suite "Unit" di phpunit.xml tetap ada, sehingga `php artisan test` tanpa argumen tidak gagal. */
class ConfigSanityTest extends TestCase
{
    public function test_trusted_proxies_middleware_does_not_trust_everything_by_default(): void
    {
        $src = file_get_contents(__DIR__.'/../../bootstrap/app.php');
        $this->assertStringNotContainsString("trustProxies(at: '*')", $src);
    }
}
