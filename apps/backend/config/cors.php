<?php

/*
 * CORS: SPA dan API disajikan dari domain yang sama (proxy Vite di dev, Nginx di produksi),
 * jadi lintas-origin hanya diizinkan untuk FRONTEND_URL — bukan '*'.
 */
return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => array_values(array_filter([rtrim((string) env('FRONTEND_URL', 'http://localhost:5173'), '/')])),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => ['X-Request-Id', 'Retry-After'],
    'max_age' => 0,
    'supports_credentials' => true,
];
