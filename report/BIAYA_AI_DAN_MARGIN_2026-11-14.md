# Biaya AI, Kredit, dan Margin Krafio — catatan 14 November 2026

Catatan hasil diskusi: sumber AI yang dipakai, harga API, hitungan untung per fitur, sistem top-up, dan keputusan yang masih terbuka.

> **Asumsi yang harus diganti dengan angka asli:** kurs Rp16.500/USD · fee JualKirim 2% (contoh) · harga video Gemini Omni ±$0,07/detik 720p (harga Veo belum terverifikasi) · estimasi token per tugas (belum dari pemakaian nyata).

---

## 1. Keputusan sumber AI

| Keputusan | Isi |
|---|---|
| Jalur | **API resmi berbayar** (API key), bukan paket langganan chat. Langganan Claude Pro/Max atau Gemini Advanced tidak punya API key dan tidak boleh dipakai untuk melayani user aplikasi. Proxy/"hack" yang mengubah langganan jadi API **tidak dipakai** (melanggar ketentuan, akun bisa diblokir, tidak stabil). |
| Claude (Anthropic) | Provider **teks** utama. Claude tidak bisa membuat gambar/video. |
| Gemini (Google) | **Foto dan video**, plus cadangan teks. Flash/Flash-Lite punya tier gratis untuk uji coba. |
| Ollama lokal | Cadangan terakhir (gratis, sudah terpasang). |
| Key | Diisi sendiri di **Admin → AI → Provider**, jangan lewat chat. |

**Status kode:** Gemini teks kemungkinan bisa langsung lewat driver `openai_compatible` (belum diuji dengan key asli). **Driver Claude dan driver Gemini gambar/video belum dibuat.**

---

## 2. Dua satuan yang berbeda

| | **Kredit Krafio** (dibeli user) | **Token AI** (dibayar ke Claude/Gemini) |
|---|---|---|
| Dipakai oleh | User Krafio | Krafio, ke penyedia AI |
| Didapat dari | Paket langganan / add-on kredit (bisa dibeli pakai saldo top-up) | Saldo API di Anthropic dan Google |
| Nilai | Rp176 bersih per kredit (terburuk) | Tergantung model |

Selisih antara nilai kredit dan biaya token = untung.

Konversi kasar 1 kredit (Rp176): ±21.000 token Haiku 5.5, ±2.800 token Gemini Flash, atau ±1.000 token Sonnet 5.5 (dari harga token keluar).

---

## 3. Harga API (sumber resmi, November 2026)

### Teks (USD per 1 juta token)

| Model | Masuk | Keluar | Rp per token masuk / keluar |
|---|---|---|---|
| Claude Sonnet 5.5 | $2,00 | $10,00 | Rp0,033 / Rp0,165 |
| Claude Haiku 5.5 | $0,10 | $0,50 | Rp0,0017 / Rp0,0083 |
| Gemini 3.8 Flash (s.d. 31 Des 2026; naik jadi $1,50/$7,50 mulai 1 Jan 2027) | $0,75 | $3,75 | Rp0,012 / Rp0,062 |
| Gemini 3.1 Flash-Lite | $0,25 | $1,50 | — |

### Gambar dan video

| Model | Harga | Rupiah |
|---|---|---|
| Nano Banana 2.1, gambar 1K | $0,0336 | Rp555 |
| Nano Banana 2.1, gambar 2K | $0,0504 | Rp830 |
| Gemini Omni Flash, video 720p | ±$0,07/detik | ±Rp5.800 per 5 detik |
| Gemini Omni Flash, video 1080p | ±$0,104/detik | ±Rp8.600 per 5 detik |

### Biaya per tugas teks

| Tugas | Sonnet 5.5 | Gemini Flash | Haiku 5.5 |
|---|---|---|---|
| Naskah 1 part (±900 masuk, ±1.200 keluar) | Rp230 | Rp85 | Rp11 |
| Caption 1 variasi | Rp90 | — | Rp4 |
| Artikel ±800 kata | Rp440 | — | — |
| 1 pesan AI Assistant | Rp115 | — | Rp6 |

---

## 4. Nilai bersih 1 kredit

Harga paket kredit sudah termasuk PPN 11%. Bersih = harga ÷ 1,11 × (1 − fee 2%).

| Add-on | Harga | Per kredit | **Bersih** | Bersih bila dari afiliasi (−10%) |
|---|---|---|---|---|
| 200 kredit | Rp49.000 | Rp245 | Rp216 | Rp194 |
| 500 kredit | Rp109.000 | Rp218 | Rp192 | Rp173 |
| 1.500 kredit | Rp299.000 | Rp199 | **Rp176** | Rp158 |

Perhitungan di bawah memakai angka terburuk: Rp176.

---

## 5. Untung per fitur (kredit)

| Fitur | Kredit | Masuk bersih | Biaya API | **Untung** | Margin |
|---|---|---|---|---|---|
| Foto standar (1K) | 8 | Rp1.408 | Rp555 | Rp853 | 61% |
| Foto HD (2K) | 15 | Rp2.640 | Rp830 | Rp1.810 | 69% |
| Video 720p, 5 detik | 80 | Rp14.080 | ±Rp5.800 | Rp8.280 | 59% |
| Video 1080p, 5 detik | 150 | Rp26.400 | ±Rp8.600 | Rp17.800 | 67% |
| Video Clipper, per menit | 10 | Rp1.760 | Rp0 (FFmpeg) | Rp1.760 | ~100% |
| Edit Video, per menit | 5 | Rp880 | Rp0 | Rp880 | ~100% |
| Naskah → Video, per menit | 5 | Rp880 | Rp0 | Rp880 | ~100% |
| Upscale / Enhance foto | 4 | Rp704 | Rp0 | Rp704 | ~100% |
| Hapus latar | 2 | Rp352 | tergantung model | isi nanti | — |

Biaya API terbesar per kredit: video 720p, ±Rp72. Untung minimal **±Rp100 per kredit** (±Rp85 bila dari afiliasi).

---

## 6. Fitur teks: untung per request

Saat ini teks memakai **kuota request per paket** (1 request = 1 part/variasi), bukan kredit. Bila 1 request dinilai setara 1 kredit (Rp176):

| Model | Biaya | Untung per request | Catatan |
|---|---|---|---|
| Haiku 5.5 | Rp11 | **Rp165** (±Rp0,08 per token, 94%) | Bawaan untuk tool ringan dan chat |
| Gemini Flash | Rp85 | Rp91 (52%) | Alternatif |
| Sonnet 5.5 | Rp230 | **rugi Rp54** | Pakai hanya untuk naskah/artikel dengan biaya 2 request (Rp352 vs Rp230 → untung ±Rp120) |

**AI Assistant wajib pakai Haiku** (±Rp6 per pesan). Dengan Sonnet, chat paket Starter saja bisa ±Rp100 ribu per user.

---

## 7. Biaya terburuk per user per bulan (kuota terpakai 100%)

Asumsi: tulisan Sonnet 5.5, chat Haiku 5.5, 1 kredit = Rp72 biaya.

| Paket | Harga | Kredit | Tulisan | Chat | Total biaya | Sisa margin |
|---|---|---|---|---|---|---|
| Gratis | Rp0 | Rp3.600 | Rp1.150 | Rp1.800 | Rp6.550 | rugi per user |
| Starter | Rp59.000 | Rp21.600 | Rp6.900 | Rp5.400 | Rp33.900 | 43% |
| Pro | Rp179.000 | Rp72.000 | Rp34.500 | Rp14.400 | Rp120.900 | 32% |
| Business | Rp449.000 | Rp180.000 | Rp115.000 | Rp36.000 | Rp331.000 | 26% |

Pemakaian nyata biasanya 20–40% dari kuota. Bila tulisan memakai Haiku, margin hampir semua paket naik di atas 50%. Paket Gratis sebaiknya memakai model termurah.

---

## 8. Sistem top-up

1. User top-up (mis. Rp100.000 via JualKirim) → masuk **saldo top-up**. Belum untung: masih uang user (kewajiban), tidak bisa ditarik.
2. User membeli paket/add-on kredit dengan saldo → **untung terbentuk** (tabel bagian 5).
3. User memakai kredit → biaya API keluar.
4. Job gagal → kredit kembali otomatis, penyedia juga tidak menagih.

**Rumus harga minimal per kredit:**

```
harga per kredit (termasuk PPN) ≥ biaya API per kredit ÷ (1 − target margin) × 1,11 ÷ (1 − fee − afiliasi)
```

Biaya Rp72,5 · margin 55% · fee 2% · afiliasi 10% → **minimal Rp203 per kredit**.

**Temuan:** paket 1.500 kredit (Rp199/kredit) sedikit di bawah batas bila pembeli dari afiliasi. Pilihan: naikkan ke **Rp315.000**, atau matikan komisi afiliasi untuk paket kredit.

---

## 9. Saldo API yang perlu disiapkan

```
saldo per bulan ≈ Σ (user per paket × biaya terburuk × % pemakaian) + cadangan 30%
```

Contoh (100 Starter, 30 Pro, 5 Business, 500 Gratis, pemakaian 35%): ±Rp4,2 juta, **±Rp5,5 juta** dengan cadangan. Pemasukan paket di contoh ini ±Rp13,5 juta.

**Untuk awal:** deposit $50–100 per provider (±Rp0,8–1,6 juta), pasang batas pengeluaran dan peringatan di Anthropic Console dan Google Cloud Billing, lalu sesuaikan setelah 1–2 minggu data nyata.

---

## 10. Pengaturan di Admin

- **AI → Model → `cost_idr`:** teks = Rp per 1.000 token (Sonnet ±130, Haiku ±6, Gemini Flash ±50); gambar/video = Rp per file (Nano Banana 1K = 555).
- **Sistem → `billing.credit_value_idr`:** ubah 180 → **176**.
- **AI → Cost & margin:** memantau biaya vs kredit terpakai, peringatan bila margin < 55%.

Cara hemat: model per tool (bagian 6), effort Claude `low` untuk tugas ringan, prompt caching, Batch API (50% lebih murah) untuk pekerjaan yang tidak harus langsung jadi.

---

## 11. Keputusan dan pekerjaan yang masih terbuka

| # | Hal | Status |
|---|---|---|
| 1 | **Satukan teks ke sistem kredit** (mis. naskah Haiku 1 kredit, Sonnet 2 kredit, chat 0,1 kredit) supaya top-up juga menambah kemampuan menulis | Disarankan, **belum diputuskan / belum dikerjakan** |
| 2 | Driver Claude (teks) dan Gemini (gambar/video) | Belum dibuat |
| 3 | Kalkulator margin di Admin (kurs, fee, harga model bisa diganti) | Ditawarkan, belum dibuat |
| 4 | Harga paket 1.500 kredit vs afiliasi | Perlu keputusan |
| 5 | Renderer website user di subdomain (wildcard DNS + SSL di server) | Belum dibuat |
| 6 | Fee JualKirim asli, harga video (Omni/Veo) asli | Perlu diisi |
| 7 | Perlakuan PPN atas top-up | Konfirmasi ke konsultan pajak |
| 8 | Celah SSRF di SEO audit (B3) | Belum diperbaiki |

## Sumber

- Harga Gemini: https://ai.google.dev/gemini-api/docs/pricing (dialihkan ke https://aistudio.google.com/docs/pricing)
- Harga Claude: tabel harga model resmi Anthropic (diperbarui 3 Nov 2026)
- Data Krafio: harga paket, add-on kredit, PPN, afiliasi, dan biaya kredit per aksi dari database lokal
