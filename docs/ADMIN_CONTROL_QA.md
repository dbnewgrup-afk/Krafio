# Kontrol Admin, Akun QA & Fitur Berbasis Aturan

## 1. Atur fitur & paket user (Admin → Users → detail → tab Langganan)

- **Paket katalog**: beri Starter/Pro/Business tanpa bayar, dengan durasi (hari) atau tanpa batas.
- **Racik custom**: pilih fitur (`Entitlements::KEYS`), kuota (kredit/bulan + limit teks) dan batas jumlah (proyek, website, akun sosial, storage). Batas lain (resolusi, watermark, prioritas) diwarisi dari "paket dasar".
- Langganan berjalan **dan** periode yang sudah dibayar di muka dihentikan; nilai yang sudah dibayar tidak dikembalikan otomatis (sesuaikan saldo bila perlu).
- Alasan wajib, tercatat di audit log `admin.users.features.set` / `admin.users.features.revoke`; user diberi notifikasi.
- Setelah durasi habis → kembali ke paket Gratis lewat scheduler `renewals` (tidak memotong saldo, tidak auto-renew).
- **Akun admin adalah pengelola**: tidak bisa diberi paket/fitur/kredit (API menolak `422 admin_no_features`, `grantFreePlan` mengabaikan admin).

API: `GET|POST|DELETE /api/admin/users/{id}/features`.

## 2. Kontrol data di menu Admin

Setiap resource punya hak sendiri (`AdminResources::abilities`, dicerminkan di `resources.ts`). Semua menu punya **Lihat detail** dan ekspor CSV.

| Resource | Lihat | Tambah | Ubah | Hapus | Catatan |
|---|---|---|---|---|---|
| Paket, add-on, custom package, kupon, AI, template, SEO rules, FAQ, banner, blog, legal, flag, template email/WA | ✓ | ✓ | ✓ | ✓ | seperti sebelumnya |
| Pack kredit | ✓ | ✓ | label & masa berlaku | – | Tambah = kredit bonus via `Credits::grant` (masuk riwayat kredit) |
| Langganan user | ✓ | – | status, tanggal berakhir | – | ganti paket lewat §1 |
| Pemakaian kuota | ✓ | – | angka terpakai | ✓ (reset) | |
| Job AI | ✓ | – | hentikan (cancelled/failed) | – | hanya job antre/diproses; kredit/kuota di-refund sekali |
| Proyek | ✓ | – | nama, status | ✓ (soft delete) | |
| Website | ✓ | – | nama, status, domain | ✓ | |
| Unggahan, audit SEO, akun sosial, tiket | ✓ | – | – | ✓ | hapus unggahan ikut menghapus berkas |
| Pembayaran, top-up, ledger saldo, riwayat kredit, komisi, penarikan, kupon terpakai, approval, audit log, security, log email/WA/webhook | ✓ | – | – | – | **hanya-baca** — koreksi lewat alur resmi (penyesuaian saldo, approval) agar pembukuan & audit tidak bisa dimanipulasi |

Menu baru di sidebar: Billing → Langganan user, Pemakaian kuota, Riwayat kredit, Pemakaian kupon; Social → Akun sosial terhubung.

## 3. Akun QA (kredit melimpah)

```bash
php artisan krafio:test-account --reset-password          # buat/isi ulang qa@krafio.test
php artisan krafio:test-account --email=qa2@krafio.test --credits=500000 --balance=10000000 --days=30
```

- Paket "QA — Semua fitur": 14 fitur, kuota teks 100.000, batas jumlah besar.
- Kredit bonus (default 100.000) + saldo top-up minimal (default Rp5.000.000) untuk uji pembelian.
- Menjalankan ulang = isi ulang (kredit bertambah, kuota direset, saldo tidak dobel). Ditolak di production tanpa `--force`, ditolak untuk email admin.
- Kata sandi hanya tampil sekali — simpan di password manager tim.

## 4. Fitur berbasis aturan (tanpa AI eksternal)

| Fitur | Di mana | Cara kerja |
|---|---|---|
| SEO otomatis website | Draft baru + tombol "Perbaiki SEO otomatis" di editor | `SeoWriter`: judul 10–60 & deskripsi 50–160 karakter dari nama, bisnis & layanan, dipotong di batas kata → draft langsung lolos validasi publish |
| Asisten tanpa provider | AI Assistant | `FaqResponder`: cocokkan pertanyaan dengan FAQ aktif (token + sinonim + stemming ringan, skor IDF). Tidak memotong kuota chatbot |
| Prakiraan kredit habis | Pemakaian → Kuota | `Ops::creditForecast`: rata-rata pemakaian 14 hari → perkiraan tanggal habis & peringatan bila sebelum reset |
| Perlu tindakan | Dashboard admin | `Ops::attention`: job macet, job gagal 24 jam, langganan menunggak/segera habis, approval, penarikan, pembayaran gagal, tiket lewat SLA, akun menunggu — urut kritis dulu |
| Pemulihan job macet | Scheduler tiap 5 menit + tombol di dashboard | `Ops::recoverStuckJobs`: job "processing" > 30 menit → gagal + refund sekali + notifikasi user |

## 5. Promo & kode diskon

**Admin → Billing → Kupon & promo.** Setiap kupon bisa:
- dipakai lewat **kode** (diketik pengguna, huruf besar/kecil sama saja) atau **otomatis** (`auto_apply`, kodenya tidak pernah ditampilkan);
- dibatasi paket, siklus, minimal pembelian, maks. potongan, kuota total & per user, pembelian pertama, periode tanggal;
- **ditayangkan** (`Tampil di halaman Promo`) dengan judul, deskripsi, gambar, dan urutan. Mematikan tayang tidak menghapus kupon.

Default switch saat membuat kupon kini **mati** (sebelumnya semua switch default menyala — kupon baru otomatis menjadi promo otomatis).

Tampil di:
| Tempat | Isi |
|---|---|
| `/promo` (menu **Promo** di navbar & footer) | Promo unggulan + grid promo, syarat sebagai chip, salin kode, hitung mundur ≤ 7 hari, sisa kuota bila menipis, langkah cara pakai |
| Beranda (di bawah hero) | 2 promo teratas; section otomatis hilang bila tidak ada promo |
| Checkout | Kolom kode + chip **"Promo tersedia untuk pembelian ini"** (hanya promo yang lolos validasi untuk user & item, urut hemat terbesar) |
| Langganan (user) | Banner "Kode X siap dipakai" bila datang dari halaman Promo |

Alur: **Pakai promo** di `/promo` → kode disimpan (7 hari, bertahan lewat login/daftar) → Langganan → pilih paket → checkout dengan kode terisi → bayar → pemakaian tercatat di **Pemakaian kupon**; kode yang sudah mencapai batas per user hilang dari chip & ditolak.

API: `GET /api/public/promos`, `POST /api/app/orders/promos {kind, code, cycle}`. Kupon tidak berlaku untuk top-up.
