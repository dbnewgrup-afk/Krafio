<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;

/**
 * Hanya proxy yang terdaftar di TRUSTED_PROXIES yang boleh menentukan IP klien lewat X-Forwarded-*.
 * Mempercayai semua proxy ('*') membuat IP klien bisa dipalsukan, sehingga rate limit login,
 * OTP, dan registrasi (PRD 10.2) dapat ditembus hanya dengan mengganti header.
 * Contoh produksi di belakang Cloudflare: isi dengan rentang IP Cloudflare (https://www.cloudflare.com/ips/).
 */
class TrustProxies extends Middleware
{
    protected function proxies()
    {
        $list = array_values(array_filter(array_map('trim', explode(',', (string) config('krafio.trusted_proxies')))));

        return $list ?: null;
    }
}
