# NOTE — Perbaikan audit blueprint (12 Nov 2026)

Catatan hasil audit **pengingat langganan, sistem affiliate, dan celah PRD/blueprint**, beserta perbaikan yang sudah dikerjakan. Bagian yang butuh credential belum bisa diselesaikan; daftarnya ada di bagian 4 (nilai asli credential hanya ada di `secret.md` lokal, tidak masuk git).

## 1. Ringkasan

| Hal | Status |
|---|---|
| Celah yang bisa dikerjakan tanpa credential | ✅ Selesai semua |
| Test backend | ✅ 154/154 lulus (1.055 assertion), 11 test baru di `BlueprintGapsTest` |
| Frontend | ✅ `tsc -b`, `oxlint`, dan `vite build` lulus |
| Cek visual UI baru | ⚠️ Belum: Chromium headless macet di mesin dev, perlu dicek manual di browser |
| Celah yang butuh credential | ⏳ Kode disiapkan, tinggal diisi credential (lihat bagian 4) |

## 2. Putaran 1 — pengingat langganan & affiliate

| Masalah | Perbaikan |
|---|---|
| Pengingat perpanjangan bisa terkirim berulang | Satu pengingat per tahap (H-7, H-3, H-1) lewat `Billing::remind()` |
| Langganan dari admin ikut ditagih otomatis | Tetap diingatkan, tapi tidak ditagih |
| Gagal perpanjang tidak memberi tahu user | Notifikasi `renewal_failed` (in-app + email) + audit log |
| Selesai masa tenggang tanpa pemberitahuan | Turun ke paket Gratis + notifikasi `subscription_ended` |
| User tidak melihat status langganan | `RenewalBanner` di Dashboard & Langganan (akan berakhir / lewat jatuh tempo, harga perpanjangan) |
| Komisi tidak bisa dibatalkan | Admin bisa membatalkan komisi pending/approved, dengan alasan wajib; user diberi tahu |
| Withdrawal disetujui tanpa notifikasi | Notifikasi `withdrawal_approved` ke member |
| Link email memakai URL backend | Memakai `config('krafio.frontend_url')` |

## 3. Putaran 2 — celah PRD/blueprint

| # | Celah | Solusi | Lokasi utama |
|---|---|---|---|
| 1 | **Refund belum ada** | User mengajukan dari Langganan → Riwayat pembayaran; admin memutuskan di Approval › Refund (wajib re-auth). Dana kembali ke saldo top-up, atau transfer manual dengan nomor referensi. Paket/add-on dihentikan, kredit kedaluwarsa, akun kembali ke paket yang berlaku. Komisi dibatalkan, atau ditarik dari saldo komisi kalau sudah cair. Batas waktu (default 7 hari) dan jenis pembelian diatur di Admin → Sistem. | `Services/Refunds.php`, `shared/billing/PaymentHistory.tsx` |
| 1b | Pembelian dari saldo tidak tercatat sebagai pembayaran | Dicatat sebagai `Payment` dengan `gateway = balance`, sehingga bisa direfund, muncul di riwayat, dan komisinya tertaut. Laporan keuangan disesuaikan agar tidak terhitung ganda. | `BillingController` |
| 2 | Withdrawal "dibayar" menandai semua komisi | Alokasi FIFO: hanya komisi tertua yang tertutup nominal withdrawal | `AdminController::withdrawalStatus` |
| 3 | Peringatan margin AI 55% tidak jalan | Biaya per model (`ai_models.cost_idr`) dicatat ke provider dan job. Margin = kredit AI terpakai × nilai kredit (default Rp180) dibanding biaya. Tampil di panel "Perlu tindakan", notifikasi admin tiap hari 09.00, penghitung direset tanggal 1. | `Services/Ops.php`, `routes/console.php` |
| 4 | AI gambar/video masih simulasi | Driver ComfyUI dan HTTP kustom dengan fallback antar-provider. Kalau semua gagal, job gagal dan kredit dikembalikan sekali. Hasil disimpan privat, hanya bisa dibuka pemiliknya. Studio menampilkan media asli + tombol unduh. | `Services/Ai/MediaDrivers.php`, `Services/Ai/Outputs.php`, `Studio.tsx` |
| 5 | Prompt library tidak dipakai | Brainstorm, asisten, prompt gambar/video, dan workflow ComfyUI membaca Prompt Library. Prompt nonaktif → kembali ke teks bawaan. | `Services/Ai/Prompts.php`, `Support/AiPromptDefaults.php` |
| 6 | Watermark paket Gratis belum diterapkan | Gambar hasil AI diberi tanda "Krafio" (GD) untuk paket dengan batas watermark | `Services/Ai/Outputs.php` |
| 7 | Domain kustom belum ada | Panel di editor website: pasang domain (sesuai kuota paket), salin record TXT + CNAME, verifikasi via tombol atau otomatis tiap jam. User diberi notifikasi saat terverifikasi. | `Services/CustomDomains.php`, `Workspace.tsx` |
| 8 | Laporan & analitik kurang | Halaman **Admin → Laporan & analitik**: user per paket, funnel, job AI, operasional, keuangan, 8 KPI dengan perubahan vs periode sebelumnya, template teratas, filter tanggal/paket/metode bayar, ekspor CSV | `Services/Reports.php`, `admin-panel/Reports.tsx` |
| 9 | Template WA untuk event baru belum ada | 7 template `krafio_*` dipetakan, sengaja **nonaktif** sampai disetujui Meta | `Support/LifecycleMail.php`, migrasi `001100` |
| 10 | Job AI macet tidak pulih | Pemulihan otomatis tiap 5 menit + tombol manual di admin | `Ops::recoverStuckJobs` |

### Bug yang ditemukan saat pengerjaan

| Bug | Perbaikan |
|---|---|
| `Payment.refunded_at` tidak di-cast → riwayat pembayaran error 500 setelah ada refund | Cast tanggal ditambahkan |
| Pembatalan komisi lolos tanpa alasan (aturan `required` diubah jadi `sometimes`) | `update_rules` di AdminResources dipakai apa adanya |
| Link panel "Perlu tindakan" ke `/admin/ai/jobs` tidak ada | Diarahkan ke `/admin/media/jobs` |

### Migrasi baru

Jalankan `php artisan migrate` setelah pull:

- `2026_11_12_000700_lifecycle_notifications_and_commission_reversal`
- `2026_11_12_000800_refunds_and_item_payment_links`
- `2026_11_12_000900_ai_costs_and_prompt_defaults`
- `2026_11_12_001000_website_custom_domain_verification`
- `2026_11_12_001100_lifecycle_whatsapp_templates`

### Variabel `.env` baru (opsional, sudah ada di `.env.example`)

- `KRAFIO_CUSTOM_DOMAIN_TARGET` — target CNAME domain kustom (default `sites.<site_domain>`)
- `KRAFIO_AI_MEDIA_TIMEOUT` — batas waktu job gambar/video
- `KRAFIO_AI_POLL_MS` — interval polling ComfyUI

## 4. Menunggu credential

Kodenya sudah disiapkan; begitu credential tersedia, fitur langsung bisa diaktifkan. Langkah setup lengkap ada di `secret.md` bagian 13 (lokal).

| # | Credential | Untuk apa | Status kode |
|---|---|---|---|
| 1 | Server ComfyUI atau API AI gambar/video | Studio menghasilkan konten nyata | Siap; isi provider di Admin → AI |
| 2 | API key AI teks | Brainstorm & asisten | Siap |
| 3 | Cloudflare (Custom Hostnames + Turnstile) | HTTPS otomatis untuk domain kustom | Verifikasi DNS siap; penerbitan SSL di edge belum |
| 4 | SMTP | Email benar-benar terkirim (sekarang hanya ke log) | Siap |
| 5 | WhatsApp Cloud API + persetujuan 7 template | Notifikasi WA | Template dipetakan, nonaktif |
| 6 | Gateway pembayaran produksi | Refund otomatis lewat API gateway | Saat ini refund via saldo / transfer manual |
| 7 | Aplikasi resmi Meta / TikTok | OAuth & posting ke sosial media | **Belum dibangun**, butuh credential + review platform |
| 8 | Server produksi | Worker antrean + cron scheduler | Perlu dipasang saat deploy |

## 5. Cara cek

- Skenario QA per fitur: [docs/ADMIN_CONTROL_QA.md](docs/ADMIN_CONTROL_QA.md) bagian 6–10.
- Test backend: `php artisan test` (dari `apps/backend`).
- Frontend: `npm run build` (dari `apps/frontend`).
