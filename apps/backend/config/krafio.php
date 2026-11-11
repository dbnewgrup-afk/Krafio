<?php

return [
    'version' => env('KRAFIO_VERSION', '0.1.0'),
    'admin_email' => env('KRAFIO_ADMIN_EMAIL', 'admin@krafio.local'),
    'frontend_url' => env('FRONTEND_URL', 'http://localhost:5173'),
    // true: job AI diproses setelah respons (tanpa worker) — hanya untuk pengembangan lokal.
    'jobs_inline' => (bool) env('KRAFIO_JOBS_INLINE', false),
    'site_domain' => env('KRAFIO_SITE_DOMAIN', 'krafio.site'),
    // Lokasi mysqldump untuk backup (Laragon: C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysqldump.exe)
    'mysqldump' => env('MYSQLDUMP_PATH', 'mysqldump'),
];
