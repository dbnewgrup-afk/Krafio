# Peta aplikasi Krafio (untuk uji & audit)

Krafio = SaaS (web app + PWA) untuk kreator, UMKM, brand, agensi kecil. Spesifikasi: `docs/Krafio_Master_PRD_v2.4.md`,
desain: `docs/Krafio_UIUX_Design_Spec.md`, SEO: `docs/seo.md`.

## Arsitektur
- Backend `apps/backend` — Laravel 13 (API JSON, Sanctum SPA cookie), MySQL. Kode domain di `app/Services/*`,
  controller di `app/Http/Controllers/Api/*`, registry CRUD admin di `app/Support/AdminResources.php` (+ efek samping
  di `AdminResourceHooks.php`). Pengaturan tanpa deploy: `app/Services/Settings.php` (DEFAULTS).
- Frontend `apps/frontend` — React 19 + Vite + Tailwind 4, i18n `src/shared/locales/{id,en}.json` (format json indent=1).
  Situs publik `src/public-site`, panel user `src/user-panel` (rute di `UserApp.tsx`), admin `src/admin-panel`
  (navigasi 16 grup di `adminRoutes.tsx`, resource di `resources.ts`).
- Vite (:5173) mem-proxy `/api` & `/sanctum` ke API (:8000) → uji lewat :5173 seperti browser.

## Prefix API (`routes/api.php`)
| Prefix | Isi | Middleware |
|---|---|---|
| `/api/public/*` | settings, template, paket, promo, FAQ, legal | throttle |
| `/api/auth/*` | register, login (`login` + `password`), OTP, lupa sandi, admin/login, admin/mfa | throttle |
| `/api/me*` | profil, sesi, notifikasi, entitlements (`data.entitlements[<kunci>]` → enabled/quota/used/remaining) | auth |
| `/api/app/*` | fitur user: jobs/studio, ai-tools, projects, assets, assistant, websites, seo, social, wallet, orders, payments, subscription, affiliate, tickets, inbox | auth + active |
| `/api/admin/*` | `admin/r/{resource}` CRUD generik, settings, approvals, reports, ops, users | admin + MFA |

## Modul & risiko (urut prioritas audit)
1. **Uang** — paket/langganan, order & pembayaran (Midtrans/Xendit/Tripay/manual), wallet & top-up, refund,
   kupon/promo, affiliate & komisi, withdrawal. Kunci: `BillingController`, `Services/Billing.php`, `Wallet.php`,
   `Refunds.php`, `Promos.php`, `Credits.php`.
2. **Auth & keamanan** — login/OTP/MFA admin, sesi, reauth, SSRF (`SeoAuditor::assertPublicUrl`), unggahan, CORS,
   rate limit, idempotensi (`Support/Idempotency.php`).
3. **AI Studio & kredit** — `StudioController`, `AiToolsController`, `Services/AiGateway.php`, `Services/Ai/*`,
   `Jobs/ProcessAiJob.php` (`KRAFIO_JOBS_INLINE=true` di lokal → job jalan langsung).
4. **Website builder & domain** — `WorkspaceController` (websites), `Services/CustomDomains.php`, `SeoWriter.php`.
5. **SEO Tools** — `SeoController` (analisis konten), `Services/Seo/*`, `SeoAuditor` (audit URL), rule di Admin › SEO.
6. **Admin Panel** — CRUD generik, approval, laporan (`Services/Reports.php`), ops/backup.
7. **Komunikasi** — inbox/tiket, notifikasi, email/WhatsApp (`Services/Messenger.php`).
8. **Forum Diskusi** — privat untuk paket dengan entitlement `forum.access` (tanpa kredit). `ForumController`,
   `Services/Forum.php`, model `Forum*`, admin resource `forum-categories|threads|posts|reports|mutes`
   (efek samping di `AdminResourceHooks`). Pengguna: tulis/balas/sunting ≤30 menit/hapus milik sendiri/lapor;
   kategori, sematkan, kunci, sembunyikan, bisukan hanya Admin. UI: `/app/forum`, `/app/forum/c/:slug`,
   `/app/forum/t/:id`, `/app/forum/reports`; Admin: `/admin/forum/*`. Test: `tests/Feature/ForumTest.php`.

## Entitlement & kuota
`Services/Entitlements.php`: `KEYS` (fitur), `CREDIT_KEYS` (pakai kredit), `TEXT_KEYS` (kuota per hari/bulan).
`ensure($user, $key, $amount)` lalu `consume(...)`. Paket Gratis: `seo.basic` 3/bulan, brainstorm 5/bulan.

## Akun lokal
| Akun | Cara | Catatan |
|---|---|---|
| QA `qa@krafio.test` | `scripts/qa-account.sh` | semua fitur, kredit & saldo besar; pakai untuk uji yang mengubah data |
| Demo `demo@krafio.local` / `DemoKrafio2026` | seeder (local) | jangan kotori; bersihkan bila terpakai |
| Admin | `storage/app/private/admin-credentials.txt` + TOTP | rahasia, jangan dicetak; uji admin lewat feature test (`$this->admin()`) |

## Test backend
`tests/Feature/*Test.php`, basis `KrafioTestCase` (DB `krafio_test` di-refresh + seeder; helper `user()`,
`actingUser()`, `admin()`, `json_()` yang menambah prefix `/api`). `Http::fake()` untuk HTTP keluar.
Catatan: `json_('GET', ..., $data)` mengirim data sebagai body JSON, bukan query string.
