<?php

/*
| Integrasi JualKirim API v1 — lihat docs/jualkirim-openapi.yaml dan docs/JUALKIRIM.md.
| Semua nilai rahasia HANYA dari .env; jangan pernah diisi langsung di file ini.
*/
return [
    'base_url' => rtrim((string) env('JUALKIRIM_BASE_URL', 'https://app.jualkirim.com'), '/'),
    'email' => env('JUALKIRIM_EMAIL'),
    'password' => env('JUALKIRIM_PASSWORD'),
    'api_key' => env('JUALKIRIM_API_KEY'),
    'timeout' => (int) env('JUALKIRIM_TIMEOUT', 30),

    /*
    | Endpoint yang memindahkan uang (create order NON_COD, checkout pembayaran vendor,
    | membuat/membatalkan batch disbursement). Spec tidak menyediakan idempotency key,
    | jadi dikunci secara default agar tidak terpanggil tidak sengaja saat development.
    */
    'money_enabled' => (bool) env('JUALKIRIM_MONEY_ENABLED', false),

    // Retry hanya untuk request idempoten (GET/PUT/DELETE) saat gagal koneksi atau 5xx.
    'retry' => [
        'times' => 2,
        'sleep_ms' => (int) env('JUALKIRIM_RETRY_SLEEP_MS', 500),
    ],

    'cache' => [
        'token_key' => 'jualkirim.jwt',
        'lock_key' => 'jualkirim.login',
        // Fallback TTL bila response login tidak memuat expires_at (JWT berlaku 24 jam).
        'token_ttl_seconds' => 23 * 3600,
    ],
];
