<?php

return [
    'version' => env('KRAFIO_VERSION', '0.1.0'),
    'admin_email' => env('KRAFIO_ADMIN_EMAIL', 'admin@krafio.local'),
    'frontend_url' => env('FRONTEND_URL', 'http://localhost:5173'),
    // true: job AI diproses setelah respons (tanpa worker) — hanya untuk pengembangan lokal.
    'jobs_inline' => (bool) env('KRAFIO_JOBS_INLINE', false),
    // Batas request API global per menit (di luar batas khusus per rute). Webhook pembayaran & media dikecualikan.
    'api_rate_user' => (int) env('KRAFIO_API_RATE_USER', 300),
    'api_rate_guest' => (int) env('KRAFIO_API_RATE_GUEST', 120),
    'site_domain' => env('KRAFIO_SITE_DOMAIN', 'krafio.site'),
    // Target CNAME domain kustom website (Cloudflare for SaaS / reverse proxy). Kosong = sites.<site_domain>
    'custom_domain_target' => env('KRAFIO_CUSTOM_DOMAIN_TARGET', ''),
    // Batas tunggu hasil gambar/video dari provider (detik) & jeda polling ComfyUI (ms)
    'ai_media_timeout' => (int) env('KRAFIO_AI_MEDIA_TIMEOUT', 100),
    'ai_poll_ms' => (int) env('KRAFIO_AI_POLL_MS', 2000),
    // Batas waktu satu panggilan AI teks (detik): API cloud vs model lokal (Ollama/Jan di CPU jauh lebih lambat)
    'ai_text_timeout' => (int) env('KRAFIO_AI_TEXT_TIMEOUT', 120),
    'ai_local_timeout' => (int) env('KRAFIO_AI_LOCAL_TIMEOUT', 600),
    // FFmpeg untuk Edit Video & Video Clipper (apt install ffmpeg di server)
    'ffmpeg' => env('FFMPEG_PATH', 'ffmpeg'),
    'ffprobe' => env('FFPROBE_PATH', 'ffprobe'),
    // Lokasi mysqldump untuk backup (Laragon: C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysqldump.exe)
    'mysqldump' => env('MYSQLDUMP_PATH', 'mysqldump'),
    // Daftar IP/CIDR proxy yang dipercaya untuk header X-Forwarded-* (dipisah koma). Kosong = tidak ada.
    'trusted_proxies' => env('TRUSTED_PROXIES', ''),
];
