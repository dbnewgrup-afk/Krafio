# Krafio

Platform SaaS (web app + PWA) untuk kreator konten, UMKM, brand, dan agensi kecil: AI Studio (foto, video, edit, clipper, brainstorming), template, website builder, SEO tools, integrasi sosial, saldo dan afiliasi, dengan Admin Panel lengkap.

Spesifikasi produk ada di [`docs/Krafio_Master_PRD_v2.4.md`](docs/Krafio_Master_PRD_v2.4.md) dan acuan desain di [`docs/Krafio_UIUX_Design_Spec.md`](docs/Krafio_UIUX_Design_Spec.md).

> Status: build awal (MVP) untuk validasi. AI provider masih driver simulasi sampai provider per capability ditetapkan (PRD Bab 41). Seluruh harga dan kuota adalah nilai awal yang dapat diubah dari Admin Panel (PRD Bab 55).

---

## Daftar isi

1. [Arsitektur](#1-arsitektur)
2. [Tech stack](#2-tech-stack)
3. [Struktur folder](#3-struktur-folder)
4. [Kebutuhan server](#4-kebutuhan-server)
5. [Setup lokal (Windows + Laragon)](#5-setup-lokal-windows--laragon)
6. [Setup lokal (macOS / Linux)](#6-setup-lokal-macos--linux)
7. [Variabel lingkungan](#7-variabel-lingkungan)
8. [Akun awal](#8-akun-awal)
9. [Deploy produksi](#9-deploy-produksi)
10. [Scheduler, queue, dan backup](#10-scheduler-queue-dan-backup)
11. [Integrasi via Admin Panel](#11-integrasi-via-admin-panel)
12. [Alur penggunaan](#12-alur-penggunaan)
13. [Sistem kredit, paket, dan promo](#13-sistem-kredit-paket-dan-promo)
14. [Testing](#14-testing)
15. [Keamanan](#15-keamanan)
16. [Troubleshooting](#16-troubleshooting)

---

## 1. Arsitektur

```
Browser (React SPA)  ──/api, /sanctum──▶  Laravel API (Sanctum SPA cookie)  ──▶  MySQL
   │  public site, User Panel, Admin Panel          │
   │                                                ├─ Queue worker (job AI, notifikasi)
   │                                                ├─ Scheduler (perpanjangan, komisi, backup, retensi)
   │                                                └─ Integrasi: Payment (Midtrans/Xendit/Tripay), SMTP, WhatsApp Cloud API
```

- **Satu SPA** dengan tiga area: situs publik (`/`), User Panel (`/app`), Admin Panel (`/admin`). Panel dimuat *lazy*, jadi pengunjung publik tidak mengunduh kode admin.
- **Autentikasi** memakai cookie sesi Sanctum (stateful SPA), bukan token di localStorage. Admin wajib MFA TOTP, ada batas sesi idle, dan wajib re-autentikasi untuk tindakan keuangan.
- **Uang dan kredit** dicatat di ledger append-only (`wallet_ledger`, `credit_ledger`). Saldo selalu dihitung dari ledger.

## 2. Tech stack

| Lapisan | Teknologi |
|---|---|
| Backend | PHP 8.3+, Laravel 13, Laravel Sanctum 4 |
| Database | MySQL 8.0+ (dikembangkan dengan MySQL 8.4) |
| Session, cache, queue | Driver `database` (tanpa Redis); dapat diganti ke Redis di produksi |
| Frontend | React 19, TypeScript 7, Vite 8, Tailwind CSS v4, React Router 7, react-i18next (ID/EN), lucide-react |
| Test | PHPUnit (feature test dengan MySQL `krafio_test`) |

## 3. Struktur folder

```
Krafio/
├─ apps/
│  ├─ backend/                 Laravel API
│  │  ├─ app/Http/Controllers/Api/       Public, Auth, Me, Studio, Workspace, Billing, Admin/*
│  │  ├─ app/Services/                   Wallet, Credits, Entitlements, Billing, Integrations,
│  │  │                                  Payment/PaymentGateway, Messenger, Backups, Otp, Totp, AiGateway, SeoAuditor, Audit, Settings
│  │  ├─ app/Support/AdminResources.php  Registry CRUD Admin (validasi, kolom, filter)
│  │  ├─ database/migrations/            Skema domain, kredit & promo, integrasi/log/backup
│  │  ├─ database/seeders/               Paket, add-on, kupon, template, FAQ, legal, template email & WA, akun admin
│  │  ├─ routes/api.php, routes/console.php
│  │  └─ tests/Feature/                  Uji alur A–E
│  └─ frontend/                React SPA
│     └─ src/
│        ├─ public-site/        Landing, fitur, template, harga, afiliasi, FAQ, legal, auth
│        ├─ user-panel/         Dashboard, AI Studio, proyek, aset, website, SEO, sosial, langganan, wallet, afiliasi, support
│        ├─ admin-panel/        Dashboard, user, approval, produk & billing, keuangan, AI, template, CMS, integrasi, backup
│        ├─ shared/             UI kit, layout (AppShell), context, hooks, i18n, locales (id.json, en.json)
│        └─ api-client/         Klien HTTP (CSRF, error terstruktur)
├─ docs/                       PRD dan Design Spec
├─ repository-github/, skill/  Clone repository eksternal untuk evaluasi (PRD Bab 53) — tidak di-commit
└─ README.md
```

## 4. Kebutuhan server

### Minimum (staging / produksi awal)

| Komponen | Kebutuhan |
|---|---|
| CPU / RAM | 2 vCPU, 4 GB RAM (job AI dijalankan oleh provider eksternal, server hanya mengorkestrasi) |
| Disk | 40 GB SSD + ruang backup; unggahan media sebaiknya ke object storage |
| OS | Ubuntu 22.04/24.04 LTS (atau Linux lain) |
| Web server | Nginx + PHP-FPM 8.3+ |
| PHP extension | `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `json`, `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, `xml`, `zip`, `gd` atau `imagick`, `intl` (disarankan), `opcache` |
| Database | MySQL 8.0+ (`utf8mb4`) |
| Tool CLI | Composer 2, Node.js 20+ dan npm (hanya untuk build frontend), `mysqldump` (backup) |
| Proses latar | Cron untuk scheduler dan Supervisor/systemd untuk queue worker |
| TLS | HTTPS wajib di produksi (cookie sesi `Secure`) |

### Pengembangan lokal

Windows dengan Laragon (PHP 8.3, MySQL 8.4), atau macOS/Linux dengan PHP 8.3+, MySQL 8, Composer, dan Node.js 20+.

## 5. Setup lokal (Windows + Laragon)

1. **Siapkan Laragon:** jalankan Apache/MySQL, pastikan PHP 8.3 aktif.
2. **Buat database** (HeidiSQL atau terminal Laragon):
   ```sql
   CREATE DATABASE krafio CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE DATABASE krafio_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; -- untuk test
   ```
3. **Backend:**
   ```bash
   cd apps/backend
   composer install
   cp .env.example .env
   php artisan key:generate
   ```
   Isi `.env` minimal: `DB_*`, lalu untuk Laragon:
   ```
   MYSQLDUMP_PATH="C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysqldump.exe"
   KRAFIO_JOBS_INLINE=true
   ```
   Lalu migrasi dan seed:
   ```bash
   php artisan migrate --seed
   ```
4. **Jalankan API** dari folder `public` (penting: `server.php` memakai direktori kerja):
   ```bash
   cd apps/backend/public
   php -S 127.0.0.1:8000 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
   ```
   Atau cukup `php artisan serve --port=8000` dari `apps/backend`. Alternatif yang lebih cepat: arahkan virtual host Laragon (Apache/Nginx) ke `apps/backend/public`.
5. **Frontend:**
   ```bash
   cd apps/frontend
   npm install
   npm run dev        # http://localhost:5173, proxy /api dan /sanctum ke :8000
   ```
   Bila API tidak berjalan di `http://127.0.0.1:8000`, set `BACKEND_URL` sebelum `npm run dev`.
6. Buka `http://localhost:5173`.

> **Catatan Laragon:** pada beberapa instalasi, ekstensi `grpc` bawaan Laragon membuat PHP CLI crash (segfault) saat menjalankan Composer atau server. Bila terjadi, nonaktifkan `extension=grpc` di `php.ini` (atau jalankan PHP dengan `-c` ke salinan php.ini tanpa grpc). Aktifkan juga `opcache` (`opcache.enable=1`, `opcache.enable_cli=1`) agar respons API cepat.

## 6. Setup lokal (macOS / Linux)

```bash
# Database
mysql -u root -e "CREATE DATABASE krafio CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE DATABASE krafio_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Backend
cd apps/backend
composer install
cp .env.example .env && php artisan key:generate
# set DB_* di .env, KRAFIO_JOBS_INLINE=true untuk dev tanpa worker
php artisan migrate --seed
php artisan serve --port=8000

# Frontend (terminal lain)
cd apps/frontend
npm install
npm run dev
```

## 7. Variabel lingkungan

### Backend (`apps/backend/.env`)

| Variabel | Fungsi | Contoh |
|---|---|---|
| `APP_ENV` | `local` / `staging` / `production`. Tombol simulasi pembayaran hanya muncul di `local`. Banner lingkungan tampil di Admin selain `production`. | `production` |
| `APP_KEY` | Kunci enkripsi. Credential integrasi dienkripsi dengan kunci ini; **jangan diganti** setelah ada data. | `base64:...` |
| `APP_URL` | URL publik API (dipakai untuk URL webhook). | `https://krafio.id` |
| `DB_*` | Koneksi MySQL. | |
| `SESSION_DRIVER`, `CACHE_STORE`, `QUEUE_CONNECTION` | Default `database`. | `database` |
| `SESSION_DOMAIN` | Domain cookie di produksi. | `.krafio.id` |
| `SANCTUM_STATEFUL_DOMAINS` | Domain SPA yang boleh memakai cookie sesi. | `krafio.id,www.krafio.id` |
| `FRONTEND_URL` | URL SPA (link di email, CORS). | `https://krafio.id` |
| `KRAFIO_ADMIN_EMAIL` | Email akun admin awal yang dibuat seeder. | `admin@krafio.id` |
| `KRAFIO_JOBS_INLINE` | `true` = job AI diproses setelah respons tanpa worker (hanya dev). Produksi: `false`. | `false` |
| `KRAFIO_SITE_DOMAIN` | Domain subdomain website pengguna. | `krafio.site` |
| `MYSQLDUMP_PATH` | Lokasi `mysqldump` untuk backup. | `/usr/bin/mysqldump` |
| `MAIL_*` | Mailer fallback. SMTP sebaiknya diisi dari Admin Panel (lihat bagian 11). | `log` |

### Frontend

| Variabel | Fungsi |
|---|---|
| `BACKEND_URL` | Target proxy dev untuk `/api` dan `/sanctum` (default `http://127.0.0.1:8000`). Di produksi SPA dan API disajikan dari domain yang sama, jadi tidak perlu. |

## 8. Akun awal

- **Admin:** seeder membuat akun `KRAFIO_ADMIN_EMAIL` dengan **password acak** yang ditulis ke `apps/backend/storage/app/private/admin-credentials.txt` (tidak ikut git). Login di `/admin/login`. Pada login pertama Admin wajib memindai QR TOTP (Google Authenticator, Authy, 1Password, dan sejenisnya). Setelah login, segera ganti password dan hapus berkas tersebut.
- **User demo** (hanya `APP_ENV=local`): `demo@krafio.local` / `DemoKrafio2026` dengan saldo dan komisi contoh.

Reset admin di lokal:
```bash
php artisan tinker --execute="App\Models\User::where('role','admin')->first()->forceFill(['password'=>'PasswordBaru123!','mfa_enabled'=>false,'mfa_secret'=>null])->save();"
```

## 9. Deploy produksi

Contoh untuk Ubuntu + Nginx + PHP-FPM, satu domain (`krafio.id`) untuk SPA dan API.

```bash
# 1. Kode
git clone https://github.com/dbnewgrup-afk/krafio.git /var/www/krafio
cd /var/www/krafio/apps/backend
composer install --no-dev --optimize-autoloader
cp .env.example .env   # isi APP_ENV=production, APP_DEBUG=false, APP_URL, DB_*, SESSION_DOMAIN, SANCTUM_STATEFUL_DOMAINS, FRONTEND_URL, KRAFIO_JOBS_INLINE=false, MYSQLDUMP_PATH
php artisan key:generate
php artisan migrate --force --seed
php artisan storage:link
php artisan config:cache && php artisan route:cache && php artisan view:cache
chown -R www-data:www-data storage bootstrap/cache

# 2. Frontend
cd ../frontend
npm ci && npm run build      # hasil di apps/frontend/dist
```

Nginx (ringkas):

```nginx
server {
    listen 443 ssl http2;
    server_name krafio.id;
    root /var/www/krafio/apps/frontend/dist;

    # API dan Sanctum ke Laravel
    location ~ ^/(api|sanctum)/ {
        root /var/www/krafio/apps/backend/public;
        try_files $uri /index.php?$query_string;
    }
    location = /index.php {
        root /var/www/krafio/apps/backend/public;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root/index.php;
    }

    # SPA: semua rute lain ke index.html
    location / {
        try_files $uri /index.html;
    }
    location /assets/ { expires 1y; add_header Cache-Control "public, immutable"; }
}
```

Checklist produksi: HTTPS aktif, `APP_DEBUG=false`, cron scheduler dan queue worker berjalan (bagian 10), backup disalin ke luar server, password admin awal diganti, integrasi pembayaran/SMTP/WhatsApp diisi dan diuji dari Admin Panel.

Update rilis:
```bash
git pull
cd apps/backend && composer install --no-dev -o && php artisan migrate --force && php artisan optimize && php artisan queue:restart
cd ../frontend && npm ci && npm run build
```

## 10. Scheduler, queue, dan backup

**Cron** (wajib):
```cron
* * * * * cd /var/www/krafio/apps/backend && php artisan schedule:run >> /dev/null 2>&1
```

**Queue worker** (Supervisor):
```ini
[program:krafio-worker]
command=php /var/www/krafio/apps/backend/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
user=www-data
numprocs=2
stopwaitsecs=3600
```

Jadwal bawaan (`routes/console.php`):

| Tugas | Jadwal | Fungsi |
|---|---|---|
| `release-commissions` | per jam | Komisi afiliasi yang lewat masa tahan jadi dapat ditarik |
| `expire-payments` | 15 menit | Pembayaran kedaluwarsa dibatalkan, reservasi kupon dilepas |
| `subscription-renewals` | per jam | Pengingat H-7/H-3/H-1, perpanjangan otomatis dari saldo, downgrade terjadwal, grace period |
| Backup | harian 02.00 WIB | `mysqldump` ke `.sql.gz` (+ zip berkas unggahan bila diaktifkan), lalu rotasi |
| `daily-prune` | harian 03.00 WIB | Hapus log lama sesuai retensi |

Perintah manual:
```bash
php artisan krafio:backup            # backup database
php artisan krafio:backup --files    # + berkas unggahan
php artisan krafio:prune             # retensi log
```

**Skema backup dan retensi** (dapat diubah di Admin › Sistem › Backup):

- Backup tersimpan di `apps/backend/storage/app/backups/`.
- Rotasi: 7 backup harian terakhir + 4 mingguan; sisanya dihapus otomatis.
- Retensi: log email/WhatsApp/webhook 90 hari, audit log 365 hari, security event 180 hari, notifikasi terbaca 90 hari.
- Data keuangan (ledger saldo dan kredit, pembayaran, withdrawal, komisi) tidak pernah dipangkas.
- **Salin backup ke luar server**, misalnya dengan `rclone` ke object storage:
  ```cron
  30 2 * * * rclone copy /var/www/krafio/apps/backend/storage/app/backups remote:krafio-backups --max-age 24h
  ```
- Restore: `gunzip < db-YYYYMMDD-HHMMSS.sql.gz | mysql -u root -p krafio`.

## 11. Integrasi via Admin Panel

Credential diisi di Admin Panel, disimpan terenkripsi (`APP_KEY`), dan tidak pernah dikirim kembali ke browser.

| Menu | Isi |
|---|---|
| Keuangan › Payment gateway | Pilih driver (Manual, Midtrans Snap, Xendit Invoice, Tripay), isi key, aktifkan, uji koneksi. Salin **URL webhook** yang ditampilkan ke dashboard gateway: `https://domain/api/webhooks/payment/{midtrans,xendit,tripay}`. |
| Komunikasi › Pengaturan SMTP | Host, port, enkripsi, username, password, pengirim; tombol kirim email uji. |
| Komunikasi › Template email | Isi email per event dan bahasa, dengan variabel `{{name}}`, `{{code}}`, dan sejenisnya. |
| Integrasi Sosial › WhatsApp | Cloud API: phone number ID, WABA ID, access token; uji koneksi, kirim pesan uji, sinkronkan status template. |
| Komunikasi › Template WhatsApp | Pemetaan event ke template yang disetujui Meta (nama, bahasa, urutan variabel). |
| Komunikasi › Log email / Log WhatsApp | Riwayat pengiriman dengan status `sent` / `failed` / `skipped`. |
| Keuangan › Laporan keuangan | Penjualan, top up, withdrawal dibayar, komisi per hari/bulan, kewajiban berjalan, statistik pesan; ekspor CSV. |
| Sistem › Backup | Daftar backup, jalankan sekarang, unduh, hapus, atur retensi. |

Tanpa gateway aktif, sistem memakai mode manual: di `APP_ENV=local` ada tombol "Simulasikan pembayaran" untuk menguji alur.

## 12. Alur penggunaan

### Pengguna

1. **Daftar** (`/register`): nama, email, nomor WhatsApp (opsional), password kuat, setuju syarat. OTP 6 digit dikirim via email (dan WhatsApp bila aktif).
2. **Verifikasi OTP** (`/verify`): akun aktif dengan paket **Gratis** (50 kredit/bulan). Bila mode registrasi `manual`, akun menunggu persetujuan Admin.
3. **Login** (`/login`): email atau nomor HP + password. 5 percobaan gagal mengunci sementara.
4. **AI Studio** (`/app/create/photo|video|edit-photo|edit-video|clipper|brainstorm`): estimasi biaya kredit tampil sebelum kirim; kredit direservasi saat job dibuat dan dikembalikan bila job gagal karena sistem/provider atau dibatalkan saat antre.
5. **Proyek dan aset** (`/app/projects`, `/app/assets`): hasil job tersimpan per proyek.
6. **Template** (`/app/templates`): pakai template gratis/premium sesuai paket.
7. **Website builder** (`/app/websites`), **SEO** (`/app/seo`), **Integrasi sosial** (`/app/social`), **AI Assistant** (`/app/assistant`).
8. **Langganan** (`/app/subscription`): lihat paket, kredit, riwayat kredit; upgrade (prorata), downgrade (terjadwal di akhir periode), perpanjangan otomatis, trial Pro 7 hari (sekali per nomor HP).
9. **Add-on** (`/app/addons`): paket kredit, slot tambahan, storage, SEO lanjutan.
10. **Wallet** (`/app/wallet`): dua saldo terpisah. **Top up** (min Rp50.000) untuk membeli paket/add-on, tidak dapat ditarik. **Komisi** afiliasi dapat ditarik (min Rp100.000) ke rekening bank/e-wallet setelah disetujui Admin.
11. **Afiliasi** (`/app/affiliate`): link referral; komisi dihitung dari nominal yang benar-benar dibayar referral.
12. **Support** (`/app/support`), **Notifikasi**, **Pengaturan** (profil, keamanan, preferensi bahasa/tema/loader, consent AI).

### Admin

1. Login `/admin/login`, lalu MFA TOTP. Sesi idle berakhir sesuai pengaturan (default 30 menit).
2. **Dashboard:** ringkasan user, keuangan, AI, sistem.
3. **Approval:** akun (mode manual), withdrawal. Menolak wajib disertai alasan.
4. **Keuangan:** pembayaran, top up, ledger, withdrawal (`approved → processing → paid` dengan nomor referensi transfer), laporan. Tindakan keuangan meminta password ulang.
5. **Produk & Billing:** paket, add-on, kupon, paket kredit, ledger kredit, pengaturan biaya kredit/PPN/trial.
6. **User:** detail, ubah status, penyesuaian saldo (wajib alasan, tercatat di ledger), buka data tersamar (tercatat di audit).
7. **AI, Template, Media, Website, SEO, Sosial, Komunikasi, Konten, Sistem & Keamanan, Support, Pengaturan:** sesuai menu.

## 13. Sistem kredit, paket, dan promo

Ringkasan PRD Bab 55 (nilai awal, dapat diubah Admin):

| Paket | Harga/bulan | Kredit/bulan |
|---|---|---|
| Gratis | Rp0 | 50 |
| Starter | Rp59.000 | 300 |
| Pro | Rp179.000 | 1.000 |
| Business | Rp449.000 | 2.500 |
| Enterprise | Custom | Custom |

- **Kredit** untuk fitur media (foto standar 8, HD lebih tinggi, video per 5 detik, edit, clipper). Fitur teks memakai **limit request** (chatbot per hari, brainstorming dan audit SEO per bulan). Fitur struktural memakai **limit jumlah** (website, akun sosial, proyek, storage).
- Kredit paket dipakai lebih dulu dan tidak diakumulasi; kredit add-on/bonus berlaku 90 hari.
- **Kupon:** persen, nominal, bonus kredit, bulan gratis; satu kupon per transaksi. Kode yang diketik pengguna diutamakan; promo otomatis (referral, upgrade, winback) dipakai bila tidak ada kode valid.

## 14. Testing

```bash
cd apps/backend
php artisan test        # atau: vendor/bin/phpunit
```

Test memakai database MySQL `krafio_test` (lihat `phpunit.xml`), di-refresh dan di-seed setiap test. Skema uji:

| Suite | Alur | Yang diuji |
|---|---|---|
| `AuthFlowTest` (A1–A7) | Registrasi, OTP, login, MFA | OTP terkirim dan tercatat, password lemah/duplikat ditolak, mode registrasi manual + approval, error login generik + lockout, login via HP, akun suspended, admin wajib MFA, setup TOTP |
| `StudioCreditsTest` (B1–B7) | AI Studio dan kredit | Estimasi dan pemotongan kredit, kredit tidak cukup, batas paket (HD, fitur terkunci), refund job gagal ke pack asal, limit request brainstorming, batal job, consent AI, blokir URL internal (SSRF) |
| `BillingFlowTest` (C1–C8) | Top up, checkout, promo | Batas top up, idempotensi pembayaran, kupon bonus + komisi dari nominal dibayar, upgrade prorata + promo otomatis, downgrade terjadwal, saldo kurang, konflik add-on, webhook Midtrans/Xendit/Tripay (signature salah ditolak, duplikat aman), pelepasan reservasi kupon, trial sekali per nomor |
| `WithdrawalAdminTest` (D1–D4) | Withdrawal dan keputusan Admin | Minimum dan hanya saldo komisi, tolak mengembalikan dana, re-auth, transisi status + nomor referensi, email notifikasi, penyesuaian saldo beralasan, reveal data diaudit |
| `OpsIntegrationTest` (E1–E8) | Integrasi, log, laporan, backup | Credential terenkripsi dan tidak dikembalikan, uji koneksi payment/WhatsApp, email tercatat, log WhatsApp sent/failed/skipped, laporan keuangan + CSV, retensi tanpa menyentuh data keuangan, rotasi backup, CRUD admin tervalidasi dan diaudit |

Frontend:
```bash
cd apps/frontend
npm run build    # type-check (tsc -b) + build produksi
npm run lint
```

## 15. Keamanan

- Cookie sesi HttpOnly + CSRF (Sanctum); throttle per endpoint sensitif.
- Password di-hash (bcrypt), OTP disimpan sebagai hash, credential integrasi dan API key AI dienkripsi.
- Admin: MFA TOTP wajib, timeout sesi idle, re-auth untuk tindakan keuangan, audit log untuk setiap perubahan, data pribadi tersamar sampai dibuka (dan dicatat).
- Webhook pembayaran diverifikasi signature, idempoten, dan dicatat.
- Audit SEO memblokir URL ke jaringan internal (SSRF).
- Jangan commit `.env`, `storage/app/private/*`, atau berkas backup.

## 16. Troubleshooting

| Gejala | Penyebab / solusi |
|---|---|
| Halaman tertahan di layar loading | API tidak berjalan atau server PHP tidak dijalankan dari `public/`. Cek `http://127.0.0.1:8000/api/public/settings`. |
| `419 CSRF token mismatch` / selalu logout | Domain SPA belum ada di `SANCTUM_STATEFUL_DOMAINS`, atau `SESSION_DOMAIN` tidak cocok. |
| PHP crash saat `composer install` (Windows) | Nonaktifkan ekstensi `grpc` (lihat bagian 5). |
| Respons API lambat di lokal | Aktifkan OPcache; server bawaan PHP (`php -S`) memproses satu request sekaligus, sehingga lebih lambat daripada Nginx/Apache + PHP-FPM. |
| Backup gagal | `MYSQLDUMP_PATH` salah atau tidak dapat dieksekusi; lihat pesan di Admin › Sistem › Backup. |
| Email/WhatsApp tidak terkirim | Cek status di Log email / Log WhatsApp; `skipped` berarti integrasi atau template belum aktif. |
| Job AI tetap `queued` | Queue worker belum berjalan (atau set `KRAFIO_JOBS_INLINE=true` di dev). |
