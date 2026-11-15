# Yang Sedang Dimatikan / Belum Aktif — Menunggu Keputusanmu

Dicek dari database lokal pada 15 November 2026. Semua item di bawah **sengaja** belum aktif. Jangan dinyalakan tanpa keputusanmu; sebagian **wajib** dinyalakan sebelum go-live.

Legenda: 🔴 wajib sebelum go-live · 🟡 perlu sebelum fiturnya dipakai · ⚪ opsional

---

## 1. Keamanan yang dimatikan selama lokal

| | Pengaturan | Status lokal | Cara menyalakan |
|---|---|---|---|
| 🔴 | **MFA admin** (`security.admin_mfa_required`) | Mati | Admin → Pengaturan → Keamanan → "Wajib MFA…" (minta konfirmasi kata sandi). Login admin berikutnya diminta scan QR authenticator. |
| 🔴 | **Verifikasi akun user** (`security.user_verification_required`) — OTP email saat daftar & verifikasi kontak akun buatan Admin | Mati | Admin → Pengaturan → Keamanan → "Wajib verifikasi akun user". Butuh SMTP aktif (bagian 3) agar OTP terkirim. |
| 🔴 | Turnstile (captcha login/daftar) | Kosong | Admin → Pengaturan → isi `security.turnstile_site_key` & `security.turnstile_secret` dari Cloudflare. |
| 🔴 | `APP_ENV=local`, `APP_DEBUG=true` | Lokal | Di server: `APP_ENV=production`, `APP_DEBUG=false`. Tombol simulasi pembayaran otomatis hilang di production. |

Catatan: di server baru, MFA admin & verifikasi user **aktif sejak awal** (nilai bawaan kode = wajib). Yang mati hanya di database lokal ini.

## 2. AI

Driver **Claude** (teks, SDK resmi Anthropic) dan **Gemini** (teks, gambar, video, lewat API resmi Google) sudah jadi dan dites.

| | Item | Status | Yang dibutuhkan |
|---|---|---|---|
| 🟡 | **Provider "Anthropic Claude"** (Haiku 5.5 & Sonnet 5.5) | `draft`, belum ada key | Admin → AI → Provider → isi API key dari console.anthropic.com → Test koneksi → status Active. |
| ✅ | **Provider "Google Gemini"** | Aktif, key kamu yang lama tetap dipakai (driver diganti ke `gemini`) | — |
| 🔴 | **Generate Foto / Edit Foto AI lewat Gemini** | Driver jalan, tapi Google menolak: key masih **Free Tier** (kuota gambar = 0/hari) | Aktifkan billing di Google AI Studio (ai.dev → Billing). Setelah itu langsung jalan tanpa ubah kode. |
| 🔴 | **Generate Video lewat Gemini Omni Flash** | Sama: butuh key berbayar. Belum dites sungguhan (±Rp5.800 per 5 detik) | Aktifkan billing, lalu coba 1 video dari panel user. |
| 🟡 | Gemini 3.8 Flash (model pilihan Naskah & Artikel) | Saat dites: 503 "high demand" dari Google | Sementara Naskah & Artikel memakai **Gemini 3.5 Flash-Lite** (jalan, lebih murah). Kembalikan ke 3.8 Flash di Admin → AI Tools → Atur → Model teks bila sudah stabil. |
| ⚪ | Ollama lokal (cadangan teks) | Aktif di Mac ini | Di server: pasang Ollama bila ingin cadangan gratis, atau nonaktifkan provider ini. |
| ⚪ | Provider "API teks utama (OpenAI-compatible)" | `draft` | Tidak wajib lagi (sudah ada Claude & Gemini). Boleh dihapus. |

Catatan:
- **Model per tool** bisa dipilih di Admin → AI Tools → Atur → *Model teks*. Kalau model pilihan gagal, sistem otomatis mencoba model bawaan provider yang sama, lalu provider berikutnya.
- Claude: thinking dimatikan dan effort `low` untuk Haiku/Sonnet (hemat & cepat). Sonnet memakai *fallback server-side* Anthropic: kalau permintaan ditolak filter keamanan, server mencoba model cadangan.
- **Hapus latar** sekarang dikerjakan model gambar Gemini (biaya ±1 gambar), jadi biaya kredit bawaannya naik **2 → 8 kredit**. Kalau nanti pakai model khusus hapus latar yang murah (mis. fal.ai birefnet), turunkan lagi di Pengaturan kredit.
- `cost_idr` per model sudah terisi otomatis dengan angka acuan (Haiku 6, Sonnet 121, Gemini Flash 46 per 1.000 token; gambar 555; video 5.775 per 5 detik).

## 3. Integrasi (Admin → Integrasi / Social)

| | Integrasi | Status | Catatan |
|---|---|---|---|
| 🔴 | Pembayaran (JualKirim) | Belum diisi (mode `manual`) | Isi di Admin → Keuangan → Payment gateway. Kredensial JualKirim dari `.env` (`JUALKIRIM_*`). |
| 🔴 | SMTP (email) | Belum diisi | Wajib sebelum verifikasi user dinyalakan (OTP, reset kata sandi, notifikasi). |
| 🟡 | WhatsApp (Cloud API) | Belum diisi, `whatsapp.enabled` mati | 7 template `krafio_*` sengaja **nonaktif** sampai disetujui Meta. |
| 🟡 | Postiz (login medsos & posting/jadwal) | Belum diisi | Base URL backend Postiz + API key. |
| ⚪ | Aplikasi OAuth resmi per platform (Instagram, Facebook, Threads, TikTok, YouTube, LinkedIn, X) | Belum diisi | Hanya bila tidak sepenuhnya lewat Postiz. Daftarkan URL callback yang tampil di halaman Admin. |
| ⚪ | Redis (session/cache/queue) | Memakai driver `database` | Ubah `SESSION_DRIVER`, `CACHE_STORE`, `QUEUE_CONNECTION` ke `redis` di `.env` server. |

## 4. Server & deploy

| | Item | Catatan |
|---|---|---|
| 🔴 | Wildcard DNS `*.krafio.site` + sertifikat wildcard | Agar website pengguna tampil di subdomain (renderer sudah jadi). Domain kustom: CNAME ke `sites.krafio.site`. |
| 🔴 | Nginx: header keamanan, `client_max_body_size 220m`, server block website | Contoh lengkap di README §9. |
| 🔴 | Queue worker (Supervisor/systemd) + cron scheduler | README §10. Tanpa worker, job AI tidak diproses. |
| 🔴 | FFmpeg + Imagick di server | Clipper, Edit Video, Naskah → Video, upscale. |
| 🔴 | `TRUSTED_PROXIES` | Isi rentang IP Cloudflare bila di belakang Cloudflare. |
| 🔴 | Ganti kata sandi admin awal & hapus `storage/app/private/admin-credentials.txt` | |
| 🟡 | **Commit** | Ditunda atas permintaanmu (beberapa sesi bekerja di folder yang sama). |

## 5. Data yang perlu kamu isi

| Item | Dipakai di | Sekarang |
|---|---|---|
| Fee JualKirim asli (%) | Kalkulator margin | Contoh 2% |
| Harga video asli (Gemini Omni / Veo) | Kalkulator margin | ±$0,07/detik 720p (belum terverifikasi) |
| Harga model hapus latar | Kalkulator margin | 0 (belum diisi) |
| Perlakuan PPN atas top-up | Pembukuan | Konfirmasi ke konsultan pajak |

Semua angka asumsi bisa diubah dan disimpan di **Admin → Keuangan → Kalkulator margin**.

## 6. Catatan perubahan yang perlu diketahui

- **Fitur teks sekarang memakai kredit** (naskah 2/part, artikel 3, cerita 2/part, caption/copy/prompt 1, ide & kalender 1 per 10 item, chat 1 kredit per 10 pesan — semua bisa diubah di Admin → AI Tools dan Pengaturan `credits.chat_messages_per_credit`). Kuota tulisan lama dipindah jadi kredit tambahan paket: Gratis 55, Starter 330, Pro 1.150, Business 3.000. Langganan yang **sudah aktif** memakai snapshot paket lama sampai diperpanjang.
- **Paket 1.500 kredit** sekarang Rp315.000 (sebelumnya Rp299.000).
- **`billing.credit_value_idr`** = Rp176 (nilai bersih terburuk per kredit) untuk pantauan margin.
- **Kalkulator margin — pilihan model menentukan untung.** Kalau Naskah & Artikel memakai Claude Sonnet, biaya per kredit jadi ±Rp114–146, jadi semua paket rugi di skenario terburuk. Karena itu asumsi bawaan sekarang memakai **Gemini Flash** untuk Naskah & Artikel (±Rp43–55/kredit) dan Haiku untuk tool teks lain. Biaya terbesar per kredit jadi dari media (±Rp72/kredit untuk foto/video), dan harga paket sekarang masih untung. Kalau nanti mau pakai Sonnet: naikkan kredit Artikel ke 8 dan Naskah ke 6 per part di Admin → AI Tools.

## 7. Insiden yang sudah diperbaiki

- **APP_KEY berubah** (file `.env` diubah 14 Nov ±20:52 WIB, kemungkinan oleh sesi lain). Akibatnya API key provider yang terenkripsi dengan kunci lama tidak bisa dibuka, dan halaman AI Tools sempat error 500 ("The MAC is invalid"). Sudah diperbaiki: key Ollama dienkripsi ulang, dan sekarang provider yang key-nya tidak bisa dibuka dilewati otomatis (ditandai *down* + dicatat di log), tidak lagi bikin error 500. **Di server: jangan pernah ganti `APP_KEY` setelah API key diisi.** Kalau terpaksa ganti, isi ulang semua API key di Admin → AI → Provider dan Integrasi.
