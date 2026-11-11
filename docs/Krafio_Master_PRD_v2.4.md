# Krafio --- Product Requirements Document (PRD)

**Dokumen:** PRD + System Flow + Business Rules + Technical Blueprint\
**Nama produk:** Krafio (sebelumnya bernama sementara ToolsMP)\
**Jenis produk:** SaaS berbasis Web Application dengan dukungan
Progressive Web App (PWA)\
**Target pasar awal:** Kreator konten, UMKM, pemilik brand, agensi
kecil, dan pengguna yang membutuhkan alat produksi serta pemasaran
digital dalam satu platform\
**Status dokumen:** Draft komprehensif untuk validasi dan perencanaan
pengembangan\
**Versi:** 2.4\
**Tanggal:** 11 November 2026

------------------------------------------------------------------------

## Catatan Integrasi Versi 2.2

Dokumen ini merupakan satu Master PRD terpadu. Seluruh 24 bagian dari `Spesifikasi-Admin-Panel-ToolsMP.md` telah ditempatkan ke bab yang relevan, bukan dijadikan lampiran terpisah. Isi Master PRD v2.1 dipertahankan, sedangkan spesifikasi Admin Panel dimasukkan sebagai persyaratan operasional terperinci pada bab terkait. Jika suatu topik sudah memiliki aturan umum di bagian lain, kedua bagian harus dibaca bersama: aturan umum menetapkan perilaku platform, sedangkan spesifikasi Admin Panel menetapkan kendali dan tindakan operasional Admin.

**Peta integrasi spesifikasi Admin Panel:**

| Bagian sumber Admin Panel | Lokasi integrasi di Master PRD |
|---|---|
| 1–3. Konsep, Dashboard, User Management | Bab 13 — Admin Panel |
| 4. Account Approval Management | Bab 44 — OTP, Verifikasi Akun, dan Account Security |
| 5. Approval Management | Bab 13 — Admin Panel |
| 6–7. AI Management dan AI Agent Management | Bab 7 — Integrasi AI dan Arsitektur Provider |
| 8. Template Management & AI Template Generator | Bab 27 — Spesifikasi Media dan Template |
| 9. Product & Subscription Management | Bab 4 — Model Bisnis dan Langganan |
| 10. Financial Management | Bab 31 — Billing, Wallet, Payment, dan Financial Controls |
| 11. Website Builder Management | Bab 28 — Company Profile Website Builder |
| 12. SEO Management | Bab 29 — SEO Tools dan Custom Rules Engine |
| 13. Social Media Integration | Bab 30 — Integrasi Media Sosial dan Messaging |
| 14. Communication Management | Bab 45 — SMTP dan Transactional Email |
| 15. Notification Center & Announcement | Bab 47 — Notification Center Terpadu |
| 16. Content Management | Bab 13 — Admin Panel |
| 17. Media Jobs Management | Bab 36 — Observability, Support, dan Operasional |
| 18. System & Security Management | Bab 35 — Security, Privacy, Compliance, dan Abuse Prevention |
| 19. Audit Logs | Bab 36 — Observability, Support, dan Operasional |
| 20–22. Sidebar, Flow Operasional, Aturan Approval | Bab 13 — Admin Panel |
| 23. Acceptance Criteria Admin Panel | Bab 17 — Acceptance Criteria |
| 24. Kesimpulan Arsitektur Admin | Bab 13 — Admin Panel |

**Catatan konsistensi:** istilah Super Admin pada spesifikasi Admin Panel adalah satu-satunya tipe Admin untuk tahap awal. RBAC tetap merupakan opsi pengembangan masa depan, bukan persyaratan bahwa beberapa tipe Admin harus tersedia sejak peluncuran.

## Catatan Integrasi Versi 2.3

Versi 2.3 **menambah** tiga bab baru (Bab 52, 53, 54) setelah Bab 51. Seluruh isi v2.2 dipertahankan. Perubahan pada bagian lama hanya berupa: penandaan versi, catatan ini, dua penunjuk silang singkat (akhir Bab 12 dan akhir Bab 37), dan baris riwayat versi. Bab 37 (Repository dan Open-Source Integration Register) tetap utuh. Catatan: pernyataan status pada Bab 42 mencerminkan baseline awal; versi yang berlaku tercantum pada header dokumen dan Riwayat Versi.

**Peta bab baru:**

| Bab | Judul | Isi utama |
|---|---|---|
| 52 | GitHub Repository, Source Code Management & CI/CD | Repository utama, struktur source code, branch, PR, review, versi, rilis, GitHub Actions, deployment ke VPS, keamanan akses, backup dan recovery, dokumentasi developer (52.1–52.70) |
| 53 | Repository Eksternal: Register, Audit, dan Metode Integrasi | Register 25 kandidat, audit 20 kriteria, status audit dan implementasi, 8 metode integrasi, isolasi, lisensi (53.1–53.14) |
| 54 | Checklist GitHub (90 Poin), Keterlacakan, Ketentuan Teknis Wajib, dan Register Keputusan | Checklist 90 poin, matriks keterlacakan, 20 ketentuan teknis, kriteria penerimaan dokumen, register keputusan (54.1–54.9) |

**Tiga kategori yang tidak boleh dicampur:** (A) repository utama Krafio (Bab 52); (B) 25 repository eksternal kandidat (Bab 12, 37, 53); (C) checklist GitHub 90 poin (Bab 54), yang **bukan** 90 repository tambahan.

**Rujukan ke requirement yang sudah ada:** Bab 34.4 (pipeline), Bab 15.3 dan 34.6 (backup dan DR), Bab 35.1 dan 48 (keamanan dan secret), Bab 38.3 (gate rilis), Bab 43.1 (stack), Bab 49 (process group), Bab 41 (keputusan final).

**Nilai yang belum ditetapkan** (identitas repository utama, VPS, RPO/RTO, identitas repository eksternal tertentu, dan lainnya) ditandai `[BELUM DITETAPKAN]` atau `[MENUNGGU KEPUTUSAN]` dan dihimpun pada Bab 54.7. Tidak ada nama, URL, atau nilai yang dikarang.

## Catatan Integrasi Versi 2.3.1

Versi 2.3.1 adalah **pembaruan klarifikasi** dan tidak menambah bab baru. Tujuannya satu: mengisi identitas lima repository eksternal yang pada v2.3 belum teridentifikasi (No. 1, 9, 10, 12, dan 25 pada Bab 53.3). Seluruh isi v2.3 dipertahankan. **Bab 12 dan Bab 37 tidak diubah.**

**Sumber data dan batasannya:** data baru berasal dari **tangkapan layar konten media sosial (TikTok)** yang diberikan pemilik produk, **bukan** dari pemeriksaan langsung ke GitHub. Karena itu, seluruh URL baru berlabel *URL kandidat, belum diverifikasi*. Lisensi, jumlah bintang, status maintenance, dan teknologi **tidak** dicatat sebagai fakta. Nilai yang hanya disebut oleh konten sumber (mis. lisensi GPL-3.0 untuk No. 25) ditulis sebagai "menurut konten sumber" dan tetap `[BELUM DIVERIFIKASI]`. Pada pembaruan ini tidak dilakukan pemeriksaan web langsung ke repository.

**Bagian yang diubah:** Bab 53.3 (Tabel A, Tabel B, ringkasan, dan sub-bab baru 53.3.1), 53.8 (contoh kandidat indikatif), 53.12 (pemeriksaan khusus dan prosedur klarifikasi), 53.13 (kelompok resource), 53.14 (satu butir kriteria), serta Bab 54.6 (sub-bab baru 54.6.1), 54.7.5 (D-38 sampai D-44, serta keputusan baru D-45 dan D-46), 54.8, 54.9, dan Riwayat Versi.

**Status yang tidak berubah:** kelima kandidat tetap berstatus evaluasi *Perlu klarifikasi identitas* dan status implementasi *Blocked*, karena identitasnya belum diverifikasi dan belum diputuskan pemilik produk (53.12, prosedur butir 5). Tidak ada kandidat berstatus *Implemented* atau *Tested*.

## Catatan Versi 2.3.2 — Perubahan Nama Produk

Nama produk diganti dari **ToolsMP** menjadi **Krafio**. Seluruh penyebutan nama produk, istilah teknis turunan (contoh subdomain, alamat email, direktori deployment, dan kunci penyimpanan lokal), serta nama berkas dokumen disesuaikan. Tidak ada requirement, status, atau keputusan yang berubah. Satu pengecualian: nama berkas sumber historis `Spesifikasi-Admin-Panel-ToolsMP.md` (Catatan Integrasi Versi 2.2) dipertahankan apa adanya.

**Catatan domain:** subdomain `krafio.com` pada Bab 34.3 dan alamat email pada Bab 45 tetap berstatus **rancangan/contoh**. Domain final masih keputusan terbuka D-28 (54.7.4). Ketersediaan dan kepemilikan domain `krafio.com` belum diperiksa.

**Berkas yang dihapus karena digantikan dokumen ini:** `ToolsMP_Master_PRD_v2.2_Lengkap_Terintegrasi.md`, `ToolsMP_Master_PRD_v2.3_GitHub_Repository_CICD.md`, dan `ToolsMP_Master_PRD_v2.3.1_Klarifikasi_Repository.md`. Seluruh isinya telah termuat dalam dokumen ini.

# 1. Ringkasan Produk

## 1.1 Gambaran Umum

Krafio adalah platform SaaS berbasis web yang menggabungkan berbagai
tools produksi konten, kecerdasan buatan (AI), pengelolaan aset digital,
pembuatan website company profile, SEO, serta integrasi kanal media
sosial dalam satu ekosistem berlangganan.

Pengguna dapat memilih paket berlangganan, membeli add-on, melakukan top
up saldo, menggunakan fitur yang telah dibuka, menghubungkan akun media
sosial, serta memantau aktivitas dan penggunaan dari satu panel
pengguna. Krafio juga menyediakan sistem affiliate/komisi untuk
aktivitas promosi produk atau layanan Krafio yang memenuhi ketentuan.

Platform memiliki tiga area utama yang saling terhubung:

1.  **Website utama / Frontpage:** halaman publik untuk memperkenalkan
    produk, fitur, template, harga, paket, FAQ, dan proses pendaftaran.
2.  **User Panel:** area pengguna untuk mengelola akun, langganan,
    saldo, aset, proyek, integrasi, dan menggunakan fitur sesuai hak
    akses.
3.  **Admin Panel:** area operasional tunggal yang digunakan
    pemilik/operator Krafio untuk mengelola seluruh sistem, pengguna,
    paket, add-on, pembayaran, AI provider, template, aturan SEO,
    integrasi, komisi, withdrawal, dan konfigurasi platform.

Krafio bukan sekadar kumpulan tautan ke tools eksternal. Target
akhirnya adalah satu produk terpadu dengan autentikasi, billing, hak
akses, kuota, penyimpanan proyek, riwayat pekerjaan, dan pengalaman
pengguna yang konsisten. Komponen open-source dapat digunakan sebagai
mesin atau modul pendukung setelah audit lisensi, keamanan,
kompatibilitas, dan kelayakan operasional.

## 1.2 Tujuan Produk

-   Menyatukan proses ideasi, produksi, penyuntingan, optimasi, dan
    distribusi konten.
-   Menyediakan sistem berlangganan fleksibel melalui paket utama dan
    add-on.
-   Memungkinkan pemilik Krafio mengatur fitur, kuota, harga, dan
    aturan dari Admin Panel tanpa mengubah kode untuk setiap perubahan
    komersial.
-   Mengintegrasikan AI provider yang dapat diganti sesuai kebutuhan,
    biaya, kualitas, dan kompatibilitas.
-   Menyediakan dashboard terpusat untuk proyek, hasil generate, aset,
    penggunaan, dan integrasi sosial.
-   Membangun sumber pendapatan tambahan melalui affiliate/komisi.
-   Menyediakan pengalaman PWA agar aplikasi dapat dipasang di perangkat
    yang mendukung.
-   Menjaga keamanan, privasi, dan keterlacakan aktivitas pengguna serta
    transaksi.

## 1.3 Prinsip Produk

1.  **Modular:** setiap fitur dapat dikembangkan, diaktifkan,
    dinonaktifkan, atau ditingkatkan secara terpisah.
2.  **Subscription-aware:** seluruh fitur berbayar harus memeriksa
    paket, add-on, kuota, status langganan, dan hak akses.
3.  **Provider-agnostic:** integrasi AI dan layanan eksternal tidak
    boleh mengunci keseluruhan sistem pada satu vendor.
4.  **Admin-configurable:** harga, paket, add-on, kuota, template, dan
    aturan bisnis dikelola melalui Admin Panel.
5.  **Traceable:** setiap transaksi, penggunaan kuota, perubahan status,
    komisi, dan withdrawal memiliki catatan.
6.  **Secure by default:** akses, file, API key, webhook, dan integrasi
    pihak ketiga harus dilindungi.
7.  **Human review:** keluaran AI, konten publikasi, dan rekomendasi SEO
    tetap dapat ditinjau pengguna sebelum diterapkan atau
    dipublikasikan.

## 1.4 Batasan dan Asumsi Awal

-   Krafio menggunakan satu tipe akun admin dengan hak akses penuh.
    Tidak ada role admin bertingkat pada versi awal.
-   Akun pengguna dan akun admin dipisahkan melalui otorisasi. Admin
    tidak boleh mengakses panel admin hanya karena mengetahui URL.
-   Satu pengguna dapat memiliki satu atau beberapa proyek/workspace
    sesuai aturan paket yang ditetapkan.
-   Pembayaran menggunakan payment gateway yang dipilih dan disetujui
    pemilik Krafio. Nama provider belum ditetapkan.
-   Integrasi WhatsApp, Facebook, Instagram, TikTok, Threads, dan kanal
    lain mengikuti API resmi, kebijakan platform, proses review
    aplikasi, serta izin yang tersedia. Tidak semua aktivitas otomatis
    dapat dilakukan pada semua platform.
-   GPT dan Claude merupakan kandidat provider AI, bukan ketergantungan
    wajib. Model/provider dapat berubah.
-   ComfyUI dan mesin media lain dapat berjalan di server terpisah
    karena kebutuhan GPU dan resource.
-   Nominal top up, batas withdrawal, dan waktu proses pada dokumen ini
    mengikuti ketentuan awal yang diberikan dan perlu dikonfirmasi
    sebelum peluncuran.
-   Ketersediaan fitur tertentu dapat berbeda berdasarkan wilayah,
    kebijakan platform, biaya provider, dan kapasitas infrastruktur.

------------------------------------------------------------------------

# 2. Ruang Lingkup Produk

## 2.1 Tiga Panel Utama

### A. Website Utama / Frontpage

Halaman publik yang dapat diakses tanpa login.

Fitur: - Landing page Krafio. - Penjelasan manfaat dan daftar fitur. -
Halaman detail setiap fitur. - Galeri template foto dan video. - Halaman
paket berlangganan. - Halaman add-on atau fitur tambahan. -
Kalkulator/perbandingan paket bila diperlukan. - Halaman affiliate dan
informasi komisi. - FAQ, kebijakan privasi, syarat layanan, kebijakan
refund, dan kontak. - Tombol daftar, login, dan mulai berlangganan. -
Informasi status layanan bila terjadi pemeliharaan.

### B. User Panel

Panel utama setelah pengguna login.

Menu yang direncanakan: - Dashboard. - Profil dan pengaturan akun. -
Langganan dan paket aktif. - Add-on dan custom package yang tersedia. -
Saldo dan riwayat transaksi. - Top up. - Withdrawal dan riwayat
withdrawal. - Affiliate dan komisi. - AI Assistant / Chatbot. - Generate
Foto. - Generate Video. - Edit Foto. - Edit Video. - Brainstorming
Konten. - Template Library. - Company Profile Website Builder. - SEO
Tools. - Clip Video. - Integrasi akun sosial. - Content/project
library. - Riwayat aktivitas dan penggunaan kuota. - Pusat notifikasi
dan bantuan.

Menu yang tidak termasuk paket pengguna tetap dapat ditampilkan sebagai
fitur terkunci dengan informasi paket/add-on yang diperlukan. Sistem
tidak boleh hanya menyembunyikan tombol di frontend; backend wajib
memvalidasi hak akses setiap request.

### C. Admin Panel

Satu tipe akun admin dengan hak akses penuh.

Menu admin: - Dashboard operasional. - Manajemen pengguna. - Manajemen
paket berlangganan. - Manajemen add-on. - Package builder / custom
package. - Manajemen harga dan promosi. - Manajemen pembayaran dan
webhook. - Manajemen saldo dan ledger. - Manajemen withdrawal. -
Manajemen affiliate dan komisi. - Manajemen fitur dan entitlement. -
Manajemen kuota dan limit. - Manajemen AI provider, model, dan API
credential. - Manajemen template foto/video. - Manajemen job generate
dan antrean. - Manajemen proyek website company profile. - Manajemen SEO
rules/algoritma. - Manajemen integrasi sosial. - Manajemen konten
publikasi bila tersedia. - Manajemen file dan penyimpanan. - Manajemen
notifikasi. - Manajemen captcha, keamanan, dan konfigurasi Cloudflare. -
Audit log. - System health, error log, dan monitoring. - Pengaturan umum
platform.

**Catatan:** meskipun hanya ada satu tipe admin, tetap gunakan
pencatatan audit untuk setiap tindakan penting. Admin tunggal bukan
alasan untuk menghilangkan kontrol keamanan.

------------------------------------------------------------------------

# 3. Jenis Akun dan Hak Akses

## 3.1 Akun User

Pengguna dapat: - Mendaftar dan login. - Melakukan verifikasi
email/nomor sesuai kebijakan. - Memilih paket. - Membeli add-on. -
Melakukan top up. - Menggunakan fitur sesuai entitlement. - Melihat
kuota dan penggunaan. - Menyimpan dan mengelola proyek. - Menghubungkan
akun sosial. - Melihat hasil generate dan mengunduh file. - Mengelola
affiliate apabila memenuhi syarat. - Mengajukan withdrawal jika memiliki
saldo komisi yang dapat ditarik. - Mengelola chatbot sesuai paket.

## 3.2 Akun Admin

Admin dapat: - Melihat dan mengelola seluruh akun pengguna. - Mengubah
status akun sesuai prosedur. - Membuat, mengubah, mengarsipkan, dan
mengaktifkan paket. - Membuat add-on dan menetapkan kompatibilitasnya. -
Menentukan harga, kuota, masa berlaku, dan batas pemakaian. - Mengatur
provider AI dan model yang tersedia. - Mengatur template, SEO rules, dan
parameter tools. - Memeriksa transaksi, saldo, komisi, dan withdrawal. -
Menyetujui atau menolak withdrawal disertai alasan. - Melihat seluruh
job, error, penggunaan, dan audit log. - Mengatur integrasi dan
kredensial layanan. - Mengelola konten frontpage.

## 3.3 Matriks Akses Ringkas

  -----------------------------------------------------------------------
  Area                Pengunjung             User                   Admin
  ------------- ---------------- ---------------- -----------------------
  Melihat                     Ya               Ya                      Ya
  frontpage                                       

  Melihat                     Ya               Ya                      Ya
  harga/paket                                     
  publik                                          

  Membuat akun                Ya      Tidak perlu                   Tidak

  Menggunakan              Tidak     Sesuai paket                   Untuk
  fitur                                             operasional/pengujian
  berbayar                                        dengan mekanisme khusus

  Melihat saldo            Tidak               Ya        Ya, seluruh user
  sendiri                                         

  Mengelola                Tidak            Tidak                      Ya
  paket                                           

  Mengelola AI             Tidak            Tidak                      Ya
  provider                                        

  Mengelola SEO            Tidak            Tidak                      Ya
  rules                                           

  Mengelola           Mengajukan Mengajukan milik       Memproses seluruh
  withdrawal       setelah login          sendiri               pengajuan

  Mengelola                Tidak       Akun milik  Konfigurasi sistem dan
  integrasi                               sendiri    dukungan operasional
  -----------------------------------------------------------------------

------------------------------------------------------------------------

# 4. Model Bisnis dan Langganan

## 4.1 Jenis Produk Berbayar

Krafio menggunakan kombinasi: - Paket berlangganan utama. - Add-on
fitur. - Custom package yang disusun admin. - Top up saldo. -
Kemungkinan pembelian kuota tambahan apabila diaktifkan. - Komisi
affiliate untuk promosi yang memenuhi ketentuan.

## 4.2 Paket Berlangganan

Setiap paket minimal memiliki atribut: - Nama paket. - Slug/kode
paket. - Deskripsi. - Harga. - Siklus tagihan: bulanan, tahunan, atau
periode lain. - Masa aktif. - Daftar fitur yang dibuka. - Kuota setiap
fitur. - Batas proyek/workspace. - Batas penyimpanan. - Batas integrasi
akun sosial. - Batas penggunaan chatbot. - Batas antrean atau prioritas
job. - Status aktif/nonaktif. - Urutan tampilan di frontpage. -
Ketentuan pembaruan dan kedaluwarsa.

Harga dan isi paket tidak ditetapkan permanen di kode. Admin harus dapat
mengubah konfigurasi paket dari panel.

## 4.3 Add-on dan Custom Package

Admin dapat membuat add-on, misalnya: - Tambahan kuota generate foto. -
Tambahan menit generate video. - Paket template premium. - Tambahan
jumlah akun sosial. - SEO advanced. - Chatbot dengan limit lebih
tinggi. - Website builder tambahan. - Tambahan storage. - Akses fitur
tertentu yang tidak termasuk paket utama.

Aturan: 1. Add-on harus memiliki nama, harga, masa berlaku, dan
entitlement yang diberikan. 2. Admin dapat menentukan apakah add-on
hanya berlaku untuk paket tertentu atau semua paket. 3. Add-on dapat
bersifat satu kali, berulang, atau mengikuti masa langganan. 4. Sistem
harus memeriksa konflik add-on, misalnya add-on tidak dapat dibeli jika
fitur dasarnya belum tersedia. 5. Custom package dibuat melalui Admin
Panel dengan memilih kombinasi fitur, kuota, harga, dan masa berlaku. 6.
Pengguna hanya dapat membeli custom package yang sudah dipublikasikan
atau ditawarkan admin. 7. Perubahan paket tidak boleh mengubah
invoice/transaksi lama. Simpan snapshot harga dan entitlement saat
pembelian.

## 4.4 Status Langganan

Status minimal: - `pending_payment` - `active` - `past_due` (bila ada
skema tagihan berulang) - `expired` - `cancelled` - `suspended`

Aturan: - Langganan aktif setelah pembayaran terkonfirmasi melalui
callback/webhook yang valid. - Redirect sukses dari browser tidak cukup
untuk mengaktifkan paket. - Jika masa aktif berakhir, fitur berbayar
dikunci sesuai grace period yang ditetapkan admin. - Data dan hasil
pekerjaan pengguna tidak otomatis dihapus saat langganan berakhir. -
Pengguna dapat memperpanjang atau membeli paket baru. - Kebijakan
downgrade/upgrade harus ditetapkan: prorata, langsung berlaku, atau
berlaku pada periode berikutnya. - Sistem menyimpan histori perubahan
langganan.

## 4.5 Entitlement dan Feature Gate

Gunakan satu mekanisme entitlement terpusat.

Contoh konsep: - `photo.generate` - `video.generate` - `photo.edit` -
`video.edit` - `content.brainstorm` - `templates.premium` -
`website.builder` - `seo.basic` - `seo.advanced` -
`social.integrations` - `chatbot.basic` - `chatbot.premium` -
`video.clipper` - `exports.download`

Setiap entitlement dapat memiliki: - Status aktif. - Kuota. - Periode
reset kuota. - Batas ukuran/durasi file. - Batas kualitas/resolusi. -
Batas jumlah proyek. - Batas jumlah akun integrasi.

**Validasi wajib dilakukan di backend** sebelum job atau tindakan
berbayar dijalankan.

------------------------------------------------------------------------


---

## Integrasi Spesifikasi Admin Panel pada Bab 4

### Spesifikasi Admin Panel Terintegrasi — Product & Subscription Management
Admin dapat mengatur seluruh produk komersial Krafio.

#### Subscription Plans

Admin dapat membuat paket seperti Free, Basic, Pro, Business,
Enterprise, atau Custom. Nama paket tidak harus permanen; Admin dapat
menambah dan mengubah paket.

Setiap paket memiliki:

-   Nama.
-   Harga.
-   Durasi.
-   Kuota AI.
-   Kuota generate gambar.
-   Kuota generate video.
-   Kuota editing.
-   Akses chatbot.
-   Akses AI Agent.
-   Akses template.
-   Akses website builder.
-   Akses SEO Tools.
-   Batas penyimpanan.
-   Batas jumlah website.
-   Batas ekspor.
-   Fitur tambahan.

#### Add-ons

Admin juga dapat membuat add-on, contohnya:

-   Tambahan token.
-   Tambahan kredit generate gambar.
-   Tambahan kredit video.
-   Tambahan storage.
-   Tambahan website.
-   Tambahan penggunaan AI Agent.

#### Custom Package

Jika pengguna membutuhkan paket khusus, mereka bisa mengajukan
permintaan.

``` text
USER
  |
  v
CUSTOM PACKAGE REQUEST
  |
  v
APPROVAL MANAGEMENT
  |
  v
ADMIN MEMERIKSA KEBUTUHAN
  |
  v
ADMIN MENENTUKAN HARGA
  |
  v
KIRIM PENAWARAN KE USER
  |
  v
USER MENYETUJUI
  |
  v
PEMBAYARAN
  |
  v
CUSTOM PACKAGE AKTIF
```

Admin dapat mengubah harga, kuota, fitur, dan masa berlaku sebelum
penawaran diberikan.

# 5. Saldo, Top Up, Pembayaran, dan Withdrawal

## 5.1 Konsep Saldo: Pisahkan Uang Top Up dan Komisi

Agar tidak terjadi pencampuran dana, Krafio wajib menggunakan minimal
dua jenis saldo:

1.  **Saldo Top Up / Credit:** dana yang diisi pengguna untuk membayar
    langganan, add-on, atau layanan Krafio. Saldo ini pada prinsipnya
    bukan saldo yang dapat ditarik kembali oleh pengguna, kecuali ada
    refund yang disetujui sesuai kebijakan.
2.  **Saldo Komisi / Affiliate Earnings:** pendapatan komisi yang
    diperoleh dari aktivitas affiliate/promosi yang valid. Saldo ini
    dapat diajukan untuk withdrawal setelah memenuhi syarat.

Jangan menggunakan satu angka saldo yang mencampur dana top up dan
komisi. Di dashboard, keduanya boleh ditampilkan berdampingan, tetapi
ledger dan aturan penggunaannya harus terpisah.

## 5.2 Sumber Saldo

### Saldo Top Up

Berasal dari: - Pembayaran top up yang berhasil. - Kredit promosi/bonus
yang diberikan admin, jika kebijakan mengizinkan. - Penyesuaian/refund
internal yang tercatat.

### Saldo Komisi

Berasal dari: - Komisi affiliate atas transaksi yang memenuhi syarat. -
Bonus promosi yang secara eksplisit ditetapkan sebagai saldo dapat
ditarik. - Penyesuaian admin yang disertai alasan dan audit log.

Komisi tidak langsung dapat ditarik ketika transaksi referral baru
terjadi. Gunakan status komisi: - `pending`: transaksi belum selesai
atau masih dalam masa validasi. - `approved`: komisi telah memenuhi
ketentuan. - `available`: komisi sudah masuk saldo yang dapat ditarik. -
`reversed`: komisi dibatalkan karena refund, chargeback, fraud, atau
pelanggaran. - `paid`: komisi telah dibayarkan melalui withdrawal.

## 5.3 Aturan Top Up

Ketentuan awal: - Minimal top up: **Rp50.000**. - Maksimal top up per
transaksi: **Rp1.000.000**.

Admin dapat mengatur nominal minimum/maksimum di masa mendatang.

Alur: 1. User memilih menu Top Up. 2. User memasukkan nominal yang
memenuhi batas. 3. Backend membuat transaksi top up dengan status
`pending`. 4. Sistem membuat payment session/invoice melalui payment
gateway. 5. User menyelesaikan pembayaran pada kanal yang disediakan
gateway. 6. Payment gateway mengirim webhook/callback. 7. Backend
memverifikasi signature, nominal, merchant reference, status, dan
idempotency. 8. Jika valid dan sukses, saldo credit bertambah satu kali.
9. Sistem mencatat ledger dan mengirim notifikasi. 10. Jika
gagal/kedaluwarsa, saldo tidak bertambah.

Aturan tambahan: - Nominal tidak boleh diubah setelah invoice dibuat. -
Webhook duplikat tidak boleh menambah saldo dua kali. - Status
pembayaran harus dapat direkonsiliasi. - Admin tidak boleh mengubah
saldo langsung tanpa membuat ledger adjustment dengan alasan.

## 5.4 Pembayaran Langganan dan Add-on

Sumber pembayaran: - Saldo top up. - Payment gateway langsung. -
Kombinasi keduanya hanya jika memang dirancang dan diuji.

Rekomendasi versi awal: izinkan pembayaran menggunakan saldo credit atau
payment gateway langsung. Hindari kombinasi dua sumber dalam satu
transaksi pada tahap pertama agar rekonsiliasi lebih sederhana.

Alur pembayaran dari saldo: 1. User memilih paket/add-on. 2. Sistem
menampilkan harga dan ringkasan. 3. Backend memvalidasi harga terkini
dan entitlement. 4. Backend memeriksa saldo. 5. Sistem membuat order. 6.
Saldo dikurangi melalui transaksi ledger atomik. 7. Langganan/add-on
diaktifkan. 8. Invoice dan notifikasi dibuat.

Jika proses aktivasi gagal setelah saldo didebit, sistem harus melakukan
kompensasi/refund internal secara otomatis dan tercatat.

## 5.5 Withdrawal

Ketentuan awal: - Minimal withdrawal: **Rp100.000**. - Waktu proses:
**1--3 hari kerja** setelah pengajuan dinyatakan valid. - Sumber dana:
hanya saldo komisi/affiliate yang berstatus tersedia, bukan saldo top
up.

Data pengajuan: - User. - Nominal. - Nama pemilik rekening/e-wallet. -
Nama bank/provider. - Nomor rekening/akun tujuan. - Tanggal pengajuan. -
Status. - Catatan admin. - Bukti transfer atau referensi pembayaran. -
Waktu diproses dan diselesaikan.

Status withdrawal: - `requested` - `under_review` - `approved` -
`processing` - `paid` - `rejected` - `cancelled`

Alur: 1. User membuka menu Withdrawal. 2. Sistem menampilkan saldo
komisi yang tersedia. 3. User memasukkan nominal minimal Rp100.000. 4.
User memilih/menambahkan tujuan pencairan. 5. Backend memvalidasi saldo
tersedia dan data tujuan. 6. Sistem memindahkan nominal ke saldo
tertahan/reserved agar tidak diajukan dua kali. 7. Pengajuan masuk ke
Admin Panel. 8. Admin memeriksa validitas komisi dan data penerima. 9.
Admin menyetujui atau menolak. 10. Jika disetujui, admin melakukan
pembayaran melalui kanal pencairan yang digunakan. 11. Admin memasukkan
bukti/referensi pembayaran. 12. Status menjadi `paid`; ledger komisi
ditandai selesai. 13. User menerima notifikasi.

Jika ditolak, dana yang sebelumnya di-reserve dikembalikan ke saldo
komisi tersedia, kecuali terdapat alasan hukum/fraud yang membutuhkan
penanganan khusus.

**Catatan kebijakan:** tetapkan dengan jelas apakah biaya transfer,
pajak, verifikasi identitas, dan batas withdrawal harian berlaku.
Informasikan sebelum pengguna mengajukan pencairan.

## 5.6 Affiliate

Komponen: - Kode referral unik. - Link referral. - Atribusi referral. -
Masa atribusi/cookie window. - Komisi berdasarkan persentase atau
nominal tetap. - Aturan transaksi yang memenuhi syarat. - Masa tunggu
sebelum komisi tersedia. - Penanganan refund dan chargeback. - Dashboard
klik, referral, konversi, komisi pending, komisi tersedia, dan komisi
dibatalkan.

Admin mengatur: - Persentase/nominal komisi. - Produk/paket yang
memperoleh komisi. - Periode atribusi. - Batas dan syarat pembayaran. -
Status program affiliate. - Ketentuan anti-self-referral dan anti-fraud.

Jangan menghitung komisi dari top up kecuali secara eksplisit menjadi
kebijakan. Rekomendasi awal: komisi dihitung dari transaksi
langganan/add-on yang valid dan telah melewati masa refund/validasi.

------------------------------------------------------------------------

# 6. Modul Fitur Krafio

## 6.1 Generate Foto

Tujuan: menghasilkan gambar dari prompt teks atau referensi gambar.

Fitur: - Prompt utama. - Negative prompt bila model mendukung. - Rasio
gambar. - Resolusi/kualitas sesuai paket. - Jumlah variasi. -
Style/preset. - Referensi gambar. - Riwayat prompt. - Simpan ke
project/library. - Download hasil. - Regenerate/variation. - Metadata
model dan biaya penggunaan.

Alur: 1. User membuka Generate Foto. 2. Sistem memeriksa entitlement dan
kuota. 3. User memilih model/preset. 4. User mengisi prompt dan
parameter. 5. Backend memvalidasi file dan parameter. 6. Sistem membuat
job antrean. 7. Worker meneruskan request ke provider yang sesuai. 8.
Hasil disimpan di object storage. 9. Status job diperbarui. 10. User
menerima notifikasi dan dapat melihat/mengunduh hasil.

Status job: `queued`, `processing`, `completed`, `failed`, `cancelled`.

## 6.2 Generate Video

Tujuan: menghasilkan video dari prompt, gambar, atau kombinasi input
yang didukung model.

Fitur: - Text-to-video. - Image-to-video bila provider mendukung. -
Prompt dan negative prompt. - Durasi. - Rasio. - Resolusi. -
Gerakan/kamera. - Preset. - Antrean dan estimasi waktu. - Preview dan
download. - Riwayat job.

Karena proses video membutuhkan resource besar, kuota sebaiknya
menggunakan satuan yang jelas, misalnya kredit per detik, per job, atau
berdasarkan resolusi/model. Jangan menggunakan limit yang tidak
sebanding dengan biaya provider.

## 6.3 Edit Foto dengan Bantuan AI

Fitur yang dapat disediakan secara bertahap: - Remove background. -
Object removal/inpainting. - Image enhancement/upscale. - Resize dan
crop. - Relighting atau style transfer jika provider mendukung. -
Generative fill. - Retouch dasar. - Edit melalui instruksi bahasa
alami. - Perbandingan before/after. - Undo/redo dan versi hasil.

Simpan file asli dan hasil edit sebagai versi berbeda. Jangan menimpa
file sumber secara permanen tanpa persetujuan pengguna.

## 6.4 Edit Video dengan Bantuan AI

Fitur: - Trim, cut, split, merge. - Crop dan resize. -
Subtitle/caption. - Penghapusan bagian tertentu jika didukung. - Audio
cleanup bila tersedia. - Transisi dan template. - Auto highlight. -
Perintah edit berbasis prompt untuk operasi yang didukung. - Preview
timeline. - Export dengan preset.

Gunakan editor berbasis browser atau engine rendering di backend sesuai
kemampuan teknis. Operasi berat dijalankan sebagai job asynchronous.

## 6.5 Brainstorming Content

AI membantu menghasilkan: - Ide konten. - Content pillar. - Kalender
konten. - Hook. - Caption. - Script video. - CTA. - Hashtag. - Angle
promosi. - Ide kampanye. - Adaptasi konten untuk platform berbeda. -
Brief produksi.

Form masukan: - Brand/produk. - Target audiens. - Tujuan konten. -
Platform tujuan. - Gaya bahasa. - Bahasa. - Durasi/format. - Keunggulan
produk. - Larangan atau batasan brand.

Output harus dapat disimpan sebagai project dan diteruskan ke generator
foto/video atau clipper.

## 6.6 Template Foto dan Video 100+

Template Library menargetkan minimal 100 template foto/video pada tahap
peluncuran yang disepakati.

Kategori contoh: - Produk UMKM. - Kuliner. - Fashion/fesyen. -
Properti. - Personal branding. - Promo dan diskon. - Testimoni. -
Edukasi. - Reels/Shorts/TikTok. - Company profile. - Story dan feed. -
Iklan produk.

Setiap template memiliki: - Nama dan kategori. - Preview. - Jenis
media. - Rasio. - Durasi bila video. - Elemen yang dapat diedit. -
Placeholder. - Instruksi penggunaan. - Status free/premium. -
Paket/add-on yang diperlukan. - Versi dan tanggal pembaruan.

Template tidak boleh menampilkan aset berhak cipta tanpa izin. Lisensi
font, musik, footage, dan elemen grafis wajib diperiksa.

## 6.7 Company Profile Website Automation

Tujuan: membantu user membuat website company profile dari template yang
telah disediakan, dengan kombinasi AI dan editor manual.

Fitur: - Pilih template. - Isi data perusahaan. - Upload logo, foto, dan
materi. - Generate draft konten dengan AI. - Edit teks manual. - Edit
warna, font, gambar, section, dan urutan konten. - Preview
desktop/tablet/mobile. - Simpan beberapa versi. - Export/download hasil
sesuai paket. - Opsi publish/hosting dapat menjadi tahap lanjutan.

Section template: - Hero. - Tentang perusahaan. - Produk/layanan. -
Keunggulan. - Portofolio. - Galeri. - Testimoni. - Tim. - Kontak. -
CTA. - Footer.

AI hanya membuat draft. User tetap dapat mengedit seluruh konten yang
diizinkan. Sistem harus memiliki validasi responsive, link, gambar,
metadata SEO, dan aksesibilitas dasar.

**Keputusan ruang lingkup:** bedakan antara website builder/export dan
hosting/publish. Jika Krafio menyediakan hosting langsung, perlu modul
domain, SSL, deployment, bandwidth, dan penyalahgunaan hosting. Jika
belum, tahap pertama cukup membuat preview dan export hasil.

## 6.8 SEO Tools dengan Rules Engine Krafio

SEO Tools merupakan salah satu pembeda produk. Rekomendasi dan penilaian
tidak hanya bergantung pada jawaban AI, tetapi memakai aturan/rubrik
yang ditentukan Krafio.

Komponen: 1. **Input:** URL, keyword, topik, target lokasi, bahasa, dan
jenis halaman. 2. **Crawler/analyzer:** mengambil data yang diizinkan
dari URL. 3. **Parser:** membaca title, meta description, heading,
konten, link, gambar, canonical, robots, dan struktur teknis yang dapat
diakses. 4. **Rules Engine:** menjalankan aturan yang dikelola Krafio.
5. **Scoring:** memberi nilai berdasarkan bobot dan kriteria. 6.
**Recommendation:** menghasilkan daftar masalah dan prioritas perbaikan.
7. **AI explanation:** menjelaskan hasil dengan bahasa yang mudah
dipahami. 8. **Report:** menyimpan dan mengunduh laporan.

Contoh kelompok aturan: - Technical SEO. - On-page SEO. - Content
quality. - Keyword placement. - Heading structure. - Meta
title/description. - Internal linking. - Image alt text. - Page
structure. - Mobile usability (jika data tersedia). - Performance/Core
Web Vitals (jika pengukuran tersedia). - Structured data. -
Indexability.

Admin dapat mengatur: - Nama rule. - Kategori. - Kondisi pemeriksaan. -
Bobot/skor. - Tingkat prioritas. - Rekomendasi. - Versi rule. - Status
aktif. - Target halaman/jenis website.

Setiap hasil analisis harus menyimpan versi ruleset yang digunakan agar
laporan lama tetap dapat dijelaskan. Hindari klaim bahwa skor Krafio
adalah skor resmi mesin pencari. Tampilkan sebagai skor evaluasi
internal Krafio.

Perlindungan crawler: - Batasi domain/IP privat dan alamat internal
untuk mencegah SSRF. - Batasi redirect, ukuran halaman, waktu proses,
dan jumlah URL. - Hormati robots/policy yang relevan. - Jangan mengambil
data login atau halaman privat tanpa otorisasi. - Gunakan rate limit.

## 6.9 Chatbot / AI Assistant

Tersedia minimal dua tingkat: - **Free/Basic:** menjawab pertanyaan umum
dengan limit terbatas. - **Berlangganan/Premium:** limit lebih tinggi,
model lebih kuat, konteks lebih panjang, atau akses ke fitur assistant
tambahan sesuai paket.

Fitur: - Chat berbasis teks. - Riwayat percakapan. - Pemilihan
assistant/preset. - Konteks brand/project jika diizinkan. - Prompt
system yang dikelola Krafio. - Batas token/pesan. - Deteksi penggunaan
berlebihan. - Feedback jawaban.

AI provider dapat menggunakan GPT, Claude, atau provider lain. Pengguna
tidak harus memilih provider secara langsung; admin dapat menentukan
model berdasarkan fitur, biaya, dan kualitas.

## 6.10 Video Clipper dan Video dari Form/Prompt

Tujuan: mengubah video panjang menjadi klip pendek atau membuat video
pendek berdasarkan brief yang terstruktur.

Mode A --- Clip dari video: - Upload atau pilih video dari library. -
Tentukan platform target. - Pilih durasi target. - Pilih jumlah klip. -
Pilih bahasa subtitle. - Tentukan gaya caption. - AI mendeteksi momen
penting. - Sistem membuat kandidat klip. - User mengedit hasil sebelum
export.

Mode B --- Generate video dari form/prompt: - User mengisi form
terstruktur. - Form dapat mencakup tujuan, produk, target audiens, pesan
utama, hook, durasi, gaya visual, scene, CTA, rasio, bahasa, dan
platform. - AI mengubah input menjadi brief/script/storyboard. - User
meninjau brief. - Setelah disetujui, sistem meneruskan ke generator
video atau workflow rendering. - Hasil dapat diedit dan diexport.

Klip otomatis tidak boleh langsung dipublikasikan tanpa persetujuan user
kecuali fitur auto-publish diaktifkan secara eksplisit dan mendapat izin
platform.

## 6.11 Integrasi Media Sosial

Target integrasi: - WhatsApp Business. - Facebook. - Instagram. -
TikTok. - Threads.

Fitur potensial: - Connect/disconnect akun. - OAuth/authorization. -
Menampilkan akun yang terhubung. - Mengambil data insight yang
diizinkan. - Menampilkan status posting. - Menjadwalkan/mempublikasikan
konten jika API mengizinkan. - Mengambil komentar/pesan jika izin dan
API tersedia. - Menghubungkan hasil konten Krafio dengan akun tujuan. -
Memantau data performa yang tersedia.

Setiap platform memiliki batas API, permission, proses app review, jenis
akun, dan kebijakan berbeda. Karena itu: - Jangan menjanjikan seluruh
fitur integrasi sebelum uji API. - Jangan meminta password media sosial
pengguna. - Gunakan OAuth atau mekanisme resmi. - Token harus dienkripsi
dan dapat dicabut. - Tampilkan izin yang diminta. - Sediakan disconnect
dan penghapusan token. - Tangani token kedaluwarsa dan refresh token. -
Simpan sumber, waktu sinkronisasi, serta status data. - Sediakan
fallback manual upload/export jika API tidak mendukung fungsi tertentu.

**Khusus WhatsApp:** utamakan WhatsApp Business Platform/Cloud API untuk
penggunaan resmi. Dashboard WhatsApp Business API open-source yang
ditemukan dapat menjadi referensi UI/operasional, tetapi bukan pengganti
persetujuan dan kredensial Meta.

------------------------------------------------------------------------

# 7. Integrasi AI dan Arsitektur Provider

## 7.1 AI Gateway

Krafio sebaiknya memiliki satu lapisan AI Gateway internal.

Tanggung jawab: - Menerima request dari modul. - Memeriksa hak akses dan
kuota. - Memilih provider/model berdasarkan konfigurasi. - Mengubah
format request internal ke format provider. - Mengelola timeout dan
retry. - Menangani response dan error. - Mencatat token/biaya/usage. -
Menyamarkan credential. - Menjalankan fallback bila diizinkan. -
Mencatat versi prompt dan konfigurasi.

Modul tidak boleh memanggil API GPT/Claude langsung dari browser. Semua
request sensitif melalui backend.

## 7.2 Pemilihan Provider

Kriteria: - Kualitas output. - Dukungan gambar/video/teks. -
Kecepatan. - Harga. - Batas rate. - Ketersediaan wilayah. - Lisensi dan
kebijakan penggunaan data. - Kemampuan streaming. - Kompatibilitas
tool/function calling. - Stabilitas API.

Provider dapat berbeda per fitur. Misalnya model teks untuk
brainstorming, model vision untuk analisis gambar, dan engine khusus
untuk generasi media.

## 7.3 ComfyUI

ComfyUI dapat menjadi kandidat engine workflow image generation dan
image processing.

Ketentuan: - Jalankan sebagai service terpisah dari aplikasi utama. -
Gunakan queue dan worker. - Batasi akses jaringan ke ComfyUI. - Jangan
membuka endpoint ComfyUI ke publik tanpa proteksi. - Kelola workflow
sebagai versi yang disetujui. - Validasi input dan output. - Pantau
VRAM, GPU, waktu proses, dan kegagalan. - Sediakan fallback provider
eksternal bila diperlukan.

## 7.4 Prompt Management

Prompt template dikelola terpusat: - Prompt system. - Prompt fitur. -
Prompt template. - Prompt versi. - Variabel input. - Bahasa. -
Provider/model yang kompatibel. - Contoh input/output. - Status
draft/active/archived.

Prompt optimizer dapat digunakan sebagai alat internal untuk membantu
menyusun prompt, bukan sebagai satu-satunya sumber kebenaran. Prompt
yang telah disetujui harus diuji sebelum dipublikasikan.

------------------------------------------------------------------------


---

## Integrasi Spesifikasi Admin Panel pada Bab 7

### Spesifikasi Admin Panel Terintegrasi — AI Management --- Kendali Penuh AI
Admin harus dapat mengendalikan seluruh provider, model, agent,
penggunaan, limit, biaya, dan performa AI.

#### A. AI Provider Management

Admin bisa menambahkan provider baru, misalnya:

-   OpenAI.
-   Anthropic.
-   Google AI.
-   Provider kompatibel OpenAI API.
-   Provider image generation.
-   Provider video generation.

Informasi konfigurasi:

-   Provider Name.
-   Base URL.
-   API Key.
-   Model yang tersedia.
-   Status provider.
-   Priority.
-   Timeout.
-   Retry.
-   Rate Limit.
-   Concurrency.
-   Pricing.
-   Fallback Provider.

API Key disimpan secara terenkripsi dan tidak ditampilkan kembali dalam
bentuk plaintext.

#### B. AI Model Management

Admin dapat mengelola model satu per satu.

  Model     Provider     Status
  --------- ------------ ----------
  Model A   Provider 1   Active
  Model B   Provider 2   Active
  Model C   Provider 3   Disabled

Admin dapat menambahkan model, mengubah konfigurasi, mengatur harga
input/output, context window, kemampuan model, prioritas, status
aktif/nonaktif, dan model default untuk setiap fitur.

Dengan demikian, fitur brainstorming bisa menggunakan model berbeda dari
fitur chatbot atau AI editing.

#### C. AI Token Monitoring

Admin dapat melihat berapa token yang sudah digunakan dan berapa
sisanya.

  Provider     Model       Limit Internal    Terpakai   Sisa Internal
  ------------ --------- ---------------- ----------- ---------------
  Provider A   Model 1          1.000.000     650.000         350.000
  Provider B   Model 2            500.000     300.000         200.000
  Provider C   Model 3          2.000.000   1.200.000         800.000

Angka tersebut hanya ilustrasi.

Informasi yang ditampilkan:

1.  Total input token.
2.  Total output token.
3.  Total token.
4.  Total request.
5.  Token per model.
6.  Token per provider.
7.  Token per fitur.
8.  Token per pengguna.
9.  Penggunaan harian.
10. Penggunaan bulanan.
11. Limit internal.
12. Sisa limit internal.
13. Estimasi biaya.
14. Waktu reset jika tersedia.

**Perbedaan limit provider dan limit Krafio**

-   **Provider Limit:** Limit resmi yang diberikan oleh provider dan
    dapat dibaca dari dashboard/API resmi mereka.
-   **Krafio Internal Limit:** Limit yang ditentukan sendiri oleh Admin
    berdasarkan kebutuhan bisnis.
-   **Estimated Remaining:** Estimasi sisa berdasarkan pemakaian yang
    tercatat Krafio.

Tidak semua provider menyediakan API untuk membaca sisa kuota secara
langsung. Krafio tidak boleh menampilkan estimasi internal seolah-olah
itu merupakan angka kuota resmi provider.

#### D. AI Cost Monitoring

Admin dapat melihat pengeluaran AI.

  Fitur                    Penggunaan   Estimasi Biaya
  ------------------ ---------------- ----------------
  Chatbot              10.000 request        Rp250.000
  Brainstorming         2.000 request        Rp100.000
  Image Generation         500 gambar        Rp350.000
  Video Generation          100 video        Rp750.000

Admin juga dapat melihat biaya harian, mingguan, bulanan, per pengguna,
per paket, per model, per fitur, serta margin antara pendapatan dan
biaya AI.

Untuk image dan video, sistem harus menghitung unit yang relevan,
seperti jumlah gambar, resolusi, durasi video, frame, GPU time, atau
kredit provider. Jangan memaksakan semuanya menjadi hitungan token.

#### E. AI Limit Alert

Admin dapat menentukan batas peringatan.

  Penggunaan   Tindakan
  ------------ ---------------------
  50%          Peringatan normal.
  75%          Peringatan.
  90%          Peringatan penting.
  100%         Limit tercapai.

Admin dapat mengatur tindakan ketika limit tercapai:

-   Mengirim notifikasi.
-   Membatasi fitur tertentu.
-   Menghentikan request baru.
-   Beralih ke provider cadangan.
-   Menggunakan model yang lebih ekonomis.
-   Menonaktifkan provider sementara.

#### F. AI Fallback System

``` text
USER MEMINTA GENERATE
        |
        v
KRAFIO AI GATEWAY
        |
        v
CEK PROVIDER UTAMA
        |
        +----------------------+
        |                      |
        v                      v
TERSEDIA                LIMIT / ERROR
        |                      |
        v                      v
PROSES REQUEST          CEK FALLBACK
        |                      |
        v                      v
HASIL BERHASIL          PROVIDER CADANGAN
                               |
                               v
                         PROSES REQUEST
                               |
                     +---------+---------+
                     |                   |
                     v                   v
                   SUKSES              GAGAL
                     |                   |
                     v                   v
                KIRIM HASIL         STATUS FAILED
```

Fallback harus mempertimbangkan kompatibilitas model, kemampuan fitur,
biaya, dan kebijakan data. Retry juga harus dirancang agar tidak
menggandakan request atau biaya.

### Spesifikasi Admin Panel Terintegrasi — AI Agent Management
Admin dapat membuat dan mengelola AI Agent, misalnya:

-   AI Business Assistant.
-   AI Content Creator.
-   AI SEO Assistant.
-   AI Marketing Assistant.
-   AI Website Assistant.
-   AI Product Description Generator.

Admin dapat mengatur nama Agent, deskripsi, System Prompt, model AI,
provider, tools yang dapat digunakan, batas token, batas biaya, batas
iterasi, hak akses fitur, Knowledge Base, serta status aktif/nonaktif.

Admin dapat menguji Agent sebelum dipublikasikan.

Untuk Agent yang dapat melakukan tindakan eksternal, seperti
memublikasikan konten atau mengirim pesan, harus ada persetujuan
pengguna sesuai risiko tindakannya.

# 8. PWA dan Pengalaman Pengguna

## 8.1 Persyaratan PWA

-   Web app responsive.
-   Web App Manifest.
-   Service Worker.
-   Installable pada browser/perangkat yang mendukung.
-   Icon aplikasi dan splash/launch behavior yang sesuai.
-   HTTPS wajib.
-   Cache untuk shell aplikasi dan aset statis.
-   Offline state yang informatif.
-   Update aplikasi yang terkontrol.

## 8.2 Batasan Offline

Fitur AI, pembayaran, sinkronisasi sosial, dan job server membutuhkan
koneksi internet. PWA tidak boleh menampilkan seolah-olah proses
generate dapat berjalan offline.

Yang dapat diakses offline secara terbatas: - App shell. - Halaman
bantuan yang telah dicache. - Draft lokal jika memang dirancang. -
Status offline dan instruksi untuk menyambung kembali.

Data sensitif, saldo, token, dan hasil privat tidak boleh disimpan
sembarangan di cache browser.

## 8.3 UX Utama

-   Dashboard menampilkan status paket, masa aktif, kuota, saldo credit,
    saldo komisi, dan job terbaru.
-   Fitur terkunci menjelaskan syarat membuka akses.
-   Proses generate menampilkan status antrean/proses.
-   Error harus menjelaskan tindakan berikutnya.
-   Pengguna dapat membatalkan job jika secara teknis masih
    memungkinkan.
-   File hasil memiliki tombol preview/download.
-   Tampilkan estimasi biaya/kuota sebelum job dijalankan.

------------------------------------------------------------------------

# 9. Alur Sistem End-to-End

## 9.1 Alur Pengunjung Menjadi User

1.  Pengunjung membuka frontpage.
2.  Pengunjung melihat fitur dan paket.
3.  Pengunjung memilih daftar.
4.  Sistem memvalidasi email/nomor dan captcha.
5.  User membuat akun.
6.  Sistem mengirim verifikasi bila diwajibkan.
7.  User login.
8.  User masuk ke dashboard.
9.  User memilih paket atau mencoba fitur gratis yang tersedia.

## 9.2 Alur Berlangganan

1.  User memilih paket.
2.  Sistem menampilkan harga, siklus, fitur, kuota, dan syarat.
3.  User memilih metode pembayaran.
4.  Backend membuat order dan invoice.
5.  User membayar.
6.  Gateway mengirim webhook.
7.  Backend memverifikasi webhook.
8.  Sistem mengaktifkan subscription dan entitlement.
9.  Dashboard memperbarui status.
10. User menerima bukti transaksi/notifikasi.

## 9.3 Alur Membeli Add-on

1.  User membuka katalog add-on.
2.  Sistem menampilkan add-on yang kompatibel dengan paket aktif.
3.  User memilih add-on.
4.  Sistem memeriksa konflik dan harga.
5.  User membayar.
6.  Sistem menambahkan entitlement sesuai masa berlaku.
7.  Kuota/fitur diperbarui.
8.  Riwayat pembelian disimpan.

## 9.4 Alur Menggunakan Fitur AI

1.  User memilih fitur.
2.  Backend memeriksa autentikasi.
3.  Backend memeriksa subscription, add-on, entitlement, kuota, dan
    saldo bila diperlukan.
4.  User mengisi form/prompt.
5.  Backend memvalidasi input.
6.  Sistem membuat job.
7.  Worker menjalankan provider/engine.
8.  Sistem menyimpan output.
9.  Sistem mengurangi atau mengunci kuota sesuai kebijakan.
10. Job selesai/gagal.
11. User menerima notifikasi dan hasil.

**Rekomendasi billing job:** tentukan apakah kuota dipotong saat job
dimulai atau setelah berhasil. Untuk menghindari kerugian user akibat
kegagalan provider, gunakan reservasi kuota saat job dimulai dan
finalisasi saat berhasil; jika gagal karena kesalahan sistem/provider,
lepaskan reservasi atau kembalikan kuota.

## 9.5 Alur Integrasi Sosial

1.  User memilih platform.
2.  Sistem menjelaskan izin yang dibutuhkan.
3.  User diarahkan ke OAuth/authorization resmi.
4.  Platform mengembalikan authorization code.
5.  Backend menukar code dengan token.
6.  Token dienkripsi dan disimpan.
7.  Sistem mengambil profil/akun yang diizinkan.
8.  User memilih akun yang akan dipakai.
9.  Sinkronisasi berjalan sesuai izin dan jadwal.
10. User dapat memutus koneksi kapan saja.

## 9.6 Alur Affiliate dan Withdrawal

1.  User mengaktifkan/bergabung dengan program affiliate jika memenuhi
    syarat.
2.  Sistem membuat referral link.
3.  Calon pelanggan menggunakan link.
4.  Sistem mencatat atribusi sesuai aturan.
5.  Pelanggan melakukan transaksi.
6.  Komisi masuk status pending.
7.  Setelah transaksi valid dan masa tunggu selesai, komisi menjadi
    available.
8.  User mengajukan withdrawal minimal Rp100.000.
9.  Sistem reserve saldo.
10. Admin memeriksa pengajuan.
11. Admin menyetujui/menolak.
12. Jika disetujui, pembayaran dilakukan dalam target 1--3 hari kerja.
13. Bukti pembayaran disimpan dan user diberi notifikasi.

------------------------------------------------------------------------

# 10. Cloudflare, Keamanan, dan Anti-Abuse

## 10.1 Komponen Cloudflare

Cloudflare dapat digunakan untuk: - DNS management. - CDN dan caching
aset statis. - WAF. - DDoS protection. - Rate limiting sesuai
paket/konfigurasi. - SSL/TLS. - Turnstile captcha. - Bot protection
sesuai ketersediaan. - Security headers melalui konfigurasi
aplikasi/server.

Cloudflare bukan pengganti keamanan backend. Validasi, autentikasi,
otorisasi, enkripsi, dan audit tetap wajib dilakukan di aplikasi.

## 10.2 Autentikasi dan Sesi

-   Password di-hash dengan algoritma yang aman.
-   Terapkan rate limit login.
-   Verifikasi email/nomor sesuai kebijakan.
-   Admin wajib menggunakan MFA.
-   Session cookie menggunakan HttpOnly, Secure, dan SameSite yang
    sesuai.
-   Logout mencabut sesi.
-   Terapkan proteksi CSRF bila menggunakan cookie session.
-   Terapkan reset password dengan token sekali pakai dan masa berlaku.
-   Jangan menyimpan password atau API key dalam log.

## 10.3 Otorisasi

-   Terapkan middleware autentikasi dan policy/permission di backend.
-   Pisahkan route user dan admin.
-   Validasi kepemilikan project, file, transaksi, dan integrasi.
-   Cegah IDOR dengan memeriksa owner setiap resource.
-   Semua endpoint berbayar memeriksa entitlement server-side.
-   Admin route wajib memeriksa role admin dan MFA/session yang valid.

## 10.4 Perlindungan File

-   Validasi MIME type dan ekstensi.
-   Batasi ukuran file.
-   Scan file bila fasilitas tersedia.
-   Gunakan nama file acak.
-   Simpan file privat di object storage.
-   Gunakan signed URL dengan masa berlaku.
-   Jangan mengeksekusi file upload.
-   Batasi format video/gambar yang diterima.
-   Hapus metadata sensitif bila dibutuhkan.

## 10.5 Keamanan AI

-   API key hanya di server.
-   Rate limit per user dan per fitur.
-   Proteksi prompt injection untuk fitur yang membaca dokumen/URL.
-   Pisahkan instruksi sistem dan konten user.
-   Jangan mengirim data pribadi ke provider tanpa dasar/pemberitahuan
    yang sesuai.
-   Catat provider/model dan penggunaan.
-   Terapkan moderasi input/output sesuai jenis fitur.
-   Batasi tool/action yang dapat dilakukan agent.
-   AI tidak boleh memiliki akses langsung ke saldo, pembayaran, atau
    withdrawal tanpa business logic dan otorisasi backend.

## 10.6 Audit dan Monitoring

Catat minimal: - Login berhasil/gagal. - Perubahan profil penting. -
Perubahan paket/add-on. - Pembayaran dan webhook. - Perubahan
saldo/ledger. - Pengajuan dan keputusan withdrawal. - Perubahan
komisi. - Perubahan AI provider/API credential. - Perubahan SEO rules. -
Perubahan template. - Perubahan konfigurasi keamanan. - Tindakan admin.

Log tidak boleh memuat password, token mentah, atau data pembayaran
sensitif.

------------------------------------------------------------------------

# 11. Arsitektur Teknis yang Direkomendasikan

## 11.1 Pola Arsitektur

Rekomendasi awal: **modular monolith + background workers**, bukan
langsung microservices penuh.

Alasan: - Lebih mudah dikembangkan dan dioperasikan oleh tim kecil. -
Modul bisnis tetap dapat dipisahkan secara logis. - Mengurangi
kompleksitas deployment dan observability. - Worker dapat dipisah ketika
beban generate media meningkat. - Service khusus GPU/ComfyUI dapat
berjalan terpisah.

## 11.2 Komponen Logis

1.  Frontend public website.
2.  Frontend user dashboard.
3.  Frontend admin dashboard.
4.  Backend API.
5.  Authentication & authorization.
6.  Subscription/billing service.
7.  Wallet/ledger service.
8.  Feature entitlement service.
9.  AI gateway.
10. Media processing workers.
11. Video rendering/clipper workers.
12. SEO analyzer/rules engine.
13. Social integration service.
14. Notification service.
15. Queue/message broker.
16. Relational database.
17. Object storage.
18. Cache/session store.
19. Monitoring/logging.
20. Cloudflare edge/security layer.

## 11.3 Database

Gunakan database relasional seperti PostgreSQL atau MySQL/MariaDB sesuai
stack yang dipilih. Struktur final harus dibuat melalui ERD dan
migration.

Tabel/entitas inti yang disarankan:

### Identity

-   `users`
-   `user_profiles`
-   `admin_accounts` atau tabel users dengan role yang aman
-   `user_sessions`
-   `email_verifications`
-   `password_resets`

### Subscription & Catalog

-   `plans`
-   `plan_features`
-   `addons`
-   `addon_entitlements`
-   `custom_packages`
-   `custom_package_items`
-   `subscriptions`
-   `subscription_items`
-   `entitlements`
-   `usage_counters`
-   `usage_reservations`

### Payment & Wallet

-   `orders`
-   `order_items`
-   `payments`
-   `payment_webhook_events`
-   `wallet_accounts`
-   `wallet_ledger_entries`
-   `topup_transactions`
-   `invoices`
-   `refunds`

### Affiliate & Withdrawal

-   `affiliate_profiles`
-   `referral_links`
-   `referral_attributions`
-   `affiliate_commissions`
-   `withdrawal_requests`
-   `withdrawal_destinations`
-   `withdrawal_payouts`

### AI & Media

-   `ai_providers`
-   `ai_models`
-   `ai_usage_logs`
-   `prompt_templates`
-   `prompt_versions`
-   `generation_jobs`
-   `media_assets`
-   `media_asset_versions`
-   `media_projects`
-   `video_projects`
-   `video_clips`
-   `render_jobs`

### Templates & Website Builder

-   `templates`
-   `template_categories`
-   `template_assets`
-   `website_projects`
-   `website_pages`
-   `website_versions`
-   `website_exports`

### SEO

-   `seo_projects`
-   `seo_audits`
-   `seo_rulesets`
-   `seo_rules`
-   `seo_rule_versions`
-   `seo_findings`
-   `seo_reports`

### Social Integration

-   `social_connections`
-   `social_accounts`
-   `social_tokens`
-   `social_sync_jobs`
-   `social_metrics`
-   `social_publication_jobs`

### Platform

-   `notifications`
-   `support_tickets` (opsional)
-   `audit_logs`
-   `system_settings`
-   `feature_flags`
-   `background_jobs` (atau tabel queue dari sistem yang dipakai)

## 11.4 Prinsip Database Keuangan

-   Gunakan ledger append-only untuk transaksi saldo.
-   Jangan mengandalkan update angka saldo tanpa catatan transaksi.
-   Setiap transaksi memiliki reference ID unik.
-   Gunakan database transaction untuk debit/kredit.
-   Terapkan idempotency key untuk pembayaran dan webhook.
-   Pisahkan saldo credit, saldo komisi tersedia, saldo komisi tertahan,
    dan saldo komisi yang telah dibayar.
-   Simpan snapshot harga dan item pada order.
-   Jangan menghapus transaksi keuangan; lakukan reversal/adjustment
    dengan referensi transaksi awal.

## 11.5 Background Job dan Queue

Job yang sebaiknya asynchronous: - Generate foto/video. - Edit media
berat. - Rendering video. - Clipping. - SEO crawl. - Sinkronisasi
sosial. - Pengiriman email/notifikasi. - Export laporan. - Proses file
besar.

Setiap job harus memiliki: - ID. - User/project owner. - Jenis job. -
Input reference. - Provider/worker. - Status. - Progress jika
tersedia. - Waktu mulai/selesai. - Error code dan pesan aman. - Usage
reservation. - Retry count. - Idempotency key.

------------------------------------------------------------------------

# 12. Rekomendasi Pemanfaatan Repository/Open Source

Daftar repository yang ditemukan merupakan kandidat, bukan keputusan
final. Nama proyek saja belum cukup untuk menyimpulkan bahwa proyek
aman, aktif, kompatibel, atau boleh digunakan secara komersial. Sebelum
integrasi, lakukan pemeriksaan repository, lisensi, aktivitas
maintainer, dependency, security advisory, dokumentasi, dan kebutuhan
runtime.

  -----------------------------------------------------------------------
  Kandidat                Potensi pemanfaatan di  Catatan keputusan
                          Krafio                 
  ----------------------- ----------------------- -----------------------
  WhatsApp Business API   Referensi/dashboard     Pastikan menggunakan
  Dashboard               untuk operasional       API resmi Meta dan izin
                          integrasi WhatsApp      yang sah.

  Concat                  Kandidat editor         Uji fitur timeline,
                          video/workflow editing  codec, performa,
                                                  lisensi, dan integrasi
                                                  backend.

  Modly                   Kandidat fitur          Jadikan modul opsional;
                          generate/olah gambar 3D bukan prioritas MVP
                                                  sebelum kebutuhan 3D
                                                  tervalidasi.

  PowerToys               Utilitas desktop untuk  Bukan komponen inti web
                          tim internal            app; hanya membantu
                                                  workflow operator
                                                  Windows.

  Jan                     Kandidat menjalankan    Opsional. Tidak wajib
                          model AI                jika AI Gateway dan
                          lokal/eksperimen        provider cloud sudah
                                                  memadai.

  LosslessCut             Pemotongan video tanpa  Berguna sebagai
                          re-encode untuk         utilitas/engine
                          workflow tertentu       pendukung; cek
                                                  kemampuan server dan
                                                  lisensi.

  Prompt Optimizer        Membantu menyusun dan   Dapat menjadi tool
                          menguji prompt          internal prompt
                                                  engineering.

  ComfyUI                 Workflow image          Kandidat kuat untuk
                          generation dan image    media generation; perlu
                          processing              GPU, queue, isolasi,
                                                  dan monitoring.

  Archify                 Perlu validasi fungsi   Jangan tetapkan sebagai
                          sebenarnya              dependency sebelum repo
                                                  dan use case
                                                  diverifikasi.

  OpenSEO                 Referensi/komponen SEO  Audit kualitas crawler,
                                                  rules, lisensi, dan
                                                  ketepatan analisis.

  Screenshot to Code      Mengubah referensi      Cocok sebagai alat
                          screenshot menjadi      bantu internal, bukan
                          draft UI                generator final tanpa
                                                  review.

  FREELLMAPI              Kandidat                Audit keamanan
                          abstraksi/akses model   credential,
                          LLM                     kompatibilitas, dan
                                                  lisensi.

  AI Design Skills        Referensi workflow      Gunakan sebagai panduan
                          desain berbantuan AI    internal jika lisensi
                                                  sesuai.

  Postiz                  Kandidat social media   Audit dukungan
                          scheduling/management   platform, API, lisensi,
                                                  dan pemisahan tenant.

  Video Shotcraft         Kandidat workflow       Validasi fitur,
                          produksi video          lisensi, dan integrasi.

  Security Audit Skill    Checklist/audit         Pendukung proses audit,
                          keamanan                bukan pengganti
                                                  penetration test.

  Wordcraft               Kandidat pembuatan      Modul tambahan jika
                          dokumen                 kebutuhan dokumen
                                                  tervalidasi.

  Deckcraft               Kandidat pembuatan      Modul tambahan untuk
                          presentasi              output pitch deck.

  Gridcraft               Kandidat desain/grid    Perlu validasi fungsi
                          atau konten visual      dan lisensi.

  Reclip                  Kandidat video clipping Bandingkan dengan
                                                  AutoClip dan pipeline
                                                  internal.

  REA                     Belum cukup informasi   Tahan sampai repository
                          untuk menentukan fungsi dan use case jelas.

  AutoClip                Kandidat auto clipping  Uji kualitas deteksi
                                                  highlight, subtitle,
                                                  dan export.

  Remotion                Kandidat rendering      Potensial untuk
                          video berbasis          template video dinamis;
                          komponen/kode           cek lisensi dan biaya
                                                  render.

  OpenHands               Kandidat coding         Jangan diberi akses
                          agent/automation        produksi atau secret
                          internal                tanpa sandbox dan
                                                  approval.

  MuMuAINovel             Kandidat                Bukan prioritas inti
                          penulisan/novel AI      Krafio kecuali ada
                                                  modul penulisan
                                                  kreatif.
  -----------------------------------------------------------------------

## 12.1 Prinsip Integrasi Repository

Sebelum repository masuk ke produk: 1. Identifikasi repository resmi dan
maintainer. 2. Periksa lisensi untuk penggunaan komersial, modifikasi,
distribusi, dan SaaS. 3. Periksa aktivitas commit dan release. 4.
Periksa dependency dan security advisory. 5. Jalankan di environment
terisolasi. 6. Lakukan uji beban dan uji output. 7. Tentukan apakah
dipakai sebagai library, service, fork, atau referensi. 8. Catat versi
yang digunakan. 9. Buat mekanisme update dan rollback. 10. Pastikan ada
rencana pengganti jika proyek tidak lagi dipelihara.

**Catatan lisensi:** lisensi open-source tidak otomatis mengizinkan
semua bentuk penggunaan SaaS. Periksa klausul khusus, termasuk AGPL,
SSPL, model license, asset license, font, musik, dan komponen pihak
ketiga.

**Catatan v2.3:** register repository eksternal yang diperinci (URL, audit 20 kriteria, delapan metode integrasi, kebijakan lisensi) tersedia pada Bab 53. Bab ini tidak berubah.

------------------------------------------------------------------------

# 13. Admin Panel --- Detail Operasional

## 13.1 Dashboard Admin

Informasi utama: - Total user. - User aktif. - Langganan
aktif/kedaluwarsa. - Pendapatan periode berjalan. - Transaksi
pending/gagal. - Total top up. - Komisi pending/available. - Withdrawal
menunggu proses. - Job AI berjalan/gagal. - Penggunaan provider dan
estimasi biaya. - Error sistem. - Status queue, storage, dan worker.

## 13.2 Manajemen Paket

Admin dapat: - Membuat paket. - Mengedit deskripsi dan harga. -
Menetapkan periode. - Memilih fitur. - Menetapkan kuota. - Menentukan
batas proyek dan storage. - Mengatur visibilitas. - Menjadwalkan
perubahan harga. - Mengarsipkan paket tanpa merusak subscription lama.

## 13.3 Manajemen Fitur dan Kuota

Admin dapat mengaktifkan/nonaktifkan fitur secara global atau per paket.
Perubahan entitlement harus memiliki tanggal efektif dan histori.

## 13.4 Manajemen Provider AI

Admin dapat: - Menambahkan provider. - Menambahkan model. - Mengatur API
endpoint. - Mengatur credential secara terenkripsi. - Memilih model
default per fitur. - Mengatur fallback. - Mengatur limit dan timeout. -
Menonaktifkan provider bermasalah. - Melihat penggunaan dan estimasi
biaya.

Jangan menampilkan API key secara utuh setelah disimpan. Admin cukup
melihat status credential dan melakukan rotasi.

## 13.5 Manajemen SEO Rules

Admin dapat membuat ruleset versi baru, menguji pada sample URL,
membandingkan hasil, lalu mempublikasikannya. Perubahan ruleset tidak
mengubah hasil audit yang telah tersimpan.

## 13.6 Manajemen Withdrawal

Admin melihat daftar pengajuan, detail saldo tersedia, histori komisi,
data tujuan, dan risiko transaksi. Setiap keputusan wajib memiliki
catatan. Perubahan status tidak boleh dilakukan hanya di frontend.

------------------------------------------------------------------------


---

## Integrasi Spesifikasi Admin Panel pada Bab 13

### Spesifikasi Admin Panel Terintegrasi — Ketentuan Dasar

Admin Panel adalah pusat kendali seluruh ekosistem Krafio. Admin memiliki kemampuan untuk mengatur, mengelola, memantau, mengubah, dan mengendalikan operasional platform.

Untuk tahap pertama, hanya ada satu jenis akun Admin (Super Admin) dengan akses penuh. Tidak ada pembagian Finance Admin, Technical Admin, Content Admin, Support Admin, atau Marketing Admin. Seluruh fungsi berada di bawah satu akun Admin. Jika Krafio berkembang, sistem dapat dikembangkan menggunakan Role-Based Access Control (RBAC).

> Ketentuan ini menjadi dasar untuk seluruh spesifikasi Admin Panel pada bab-bab terkait.

### Spesifikasi Admin Panel Terintegrasi — Konsep Utama Admin Panel
#### Prinsip kendali Admin

  -----------------------------------------------------------------------
  Prinsip                             Penjelasan
  ----------------------------------- -----------------------------------
  Full Control                        Admin dapat mengakses seluruh
                                      modul.

  Full Monitoring                     Admin dapat memantau aktivitas
                                      seluruh platform.

  Approval Control                    Admin menentukan pengajuan pengguna
                                      yang diterima atau ditolak.

  Direct Execution                    Aktivitas yang dibuat Admin dapat
                                      langsung dijalankan setelah
                                      validasi dan konfirmasi yang
                                      diperlukan.

  Financial Control                   Admin mengelola transaksi, saldo,
                                      komisi, dan pencairan.

  AI Control                          Admin mengendalikan provider,
                                      model, limit, penggunaan, dan biaya
                                      AI.

  Security Control                    Admin mengelola keamanan dan
                                      konfigurasi sistem.

  Audit Trail                         Seluruh tindakan penting tetap
                                      tercatat.
  -----------------------------------------------------------------------

### Spesifikasi Admin Panel Terintegrasi — Admin Dashboard --- Pusat Monitoring
Ketika Admin berhasil login, halaman pertama yang ditampilkan adalah
Dashboard. Dashboard memberikan gambaran kondisi Krafio secara
menyeluruh.

#### A. User Overview

-   Total pengguna.
-   Pengguna aktif.
-   Pengguna baru hari ini.
-   Pengguna baru bulan ini.
-   Pengguna menunggu approval.
-   Pengguna belum melakukan verifikasi.
-   Pengguna suspended.
-   Pengguna banned.
-   Pengguna dengan subscription aktif.
-   Pengguna dengan subscription kedaluwarsa.

Admin dapat mengeklik setiap angka untuk membuka daftar pengguna yang
sesuai.

#### B. Financial Overview

-   Total pendapatan.
-   Pendapatan subscription.
-   Pendapatan add-on.
-   Total top-up.
-   Saldo wallet pengguna.
-   Total komisi affiliate.
-   Komisi yang masih pending.
-   Komisi yang tersedia untuk dicairkan.
-   Withdrawal menunggu persetujuan.
-   Total refund.
-   Transaksi berhasil dan gagal.

Saldo top-up dan saldo komisi affiliate harus tetap dipisahkan.

#### C. AI Overview

-   Total request AI.
-   Total token input.
-   Total token output.
-   Total token terpakai.
-   Estimasi biaya AI.
-   Penggunaan berdasarkan provider.
-   Penggunaan berdasarkan model.
-   Penggunaan berdasarkan fitur.
-   Penggunaan berdasarkan pengguna.
-   AI jobs yang sedang berjalan.
-   AI jobs yang berhasil.
-   AI jobs yang gagal.
-   Provider yang mengalami gangguan.
-   Limit internal yang hampir habis.

#### D. System Overview

-   CPU usage.
-   RAM usage.
-   Storage usage.
-   Disk I/O.
-   Network bandwidth.
-   Database connections.
-   Redis memory.
-   Queue depth.
-   Failed jobs.
-   Worker status.
-   GPU dan VRAM jika server memiliki GPU.
-   Object storage usage.

Dashboard juga perlu memiliki notifikasi ketika terjadi masalah serius.

### Spesifikasi Admin Panel Terintegrasi — User Management
Admin memiliki kendali terhadap seluruh akun pengguna.

#### Fitur yang tersedia

1.  Melihat seluruh pengguna.
2.  Mencari pengguna berdasarkan nama, email, nomor telepon, atau ID.
3.  Melihat detail profil.
4.  Mengubah informasi akun yang diperbolehkan.
5.  Mengaktifkan akun.
6.  Menonaktifkan akun.
7.  Menangguhkan akun.
8.  Membanned akun.
9.  Memulihkan akun.
10. Melihat riwayat login.
11. Melihat riwayat aktivitas.
12. Melihat penggunaan AI.
13. Melihat subscription.
14. Melihat transaksi.
15. Melihat wallet.
16. Melihat komisi affiliate.
17. Melihat website yang dibuat pengguna.
18. Melihat template yang digunakan.
19. Melihat riwayat pekerjaan AI.
20. Mencabut sesi login pengguna.

#### User Detail

  Tab             Isi
  --------------- -------------------------------------------
  Overview        Informasi dasar pengguna dan status akun.
  Profile         Data profil pengguna.
  Subscription    Paket aktif dan histori langganan.
  Wallet          Saldo top-up dan saldo affiliate.
  Transactions    Riwayat transaksi.
  AI Usage        Penggunaan token dan biaya.
  Media Jobs      Riwayat generate dan editing.
  Websites        Website yang dibuat pengguna.
  Affiliate       Informasi referral dan komisi.
  Security        Login, sesi, dan aktivitas keamanan.
  Activity Logs   Seluruh aktivitas pengguna.

Admin dapat memberikan kompensasi saldo kepada pengguna, misalnya karena
terjadi kegagalan sistem. Setiap penambahan atau pengurangan saldo harus
memiliki alasan dan tercatat di wallet ledger. Admin tidak boleh sekadar
mengganti angka saldo tanpa catatan transaksi.

### Spesifikasi Admin Panel Terintegrasi — Approval Management --- Persetujuan Seluruh Aktivitas
Selain registrasi, beberapa aktivitas perlu melalui persetujuan Admin.
Seluruhnya dikelola melalui satu pusat bernama **Approval Management**.

#### A. Jenis Approval

  -----------------------------------------------------------------------
  Jenis Pengajuan                     Persetujuan
  ----------------------------------- -----------------------------------
  Registrasi akun                     Ya, jika mode manual aktif.

  Verifikasi akun khusus              Jika diperlukan.

  Pengajuan custom package            Ya.

  Pengajuan withdrawal                Ya.

  Pengajuan refund                    Ya.

  Perubahan rekening pencairan        Pemeriksaan keamanan.

  Permintaan fitur khusus             Ya.

  Peningkatan limit khusus            Ya.

  Pengajuan integrasi tertentu        Ya.

  Pengajuan template dari pengguna    Ya, jika kontribusi template
                                      tersedia.

  Pengajuan kerja sama                Ya.
  -----------------------------------------------------------------------

#### B. Flow Approval

``` text
USER
  |
  v
Mengajukan Permintaan
  |
  v
Sistem Menyimpan Pengajuan
  |
  v
Status: PENDING
  |
  v
Masuk Approval Management
  |
  v
Admin Mendapat Notifikasi
  |
  v
Admin Memeriksa Pengajuan
  |
  +----------------------------+
  |             |              |
  v             v              v
APPROVE       REJECT       REVISION
  |             |              |
  v             v              v
Diproses      Ditolak       Kembali ke User
  |             |              |
  v             v              v
Notifikasi    Notifikasi    Perbaikan Data
User          User              |
                              v
                         Ajukan Kembali
```

#### C. Approval History

Admin dapat melihat seluruh keputusan terdahulu:

-   Siapa yang mengajukan.
-   Jenis pengajuan.
-   Kapan diajukan.
-   Kapan diproses.
-   Keputusan.
-   Alasan penolakan.
-   Catatan pemeriksaan.
-   Dampak keputusan terhadap akun atau transaksi.

Walaupun hanya ada satu tipe Admin, histori tetap menyimpan identitas
Admin yang melakukan tindakan.

### Spesifikasi Admin Panel Terintegrasi — Content Management
Admin dapat mengelola seluruh konten halaman publik.

Fitur:

-   Homepage.
-   Hero section.
-   Banner.
-   Headline.
-   Deskripsi.
-   Feature section.
-   Pricing section.
-   FAQ.
-   Testimonials.
-   Blog.
-   Terms and Conditions.
-   Privacy Policy.
-   Contact.
-   Footer.
-   Social media links.
-   SEO metadata.

Perubahan konten yang dibuat Admin dapat langsung diterbitkan setelah
validasi dan konfirmasi.

### Spesifikasi Admin Panel Terintegrasi — Struktur Sidebar Admin Panel Final
``` text
KRAFIO ADMIN PANEL

01. OVERVIEW
    Dashboard
    Platform Analytics

02. USER MANAGEMENT
    All Users
    Pending Accounts
    User Verification
    User Activity
    Suspended Accounts
    Banned Accounts

03. APPROVAL MANAGEMENT
    Pending Approvals
    Account Approval
    Custom Package Approval
    Withdrawal Approval
    Refund Approval
    Integration Approval
    Template Approval
    Approval History

04. PRODUCT & BILLING
    Subscription Plans
    Add-ons
    Custom Packages
    Feature Entitlements
    Coupons & Promotions
    Usage & Quotas

05. FINANCIAL
    Payments
    Top-ups
    Wallet Ledger
    Withdrawals
    Affiliate
    Reconciliation

06. AI MANAGEMENT
    AI Providers
    AI Models
    AI Gateway
    AI Agents
    Prompt Library
    AI Usage Monitoring
    Token Monitoring
    Provider Limits
    AI Cost Monitoring
    Provider Health
    Fallback Settings

07. TEMPLATE STUDIO
    Template Dashboard
    All Templates
    Create Template
    AI Template Generator
    Template Editor
    Photo Templates
    Video Templates
    Website Templates
    Social Media Templates
    Business Templates
    Template Categories
    Template Assets
    Template Access & Pricing
    Featured Templates
    Draft Templates
    Archived Templates
    Template Usage Analytics

08. MEDIA MANAGEMENT
    Image Generation
    Video Generation
    AI Editing
    Video Clipper
    Media Jobs
    Asset Storage

09. WEBSITE BUILDER
    Website Templates
    User Websites
    Website Projects
    Domains
    Deployments

10. SEO MANAGEMENT
    SEO Dashboard
    SEO Rules
    Algorithm Settings
    Crawl Jobs
    SEO Reports

11. SOCIAL INTEGRATION
    WhatsApp
    Facebook
    Instagram
    TikTok
    Threads
    Webhook Logs

12. COMMUNICATION
    SMTP Settings
    Email Templates
    OTP Settings
    WhatsApp Templates
    Notification Center

13. CONTENT MANAGEMENT
    Frontpage
    Banners
    Blog
    FAQ
    SEO Metadata

14. SYSTEM & SECURITY
    Server Monitoring
    Database Monitoring
    Redis Monitoring
    Queue & Workers
    Cloudflare
    Security Events
    Backup
    Audit Logs

15. CUSTOMER SUPPORT
    Support Tickets
    Help Center

16. SETTINGS
    General Settings
    Admin Security
    Feature Flags
    System Configuration
```

### Spesifikasi Admin Panel Terintegrasi — Contoh Flow Operasional Admin dari Awal sampai Akhir
#### Skenario A --- Admin menyetujui akun pengguna

1.  Admin login.
2.  Dashboard menampilkan notifikasi akun baru.
3.  Admin membuka Pending Accounts.
4.  Admin memeriksa informasi pengguna.
5.  Admin memilih Approve.
6.  Backend mengubah status akun menjadi Active.
7.  Sistem mengirim email/in-app notification.
8.  Pengguna dapat mengakses fitur sesuai paketnya.

#### Skenario B --- Admin menambahkan provider AI

1.  Admin membuka AI Providers.
2.  Klik Add Provider.
3.  Mengisi nama dan Base URL.
4.  Memasukkan API Key.
5.  Memilih model yang tersedia.
6.  Mengatur harga dan limit internal.
7.  Melakukan Test Connection.
8.  Mengatur prioritas.
9.  Mengaktifkan provider.
10. AI Gateway mulai dapat menggunakan provider tersebut.

#### Skenario C --- Admin membuat template menggunakan AI

1.  Admin membuka Template Studio.
2.  Memilih AI Template Generator.
3.  Memasukkan prompt.
4.  Memilih model AI.
5.  Menentukan variasi.
6.  Memeriksa estimasi biaya.
7.  Menjalankan Generate.
8.  Sistem mencatat penggunaan AI.
9.  Admin memilih hasil terbaik.
10. Admin mengedit template.
11. Admin menentukan paket pengguna yang dapat mengakses.
12. Admin memublikasikan template.
13. Template muncul di User Panel.

#### Skenario D --- Pengguna mengajukan withdrawal

1.  Pengguna mengajukan withdrawal.
2.  Sistem memvalidasi saldo komisi.
3.  Pengajuan masuk Pending Approval.
4.  Admin menerima notifikasi.
5.  Admin memeriksa rekening dan jumlah.
6.  Admin menyetujui.
7.  Sistem mengunci saldo yang akan dicairkan.
8.  Admin atau petugas pembayaran yang ditunjuk melakukan transfer.
9.  Bukti pembayaran dicatat.
10. Status berubah menjadi Paid.
11. Pengguna menerima notifikasi.

#### Skenario E --- Limit provider AI hampir habis

1.  Sistem mencatat penggunaan token.
2.  Penggunaan mencapai threshold 90%.
3.  Sistem mengirim peringatan kepada Admin.
4.  Admin membuka AI Usage Monitoring.
5.  Admin melihat provider dan model yang mendekati limit.
6.  Admin memutuskan mengubah limit internal, menonaktifkan provider,
    atau mengaktifkan fallback.
7.  Sistem menerapkan konfigurasi baru.
8.  Semua perubahan tercatat di Audit Logs.

### Spesifikasi Admin Panel Terintegrasi — Aturan Penting: Mana yang Perlu Approval dan Mana yang Langsung?
  -----------------------------------------------------------------------
  Aktivitas               User                    Admin
  ----------------------- ----------------------- -----------------------
  Registrasi akun         Approval sesuai mode    Approve/reject

  Generate foto           Langsung sesuai         Langsung
                          entitlement             

  Generate video          Langsung sesuai         Langsung
                          entitlement             

  AI editing              Langsung sesuai         Langsung
                          entitlement             

  Membuat konten          Langsung sesuai         Langsung
                          entitlement             

  Membuat website         Langsung sesuai         Langsung
                          entitlement             

  Menggunakan template    Langsung sesuai         Langsung
                          entitlement             

  Membuat template publik Approval jika           Langsung setelah
                          kontribusi tersedia     validasi

  Membuat paket resmi     Tidak bisa              Langsung setelah
                                                  konfirmasi

  Mengubah harga          Tidak bisa              Langsung setelah
                                                  konfirmasi

  Mengubah provider AI    Tidak bisa              Langsung setelah
                                                  pengujian dan
                                                  konfirmasi

  Mengubah SEO rules      Tidak bisa              Langsung setelah
                                                  pengujian dan
                                                  konfirmasi

  Mengajukan withdrawal   Perlu approval          Approve/reject

  Mengajukan refund       Perlu pemeriksaan       Approve/reject

  Mengajukan custom       Perlu approval          Langsung membuat
  package                                         penawaran

  Menerbitkan pengumuman  Tidak bisa menerbitkan  Langsung setelah
                          pengumuman resmi        konfirmasi
  -----------------------------------------------------------------------

**Catatan:** Generate oleh Admin tidak perlu approval tambahan. Tetapi
jika proses tersebut menghabiskan biaya AI, kuota, atau storage,
penggunaan tetap harus dicatat. Demikian juga perubahan konfigurasi
penting tetap memerlukan validasi dan konfirmasi.

### Spesifikasi Admin Panel Terintegrasi — Kesimpulan Arsitektur Admin Krafio
Admin Panel Krafio mempunyai empat fungsi utama:

#### 1. Business Control

Mengelola pengguna, paket, harga, subscription, pembayaran, wallet,
affiliate, dan pendapatan.

#### 2. AI & Technology Control

Mengelola provider, model, agent, token, limit, biaya, fallback, media
processing, dan infrastruktur.

#### 3. Content & Product Control

Mengelola template, AI Template Generator, website builder, SEO, konten
publik, dan integrasi sosial.

#### 4. Governance & Approval Control

Mengelola persetujuan akun, withdrawal, refund, custom package,
pengajuan pengguna, keamanan, dan audit.

#### Keputusan final yang perlu dipegang

-   Satu tipe Admin dengan akses penuh pada versi awal.
-   Semua fitur yang sudah direncanakan tetap masuk scope.
-   Pengguna dapat menjalankan fitur sesuai paket dan entitlement tanpa
    approval manual untuk setiap generate.
-   Aktivitas tertentu membutuhkan approval Admin.
-   Konten dan template yang dibuat Admin dapat langsung dipublikasikan
    setelah validasi.
-   AI usage, token, kuota, kredit, dan biaya harus dapat dimonitor.
-   Semua tindakan penting tetap tercatat.

Dengan demikian, Admin Panel Krafio menjadi pusat kendali untuk
mengoperasikan bisnis, mengelola produk, mengontrol AI, dan mengawasi
seluruh aktivitas platform.

# 14. Notifikasi

Kanal notifikasi dapat meliputi: - Notifikasi in-app. - Email. -
WhatsApp Business (jika disetujui dan sesuai kebijakan).

Pemicu: - Pendaftaran/verifikasi. - Pembayaran sukses/gagal. - Langganan
aktif/akan berakhir/kedaluwarsa. - Add-on aktif. - Top up sukses. - Job
selesai/gagal. - Kuota hampir habis. - Integrasi terputus. - Komisi
tersedia. - Withdrawal diterima/disetujui/ditolak/dibayar. -
Pemeliharaan sistem.

User harus dapat mengatur preferensi notifikasi non-esensial.

------------------------------------------------------------------------

# 15. Non-Functional Requirements (NFR)

## 15.1 Performa

-   Halaman dashboard dan katalog harus responsif pada koneksi normal.
-   Operasi berat tidak boleh membuat request web menggantung terlalu
    lama.
-   Gunakan queue untuk pekerjaan media, crawl, dan sinkronisasi.
-   Terapkan pagination untuk daftar transaksi, job, dan aset.
-   Optimalkan thumbnail dan preview media.
-   Terapkan caching untuk data publik yang aman dicache.

Target angka performa final ditentukan setelah pemilihan stack dan
infrastruktur, lalu diuji melalui load test.

## 15.2 Skalabilitas

-   Worker dapat ditambah tanpa mengubah alur bisnis.
-   GPU worker dapat dipisahkan dari web server.
-   Storage dapat diperbesar secara horizontal.
-   Queue mendukung retry dan dead-letter handling.
-   Integrasi provider memakai adapter agar mudah diganti.

## 15.3 Availability dan Recovery

-   Backup database terjadwal.
-   Backup konfigurasi penting.
-   Object storage memiliki kebijakan versioning/retention sesuai
    kebutuhan.
-   Uji pemulihan backup secara berkala.
-   Monitoring uptime dan error.
-   Dokumentasi prosedur incident response.
-   Tentukan target RPO dan RTO sebelum produksi.

## 15.4 Kompatibilitas

-   Desktop dan mobile browser modern.
-   PWA pada perangkat yang mendukung.
-   Layout responsif.
-   Uji browser utama.
-   Uji upload/download file berukuran besar pada perangkat mobile.

## 15.5 Privasi dan Kepatuhan

-   Sediakan Privacy Policy dan Terms of Service.
-   Jelaskan data apa yang diproses AI provider.
-   Jelaskan penyimpanan token sosial.
-   Sediakan mekanisme penghapusan akun/data sesuai kebijakan dan
    kewajiban yang berlaku.
-   Batasi akses internal terhadap file user.
-   Tinjau ketentuan perlindungan data pribadi dan kewajiban usaha yang
    berlaku di Indonesia.
-   Siapkan prosedur permintaan data, penghapusan, dan penanganan
    insiden.

------------------------------------------------------------------------

# 16. Aturan Bisnis Inti

  ---------------------------------------------------------------------
  ID                                 Aturan
  ---------------------------------- ----------------------------------
  BR-001                             Sistem memiliki dua jenis akun
                                     utama: User dan Admin.

  BR-002                             Admin hanya satu tipe dengan hak
                                     akses penuh.

  BR-003                             Seluruh fitur berbayar harus
                                     melalui validasi entitlement di
                                     backend.

  BR-004                             Paket dan add-on dikonfigurasi
                                     melalui Admin Panel.

  BR-005                             Custom package hanya dapat
                                     ditawarkan setelah
                                     disusun/publikasi oleh admin.

  BR-006                             Top up minimal Rp50.000.

  BR-007                             Top up maksimal Rp1.000.000 per
                                     transaksi.

  BR-008                             Withdrawal minimal Rp100.000.

  BR-009                             Target proses withdrawal 1--3 hari
                                     kerja setelah pengajuan valid.

  BR-010                             Saldo top up dan saldo komisi
                                     wajib dipisahkan.

  BR-011                             Saldo top up tidak dapat ditarik
                                     sebagai withdrawal biasa.

  BR-012                             Withdrawal hanya berasal dari
                                     komisi yang telah berstatus
                                     available.

  BR-013                             Komisi affiliate tidak langsung
                                     available sebelum validasi
                                     transaksi.

  BR-014                             Pembayaran hanya dianggap berhasil
                                     setelah webhook terverifikasi atau
                                     rekonsiliasi resmi.

  BR-015                             Webhook duplikat tidak boleh
                                     memproses transaksi lebih dari
                                     satu kali.

  BR-016                             Job AI harus memeriksa kuota
                                     sebelum diproses.

  BR-017                             Kuota yang di-reserve harus
                                     dilepas/dikembalikan jika job
                                     gagal karena kesalahan
                                     sistem/provider.

  BR-018                             Hasil generate disimpan sebagai
                                     aset milik user sesuai kebijakan
                                     penyimpanan.

  BR-019                             Token integrasi sosial disimpan
                                     terenkripsi dan tidak pernah
                                     diminta dalam bentuk password akun
                                     sosial.

  BR-020                             Fitur integrasi mengikuti izin dan
                                     API resmi masing-masing platform.

  BR-021                             SEO score adalah skor internal
                                     Krafio, bukan skor resmi mesin
                                     pencari.

  BR-022                             Hasil AI dan konten publikasi
                                     dapat ditinjau user sebelum
                                     dipakai.

  BR-023                             Setiap perubahan finansial dan
                                     tindakan admin penting harus
                                     tercatat dalam audit log.

  BR-024                             Data transaksi lama mempertahankan
                                     snapshot harga dan ketentuan saat
                                     pembelian.

  BR-025                             Berakhirnya langganan tidak
                                     otomatis menghapus data dan aset
                                     user.
  ---------------------------------------------------------------------

------------------------------------------------------------------------

# 17. Acceptance Criteria / Kriteria Penerimaan

## 17.1 Akun dan Panel

-   User dapat mendaftar, login, logout, dan reset password.
-   User tidak dapat membuka route admin.
-   Admin dapat mengakses seluruh menu admin setelah autentikasi yang
    sesuai.
-   User hanya dapat mengakses data miliknya.

## 17.2 Paket dan Add-on

-   Admin dapat membuat paket dan memilih fitur/kuota.
-   Paket tampil di frontpage sesuai status publikasi.
-   User dapat membeli paket.
-   Entitlement berubah setelah pembayaran terkonfirmasi.
-   Add-on hanya dapat dibeli jika kompatibel.
-   Perubahan paket baru tidak merusak transaksi lama.

## 17.3 Pembayaran dan Saldo

-   Top up Rp50.000 diterima.
-   Top up di bawah Rp50.000 ditolak.
-   Top up di atas Rp1.000.000 ditolak untuk satu transaksi.
-   Saldo hanya bertambah setelah konfirmasi valid.
-   Webhook duplikat tidak menggandakan saldo.
-   Ledger mencatat semua debit/kredit.
-   Saldo top up tidak dapat digunakan untuk withdrawal komisi.

## 17.4 Withdrawal

-   Pengajuan di bawah Rp100.000 ditolak.
-   Pengajuan tidak dapat melebihi saldo komisi available.
-   Saldo yang diajukan menjadi reserved.
-   Admin dapat menyetujui/menolak dengan alasan.
-   Penolakan mengembalikan saldo reserved sesuai aturan.
-   Pembayaran yang selesai memiliki bukti/referensi.
-   User menerima pembaruan status.

## 17.5 Fitur AI

-   User tanpa entitlement tidak dapat menjalankan fitur premium melalui
    API langsung.
-   Kuota berkurang sesuai aturan.
-   Job memiliki status dan histori.
-   File hasil hanya dapat diakses pemilik atau pihak yang diberi izin.
-   Kegagalan provider ditangani tanpa kehilangan kuota secara tidak
    adil.
-   Credential provider tidak terlihat di browser.

## 17.6 Integrasi Sosial

-   User dapat menghubungkan akun melalui alur resmi.
-   Token disimpan terenkripsi.
-   User dapat memutus koneksi.
-   Sistem menangani token kedaluwarsa.
-   Data yang ditampilkan berasal dari respons API yang benar dan
    mencantumkan waktu sinkronisasi.
-   Fitur yang tidak didukung API tidak ditampilkan sebagai fitur aktif.

## 17.7 SEO dan Website Builder

-   Ruleset SEO memiliki versi.
-   Audit menyimpan versi ruleset.
-   Hasil laporan dapat diunduh.
-   Website builder menghasilkan preview responsif.
-   Konten dapat diedit manual.
-   Draft AI tidak langsung dipublikasikan tanpa persetujuan.

## 17.8 PWA dan Keamanan

-   Aplikasi dapat di-install pada browser yang mendukung.
-   HTTPS aktif.
-   Turnstile/captcha bekerja pada titik yang ditentukan.
-   Rate limit berjalan.
-   Route admin dilindungi.
-   File upload divalidasi.
-   Audit log menyimpan tindakan penting.

------------------------------------------------------------------------


---

## Integrasi Spesifikasi Admin Panel pada Bab 17

### Spesifikasi Admin Panel Terintegrasi — Acceptance Criteria --- Syarat Admin Panel Dianggap Selesai
Sebelum Admin Panel dinyatakan selesai, seluruh hal berikut harus bisa
diuji.

  -----------------------------------------------------------------------
  No.                     Kriteria                Hasil yang Diharapkan
  ----------------------- ----------------------- -----------------------
  1                       Admin login             Berhasil mengakses
                                                  seluruh modul.

  2                       User Management         Admin dapat mengelola
                                                  akun.

  3                       Account Approval        Akun pending dapat
                                                  disetujui/ditolak.

  4                       Approval Management     Semua pengajuan masuk
                                                  antrean.

  5                       Subscription Management Admin dapat membuat dan
                                                  mengubah paket.

  6                       Financial Management    Transaksi dan wallet
                                                  dapat dikelola.

  7                       AI Provider Management  Provider dapat
                                                  ditambahkan dan diuji.

  8                       AI Monitoring           Token dan unit
                                                  penggunaan tercatat.

  9                       AI Limit                Peringatan muncul
                                                  ketika threshold
                                                  tercapai.

  10                      AI Fallback             Provider cadangan dapat
                                                  digunakan sesuai
                                                  konfigurasi.

  11                      AI Agent                Admin dapat membuat dan
                                                  mengelola agent.

  12                      Manual Template         Admin dapat membuat
                                                  template.

  13                      AI Template Generator   Admin dapat
                                                  menghasilkan template.

  14                      Template Editor         Template dapat diedit
                                                  dan dipreview.

  15                      Template Publishing     Template dapat
                                                  dipublikasikan.

  16                      Template Entitlement    Akses mengikuti paket
                                                  pengguna.

  17                      Website Builder         Admin dapat mengelola
                                                  website dan template.

  18                      SEO Management          Rules dapat diuji dan
                                                  diterapkan.

  19                      Social Integration      Status integrasi dapat
                                                  dipantau.

  20                      Notification            Email dan notifikasi
                                                  dapat dikelola.

  21                      System Monitoring       Kondisi server dapat
                                                  dipantau.

  22                      Security                MFA, rate limit, dan
                                                  audit log berjalan.

  23                      Audit Logs              Semua perubahan penting
                                                  tercatat.
  -----------------------------------------------------------------------

# 18. Strategi Implementasi dan Urutan Pengerjaan (Bukan Pengurangan Scope)

Jangan membangun seluruh fitur sekaligus. Banyaknya fitur media, AI,
billing, dan integrasi sosial berpotensi membuat proyek terlalu kompleks
jika semua dipaksakan ke rilis pertama.

## Phase 0 --- Validasi dan Fondasi

-   Finalisasi kebutuhan dan aturan bisnis.
-   Tentukan stack.
-   Tentukan payment gateway.
-   Tentukan provider AI awal.
-   Audit repository kandidat.
-   Buat UX flow dan wireframe.
-   Buat ERD.
-   Buat threat model dan arsitektur deployment.

## Phase 1 --- SaaS Core / MVP

-   Frontpage.
-   Registrasi/login.
-   User Panel.
-   Admin Panel.
-   Paket berlangganan.
-   Add-on sederhana.
-   Payment gateway.
-   Top up dan ledger.
-   Entitlement/feature gate.
-   Dashboard penggunaan.
-   Notifikasi dasar.
-   Cloudflare, captcha, dan keamanan dasar.
-   PWA dasar.

**Tujuan:** membuktikan bahwa sistem akun, paket, pembayaran, dan
pembatasan fitur bekerja dengan benar.

## Phase 2 --- AI Core

-   AI Gateway.
-   Chatbot basic/premium.
-   Brainstorming content.
-   Prompt management.
-   Generate foto.
-   ComfyUI/provider image.
-   Template Library tahap awal.
-   Job queue dan penyimpanan hasil.

## Phase 3 --- Video Production

-   Generate video.
-   Edit video dasar.
-   Clipper.
-   Subtitle/caption.
-   Template video.
-   Rendering worker.
-   Integrasi Remotion/LosslessCut/engine lain jika lolos evaluasi.

## Phase 4 --- Website Builder dan SEO

-   Company profile builder.
-   Template website.
-   AI content draft.
-   Manual editor.
-   Preview/export.
-   SEO analyzer.
-   Rules engine internal.
-   Laporan SEO.

## Phase 5 --- Social Integration dan Affiliate

-   OAuth/social connections.
-   Sinkronisasi insight sesuai izin.
-   Publishing/scheduling jika API mengizinkan.
-   Affiliate attribution.
-   Komisi.
-   Withdrawal.
-   Admin review dan rekonsiliasi.

## Phase 6 --- Scale and Optimization

-   GPU worker scaling.
-   Provider fallback.
-   Advanced analytics.
-   Usage-based pricing.
-   Optimasi biaya AI.
-   Multi-workspace jika dibutuhkan.
-   Monitoring dan observability lanjutan.

------------------------------------------------------------------------

# 19. Risiko dan Mitigasi

  -----------------------------------------------------------------------
  Risiko                  Dampak                  Mitigasi
  ----------------------- ----------------------- -----------------------
  Biaya AI terlalu tinggi Margin produk negatif   Kuota berbasis biaya,
                                                  limit per paket,
                                                  monitoring biaya/model,
                                                  rate limit.

  Job video menumpuk      Pengalaman pengguna     Queue, prioritas paket,
                          buruk                   batas durasi, worker
                                                  terpisah.

  Repository tidak        Kebocoran data atau     Audit lisensi,
  aman/tidak kompatibel   kegagalan produk        dependency, sandbox,
                                                  uji integrasi, pin
                                                  versi.

  API sosial              Integrasi berhenti      Gunakan API resmi,
  berubah/dibatasi                                adapter, monitoring,
                                                  fallback manual.

  Webhook pembayaran      Kerugian finansial      Signature verification,
  palsu/duplikat                                  idempotency,
                                                  rekonsiliasi.

  Fraud affiliate         Kerugian komisi         Masa pending,
                                                  anti-self-referral,
                                                  deteksi pola, reversal.

  Withdrawal              Kerugian dana           Reserve balance, review
  disalahgunakan                                  admin, audit log,
                                                  verifikasi penerima.

  Token sosial bocor      Pengambilalihan akun    Enkripsi, secret
                                                  management, least
                                                  privilege, revoke.

  SSRF dari SEO crawler   Akses ke jaringan       Block private IP,
                          internal                redirect validation,
                                                  egress restrictions.

  Upload berbahaya        Kompromi server         Validasi file,
                                                  scanning, object
                                                  storage, tidak
                                                  mengeksekusi file.

  Ketergantungan satu     Fitur berhenti          AI Gateway, provider
  provider                                        adapter, fallback.

  Ruang lingkup terlalu   Proyek terlambat        Phase delivery dan
  besar                                           prioritas MVP.
  -----------------------------------------------------------------------

------------------------------------------------------------------------

# 20. Keputusan yang Masih Harus Ditetapkan

Bagian ini sengaja dibiarkan sebagai daftar keputusan, bukan diasumsikan
sepihak.

1.  **Nama final:** apakah tetap Krafio atau diganti?
2.  **Target pengguna awal:** kreator individu, UMKM, agensi, atau
    semuanya?
3.  **Stack:** framework frontend, backend, database, queue, dan object
    storage.
4.  **Payment gateway:** provider yang digunakan, metode pembayaran,
    biaya, refund, dan settlement.
5.  **Harga paket:** nama paket, harga, periode, fitur, dan kuota.
6.  **Satuan kuota:** kredit, jumlah job, detik video, token, atau
    kombinasi.
7.  **Saldo top up:** apakah hanya untuk pembelian internal atau dapat
    digunakan untuk semua layanan?
8.  **Refund:** kapan top up/langganan/add-on dapat direfund?
9.  **Affiliate:** persentase komisi, masa atribusi, masa pending, dan
    produk yang dihitung.
10. **Withdrawal:** bank/e-wallet yang didukung, biaya, pajak, KYC,
    batas harian, dan hari libur.
11. **AI provider:** GPT/Claude/provider lain yang akan dipakai pertama
    kali.
12. **Media infrastructure:** GPU sendiri, cloud GPU, atau provider API.
13. **Video:** batas durasi, resolusi, format, dan biaya per job.
14. **Website builder:** hanya preview/export atau termasuk hosting dan
    domain?
15. **Social API:** fitur mana yang benar-benar dibutuhkan pada setiap
    platform?
16. **Template:** siapa yang membuat, mengkurasi, dan memiliki hak
    penggunaan aset?
17. **Data retention:** berapa lama hasil generate dan file disimpan?
18. **Free tier:** fitur apa yang gratis, berapa limit, dan apakah
    memerlukan verifikasi?
19. **Support:** kanal bantuan dan target waktu respons.
20. **Legal:** Terms of Service, Privacy Policy, refund policy, dan
    ketentuan affiliate.

------------------------------------------------------------------------

# 21. Rekomendasi Prioritas Produk

Agar Krafio dapat dibangun secara realistis, urutan prioritas yang
disarankan:

### Prioritas A --- Wajib sebelum menerima pembayaran publik

-   Akun dan autentikasi.
-   User/Admin Panel.
-   Paket dan add-on.
-   Payment gateway dan webhook.
-   Ledger saldo.
-   Entitlement dan kuota.
-   Audit log.
-   Keamanan dasar.
-   Terms, privacy, refund policy.

### Prioritas B --- Fitur yang membentuk nilai utama

-   Chatbot.
-   Brainstorming content.
-   Generate foto.
-   Edit foto dasar.
-   Template library.
-   AI Gateway.
-   Job queue.

### Prioritas C --- Fitur dengan kebutuhan resource/kompleksitas tinggi

-   Generate video.
-   Edit video lanjutan.
-   Clipper.
-   Website builder.
-   SEO rules engine.

### Prioritas D --- Ekosistem dan monetisasi lanjutan

-   Integrasi sosial.
-   Scheduling/publishing.
-   Affiliate.
-   Withdrawal.
-   Advanced analytics.

**Rekomendasi penting:** jangan membuka top up, withdrawal, dan
affiliate secara publik sebelum ledger, webhook, rekonsiliasi,
anti-fraud, dan audit log benar-benar diuji. Jangan pula mengaktifkan
semua provider AI sebelum biaya per job dapat diukur.

------------------------------------------------------------------------

# 22. Definisi Selesai (Definition of Done)

Sebuah modul dianggap selesai apabila: 1. Kebutuhan dan alurnya
terdokumentasi. 2. UI responsive dan dapat digunakan di mobile/desktop.
3. Backend validation dan authorization tersedia. 4. Entitlement dan
kuota bekerja. 5. Error handling dan logging tersedia. 6.
Unit/integration test untuk alur kritis lulus. 7. Tidak ada secret di
frontend atau repository. 8. Data user terisolasi. 9. Audit log tersedia
untuk tindakan penting. 10. Dokumentasi konfigurasi dan deployment
diperbarui. 11. Uji keamanan dasar dilakukan. 12. Admin dapat
mengoperasikan modul melalui panel bila modul memang memerlukan
konfigurasi admin.

------------------------------------------------------------------------

# 23. Kesimpulan

Krafio dirancang sebagai platform SaaS terpadu untuk produksi dan
pengelolaan konten digital dengan AI, mulai dari brainstorming, generate
dan edit foto/video, template, video clipping, website company profile,
SEO berbasis rules internal, chatbot, hingga integrasi media sosial.

Fondasi terpenting bukan banyaknya fitur, melainkan sistem inti yang
menghubungkan ketiganya: akun, subscription, add-on, entitlement, kuota,
pembayaran, saldo, penyimpanan, AI Gateway, dan Admin Panel. Dengan
fondasi tersebut, fitur dapat ditambahkan secara bertahap tanpa
membangun ulang mekanisme bisnis untuk setiap modul.

Skema saldo harus memisahkan credit hasil top up dari komisi affiliate
yang dapat ditarik. Top up ditetapkan Rp50.000--Rp1.000.000 per
transaksi, withdrawal minimal Rp100.000, dan target proses withdrawal
1--3 hari kerja setelah validasi. Semua transaksi wajib tercatat dan
dapat direkonsiliasi.

Krafio direkomendasikan dibangun dengan arsitektur modular monolith,
background workers, AI Gateway, object storage, dan service terpisah
untuk pekerjaan berat seperti generasi media. Cloudflare digunakan
sebagai lapisan proteksi tambahan, sedangkan keamanan inti tetap
diterapkan di backend.

Repository open-source yang telah ditemukan dapat mempercepat
pengembangan, tetapi harus diperlakukan sebagai kandidat. Setiap
repository wajib melewati pemeriksaan lisensi, keamanan, kompatibilitas,
dan kelayakan komersial sebelum digunakan.

Dengan pengembangan bertahap, Krafio dapat dimulai dari SaaS core yang
stabil, dilanjutkan AI dan media, kemudian website builder, SEO,
integrasi sosial, serta affiliate. Pendekatan ini mengurangi risiko
teknis dan finansial sekaligus menjaga agar seluruh fitur tetap
terhubung dalam satu ekosistem produk.

------------------------------------------------------------------------

# Lampiran A --- Gambaran Flow Sistem

``` text
                         KRAFIO 
                            |
          +-----------------+-----------------+
          |                 |                 |
      FRONTPAGE          USER PANEL        ADMIN PANEL
          |                 |                 |
   Register / Login    Dashboard          Full Control
          |                 |                 |
          +---------- AUTH & API ------------+
                            |
                 SUBSCRIPTION / BILLING
                            |
              +-------------+-------------+
              |             |             |
            Plans         Add-ons      Custom Package
              |             |             |
              +------ ENTITLEMENT --------+
                            |
               FEATURE ACCESS VALIDATION
                            |
       +---------+----------+----------+----------+
       |         |          |          |          |
     Image     Video      Chat       SEO      Website
     Tools     Tools      AI       Engine     Builder
       |         |          |          |          |
       +---------+----------+----------+----------+
                            |
                    AI / MEDIA GATEWAY
                            |
             +--------------+--------------+
             |              |              |
          GPT/Claude      ComfyUI       Video Workers
             |              |              |
             +--------------+--------------+
                            |
                     PROJECT / STORAGE
                            |
              +-------------+-------------+
              |                           |
         SOCIAL CONNECTORS             DOWNLOADS
              |
    WhatsApp / Facebook / Instagram /
          TikTok / Threads
              |
       Sync / Insights / Publish*
              |
         USER DASHBOARD

  PAYMENT GATEWAY --> WEBHOOK --> ORDER / LEDGER
                                  |
                      +-----------+-----------+
                      |                       |
                 TOP UP CREDIT          SUBSCRIPTION
                      |
              INTERNAL PURCHASES

  AFFILIATE REFERRAL --> VALID TRANSACTION --> PENDING COMMISSION
                                                |
                                           AVAILABLE
                                                |
                                      WITHDRAWAL REQUEST
                                                |
                                           ADMIN REVIEW
                                                |
                                      PAYOUT / 1-3 DAYS

  *Publishing and data access depend on each platform's official API,
   permission, account type, and approval.
```

# Lampiran B --- Contoh Skenario Pengguna

**Skenario:** pengguna UMKM ingin membuat konten promosi produk.

1.  Pengguna mendaftar dan memilih paket yang memiliki brainstorming,
    generate foto, template, dan chatbot.
2.  Pengguna membayar melalui payment gateway.
3.  Webhook valid mengaktifkan paket.
4.  Pengguna memasukkan profil brand dan produk.
5.  Chatbot membantu menyusun ide konten.
6.  Pengguna memilih satu ide dan membuat brief.
7.  Pengguna memilih template promosi.
8.  Pengguna generate foto produk atau mengedit foto yang diunggah.
9.  Pengguna menyimpan hasil ke project.
10. Pengguna menghubungkan akun sosial melalui API resmi jika tersedia.
11. Pengguna mengunduh atau mempublikasikan konten sesuai kemampuan
    integrasi.
12. Dashboard mencatat penggunaan kuota dan hasil pekerjaan.

# Lampiran C --- Contoh Skenario Komisi

**Skenario:** user mempromosikan paket Krafio melalui referral link.

1.  User memperoleh referral link.
2.  Calon pelanggan membuka link dan mendaftar.
3.  Sistem menyimpan atribusi referral sesuai periode yang berlaku.
4.  Calon pelanggan membeli paket.
5.  Transaksi dikonfirmasi payment gateway.
6.  Komisi dicatat sebagai pending.
7.  Setelah transaksi melewati masa validasi, komisi menjadi available.
8.  Affiliate mengajukan withdrawal minimal Rp100.000.
9.  Saldo di-reserve.
10. Admin memeriksa dan menyetujui.
11. Pembayaran dilakukan dalam target 1--3 hari kerja.
12. Bukti pembayaran dan status paid disimpan.

------------------------------------------------------------------------

**Akhir dokumen --- Krafio PRD v1.0**

------------------------------------------------------------------------

# 24. Penegasan Ruang Lingkup Menyeluruh

## 24.1 Komitmen Kelengkapan Produk

Krafio dirancang sebagai satu ekosistem lengkap. Semua modul berikut
termasuk dalam target produk:

1.  Frontpage/marketing website.
2.  User Panel.
3.  Admin Panel tunggal dengan hak penuh.
4.  Registrasi, login, verifikasi, pemulihan akun, dan manajemen sesi.
5.  Subscription, add-on, custom package, promo, kupon, dan entitlement.
6.  Payment gateway dan rekonsiliasi pembayaran.
7.  Wallet dengan pemisahan saldo top-up dan saldo komisi.
8.  Top-up Rp50.000--Rp1.000.000 per transaksi.
9.  Withdrawal komisi minimal Rp100.000, SLA target 1--3 hari kerja.
10. Affiliate, atribusi referral, komisi, reversal, dan payout.
11. Generate foto.
12. Generate video.
13. Edit foto berbantuan AI.
14. Edit video berbantuan AI.
15. Brainstorming ide konten.
16. Template foto/video 100+ dan sistem kategori/tag.
17. Company Profile Website Builder berbasis template, editor manual,
    dan AI.
18. SEO Tools dan custom SEO Rules Engine.
19. Chatbot/AI Assistant dengan akses gratis dan berlangganan terbatas.
20. Video clipper, highlight extraction, subtitle, reframing, dan
    rendering.
21. Generator video berbasis form terstruktur maupun prompt bebas.
22. Integrasi WhatsApp, Facebook, Instagram, TikTok, dan Threads sesuai
    API resmi.
23. Content/project library dan asset management.
24. PWA, notifikasi, monitoring, Cloudflare, CAPTCHA, audit log, backup,
    dan observability.
25. AI Gateway multi-provider, AI agents, prompt management, dan
    evaluasi hasil.
26. Integrasi atau pemanfaatan seluruh repository kandidat pada Bab 12
    setelah audit teknis dan lisensi.

Fitur tidak boleh dihilangkan hanya karena integrasinya kompleks. Jika
suatu provider/API belum memberikan akses, modul tetap disiapkan dengan
adapter, status koneksi, simulasi development yang jelas, dan mekanisme
aktivasi setelah akses resmi tersedia. Simulasi tidak boleh disajikan
kepada pengguna sebagai transaksi atau publikasi sungguhan.

## 24.2 Definisi "Lengkap"

Sebuah modul dianggap lengkap apabila memiliki: - UI/UX pada panel yang
relevan. - Backend service dan API. - Database dan migrasi. - Validasi
entitlement, kuota, dan saldo bila berbayar. - Status lifecycle dan
audit trail. - Error handling, retry, timeout, dan idempotency. -
Monitoring serta notifikasi. - Pengujian unit, integrasi, keamanan, dan
acceptance. - Dokumentasi konfigurasi dan operasional. - Integrasi
dengan Admin Panel untuk pengaturan yang memang perlu dikelola admin. -
Mekanisme export/download jika output modul berupa file. - Dokumentasi
keterbatasan eksternal yang berasal dari provider/API.

------------------------------------------------------------------------

# 25. Spesifikasi Fungsional Lintas Sistem

## 25.1 Navigasi dan Shell Aplikasi

Ketiga panel memakai design system dan identitas visual yang konsisten,
tetapi layout dan navigasinya dibedakan berdasarkan konteks: -
Frontpage: orientasi pemasaran, SEO, konversi, dan informasi publik. -
User Panel: orientasi pekerjaan, proyek, aset, pemakaian, dan billing. -
Admin Panel: orientasi konfigurasi, operasi, moderasi, keuangan, dan
monitoring.

Persyaratan: - Responsive untuk desktop, tablet, dan mobile. - Sidebar
dapat diciutkan pada desktop dan menjadi drawer pada mobile. -
Breadcrumb pada halaman bertingkat. - Global search untuk
proyek/aset/template jika relevan. - Empty state, loading state,
skeleton, error state, dan success state. - Konfirmasi untuk tindakan
destruktif. - Form memiliki validasi sisi client dan server. - Tidak ada
informasi rahasia provider yang ditampilkan ke browser. - Semua waktu
disimpan dalam UTC dan ditampilkan mengikuti zona waktu akun/platform. -
Bahasa awal Indonesia; struktur harus siap untuk lokalisasi bahasa
Inggris.

## 25.2 Dashboard User

Dashboard minimal menampilkan: - Status paket dan tanggal
berakhir/perpanjangan. - Add-on aktif. - Sisa kuota per fitur dan
periode reset. - Saldo top-up dan saldo komisi secara terpisah. -
Ringkasan pekerjaan AI: antrean, berjalan, selesai, gagal. - Proyek
terakhir dan aset terakhir. - Ringkasan performa sosial jika integrasi
tersedia. - Notifikasi penting: pembayaran, kuota, job selesai/gagal,
token integrasi kedaluwarsa. - Shortcut ke fitur yang diizinkan paket.

Data dashboard harus berasal dari sumber backend yang sama dengan
halaman detail. Tidak boleh ada angka ringkasan yang berbeda dari
ledger, job record, atau data integrasi.

## 25.3 Pusat Proyek dan Aset

Satu pengguna dapat mengelola proyek sesuai batas paket: - Project ID,
nama, tipe, deskripsi, status, owner, timestamps. - Folder/collection
dan tag. - Aset gambar, video, audio, dokumen, subtitle, thumbnail, dan
hasil ekspor. - Versi aset dan riwayat perubahan. - Metadata prompt,
model, seed, parameter, template, dan job asal jika output AI. - Hak
akses privat per pengguna/workspace. - Soft delete, restore sesuai masa
retensi, dan permanent deletion. - Signed URL untuk download; jangan
membuat bucket privat menjadi publik. - Pemeriksaan MIME, ukuran,
ekstensi, malware scanning, dan validasi file.

## 25.4 Pencarian, Filter, dan Export

Pencarian dan filter harus konsisten pada user/admin panel: - Filter
tanggal, status, tipe, kategori, provider, dan pemilik. - Pagination
server-side. - Sorting server-side dengan allowlist kolom. - Export
CSV/XLSX/PDF bila relevan. - Export harus menggunakan filter dan
parameter yang sama dengan tampilan. - File hasil export dibuat sebagai
background job jika data besar. - Download menggunakan URL sementara dan
dicatat dalam audit log untuk data sensitif.

------------------------------------------------------------------------

# 26. Spesifikasi Modul AI dan Agent

## 26.1 AI Gateway sebagai Satu Pintu

Seluruh modul AI memanggil AI Gateway, bukan mengakses vendor langsung
dari frontend. Gateway bertanggung jawab atas: - Provider routing dan
fallback. - Model selection per tugas. - Secret management. - Timeout,
retry, rate limit, dan circuit breaker. - Normalisasi
request/response. - Penghitungan token, biaya, durasi, dan konsumsi
kuota. - Logging yang tidak membocorkan prompt sensitif atau
credential. - Moderasi input/output sesuai kebijakan. - Penanganan
provider error dan provider outage. - Pemetaan error provider menjadi
error internal yang konsisten.

Interface konseptual: - `generateText()` - `generateImage()` -
`editImage()` - `generateVideo()` - `editVideo()` -
`transcribeAudio()` - `generateSpeech()` - `moderateContent()` -
`embedText()` - `runStructuredAgent()`

Nama endpoint aktual ditentukan pada API specification, tetapi kontrak
internal harus stabil walaupun vendor berubah.

## 26.2 Provider Registry

Admin dapat mengelola: - Nama provider. - Jenis kemampuan: text, image,
video, audio, embedding, moderation. - Base URL dan versi API. - Model
ID. - Credential reference (bukan plaintext yang dapat dibaca ulang). -
Status aktif/nonaktif. - Batas concurrency. - Timeout dan retry
policy. - Cost rate dan currency. - Prioritas/fallback. - Wilayah
pemrosesan jika tersedia. - Tanggal terakhir health check.

Credential disimpan dalam secret manager atau encrypted secret store.
Admin UI hanya mengizinkan input/rotasi; nilai rahasia yang telah
disimpan tidak ditampilkan kembali.

## 26.3 Agent Orchestrator

AI Agent bukan agen bebas yang dapat menjalankan semua tindakan sistem.
Setiap agent mempunyai: - Tujuan dan deskripsi. - Allowed tools. -
Denied tools. - Model yang ditentukan. - System prompt/version. - Batas
token, biaya, waktu, dan iterasi. - Kebijakan data yang boleh dikirim ke
provider. - Approval requirement untuk tindakan eksternal. - Trace ID
untuk menelusuri setiap langkah. - Evaluasi output dan fallback.

Agent yang dapat mengubah data, menerbitkan konten, mengirim pesan, atau
melakukan transaksi harus menggunakan permission khusus dan konfirmasi
pengguna. Agent tidak boleh mengakses database langsung; seluruh aksi
melalui tool/API yang tervalidasi.

## 26.4 AI Workflow dan Human Approval

Alur standar: 1. User mengisi form/prompt dan memilih proyek. 2. Backend
memvalidasi akun, entitlement, kuota, file, dan saldo. 3. Sistem membuat
job dan reservation kuota/biaya. 4. Orchestrator memilih workflow dan
provider. 5. Worker menjalankan tahap-tahap proses. 6. Output disimpan
ke object storage. 7. Sistem melakukan validasi format dan safety check.
8. Usage aktual direkonsiliasi dengan reservation. 9. User mendapat
notifikasi dan dapat preview. 10. Tindakan publikasi/eksternal menunggu
approval jika disyaratkan.

## 26.5 Prompt Library

Prompt library mendukung: - Prompt sistem, prompt template, prompt
pengguna, dan prompt hasil optimasi. - Versioning. - Kategori dan tag. -
Variabel dinamis. - Bahasa. - Model compatibility. - Parameter
rekomendasi. - Preview dan test run. - Rating dan feedback. - Rollback
ke versi sebelumnya.

Prompt Optimizer boleh membantu menyusun prompt, tetapi prompt final
harus tetap dapat dilihat dan diedit pengguna.

------------------------------------------------------------------------

# 27. Spesifikasi Media: Foto, Video, Clipper, dan Template

## 27.1 Generate Foto

Input: - Prompt utama. - Negative prompt bila engine mendukung. -
Rasio/aspect ratio. - Resolusi. - Jumlah variasi. - Style/preset. -
Referensi gambar (opsional). - Model dan parameter yang tersedia pada
paket.

Output: - Preview. - File final. - Metadata model, parameter, seed,
prompt, waktu, dan biaya. - Pilihan regenerate, variation, upscale,
download, dan edit lanjutan.

Validasi: - Format input dan ukuran. - Hak penggunaan gambar
referensi. - Konten terlarang sesuai kebijakan. - Kuota dan biaya
sebelum job. - Batas concurrency per paket.

## 27.2 Edit Foto AI

Tool yang dapat disediakan sesuai kemampuan engine: - Background
removal/replacement. - Object removal/inpainting. - Outpainting. -
Upscaling. - Image variation. - Color/style adjustment. - Product photo
enhancement. - Resize dan crop. - Text-to-image dan image-to-image. -
Batch processing.

Setiap tool wajib memiliki preview before/after, parameter, dan opsi
menyimpan sebagai versi baru agar aset sumber tidak tertimpa.

## 27.3 Generate Video

Input dapat berasal dari: - Text-to-video. - Image-to-video. - Form
terstruktur: tujuan, durasi, rasio, scene, style, voice, subtitle,
musik, CTA. - Prompt bebas. - Template video.

Pipeline: 1. Parse brief. 2. Susun storyboard. 3. Generate/siapkan
scene. 4. Generate atau pilih voice-over. 5. Generate subtitle. 6.
Render komposisi. 7. Validasi durasi, codec, resolusi, audio, dan
ukuran. 8. Simpan preview dan file final.

Jika provider tidak mendukung durasi/resolusi tertentu, UI harus
menjelaskan batasnya sebelum user menghabiskan kuota.

## 27.4 Edit Video AI

Kapabilitas: - Auto subtitle dan subtitle editing. - Transcription. -
Translation/dubbing bila provider tersedia. - Remove silence. - Scene
detection. - Smart crop/reframe. - Noise reduction. - Audio leveling. -
Thumbnail generation. - Cut/trim/merge. - Background music. - Aspect
ratio conversion. - Caption, title, lower-third, dan CTA. - Render ulang
dan export preset.

## 27.5 Video Clipper

Input: - Upload file atau URL sumber yang secara hukum dan teknis dapat
diproses. - Durasi target. - Jumlah klip. - Bahasa. - Rasio. - Platform
tujuan. - Keyword/topik. - Subtitle dan style.

Fungsi: - Transkripsi. - Deteksi scene. - Deteksi highlight berbasis
aturan/model. - Scoring kandidat klip. - Pemotongan. - Reframing. -
Subtitle. - Thumbnail. - Export batch. - Review manual sebelum publish.

Tidak boleh mengunduh atau memproses konten dari platform dengan cara
yang melanggar ketentuan platform atau hak cipta. Untuk URL, gunakan
metode resmi/berizin dan minta user mengunggah file bila akses legal
tidak tersedia.

## 27.6 Template Library 100+

Template wajib mempunyai: - Template ID dan nama. - Kategori. - Jenis
media. - Preview. - File sumber/struktur editable. - Rasio dan
resolusi. - Font dan asset dependency. - Lisensi penggunaan. - Versi. -
Tag dan kata kunci. - Paket/entitlement yang diperlukan. - Status draft,
review, published, archived. - Usage analytics.

Target 100+ adalah target katalog produk, bukan jumlah template yang
harus dipaksakan tanpa audit lisensi. Template harus bertambah secara
terkelola hingga jumlah aktif minimum yang dijanjikan tercapai.

------------------------------------------------------------------------


---

## Integrasi Spesifikasi Admin Panel pada Bab 27

### Spesifikasi Admin Panel Terintegrasi — Template Management & AI Template Generator
Admin dapat membuat template sendiri, mengunggah template, mengedit
template, maupun menghasilkan template menggunakan AI. Template tidak
harus selalu dibuat oleh developer.

Admin memiliki modul khusus bernama **Template Studio**.

#### A. Template Dashboard

Menampilkan:

-   Total template.
-   Template aktif.
-   Template draft.
-   Template premium.
-   Template gratis.
-   Template populer.
-   Template yang paling sering digunakan.
-   Template yang mengalami error.
-   Total kategori.
-   Total penggunaan template.

#### B. Jenis Template

  Kategori         Contoh
  ---------------- ----------------------------------------
  Photo Template   Foto produk, portrait, katalog.
  Video Template   Promosi, cinematic, slideshow.
  Social Media     Instagram, TikTok, Facebook.
  Business         Company profile, branding, presentasi.
  Marketing        Banner, poster, brosur.
  Website          Landing page, company profile.
  AI Editing       Preset dan gaya editing.
  Content          Storytelling, edukasi, campaign.

Katalog tetap menargetkan lebih dari 100 template foto dan video sesuai
ruang lingkup Krafio.

#### C. Manual Template Creation

Admin dapat membuat template secara manual.

``` text
ADMIN PANEL
    |
    v
TEMPLATE MANAGEMENT
    |
    v
CREATE TEMPLATE
    |
    v
PILIH JENIS TEMPLATE
    |
    v
UPLOAD ASET / BUAT DARI EDITOR
    |
    v
ATUR DESAIN DAN ELEMEN
    |
    v
TENTUKAN PLACEHOLDER
    |
    v
PREVIEW
    |
    v
SAVE DRAFT / PUBLISH
```

Admin dapat mengunggah gambar dan video, menambahkan teks, mengatur
font, warna, posisi, ukuran, menambahkan elemen desain dan animasi,
mengatur durasi dan transisi, serta menambahkan audio jika diizinkan
lisensinya.

#### D. AI Template Generator

Admin juga dapat menghasilkan template dengan AI.

Contoh prompt:

> Create a premium minimalist product photography template for
> Indonesian skincare products, with elegant beige background, soft
> studio lighting, clean typography, and editable product title and
> price placeholders.

Admin menentukan:

-   Kategori template.
-   Jenis template.
-   Model AI.
-   Rasio gambar/video.
-   Resolusi.
-   Jumlah variasi.
-   Gaya desain.
-   Warna.
-   Target penggunaan.
-   Jumlah elemen yang dapat diedit.

##### Flow AI Template Generator

``` text
ADMIN PANEL
     |
     v
TEMPLATE STUDIO
     |
     v
AI TEMPLATE GENERATOR
     |
     v
MASUKKAN PROMPT
     |
     v
ATUR PARAMETER
     |
     v
PILIH AI PROVIDER / MODEL
     |
     v
CEK ESTIMASI BIAYA
     |
     v
KLIK GENERATE
     |
     v
AI GENERATION JOB
     |
     v
HASIL GENERATE
     |
     v
PILIH VARIASI TERBAIK
     |
     v
TEMPLATE EDITOR
     |
     v
PREVIEW
     |
     +-----------------------+
     |                       |
     v                       v
  SAVE DRAFT               PUBLISH
     |                       |
     v                       v
TERSIMPAN              MASUK KATALOG
                             |
                             v
                       USER PANEL
```

Admin dapat menghasilkan beberapa variasi sekaligus.

Setiap proses generate tercatat dalam AI Usage Monitoring, termasuk
biaya, token atau unit penggunaan, provider, model, dan status job.

**Hal yang sangat penting:** AI Template Generator harus menghasilkan
template reusable.

Misalnya hasil AI berupa desain foto produk, sistem perlu menyimpan:

-   File gambar atau aset visual.
-   Struktur layer jika tersedia.
-   Placeholder judul.
-   Placeholder deskripsi.
-   Placeholder harga.
-   Placeholder gambar produk.
-   Posisi setiap elemen.
-   Konfigurasi warna.
-   Rasio dan ukuran desain.

Dengan demikian, pengguna bisa mengganti isi template tanpa harus
membuat ulang desain dari awal.

Namun, AI yang hanya menghasilkan satu gambar raster belum otomatis
menghasilkan file ber-layer yang dapat diedit. Karena itu, Template
Studio membutuhkan editor dan format struktur template tersendiri.

#### E. Template Editor

Admin harus bisa mengedit template setelah generate.

  Fitur               Fungsi
  ------------------- ---------------------------------------
  Layer Management    Mengatur susunan elemen.
  Text Editor         Mengubah teks.
  Image Replacement   Mengganti gambar.
  Color Editor        Mengubah warna.
  Font Editor         Mengganti font.
  Position Editor     Mengatur posisi.
  Resize              Mengubah ukuran.
  Crop                Memotong gambar.
  Animation           Mengatur animasi.
  Timeline            Mengatur durasi video.
  Preview             Melihat hasil akhir.
  Undo/Redo           Membatalkan atau mengulang perubahan.
  Version History     Melihat versi template sebelumnya.

Untuk rendering video, Remotion dapat dievaluasi sebagai salah satu
engine. Untuk editor gambar, bisa menggunakan pendekatan berbasis
canvas/layer. Keduanya perlu diuji sebelum keputusan implementasi final.

#### F. Template Access Management

Admin menentukan siapa yang boleh menggunakan template.

  Pengaturan      Keterangan
  --------------- ---------------------------------
  Free            Bisa digunakan pengguna gratis.
  Premium         Khusus pengguna berbayar.
  Specific Plan   Khusus paket tertentu.
  Add-on          Bisa dibuka melalui add-on.
  Internal        Hanya untuk kebutuhan internal.

Admin juga bisa menentukan public/private, featured, batas penggunaan,
kategori, tag, deskripsi, thumbnail, preview, dan status publikasi.

#### G. Template Publishing

**Template buatan Admin:** - Tidak perlu approval tambahan. - Bisa
langsung disimpan. - Bisa langsung dipublikasikan setelah validasi dan
konfirmasi.

**Template buatan pengguna:** - Jika fitur kontribusi dibuka, masuk
antrean approval. - Admin memeriksa kualitas, keamanan, dan lisensi. -
Admin menyetujui atau menolak publikasi.

Semua aset pihak ketiga harus memiliki lisensi penggunaan yang sesuai,
terutama jika template akan dijual atau digunakan secara komersial.

#### H. Template Usage Analytics

Admin dapat melihat:

-   Template paling populer.
-   Template paling sering digunakan.
-   Template yang jarang digunakan.
-   Jumlah pengguna per template.
-   Jumlah generate menggunakan template.
-   Kategori paling diminati.
-   Penggunaan template berdasarkan paket.
-   Biaya AI yang berkaitan dengan template.

# 28. Company Profile Website Builder

## 28.1 Tujuan

Pengguna membuat website company profile tanpa harus membangun dari nol.
Sistem menyediakan template, editor visual, AI-assisted generation,
preview, dan export/deployment sesuai paket.

## 28.2 Input

-   Nama perusahaan/brand.
-   Logo dan brand asset.
-   Profil dan sejarah.
-   Produk/jasa.
-   Alamat dan kontak.
-   Visi/misi.
-   Galeri.
-   Testimoni.
-   CTA.
-   Bahasa.
-   Warna dan font.
-   Domain/subdomain.
-   SEO metadata.

## 28.3 Editor

-   Pilih template.
-   Edit teks, gambar, warna, font, section, urutan section, tombol, dan
    link.
-   Tambah/hapus section.
-   Preview desktop/tablet/mobile.
-   AI untuk membuat copy, merangkum profil, menyarankan layout, dan
    menghasilkan gambar pendukung.
-   Autosave dan version history.
-   Draft/publish.
-   Form validasi sebelum publish.

AI tidak boleh mengubah seluruh desain tanpa preview dan persetujuan
user. User dapat kembali ke versi sebelumnya.

## 28.4 Output dan Hosting

Pilihan output yang harus dapat dikonfigurasi: - Preview internal
Krafio. - Publish pada subdomain Krafio. - Custom domain jika paket
dan infrastruktur mendukung. - Export source/build artifact jika
diizinkan oleh lisensi template. - SSL otomatis melalui provider
hosting/proxy. - Redirect, sitemap, robots.txt, favicon, dan metadata.

Website builder harus terpisah dari website utama Krafio agar kesalahan
proyek pengguna tidak mengganggu platform inti.

------------------------------------------------------------------------


---

## Integrasi Spesifikasi Admin Panel pada Bab 28

### Spesifikasi Admin Panel Terintegrasi — Website Builder Management
Admin memiliki kendali terhadap sistem pembuatan website.

Fitur:

-   Mengelola template website.
-   Mengelola komponen.
-   Mengelola section.
-   Mengelola tema.
-   Melihat website milik pengguna.
-   Melihat status publikasi.
-   Mengelola domain.
-   Mengelola subdomain.
-   Mengelola deployment.
-   Mengatur batas website per paket.
-   Melihat penggunaan storage.
-   Menangguhkan website jika melanggar kebijakan.

Admin juga dapat membuat website atau landing page internal tanpa harus
melalui approval tambahan.

# 29. SEO Tools dan Custom Rules Engine

## 29.1 Arsitektur SEO

SEO Engine terdiri dari: 1. Crawler/fetcher yang mematuhi robots.txt dan
batas crawl. 2. Parser HTML dan metadata. 3. Technical SEO analyzer. 4.
Content analyzer. 5. Keyword and topic workflow. 6. Internal-link
analyzer. 7. Structured data validator. 8. Rules engine. 9.
Recommendation generator. 10. Report/export service.

## 29.2 Rule Definition

Setiap rule memiliki: - Rule ID. - Nama dan deskripsi. - Kategori. -
Severity. - Kondisi/selector. - Parameter. - Kondisi pengecualian. -
Bobot/skor. - Rekomendasi. - Versi. - Status aktif. - Tanggal berlaku. -
Test cases.

Rules engine harus mendukung dry-run, test fixture, versioning, dan
rollback. Rule baru tidak langsung memengaruhi seluruh pelanggan sebelum
diuji.

## 29.3 Kategori Audit

-   HTTP status dan redirect.
-   Indexability dan canonical.
-   Robots.txt dan sitemap.
-   Title/meta description.
-   Heading structure.
-   Image alt dan ukuran.
-   Internal/external links.
-   Broken links.
-   Page speed/Core Web Vitals bila data tersedia.
-   Mobile usability.
-   Structured data/schema.
-   Duplicate content.
-   Content depth dan readability.
-   Keyword placement secara wajar.
-   Open Graph/social metadata.
-   HTTPS dan mixed content.

Krafio tidak menjanjikan peringkat Google tertentu. Skor adalah
indikator internal berdasarkan rules dan bukan jaminan hasil mesin
pencari.

## 29.4 Report

Report harus menampilkan: - URL dan tanggal crawl. - Total halaman
diperiksa. - Ringkasan skor. - Temuan per severity. - Bukti dan lokasi
masalah. - Rekomendasi perbaikan. - Status resolved/ignored. - Export
PDF/CSV/XLSX sesuai kebutuhan. - Versi rules yang digunakan.

------------------------------------------------------------------------


---

## Integrasi Spesifikasi Admin Panel pada Bab 29

### Spesifikasi Admin Panel Terintegrasi — SEO Management
Krafio menggunakan algoritma SEO dan aturan penilaian yang dikembangkan
oleh tim Krafio.

Admin dapat:

-   Menambah SEO rules.
-   Mengedit SEO rules.
-   Menghapus atau mengarsipkan rules.
-   Mengatur bobot penilaian.
-   Mengatur kategori audit.
-   Mengatur tingkat kesalahan.
-   Mengatur rekomendasi.
-   Menguji algoritma.
-   Melihat crawl jobs.
-   Melihat laporan SEO.
-   Mengaktifkan atau menonaktifkan aturan.
-   Mengembalikan versi sebelumnya.

Setiap perubahan algoritma harus memiliki versioning dan hasil pengujian
sebelum diterapkan.

# 30. Integrasi Media Sosial dan Messaging

## 30.1 Prinsip

Semua integrasi menggunakan API resmi, OAuth atau mekanisme autentikasi
resmi, izin yang disetujui, serta token yang disimpan terenkripsi.
Krafio tidak menggunakan scraping login, pengambilalihan sesi browser,
atau otomasi yang melanggar ketentuan platform.

## 30.2 WhatsApp

Target kapabilitas sesuai WhatsApp Business Platform: - Connect business
account. - Sinkronisasi nomor dan status koneksi. - Template message
management bila API mengizinkan. - Pengiriman pesan yang sesuai kategori
dan consent. - Status sent/delivered/read/failed bila tersedia. -
Webhook inbound. - Conversation log. - Opt-in/opt-out. - Rate limit dan
template approval. - Monitoring token dan webhook.

WhatsApp personal tidak diasumsikan dapat diotomasi melalui API bisnis.
Persetujuan Meta, nomor, business verification, dan template approval
dapat menjadi prasyarat.

## 30.3 Facebook dan Instagram

-   OAuth dan page/business account selection.
-   Read insights yang diizinkan.
-   Publish konten yang didukung API.
-   Media container/status.
-   Comment/message management jika scope disetujui.
-   Token refresh dan expiry monitor.
-   Permission review dan app review status.
-   Reconnect flow.

## 30.4 TikTok

-   OAuth.
-   Read profile/insights sesuai scope.
-   Upload/publish hanya sesuai API Content Posting yang disetujui.
-   Status processing/publishing.
-   User consent dan privacy disclosure.
-   Batasan akun, wilayah, dan review aplikasi.

## 30.5 Threads

-   OAuth dan scopes.
-   Publish post/media sesuai API.
-   Read metrics sesuai akses.
-   Status publishing.
-   Token refresh dan error handling.

## 30.6 Social Account Center

User dapat: - Melihat koneksi aktif dan masa berlaku token. -
Connect/disconnect. - Melihat izin yang diberikan. - Mengatur akun
default per channel. - Melihat jadwal/publikasi. - Melihat status gagal
dan alasan. - Retry sesuai batas aman. - Meninjau kalender konten. -
Menyetujui konten sebelum publikasi.

Satu kegagalan integrasi tidak boleh menggagalkan seluruh platform.
Setiap kanal memakai adapter terpisah.

------------------------------------------------------------------------


---

## Integrasi Spesifikasi Admin Panel pada Bab 30

### Spesifikasi Admin Panel Terintegrasi — Social Media Integration
Admin dapat mengelola integrasi:

-   WhatsApp.
-   Facebook.
-   Instagram.
-   TikTok.
-   Threads.

Informasi yang dipantau:

-   Status koneksi.
-   Status token.
-   Token expiration.
-   Webhook.
-   API errors.
-   Publishing status.
-   Insights yang diizinkan API.
-   Penggunaan API.
-   Histori integrasi.

Integrasi menggunakan API resmi dan izin pengguna. Admin tidak otomatis
memiliki akses ke pesan pribadi atau akun sosial pengguna.

Untuk WhatsApp, gunakan WhatsApp Business Platform Cloud API atau BSP
resmi, bukan otomatisasi WhatsApp Web tidak resmi.

# 31. Billing, Wallet, Payment, dan Financial Controls

## 31.1 Jenis Ledger

Wajib ada ledger immutable untuk: - Top-up credit. - Pemakaian credit. -
Refund credit. - Affiliate commission. - Commission reversal. -
Withdrawal hold. - Withdrawal paid. - Adjustment oleh admin dengan
alasan.

Saldo tidak boleh hanya disimpan sebagai satu angka yang bebas diedit.
Saldo tampilan merupakan hasil ledger atau cache terkontrol yang dapat
direkonsiliasi.

## 31.2 Top-up

Ketentuan awal: - Minimum Rp50.000 per transaksi. - Maksimum Rp1.000.000
per transaksi. - Nominal di luar rentang ditolak. - Pembayaran melalui
payment gateway. - Saldo baru bertambah setelah webhook valid dan status
pembayaran final. - Callback harus diverifikasi signature, amount,
currency, reference, dan status. - Webhook idempotent. - Pembayaran
pending/expired/failed tidak menambah saldo. - Refund/reversal membuat
ledger entry baru, bukan menghapus transaksi lama.

## 31.3 Withdrawal

-   Sumber hanya saldo komisi yang sudah approved/available.
-   Minimum Rp100.000.
-   Target pemrosesan 1--3 hari kerja setelah data valid dan disetujui.
-   Saldo ditahan saat pengajuan agar tidak dapat ditarik ganda.
-   Admin approve/reject dengan alasan.
-   Status: submitted, under_review, approved, processing, paid,
    rejected, cancelled, failed.
-   Bukti payout dan reference disimpan.
-   Perubahan rekening penerima memerlukan verifikasi tambahan.
-   AML/fraud checks dan batas payout mengikuti kebijakan
    hukum/provider.

## 31.4 Payment Gateway Adapter

Sediakan interface provider-agnostic: - Create payment. - Check
payment. - Verify webhook. - Refund. - Void/cancel. - Reconcile. - Fetch
settlement/report.

DOKU atau provider lain dapat dipilih setelah onboarding merchant,
review dokumen, sandbox access, credential, dan kontrak API diterima.
Jangan hardcode provider ke business logic.

## 31.5 Rekonsiliasi

Job terjadwal membandingkan: - Payment internal vs transaksi provider. -
Saldo ledger vs cache balance. - Withdrawal internal vs payout
provider/manual. - Refund vs transaksi awal.

Selisih dibuat sebagai reconciliation case yang perlu ditinjau admin.
Tidak ada auto-adjustment tanpa audit record.

------------------------------------------------------------------------


---

## Integrasi Spesifikasi Admin Panel pada Bab 31

### Spesifikasi Admin Panel Terintegrasi — Financial Management
Admin memiliki kendali terhadap seluruh transaksi finansial.

#### A. Payment Management

Admin dapat melihat ID transaksi, nama pengguna, paket yang dibeli,
nominal, payment gateway, status pembayaran, tanggal transaksi,
Reference ID, dan webhook log.

Admin dapat melakukan rekonsiliasi transaksi dan menangani refund sesuai
kebijakan.

#### B. Wallet Management

Wallet memiliki dua jenis saldo:

**Top-up Balance** - Berasal dari pengisian saldo. - Digunakan untuk
transaksi atau layanan yang diizinkan. - Tidak otomatis dapat dicairkan.

**Affiliate Commission Balance** - Berasal dari komisi affiliate yang
sudah disetujui. - Dapat diajukan untuk withdrawal sesuai ketentuan.

Admin dapat melihat seluruh pergerakan saldo melalui Wallet Ledger.

#### C. Withdrawal Management

Ketentuan yang sudah ditetapkan:

-   Minimum withdrawal Rp100.000.
-   Estimasi proses 1--3 hari kerja.

Flow:

``` text
USER MENGAJUKAN WITHDRAWAL
          |
          v
VALIDASI SALDO
          |
          v
STATUS PENDING
          |
          v
ADMIN MEMERIKSA
          |
     +----+----+
     |         |
     v         v
  APPROVE    REJECT
     |         |
     v         v
PROSES      SALDO TETAP
PEMBAYARAN   SESUAI LEDGER
     |
     v
TRANSFER
     |
     v
UPLOAD BUKTI
     |
     v
STATUS PAID
     |
     v
NOTIFIKASI USER
```

Sistem harus mencegah saldo yang sama dicairkan dua kali. Dana juga
tidak boleh dianggap selesai dibayarkan sebelum status pembayaran dan
bukti transfer dicatat.

# 32. Data Model dan Relasi Entitas Lengkap

Model berikut merupakan baseline konseptual. Nama tabel dan normalisasi
final ditentukan saat database design.

## 32.1 Identity dan Workspace

-   users
-   user_profiles
-   user_verifications
-   user_sessions
-   password_resets
-   admin_accounts
-   workspaces
-   workspace_members (disiapkan jika multi-member diaktifkan)
-   user_preferences
-   user_consents
-   login_events
-   account_security_events

## 32.2 Catalog dan Entitlement

-   plans
-   plan_versions
-   plan_prices
-   addons
-   addon_versions
-   custom_packages
-   custom_package_items
-   features
-   entitlements
-   plan_entitlements
-   addon_entitlements
-   subscriptions
-   subscription_items
-   subscription_events
-   coupons
-   promotions
-   invoices
-   invoice_items
-   usage_quotas
-   quota_reservations
-   usage_records

## 32.3 Finance

-   payment_orders
-   payment_transactions
-   payment_webhook_events
-   refunds
-   wallets
-   wallet_accounts
-   wallet_ledger_entries
-   topup_orders
-   withdrawal_requests
-   withdrawal_destinations
-   payout_transactions
-   reconciliation_runs
-   reconciliation_items
-   financial_adjustments

## 32.4 Affiliate

-   affiliate_profiles
-   affiliate_codes
-   affiliate_clicks
-   affiliate_attributions
-   affiliate_conversions
-   commission_rules
-   commission_entries
-   commission_reversals
-   affiliate_payout_batches

## 32.5 AI dan Media

-   ai_providers
-   ai_models
-   ai_provider_capabilities
-   ai_credentials_refs
-   ai_routing_rules
-   ai_agents
-   ai_agent_versions
-   ai_tools
-   ai_runs
-   ai_run_steps
-   prompt_templates
-   prompt_versions
-   media_jobs
-   media_job_steps
-   media_assets
-   media_asset_versions
-   media_transcripts
-   media_subtitles
-   media_renders
-   media_templates
-   template_versions
-   template_assets
-   moderation_events

## 32.6 Website Builder dan SEO

-   website_projects
-   website_pages
-   website_sections
-   website_templates
-   website_versions
-   website_domains
-   website_deployments
-   website_publish_events
-   seo_projects
-   seo_crawls
-   seo_pages
-   seo_rules
-   seo_rule_versions
-   seo_rule_tests
-   seo_findings
-   seo_reports
-   seo_keywords
-   seo_competitors (jika sumber datanya sah dan tersedia)

## 32.7 Social

-   social_providers
-   social_connections
-   social_accounts
-   social_scopes
-   social_tokens_encrypted
-   social_posts
-   social_post_media
-   social_schedules
-   social_publish_attempts
-   social_metrics_snapshots
-   social_webhook_events
-   messaging_templates
-   messaging_conversations (sesuai kebijakan dan API)

## 32.8 Platform dan Operasi

-   notifications
-   notification_preferences
-   support_tickets
-   support_messages
-   system_settings
-   feature_flags
-   audit_logs
-   admin_activity_logs
-   error_events
-   background_jobs
-   job_attempts
-   webhook_endpoints
-   webhook_deliveries
-   file_records
-   data_export_jobs
-   backup_records

## 32.9 Aturan Relasi

-   Semua data milik pengguna wajib memiliki `user_id` atau
    `workspace_id`.
-   Query wajib melakukan tenant scoping.
-   Semua transaksi keuangan memiliki reference unik dan idempotency
    key.
-   Entitas penting menggunakan UUID/ULID, bukan ID berurutan yang mudah
    ditebak sebagai satu-satunya kontrol akses.
-   Gunakan foreign key, unique constraint, index, dan check constraint
    yang relevan.
-   Soft delete hanya untuk data yang memang perlu dipulihkan; ledger
    keuangan tidak dihapus.
-   PII dan token disimpan dengan proteksi yang sesuai.
-   Data analytics tidak boleh menjadi sumber kebenaran transaksi.

------------------------------------------------------------------------

# 33. API Contract dan Integrasi Antarservice

## 33.1 Prinsip API

-   REST/JSON sebagai kontrak utama untuk web app.
-   API versioning, misalnya `/api/v1`.
-   OpenAPI/Swagger sebagai dokumentasi.
-   Authentication berbasis secure session atau token sesuai arsitektur.
-   Authorization server-side.
-   Standard error envelope.
-   Pagination, sorting, filtering yang tervalidasi.
-   Rate limit.
-   Idempotency key untuk operasi yang berpotensi menggandakan
    transaksi/job.
-   Request ID dan trace ID.
-   Webhook signature verification.

## 33.2 Contoh Kelompok Endpoint

Public: - `GET /api/v1/public/plans` - `GET /api/v1/public/features` -
`GET /api/v1/public/templates` - `POST /api/v1/auth/register` -
`POST /api/v1/auth/login` - `POST /api/v1/auth/forgot-password`

User: - `GET /api/v1/me` - `GET /api/v1/dashboard` -
`GET /api/v1/subscription` - `POST /api/v1/subscription/checkout` -
`GET /api/v1/wallet` - `POST /api/v1/wallet/topups` -
`POST /api/v1/withdrawals` - `POST /api/v1/media/image-jobs` -
`POST /api/v1/media/video-jobs` - `POST /api/v1/media/clip-jobs` -
`GET /api/v1/jobs/{jobId}` - `GET /api/v1/assets` -
`POST /api/v1/social/connections` - `POST /api/v1/seo/crawls` -
`POST /api/v1/websites`

Admin: - `GET /api/v1/admin/dashboard` - `GET /api/v1/admin/users` -
`POST /api/v1/admin/plans` - `POST /api/v1/admin/addons` -
`POST /api/v1/admin/providers` - `POST /api/v1/admin/seo/rules` -
`GET /api/v1/admin/withdrawals` -
`POST /api/v1/admin/withdrawals/{id}/approve` -
`POST /api/v1/admin/withdrawals/{id}/reject` -
`GET /api/v1/admin/jobs` - `GET /api/v1/admin/audit-logs`

Daftar ini adalah kelompok endpoint awal dan bukan pembatasan jumlah
API. Setiap modul lengkap harus mempunyai endpoint sesuai kebutuhan use
case.

## 33.3 Standard Response

Response sukses memuat data, metadata pagination bila diperlukan, dan
request ID. Response error memuat kode internal stabil, pesan aman untuk
user, detail validasi yang tidak sensitif, dan request ID. Stack trace,
SQL, secret, dan detail infrastruktur tidak boleh dikirim ke browser.

------------------------------------------------------------------------

# 34. Arsitektur Deployment dan Infrastruktur

## 34.1 Topologi Logical

Komponen: 1. DNS dan Cloudflare. 2. Reverse proxy/load balancer. 3.
Frontend web/PWA. 4. Backend API. 5. Database relational. 6.
Redis/cache/queue. 7. Object storage. 8. Worker umum. 9. Worker AI text.
10. Worker image/ComfyUI. 11. Worker video/render. 12. SEO
crawler/worker. 13. Social integration/webhook worker. 14.
Monitoring/logging. 15. Backup dan disaster recovery.

Komponen GPU-heavy tidak harus berada di VPS aplikasi utama. Pisahkan
compute node agar lonjakan render tidak mengganggu login, billing,
dashboard, dan API utama.

## 34.2 Environment

Minimal: - Local development. - Automated test/CI. - Staging/sandbox. -
Production.

Setiap environment memakai database, credential, storage, dan callback
URL yang terpisah. Dilarang menggunakan credential produksi untuk test
lokal atau staging.

## 34.3 Domain dan DNS

Rancangan subdomain: - `krafio.com` untuk frontpage. -
`app.krafio.com` untuk user panel. - `admin.krafio.com` untuk admin
panel (atau route terproteksi). - `api.krafio.com` untuk API jika
dipisah. - `assets.krafio.com` untuk asset delivery bila diperlukan. -
`status.krafio.com` untuk status layanan.

Nama domain final mengikuti domain yang dimiliki. Admin subdomain bukan
pengganti autentikasi dan otorisasi.

## 34.4 CI/CD

Pipeline: 1. Lint dan format check. 2. Unit test. 3. Static analysis. 4.
Dependency/security scan. 5. Build. 6. Integration test. 7. Migration
check. 8. Deploy staging. 9. Smoke test. 10. Approval release. 11.
Deploy production. 12. Post-deploy health check. 13. Rollback bila
health check gagal.

Migration harus backward-compatible bila deployment membutuhkan rolling
release. Backup dilakukan sebelum perubahan skema berisiko.

## 34.5 Kapasitas dan Sizing

Sizing final tidak boleh ditebak sebelum benchmark. Rekomendasi
pemisahan: - App/API node: CPU/RAM untuk request web dan business
logic. - Database node: RAM/IOPS/storage berdasarkan transaksi dan
query. - Queue/cache node: Redis atau teknologi setara. - Object
storage: media besar, backup, dan export. - GPU node: image/video AI
sesuai model dan concurrency. - Video render node: CPU/GPU dan disk
throughput sesuai pipeline.

Lakukan load test untuk concurrent login, dashboard, upload, generation
queue, webhook burst, dan download. Admin harus melihat resource
utilization, queue depth, failed jobs, dan provider latency.

## 34.6 Backup dan Disaster Recovery

-   Database backup terjadwal dan point-in-time recovery jika tersedia.
-   Object storage versioning/lifecycle.
-   Backup konfigurasi non-secret.
-   Secret recovery melalui prosedur secret manager.
-   Uji restore berkala.
-   RPO/RTO ditetapkan pemilik produk sebelum production.
-   Backup terenkripsi dan akses dibatasi.
-   Backup tidak dianggap valid sebelum pernah diuji restore.

------------------------------------------------------------------------

# 35. Security, Privacy, Compliance, dan Abuse Prevention

## 35.1 Application Security

-   OWASP Top 10 sebagai baseline.
-   CSRF protection untuk cookie session.
-   XSS prevention dan output escaping.
-   SQL injection prevention melalui parameterized query/ORM.
-   SSRF protection pada URL fetcher/crawler.
-   Upload validation dan malware scanning.
-   Secure headers dan TLS.
-   Password hashing dengan algoritma modern.
-   MFA untuk admin.
-   Session rotation dan revocation.
-   Rate limit login, OTP, reset password, dan endpoint sensitif.
-   Secrets tidak pernah masuk source control.
-   Dependency pinning dan vulnerability scanning.

## 35.2 Tenant Isolation

Setiap endpoint dan query yang membaca data privat wajib membuktikan
kepemilikan data. Jangan hanya mengandalkan UUID yang sulit ditebak.
Pengujian wajib mencoba akses silang antara dua akun berbeda untuk
seluruh objek: proyek, aset, job, invoice, wallet, social connection,
dan report.

## 35.3 Admin Security

-   MFA wajib.
-   Session timeout.
-   Re-authentication untuk tindakan keuangan dan rotasi credential.
-   Audit log append-only.
-   Konfirmasi untuk tindakan destruktif.
-   IP allowlist dapat disediakan sebagai opsi, bukan satu-satunya
    kontrol.
-   Admin tidak boleh melihat password user atau token provider
    plaintext.
-   Aksi impersonation, bila kelak disediakan, harus dibatasi, diberi
    banner, dan tercatat.

## 35.4 Privacy dan Data Lifecycle

-   Privacy policy menjelaskan data yang dikumpulkan, tujuan, retensi,
    dan pihak pemroses.
-   Consent untuk integrasi sosial dan pengiriman data ke AI provider.
-   Fitur disconnect dan revoke token.
-   Prosedur permintaan akses, koreksi, export, dan penghapusan data
    sesuai kewajiban hukum.
-   Retensi log, file, prompt, dan output ditetapkan.
-   Jangan mengirim PII yang tidak diperlukan ke AI provider.
-   Data pelanggan tidak boleh digunakan untuk training provider tanpa
    dasar persetujuan yang sah.

## 35.5 Abuse Prevention

-   CAPTCHA/Turnstile pada titik risiko.
-   Rate limiting adaptif.
-   Deteksi spam, scraping, credential stuffing, dan penggunaan kuota
    abnormal.
-   Prompt/content moderation.
-   Batas upload dan job.
-   Fraud checks affiliate dan withdrawal.
-   Blocklist/allowlist sesuai kebutuhan.
-   Manual review untuk aktivitas berisiko tinggi.

------------------------------------------------------------------------


---

## Integrasi Spesifikasi Admin Panel pada Bab 35

### Spesifikasi Admin Panel Terintegrasi — System & Security Management
Admin dapat memantau seluruh infrastruktur.

#### Server Monitoring

-   CPU.
-   RAM.
-   Storage.
-   Disk I/O.
-   Bandwidth.
-   Uptime.
-   GPU/VRAM jika tersedia.

#### Database Monitoring

-   Database size.
-   Connections.
-   Slow queries.
-   Query errors.
-   Backup status.
-   Restore testing.

#### Redis Monitoring

-   Memory.
-   Cache hit rate.
-   Queue depth.
-   Latency.
-   Failed jobs.

#### Security Monitoring

-   Login attempts.
-   Suspicious activity.
-   IP blocking.
-   Session management.
-   Admin MFA.
-   Cloudflare.
-   Turnstile.
-   Rate limiting.
-   Webhook verification.
-   Secret rotation.

#### Backup

Admin dapat melihat backup terakhir, backup database, backup media,
status backup, retention, dan hasil pengujian restore.

# 36. Observability, Support, dan Operasional

## 36.1 Monitoring

Pantau: - Uptime dan latency. - HTTP error rate. - Database
connection/query health. - Queue depth dan age. - Worker
failure/retry. - AI provider latency/error/cost. - GPU utilization dan
VRAM. - Video render duration. - Storage growth. - Payment webhook
success/failure. - Social token expiry. - Withdrawal backlog. -
Email/notification delivery.

## 36.2 Logging

Log harus terstruktur dan memiliki: - Timestamp. - Severity. -
Service. - Environment. - Request ID. - Trace ID. - User/workspace
reference yang aman. - Job/payment reference. - Error code.

Jangan mencatat password, token, kartu pembayaran, atau konten privat
secara berlebihan. Terapkan redaction.

## 36.3 Support

User Panel: - Pusat bantuan. - FAQ. - Buat tiket. - Lampirkan
screenshot/log yang telah disanitasi. - Status tiket. - Riwayat
komunikasi.

Admin Panel: - Ticket queue. - Prioritas dan status. - Riwayat akun/job
terkait. - Catatan internal. - Tindakan penyelesaian. - Audit perubahan
status.

------------------------------------------------------------------------


---

## Integrasi Spesifikasi Admin Panel pada Bab 36

### Spesifikasi Admin Panel Terintegrasi — Media Jobs Management
Seluruh proses AI dan media harus tercatat sebagai job.

Contoh: - Generate image. - Generate video. - AI photo editing. - AI
video editing. - Video clipper. - Video rendering. - Transcription. -
Export.

Admin dapat melihat:

  Informasi    Keterangan
  ------------ -----------------------------------------
  Job ID       Identitas proses.
  User         Pemilik proses.
  Feature      Fitur yang digunakan.
  Provider     Provider AI.
  Model        Model yang digunakan.
  Status       Pending, processing, completed, failed.
  Duration     Waktu proses.
  Cost         Estimasi biaya.
  Error        Detail kegagalan.
  Created At   Waktu dibuat.

Admin dapat melakukan retry atau cancel pada job yang aman untuk
diulang. Retry harus memeriksa idempotency, pemakaian kuota, dan potensi
biaya ganda.

### Spesifikasi Admin Panel Terintegrasi — Audit Logs
Semua tindakan penting harus tercatat.

  Aktivitas                     Dicatat
  ----------------------------- ---------------------------
  Approve akun                  Ya
  Reject akun                   Ya
  Mengubah harga                Ya
  Mengubah paket                Ya
  Menambah saldo                Ya
  Mengurangi saldo              Ya
  Approve withdrawal            Ya
  Mengubah provider AI          Ya
  Mengubah API Key              Ya, tanpa mencatat secret
  Generate template             Ya
  Publish template              Ya
  Mengubah SEO rules            Ya
  Ban pengguna                  Ya
  Mengubah konfigurasi sistem   Ya

Informasi log:

-   Admin ID.
-   Waktu.
-   IP.
-   Perangkat.
-   Modul.
-   Aktivitas.
-   Data sebelum perubahan.
-   Data sesudah perubahan.
-   Alasan.
-   Hasil eksekusi.

Audit log sebaiknya bersifat append-only agar tidak mudah dimanipulasi.

# 37. Repository dan Open-Source Integration Register

## 37.1 Daftar Kandidat yang Wajib Dievaluasi

Seluruh repository berikut tetap berada dalam daftar evaluasi Krafio.
Status "kandidat" berarti perlu verifikasi repository yang tepat, bukan
jaminan langsung dapat dipasang atau dipakai secara komersial.

  --------------------------------------------------------------------------
  Kandidat        Peran potensial       Pola integrasi     Pemeriksaan wajib
                                        yang ditargetkan   
  --------------- --------------------- ------------------ -----------------
  WhatsApp        Dashboard operasi     API/service        Pastikan berbasis
  Business API    WhatsApp              adapter            API resmi Meta,
  Dashboard                                                bukan otomasi
  (custom)                                                 WhatsApp Web

  Concat          Editing/concat video  Worker atau        Identitas repo,
                                        library            lisensi, codec,
                                                           API, maintenance

  Modly           3D image/model        Service/worker     Lisensi model,
                  generation            opsional           GPU, output
                                                           rights, endpoint

  PowerToys       Utilitas pengembangan Internal developer Bukan dependency
                  Windows               tool               production web

  Jan             Local AI              Optional AI        API server, model
                  runtime/model         service            license,
                  management                               isolation,
                                                           resource

  LosslessCut     Lossless trim/cut     Worker/CLI         Headless mode,
                  video                                    codec, license,
                                                           server
                                                           suitability

  Prompt          Optimasi prompt       Internal           Data handling,
  Optimizer                             module/agent       license, quality
                                                           evaluation

  ComfyUI         Image                 Dedicated AI       API, custom
                  generation/workflow   service            nodes, model
                                                           licenses, GPU,
                                                           security

  Archify         Belum ditentukan      Setelah            URL repo, owner,
                                        identifikasi       tujuan, license,
                                                           stack

  OpenSEO         SEO                   Engine/reference   Repo identity,
                  analysis/reference    module             license, crawl
                                                           policy, accuracy

  Screenshot to   Konversi referensi UI Internal dev tool  Output quality,
  Code            ke kode                                  dependency,
                                                           license

  FREELLMAPI      LLM API abstraction   AI Gateway adapter Secret handling,
                                        candidate          provider support,
                                                           license,
                                                           maintenance

  AI Design       Design workflow       Internal           License, scope,
  Skills          support               skill/tooling      compatibility

  Postiz          Social media          API/service        Tenant isolation,
                  management            integration        official API,
                                                           license, app
                                                           review

  Video Shotcraft Video workflow        Worker/tool        Repo identity,
                                        integration        pipeline, output,
                                                           license

  Security Audit  Security review       Internal dev tool  Tidak
  Skill           support                                  menggantikan
                                                           pentest, scope,
                                                           source

  Wordcraft       Dokumen/copy          Internal or user   License, export,
                  generation            module             editor
                                                           integration

  Deckcraft       Presentation          Internal or user   License,
                  generation            module             rendering,
                                                           fonts/assets

  Gridcraft       Spreadsheet/grid      Internal or user   License,
                  generation            module             formulas, file
                                                           safety

  Reclip          Automatic clipping    Worker/service     Repo identity,
                                        candidate          model, quality,
                                                           rights

  REA             Belum ditentukan      Setelah            Repo URL,
                                        identifikasi       maintainer,
                                                           purpose, license

  AutoClip        Automatic clipping    Worker/service     Repo identity,
                                        candidate          quality,
                                                           resource, license

  Remotion        Programmatic video    Node render worker License plan,
                  rendering                                render cost,
                                                           fonts/assets

  OpenHands       Coding agent          Sandboxed internal No production
                                        dev environment    secrets,
                                                           command/network
                                                           isolation

  MuMuAINovel     Creative writing      Optional AI module Repo identity,
                                                           license, model,
                                                           content policy
  --------------------------------------------------------------------------

## 37.2 Empat Pola Integrasi

A. **Embedded/library:** kode/library menjadi bagian dari aplikasi utama
jika lisensi dan framework kompatibel.

B. **API/service:** aplikasi utama memanggil service terpisah melalui
API yang terautentikasi. Cocok untuk engine yang mempunyai server mode
atau perlu runtime sendiri.

C. **Worker/CLI:** job queue mengirim pekerjaan ke worker khusus yang
menjalankan command-line tool atau pipeline, lalu menyimpan hasil ke
object storage.

D. **Internal development tool:** hanya dipakai tim pengembang untuk
membuat UI, mengaudit keamanan, membantu coding, atau membuat konten
internal. Tidak otomatis menjadi fitur yang diakses pelanggan.

Satu repository dapat berubah pola setelah audit. Jangan memaksa semua
project dimasukkan ke source code utama.

## 37.3 Checklist Audit Repository

Sebelum integrasi, catat: 1. URL repository resmi dan owner/maintainer.
2. Commit/release terakhir dan status maintenance. 3. License kode,
model, checkpoint, font, dataset, dan asset. 4. Apakah boleh dipakai
komersial dan dalam SaaS multi-tenant. 5. Apakah ada kewajiban source
disclosure, attribution, atau network copyleft. 6. Bahasa, framework,
runtime, dan versi dependency. 7. API/SDK/CLI dan stabilitas kontrak. 8.
OS/container compatibility. 9. CPU/RAM/GPU/VRAM/disk/network
requirement. 10. Cara autentikasi dan pengelolaan secret. 11. Tenant
isolation dan kemampuan concurrency. 12. Risiko SSRF, RCE, file upload,
arbitrary code, dan supply chain. 13. Kualitas output dan test dataset.
14. Observability, retry, timeout, cancellation. 15. Biaya operasional
dan estimasi cost per task. 16. Mekanisme upgrade, rollback, dan exit
strategy.

## 37.4 Integration Gate

Repository hanya boleh masuk production setelah: - Legal/license review
selesai. - Security review selesai. - Functional benchmark lolos. -
Resource benchmark tersedia. - API/worker contract terdokumentasi. -
Tenant isolation diuji. - Monitoring dan rollback tersedia. - Admin
dapat mengaktifkan/menonaktifkan adapter. - Alternatif/manual fallback
tersedia bila tool gagal.

**Catatan v2.3:** Bab 37 tetap berlaku sebagai register induk. Bab 53 menambahkan URL teridentifikasi, sistem audit 20 kriteria, status audit dan implementasi, serta metode integrasi. Pengelolaan repository utama Krafio dan CI/CD diatur pada Bab 52; keputusan yang tertunda dihimpun pada Bab 54.7.

------------------------------------------------------------------------

# 38. Testing Strategy dan Quality Gates

## 38.1 Jenis Pengujian

-   Unit test untuk business logic.
-   Feature test untuk use case.
-   API contract test.
-   Integration test provider sandbox.
-   Database migration test.
-   Queue/worker test.
-   End-to-end UI test.
-   Visual regression untuk template/website builder.
-   Security test.
-   Load/stress test.
-   Backup restore test.
-   Accessibility test.
-   Cross-browser/mobile test.

## 38.2 Skenario Kritis Wajib

1.  User A tidak dapat membaca aset/job/invoice milik User B.
2.  User tanpa entitlement tidak dapat menjalankan fitur melalui API
    langsung.
3.  Kuota tidak dapat digunakan dua kali secara bersamaan melebihi
    batas.
4.  Webhook payment yang sama diterima berkali-kali hanya diproses
    sekali.
5.  Top-up tidak bertambah sebelum payment final.
6.  Refund membuat reversal yang dapat ditelusuri.
7.  Saldo komisi tidak bercampur dengan saldo top-up.
8.  Withdrawal tidak dapat melebihi available commission.
9.  Dua withdrawal paralel tidak dapat menarik saldo yang sama.
10. Job gagal mengembalikan reservation sesuai kebijakan.
11. Provider timeout tidak membuat tagihan ganda.
12. Social token expired memicu status reconnect, bukan publikasi palsu.
13. User harus menyetujui konten sebelum publish jika approval
    diwajibkan.
14. Export report identik dengan filter dan data yang ditampilkan.
15. Download file privat membutuhkan authorization/signed URL.
16. SEO crawl mematuhi batas dan tidak menjadi SSRF proxy.
17. Admin action tercatat.
18. Restore backup dapat memulihkan database dan file.
19. PWA update tidak menghapus data draft yang belum tersinkron.
20. Pengguna mendapat pesan error yang aman dan dapat ditindaklanjuti.

## 38.3 Release Gate

Tidak boleh rilis production bila: - Ada critical/high vulnerability
yang belum ditangani. - Ada bug lintas-tenant. - Ledger tidak balance. -
Webhook tidak idempotent. - Backup/restore belum diuji. - Monitoring
error/queue/payment belum aktif. - Credential produksi masih berada di
repository atau frontend. - API eksternal digunakan tanpa izin yang
diperlukan.

------------------------------------------------------------------------

# 39. Deliverables Pengembangan

Pengembangan Krafio harus menghasilkan: 1. Source code frontend dan
backend. 2. Database schema dan migrations. 3. API specification
OpenAPI. 4. UI/UX design system dan prototype. 5. Admin Panel lengkap.
6. User Panel lengkap. 7. Public frontpage. 8. AI Gateway dan provider
adapters. 9. Agent orchestration dan prompt library. 10. Media
generation/editing workers. 11. Template library dan template
management. 12. Website builder. 13. SEO rules engine. 14. Social
integration adapters. 15. Billing, wallet, affiliate, dan withdrawal.
16. Notification service. 17. Queue/worker orchestration. 18. Deployment
scripts dan infrastructure configuration. 19. CI/CD pipeline. 20.
Monitoring dashboard dan alerting. 21. Security and privacy
documentation. 22. Test suites dan test reports. 23. Backup/restore
procedures. 24. Admin/operator manual. 25. User guide. 26. Incident
response and rollback procedure. 27. Repository audit register dan
license inventory. 28. Cost model per fitur/provider. 29. Production
readiness checklist.

------------------------------------------------------------------------

# 40. Roadmap Dependensi Teknis Tanpa Mengurangi Scope

Urutan ini menjelaskan ketergantungan, bukan keputusan untuk menghapus
fitur.

## Jalur A --- Fondasi bersama

Identity, tenant scoping, design system, API contract, database, file
storage, queue, observability, security, dan admin configuration.

## Jalur B --- Monetisasi dan entitlement

Plan/add-on/custom package, payment adapter, ledger, top-up, quota
reservation, invoice, affiliate, commission, withdrawal, reconciliation.

## Jalur C --- Konten AI

AI Gateway, provider registry, prompt library, agent orchestrator,
chatbot, brainstorming, generate/edit foto, template foto.

## Jalur D --- Video

Transcription, clipper, scene detection, video generation, editing,
subtitle, Remotion/render workers, export, template video.

## Jalur E --- Website dan SEO

Website builder, template editor, AI content assistant, preview/publish,
domain, crawler, SEO analyzer, custom rules, report.

## Jalur F --- Integrasi sosial

OAuth, account center, WhatsApp Business Platform, Meta, TikTok,
Threads, webhook, content calendar, approval, analytics.

## Jalur G --- Ekosistem dan optimasi

Repository integrations, PWA, localization, advanced analytics, cost
optimization, autoscaling, disaster recovery, dan peningkatan kapasitas.

Semua jalur merupakan bagian dari target lengkap. Ketergantungan
eksternal seperti persetujuan API, lisensi, akses model, dan kapasitas
GPU harus dikelola sebagai workstream tersendiri, bukan alasan
menghilangkan fitur dari PRD.

------------------------------------------------------------------------

# 41. Keputusan Final yang Perlu Dikunci Sebelum Production

Keputusan berikut tidak mengurangi scope; keputusan ini menentukan
konfigurasi implementasi:

1.  Nama dan domain final Krafio.
2.  Badan usaha/merchant of record dan dokumen payment gateway.
3.  Payment gateway utama dan cadangan.
4.  Kebijakan pajak, invoice, refund, dan pembatalan.
5.  Nominal harga paket, add-on, custom package, dan kuota.
6.  Masa aktif kuota: bulanan, tahunan, atau carry-over.
7.  Apakah saldo top-up memiliki masa kedaluwarsa.
8.  Metode payout affiliate dan biaya payout.
9.  Persentase komisi, masa atribusi, dan aturan fraud.
10. Negara target dan bahasa UI.
11. AI provider utama per capability.
12. Model lisensi dan kebijakan penyimpanan prompt/output.
13. Kapasitas GPU dan strategi cloud/on-premise.
14. Platform sosial mana yang sudah memperoleh akses API dan app review.
15. Kebijakan publikasi otomatis vs approval.
16. Template awal dan sumber asset berlisensi.
17. Pilihan hosting website hasil builder.
18. Retensi data dan prosedur penghapusan.
19. SLA, RPO, dan RTO.
20. Kontak support dan incident escalation.

------------------------------------------------------------------------

# 42. Pernyataan Penutup dan Status Dokumen

Dokumen ini merupakan **Master PRD dan Technical Blueprint Krafio**.
Seluruh fitur dan repository kandidat yang dicantumkan menjadi ruang
lingkup target evaluasi dan pengembangan. Urutan implementasi hanya
mengatur dependensi dan kesiapan, bukan mengurangi komitmen fitur.

Detail endpoint, skema final, versi library, konfigurasi infrastruktur,
dan pemilihan provider harus dipastikan melalui technical discovery,
repository audit, proof of compatibility, serta pengujian sandbox. Tidak
ada klaim bahwa semua repository pihak ketiga sudah diverifikasi atau
otomatis kompatibel hanya berdasarkan nama.

**Status dokumen:** Master PRD v2.0 --- baseline lengkap untuk product
discovery, UI/UX, architecture design, technical specification,
implementation planning, QA, dan production readiness.

------------------------------------------------------------------------

# 43. Technology Stack Resmi Krafio

## 43.1 Keputusan Stack Utama

Stack yang ditetapkan untuk baseline pengembangan Krafio:

  -----------------------------------------------------------------------
  Layer                   Teknologi               Keputusan
  ----------------------- ----------------------- -----------------------
  Frontend                React + TypeScript      Wajib

  Styling                 Tailwind CSS            Wajib

  Build tooling           Vite                    Wajib untuk SPA

  Backend                 Laravel (PHP)           Wajib

  API                     REST API + JSON         Wajib

  API documentation       OpenAPI/Swagger         Wajib

  Authentication SPA      Laravel Sanctum,        Direkomendasikan
                          cookie-based            

  Database utama          MySQL 8.4 LTS, InnoDB   Rekomendasi utama

  Cache                   Redis self-hosted       Wajib untuk production
                                                  baseline

  Queue                   Laravel Queue dengan    Wajib untuk pekerjaan
                          Redis                   asynchronous

  Queue dashboard         Laravel Horizon         Direkomendasikan

  Realtime                Laravel                 Untuk progress
                          Reverb/WebSocket bila   job/notifikasi live
                          diperlukan              

  Object storage          S3-compatible object    Wajib untuk aset/media
                          storage atau storage    jangka panjang
                          terpisah                

  Edge/DNS/WAF            Cloudflare              Wajib sebagai lapisan
                                                  edge/security

  CAPTCHA                 Cloudflare Turnstile    Wajib pada titik rawan
                                                  abuse

  Email                   SMTP/transactional      Wajib
                          email provider          

  WhatsApp                WhatsApp Business       Opsional per user,
                          Platform Cloud API atau tetapi integrasinya
                          BSP resmi               masuk scope

  Web server              Nginx                   Direkomendasikan

  PHP process manager     PHP-FPM                 Direkomendasikan

  Process supervisor      Supervisor atau systemd Wajib untuk worker

  Container               Docker Compose untuk    Direkomendasikan
                          konsistensi deployment  

  CI/CD                   GitHub Actions atau     Wajib untuk pipeline
                          runner setara           release

  Monitoring              Prometheus/Grafana dan  Direkomendasikan
                          log aggregation setara  
  -----------------------------------------------------------------------

Stack ini menjadi standar utama. Teknologi tambahan hanya boleh
ditambahkan jika memiliki alasan arsitektural, kebutuhan fitur, dan
dokumentasi yang jelas. Jangan menambahkan framework backend kedua tanpa
kebutuhan kuat.

## 43.2 Frontend: React + TypeScript + Tailwind CSS

Frontend dibuat sebagai aplikasi React berbasis TypeScript. TypeScript
diwajibkan agar kontrak data, props, form, dan API response lebih aman
dari kesalahan tipe.

Komponen: - React untuk UI component dan state-driven rendering. -
TypeScript untuk static typing. - Tailwind CSS untuk utility-first
styling dan design tokens. - Vite untuk development server dan
production build. - React Router untuk navigasi SPA. - TanStack Query
untuk server state, cache request, retry, dan invalidation. - React Hook
Form untuk pengelolaan form. - Zod atau validator setara untuk schema
validation di frontend. - Library chart yang terawat untuk dashboard. -
Testing Library dan Playwright untuk pengujian UI/E2E.

Ketentuan: - Komponen reusable dan design system konsisten. - Pisahkan
public frontpage, user panel, dan admin panel melalui route/layout serta
permission. - Frontend tidak menjadi sumber otorisasi. Semua permission
diperiksa ulang di Laravel. - Jangan menyimpan access token sensitif di
localStorage jika memakai cookie-based Sanctum. - Gunakan secure,
HttpOnly, SameSite cookie sesuai konfigurasi domain dan HTTPS. - Semua
form penting mempunyai loading, validation, success, error, dan retry
state. - UI harus tetap responsif saat job AI/video berjalan; jangan
menahan request HTTP sampai render selesai.

## 43.3 Backend: Laravel

Laravel menjadi satu-satunya backend utama untuk business logic Krafio.

Tanggung jawab: - Authentication dan authorization. - User/admin
management. - Subscription, add-on, entitlement, quota. - Payment,
wallet, affiliate, withdrawal, ledger. - API untuk semua panel. - AI
Gateway dan provider adapters. - Job dispatch dan orchestration. -
Social integration adapters dan webhooks. - Website Builder dan SEO
rules. - Notification service. - Audit logging, admin settings,
reporting. - Validation, rate limit, idempotency, transaction boundary.

Standar implementasi: - PHP dengan versi yang didukung Laravel release
yang dipilih. - Laravel Form Request untuk validasi request. -
Policies/Gates untuk authorization. - Service/action layer untuk
business logic kompleks. - Events/Listeners untuk proses berbasis
event. - Jobs untuk pekerjaan asynchronous. - Notifications/Mailables
untuk email dan notifikasi. - API Resources untuk bentuk response. -
Database migrations dan seeders untuk perubahan skema. - Automated tests
untuk business-critical logic. - Config/env separation untuk setiap
environment.

Jangan menaruh seluruh business logic di Controller. Controller bertugas
menerima request, memanggil use case/service, dan mengembalikan
response.

## 43.4 Database: MySQL atau PostgreSQL?

### Keputusan: MySQL 8.4 LTS sebagai database utama

MySQL sangat cocok untuk Krafio dan merupakan pilihan yang
direkomendasikan untuk baseline ini, dengan syarat: - Gunakan InnoDB. -
Aktifkan foreign key. - Gunakan transaksi database untuk operasi
finansial. - Gunakan DECIMAL untuk nilai uang, bukan FLOAT/DOUBLE. -
Gunakan unique constraint untuk reference dan idempotency key. - Buat
index berdasarkan pola query aktual. - Gunakan migration terkontrol. -
Aktifkan backup, binary log/PITR jika tersedia, dan uji restore. -
Pisahkan database production, staging, dan development. - Batasi koneksi
aplikasi dan gunakan connection pooling/proxy bila dibutuhkan pada skala
lebih besar.

MySQL dipilih karena matang, mudah dioperasikan, kompatibel dengan
Laravel, dan sesuai untuk data transaksional seperti user, subscription,
payment, wallet ledger, affiliate, job metadata, dan konfigurasi
platform.

### Kapan PostgreSQL lebih menarik?

PostgreSQL juga pilihan sangat baik, terutama jika kelak kebutuhan query
analitik kompleks, JSONB, full-text search, geospatial, atau fitur
database khusus menjadi dominan. Namun, tidak perlu menggunakan MySQL
dan PostgreSQL sekaligus untuk data transaksi inti sejak awal. Dua
database utama menambah beban operasi, backup, monitoring, konsistensi,
dan kompetensi tim.

### Data yang tidak cocok dimasukkan sebagai blob database

File gambar, video, audio, hasil render, dataset, dan export besar
disimpan di object storage/file storage. Database hanya menyimpan
metadata, ownership, path/key, ukuran, MIME, checksum, status, dan
relasi file.

## 43.5 Redis: Self-hosted, Bukan Redis Free Cloud

Krafio direkomendasikan menjalankan Redis/Valkey sendiri pada server
yang dikuasai, bukan bergantung pada kuota gratis layanan Redis Cloud.

Redis self-hosted: - Tidak memiliki kuota request bulanan gratis dari
vendor managed service. - Kapasitas dibatasi RAM yang dialokasikan, CPU,
konfigurasi, dan kemampuan server. - Tidak berarti tanpa batas; key,
payload, queue, TTL, dan eviction policy tetap harus dikontrol. - Dapat
dipakai Laravel untuk cache, queue, rate limiting, locks, dan koordinasi
worker. - Harus diamankan dengan bind ke private interface/localhost,
ACL/password, firewall, dan TLS bila melintasi jaringan.

Karena server memiliki RAM besar, Redis dapat dialokasikan resource yang
terukur. Jangan memberikan seluruh RAM kepada Redis; database, PHP-FPM,
OS, worker, dan kemungkinan AI/media process juga memerlukan RAM.

### Pemisahan logical Redis

Gunakan prefix dan logical database/instance terpisah agar tujuan data
jelas: - `krafio:cache:*` - `krafio:queue:*` -
`krafio:rate_limit:*` - `krafio:locks:*` - `krafio:session:*` bila
session memang memakai Redis.

Untuk beban yang meningkat, pertimbangkan instance Redis/Valkey terpisah
bagi queue dan cache agar eviction cache tidak mengganggu antrean
pekerjaan.

### Kebijakan Redis

-   Queue tidak boleh memakai eviction policy yang berisiko menghapus
    job aktif.
-   Cache boleh memakai TTL dan eviction policy yang sesuai.
-   Batasi ukuran payload; file besar tidak boleh dimasukkan ke Redis.
-   Simpan payload job berupa ID dan metadata minimum, bukan binary
    media.
-   Pantau memory usage, fragmentation, hit rate, queue depth, dan
    latency.
-   Atur persistence sesuai kebutuhan; Redis queue bukan satu-satunya
    catatan transaksi.
-   Job penting tetap tercatat di database dan dapat direkonsiliasi.

## 43.6 Apakah Cloudflare Menggantikan Redis?

Tidak. Cloudflare dan Redis mempunyai fungsi berbeda.

Cloudflare: - DNS dan proxy. - CDN/cache untuk konten yang aman
dicache. - WAF dan proteksi trafik. - DDoS mitigation sesuai
plan/provider. - Turnstile untuk pemeriksaan bot. - Rate limiting pada
edge sesuai plan dan konfigurasi. - Object storage/edge services jika
kelak digunakan.

Redis: - Cache internal aplikasi. - Queue backend Laravel. - Distributed
lock. - Rate limit internal. - Session/cache store jika dipilih.

Cloudflare tidak menggantikan antrean job Laravel, distributed lock,
cache internal backend, atau koordinasi worker. Krafio memerlukan
keduanya untuk lapisan yang berbeda.

------------------------------------------------------------------------

# 44. OTP, Verifikasi Akun, dan Account Security

## 44.1 Kanal Verifikasi

Krafio harus mendukung: 1. Email verification melalui link bertanda
tangan atau OTP. 2. Email OTP untuk registrasi, login berisiko, dan
perubahan data sensitif. 3. WhatsApp OTP melalui WhatsApp Business
Platform resmi jika user memilih kanal tersebut dan template/akses sudah
disetujui. 4. Password reset melalui link sekali pakai. 5. MFA/TOTP
untuk admin; dapat diperluas ke user. 6. Verifikasi ulang untuk
perubahan email, nomor WhatsApp, password, rekening payout, dan tindakan
finansial sensitif.

Email menjadi kanal verifikasi utama pada peluncuran. WhatsApp menjadi
kanal tambahan/alternatif setelah konfigurasi Meta/BSP, template,
consent, dan biaya disiapkan.

## 44.2 Alur Registrasi

1.  Pengguna mengisi nama, email, nomor telepon/WhatsApp, dan password.
2.  Backend memvalidasi format, keunikan email/nomor, password policy,
    dan CAPTCHA.
3.  Akun dibuat dengan status belum terverifikasi.
4.  Sistem mengirim email verification link atau OTP.
5.  Jika user memilih WhatsApp dan kanal aktif, sistem mengirim OTP
    menggunakan template resmi yang telah disetujui.
6.  Pengguna memasukkan OTP atau membuka verification link.
7.  Backend memeriksa expiry, hash, attempt count, tujuan, dan status
    token.
8.  Jika valid, kanal terkait ditandai verified.
9.  Akun dapat masuk ke status aktif sesuai kebijakan verifikasi.
10. User menerima notifikasi bahwa akun berhasil diverifikasi.

## 44.3 Aturan OTP

Baseline kebijakan yang dapat dikonfigurasi admin: - OTP numerik 6
digit. - Masa berlaku 5 menit. - Maksimum 5 percobaan per kode. -
Cooldown resend 60 detik. - Maksimum 5 pengiriman per akun/tujuan dalam
1 jam sebagai baseline awal. - Rate limit tambahan berdasarkan IP,
device/session, dan nomor/email. - OTP hanya dapat dipakai satu kali. -
OTP lama langsung tidak berlaku setelah OTP baru diterbitkan. - OTP
disimpan dalam bentuk hash, bukan plaintext. - Jangan menampilkan OTP di
log, analytics, atau admin dashboard. - Setelah terlalu banyak
kegagalan, lakukan cooldown dan/atau verifikasi tambahan. - Batas akhir
harus dapat dikonfigurasi sesuai risiko dan kapasitas provider.

Nilai tersebut adalah default awal, bukan angka yang tidak dapat diubah.
Admin dapat mengubah kebijakan dalam batas keamanan yang ditetapkan.

## 44.4 Database OTP dan Verifikasi

Tambahkan tabel/entitas: - `verification_challenges`: id, user_id
nullable, destination_type, destination_hash/masked, purpose,
code_hash/token_hash, expires_at, consumed_at, attempts, resend_count,
status, created_at. - `verified_contacts`: user_id, channel,
normalized_destination, verified_at, is_primary. -
`notification_deliveries`: notification_id, channel, provider,
provider_message_id, status, attempts, error_code, sent_at,
delivered_at. - `security_events`: user_id, event_type, IP hash/metadata
yang sesuai kebijakan, device/session reference, timestamp.

Jangan menyimpan OTP plaintext. Nomor telepon dan email harus dilindungi
sebagai PII; tampilkan secara masked di UI admin.

## 44.5 Password Reset dan Perubahan Kontak

-   Password reset memakai token acak sekali pakai dengan expiry.
-   Email/nomor baru belum dianggap terverifikasi sebelum proses
    selesai.
-   Perubahan email/nomor mengirim konfirmasi ke kontak lama dan/atau
    metode verifikasi tambahan berdasarkan risk policy.
-   Perubahan rekening payout membutuhkan re-authentication dan
    notifikasi ke kanal terverifikasi.
-   Cabut sesi aktif jika ada perubahan kredensial yang berisiko.

------------------------------------------------------------------------


---

## Integrasi Spesifikasi Admin Panel pada Bab 44

### Spesifikasi Admin Panel Terintegrasi — Account Approval Management
Krafio memiliki sistem persetujuan akun yang dikendalikan Admin. Admin
dapat menentukan apakah akun baru boleh langsung aktif atau harus
menunggu persetujuan.

#### A. Mode Registrasi

**Automatic Approval**

Pengguna melakukan registrasi, menyelesaikan verifikasi yang diwajibkan,
kemudian akun aktif secara otomatis.

**Manual Approval**

Pengguna melakukan registrasi, menyelesaikan verifikasi kontak, lalu
akun masuk antrean persetujuan Admin.

Admin dapat mengubah mode tersebut melalui System Settings.

#### B. Alur Registrasi Manual Approval

``` text
USER
  |
  v
Membuka Halaman Registrasi
  |
  v
Mengisi Data Akun
  |
  v
Verifikasi Email / OTP
  |
  v
Data Dikirim ke Sistem
  |
  v
ACCOUNT STATUS: PENDING APPROVAL
  |
  v
ADMIN MENERIMA NOTIFIKASI
  |
  v
Admin Membuka Pending Accounts
  |
  v
Memeriksa Informasi Pengguna
  |
  +-----------------------+
  |                       |
  v                       v
APPROVE                 REJECT
  |                       |
  v                       v
Akun Aktif              Akun Ditolak
  |                       |
  v                       v
Notifikasi User         Notifikasi User
```

Admin juga dapat memilih **Request Revision** apabila informasi yang
diberikan belum lengkap.

#### C. Status Akun

  Status                 Keterangan
  ---------------------- -----------------------------------
  Pending Verification   Belum menyelesaikan verifikasi.
  Pending Approval       Menunggu persetujuan Admin.
  Active                 Akun aktif.
  Rejected               Registrasi ditolak.
  Suspended              Akun ditangguhkan sementara.
  Banned                 Akun diblokir karena pelanggaran.
  Deactivated            Akun dinonaktifkan.

#### D. Halaman Pending Accounts

Admin dapat melihat nama pengguna, email, nomor telepon, tanggal
registrasi, status verifikasi, informasi tambahan yang diwajibkan, dan
indikator risiko jika tersedia.

Tindakan Admin:

-   Approve.
-   Reject.
-   Request Revision.
-   Hold.

Semua keputusan harus memiliki histori. Akun yang masih menunggu
persetujuan tidak boleh menggunakan fitur yang mensyaratkan akun aktif.

# 45. SMTP dan Transactional Email

## 45.1 Fungsi Email

Email digunakan untuk: - Verifikasi akun. - OTP. - Reset password. -
Login/security alert. - Invoice dan bukti pembayaran. - Status
subscription. - Notifikasi top-up dan withdrawal. - Affiliate
commission. - Job selesai/gagal. - Social token expiry. - Laporan dan
export siap diunduh. - Support ticket. - Pengumuman penting layanan.

## 45.2 SMTP Server vs Email Delivery Provider

SMTP adalah protokol/pintu pengiriman, bukan jaminan email akan masuk
inbox. Krafio dapat menggunakan SMTP credentials dari transactional
email provider atau mail server sendiri.

Rekomendasi: - Gunakan transactional email provider untuk production
agar deliverability, bounce, complaint, dan reputasi IP lebih
terkelola. - Provider harus mendukung SMTP dan/atau API, event webhook,
domain authentication, bounce/complaint handling, dan volume yang
sesuai. - Sediakan adapter agar provider dapat diganti. - Self-hosted
mail server tidak direkomendasikan sebagai pilihan awal untuk
OTP/transaksional karena reputasi IP, spam filtering, reverse DNS, dan
maintenance.

## 45.3 Domain Email

Contoh alamat: - `no-reply@krafio.com` - `support@krafio.com` -
`billing@krafio.com` - `security@krafio.com`

Konfigurasi DNS: - SPF. - DKIM. - DMARC. - MX bila menerima email pada
domain tersebut. - Return-path/bounce domain bila provider mendukung.

Jangan mengirim email production sebelum SPF, DKIM, dan DMARC diperiksa.

## 45.4 Email Queue dan Delivery Tracking

-   Semua email transaksional dikirim melalui queue agar request web
    tidak menunggu provider.
-   OTP harus mempunyai expiry sendiri; keterlambatan queue tidak boleh
    membuat kode lama tetap valid.
-   Untuk OTP, gunakan queue prioritas tinggi dan pantau delivery
    latency.
-   Email promosi/bulk harus dipisah dari email transaksional.
-   Catat provider message ID, status, retry, bounce, complaint, dan
    error code.
-   Jangan retry tanpa batas.
-   Jika provider gagal, tampilkan opsi resend setelah cooldown atau
    fallback ke kanal yang telah diverifikasi.
-   Jangan menganggap email terkirim hanya karena request SMTP diterima;
    delivery status mengikuti event provider bila tersedia.

------------------------------------------------------------------------


---

## Integrasi Spesifikasi Admin Panel pada Bab 45

### Spesifikasi Admin Panel Terintegrasi — Communication Management
#### Email Management

-   SMTP/API provider.
-   Sender name.
-   Sender email.
-   Email verification.
-   Password reset.
-   Email OTP.
-   Invoice.
-   Payment notification.
-   Subscription notification.
-   Withdrawal notification.
-   Affiliate notification.
-   AI job notification.

#### OTP Management

Admin dapat mengatur:

-   OTP 6 digit.
-   Masa berlaku 5 menit.
-   Maksimal 5 percobaan.
-   Cooldown pengiriman 60 detik.
-   Batas pengiriman per akun/destinasi per jam.
-   Rate limit berdasarkan IP dan perangkat.
-   OTP hanya dapat digunakan satu kali.

Angka tersebut menjadi kebijakan awal yang dapat dikonfigurasi.

#### WhatsApp Notification

Admin dapat mengelola:

-   WABA.
-   Phone Number ID.
-   Token.
-   Webhook.
-   Message Templates.
-   Delivery Status.
-   Biaya pengiriman.

Penggunaan WhatsApp mengikuti persetujuan pengguna, aturan template, dan
ketentuan biaya Meta/BSP.

# 46. WhatsApp Business Platform Integration

## 46.1 Pilihan Integrasi

Krafio menggunakan salah satu: - Meta WhatsApp Business Platform Cloud
API secara langsung; atau - Business Solution Provider (BSP) resmi yang
menyediakan akses WhatsApp Business Platform.

Jangan menggunakan library tidak resmi yang mengotomasi WhatsApp Web
untuk layanan production. Risiko pemblokiran nomor, sesi putus,
perubahan protokol, dan pelanggaran ketentuan terlalu besar.

## 46.2 Kebutuhan Akun dan Aktivasi

Sebelum production: - Meta Business Portfolio. - WhatsApp Business
Account (WABA). - Nomor telepon yang memenuhi persyaratan. - Business
verification bila diwajibkan. - Meta Developer App. - WhatsApp
product/API configuration. - Access token dengan permission yang
sesuai. - Webhook URL HTTPS dan verification. - Message templates yang
telah disetujui untuk jenis pesan yang memerlukannya. - Kebijakan opt-in
dan bukti consent. - Pengelolaan token, nomor, dan webhook secara aman.

## 46.3 Kegunaan WhatsApp di Krafio

-   OTP/Authentication template jika tersedia dan disetujui.
-   Alert keamanan.
-   Notifikasi pembayaran dan subscription.
-   Notifikasi job selesai/gagal.
-   Notifikasi withdrawal dan affiliate.
-   Support conversation bila scope/API mengizinkan.
-   Notifikasi integrasi sosial.
-   Pesan operasional yang sesuai kebijakan WhatsApp.

Pesan yang dimulai oleh bisnis harus mengikuti kategori/template dan
aturan messaging window yang berlaku. Jangan mengirim pesan bebas di
luar aturan platform.

## 46.4 Arsitektur WhatsApp Adapter

Frontend → Laravel API → Notification Service → WhatsApp Adapter → Meta
Cloud API/BSP.

Webhook: Meta/BSP → Webhook Gateway → signature/verification → Queue →
Delivery Status Processor → Database → User Panel.

Status minimal: - queued - submitted - sent - delivered - read (jika
disediakan) - failed - rejected - expired/unknown

Simpan provider message ID dan error code. Webhook harus idempotent
karena event dapat dikirim ulang.

## 46.5 Multi-tenant dan Nomor Pengirim

Jika Krafio hanya mengirim notifikasi platform, gunakan nomor bisnis
milik Krafio.

Jika kelak user ingin menghubungkan nomor WhatsApp bisnis mereka
sendiri, itu adalah fitur multi-tenant yang berbeda dan membutuhkan: -
Onboarding/embedded signup sesuai kebijakan Meta. - Relasi
user/workspace ke WABA dan phone number ID. - Token terenkripsi per
tenant. - Permission scoping. - Isolasi webhook dan pesan. - Pemantauan
kualitas nomor/template. - UI disconnect/revoke. - Pengelolaan biaya dan
billing per tenant.

Jangan mencampur nomor WhatsApp platform dengan nomor bisnis pelanggan.

## 46.6 Biaya dan Kuota WhatsApp

WhatsApp bukan layanan unlimited gratis. Biaya dan aturan pengiriman
mengikuti kebijakan Meta/BSP, kategori pesan, negara tujuan, dan
perubahan tarif yang berlaku. Krafio harus: - Menyimpan cost per
message/conversation sesuai data provider. - Memisahkan biaya WhatsApp
dari biaya email. - Membatasi OTP resend dan notifikasi berulang. -
Menampilkan status dan kegagalan. - Menetapkan apakah biaya masuk harga
paket, add-on, atau usage-based billing. - Menyediakan fallback email
untuk OTP jika WhatsApp gagal atau belum tersedia.

------------------------------------------------------------------------

# 47. Notification Center Terpadu

## 47.1 Kanal

Notification Service menyediakan adapter untuk: - In-app notification. -
Email. - WhatsApp. - Web push notification/PWA jika diaktifkan. - SMS
hanya jika ada kebutuhan dan provider yang disetujui.

## 47.2 Preference Center

User dapat mengatur: - Kanal yang diizinkan. - Notifikasi transaksi
wajib. - Notifikasi keamanan. - Notifikasi produk. - Notifikasi
marketing. - Quiet hours untuk pesan non-kritis. - Bahasa notifikasi.

Notifikasi keamanan/transaksi penting tidak boleh dimatikan jika ada
kewajiban hukum atau keamanan; UI harus menjelaskan kategori wajib.

## 47.3 Event dan Template

Setiap event memiliki: - Event key. - Kategori. - Severity. - Template
per kanal. - Bahasa. - Variabel. - Kebijakan retry. - Kebijakan
fallback. - Status aktif.

Template pesan dapat dikelola admin dengan preview dan test send.
Perubahan template WhatsApp yang memerlukan approval harus melewati
proses provider sebelum digunakan.

------------------------------------------------------------------------


---

## Integrasi Spesifikasi Admin Panel pada Bab 47

### Spesifikasi Admin Panel Terintegrasi — Notification Center & Announcement
Admin dapat mengirim pengumuman kepada pengguna, contohnya:

-   Maintenance.
-   Fitur baru.
-   Promo.
-   Perubahan harga.
-   Perubahan kebijakan.
-   Gangguan provider AI.
-   Informasi subscription.
-   Informasi keamanan.

Target pengiriman dapat diatur:

-   Semua pengguna.
-   Pengguna Free.
-   Pengguna Pro.
-   Pengguna Business.
-   Pengguna tertentu.
-   Pengguna dengan status tertentu.

Kanal pengiriman: - In-app notification. - Email. - WhatsApp jika
pengguna menyetujui dan pengiriman diizinkan.

# 48. Environment Variables dan Secret Management

Contoh daftar variabel konfigurasi (nilai rahasia tidak boleh dimasukkan
ke dokumentasi publik):

Frontend: - `VITE_API_BASE_URL` - `VITE_TURNSTILE_SITE_KEY`

Backend: - `APP_ENV` - `APP_URL` - `FRONTEND_URL` - `DB_CONNECTION` -
`DB_HOST` - `DB_PORT` - `DB_DATABASE` - `DB_USERNAME` - `DB_PASSWORD` -
`REDIS_HOST` - `REDIS_PORT` - `REDIS_PASSWORD` - `QUEUE_CONNECTION` -
`CACHE_STORE` - `SESSION_DRIVER` - `MAIL_MAILER` - `MAIL_HOST` -
`MAIL_PORT` - `MAIL_USERNAME` - `MAIL_PASSWORD` - `MAIL_ENCRYPTION` -
`MAIL_FROM_ADDRESS` - `MAIL_FROM_NAME` - `TURNSTILE_SECRET_KEY` -
`WHATSAPP_PROVIDER` - `WHATSAPP_ACCESS_TOKEN` -
`WHATSAPP_PHONE_NUMBER_ID` - `WHATSAPP_BUSINESS_ACCOUNT_ID` -
`WHATSAPP_WEBHOOK_VERIFY_TOKEN` - `WHATSAPP_APP_SECRET` -
`PAYMENT_PROVIDER` - `PAYMENT_SECRET_REFERENCE` -
`AI_PROVIDER_SECRET_REFERENCE` - `OBJECT_STORAGE_ENDPOINT` -
`OBJECT_STORAGE_BUCKET` - `OBJECT_STORAGE_ACCESS_KEY` -
`OBJECT_STORAGE_SECRET_KEY`

Nilai aktual disimpan di secret manager atau environment file production
dengan permission ketat. `.env` tidak boleh masuk Git. Secret rotation
harus memiliki prosedur dan audit trail. Frontend hanya menerima public
key yang memang aman dipublikasikan.

------------------------------------------------------------------------

# 49. Deployment Services dan Process Groups

Process group production yang harus dikelola melalui
Supervisor/systemd/container orchestration:

1.  Nginx.
2.  PHP-FPM.
3.  Laravel scheduler (satu scheduler aktif per environment atau
    mekanisme distributed lock).
4.  Laravel Horizon/queue workers.
5.  Queue khusus `critical`.
6.  Queue `notifications`.
7.  Queue `webhooks`.
8.  Queue `ai-text`.
9.  Queue `ai-image`.
10. Queue `video-render`.
11. Queue `seo-crawl`.
12. Queue `social-publish`.
13. Queue `exports`.
14. Reverb/WebSocket jika dipakai.
15. Redis/Valkey.
16. MySQL.
17. Monitoring/logging agents.

Worker queue berat harus dipisahkan agar video render tidak menghambat
OTP, payment webhook, atau email transaksi. Gunakan concurrency limit
per queue, provider, user, dan paket.

------------------------------------------------------------------------

# 50. Stack Acceptance Criteria

Stack dianggap diterapkan dengan benar apabila: - React + TypeScript +
Tailwind berhasil build dan terhubung ke Laravel API. - Laravel API
memakai Sanctum untuk SPA authentication dengan cookie/CSRF yang
benar. - MySQL InnoDB menjadi sumber data transaksi utama. - Redis
self-hosted dipakai untuk cache dan queue production. - Queue worker
tetap berjalan setelah proses/server restart. - Horizon/monitoring
menunjukkan queue depth, throughput, dan failed jobs. - Cloudflare
berada di edge dan tidak dianggap sebagai pengganti Redis. - Turnstile
divalidasi di backend. - Email domain lolos SPF/DKIM/DMARC checks. -
Email verification dan OTP mempunyai expiry, attempt limit, cooldown,
dan single-use. - WhatsApp adapter menggunakan Cloud API/BSP resmi. -
Webhook WhatsApp dan payment idempotent. - OTP tidak tersimpan
plaintext. - Semua credential tidak masuk frontend atau repository. -
Email/WhatsApp delivery dapat dipantau. - Notifikasi prioritas tinggi
tidak tertahan oleh antrean render video. - Backup dan restore MySQL
telah diuji.

------------------------------------------------------------------------

# 51. Keputusan Teknologi Final

Untuk baseline Krafio, keputusan yang direkomendasikan dan dijadikan
default adalah:

**Frontend:** React + TypeScript + Tailwind CSS + Vite.\
**Backend:** Laravel + PHP.\
**Database:** MySQL 8.4 LTS + InnoDB.\
**Cache dan queue:** Redis/Valkey self-hosted, dengan Laravel Horizon.\
**Authentication:** Laravel Sanctum untuk SPA.\
**Email:** Transactional email provider melalui SMTP/API, dengan SPF,
DKIM, dan DMARC.\
**WhatsApp:** WhatsApp Business Platform Cloud API atau BSP resmi.\
**Edge/security:** Cloudflare DNS, proxy, WAF/rate limit sesuai plan,
dan Turnstile.\
**Storage:** Object storage S3-compatible untuk aset/media; NVMe lokal
untuk temporary processing/cache yang sesuai.\
**Deployment:** Nginx, PHP-FPM, Supervisor/systemd atau Docker Compose,
CI/CD, monitoring, backup.

MySQL dipilih sebagai database utama. Redis dijalankan sendiri di server
sehingga Krafio tidak bergantung pada kuota gratis Redis Cloud.
Cloudflare tetap digunakan, tetapi fungsinya berbeda dan tidak
menggantikan Redis.

**Catatan:** pilihan versi software final harus disesuaikan dengan versi
Laravel/PHP yang dipilih, compatibility matrix dependency, dan dukungan
keamanan pada saat implementasi.


------------------------------------------------------------------------

# 52. GitHub Repository, Source Code Management & CI/CD

Bab ini menetapkan spesifikasi pengelolaan repository utama Krafio di GitHub, pengelolaan source code, version control, GitHub Actions, CI/CD, integrasi deployment ke VPS, keamanan akses, backup dan recovery, serta dokumentasi developer.

Bab ini **menambah** isi Master PRD v2.2 dan tidak menggantikannya. Requirement yang sudah ada tetap berlaku dan dirujuk, bukan diduplikasi. Bab 53 membahas repository eksternal (25 kandidat), sedangkan Bab 54 memuat checklist GitHub 90 poin, matriks keterlacakan, ketentuan teknis wajib, dan kriteria penerimaan pembaruan dokumen.

**Tiga kategori yang tidak boleh dicampur:**

| Kategori | Isi | Lokasi dalam PRD |
|---|---|---|
| A. Repository utama Krafio | Satu repository sumber kebenaran seluruh source code Krafio | Bab 52 |
| B. Repository eksternal | 25 proyek pihak ketiga yang berstatus kandidat untuk digunakan, dipelajari, diadaptasi, atau diintegrasikan | Bab 12, Bab 37, Bab 53 |
| C. Checklist GitHub | 90 persyaratan pengelolaan repository utama. **Bukan 90 repository tambahan.** | Bab 54 |

**Hubungan dengan requirement yang sudah ada (anti-duplikasi):**

| Topik | Requirement yang sudah ada | Penambahan di Bab 52 |
|---|---|---|
| Urutan pipeline CI/CD | Bab 34.4 (13 langkah) | Detail workflow, trigger, gate, dan konfigurasi GitHub Actions (52.27–52.36) |
| Backup dan DR | Bab 15.3, Bab 34.6 | Prosedur per jenis backup, skenario recovery, dan uji restore (52.55–52.60) |
| Keamanan aplikasi dan secret | Bab 35.1, Bab 48 | Keamanan repository, akses GitHub, secret CI/CD, deploy key, PAT (52.37–52.42) |
| Gate rilis | Bab 38.3 | Penerapan gate rilis sebagai required check dan approval gate (52.26, 52.34) |
| Stack dan CI/CD wajib | Bab 43.1 | Penetapan GitHub sebagai platform; pipeline dibuat dengan GitHub Actions |
| Process group dan queue | Bab 49 | Prosedur deploy worker dan scheduler (52.50, 52.51) |
| Deliverables | Bab 39 butir 1, 18, 19, 23, 26, 27 | Rincian bentuk deliverable pada repository |
| Repository eksternal | Bab 12, Bab 37 | Register, audit, dan metode integrasi pada Bab 53 |

**Konvensi label** pada bab ini: **Wajib** berarti harus dipenuhi sebelum production; **Direkomendasikan** berarti praktik baku yang dapat disimpangi dengan alasan tertulis; **Keputusan tertunda** berarti belum ditetapkan pemilik produk dan ditandai `[BELUM DITETAPKAN]` atau `[MENUNGGU KEPUTUSAN]`. Dokumen ini tidak mengarang nilai untuk keputusan tertunda.

------------------------------------------------------------------------

## 52.1 Tujuan Penggunaan GitHub

GitHub digunakan sebagai platform tunggal untuk:

1.  **Source of truth** seluruh source code Krafio: frontend, backend, worker, konfigurasi non-secret, migrasi database, dan dokumentasi.
2.  **Kolaborasi terkontrol** melalui branch, pull request, code review, dan CODEOWNERS.
3.  **Otomasi** pengujian, analisis kualitas, pemindaian keamanan, build, dan deployment melalui GitHub Actions.
4.  **Jejak audit** perubahan: siapa mengubah apa, kapan, disetujui oleh siapa, dan rilis mana yang memuatnya.
5.  **Manajemen rilis** melalui tag, release notes, dan changelog.
6.  **Dokumentasi developer** yang berversi bersama kode.

**Batasan peran GitHub:**

-   GitHub **bukan** tempat menyimpan secret produksi, data pelanggan, atau backup database.
-   GitHub **bukan** satu-satunya salinan source code; mirror cadangan wajib tersedia (52.55).
-   Ketersediaan GitHub tidak boleh menjadi single point of failure untuk pemulihan darurat (52.60).

------------------------------------------------------------------------

## 52.2 Identitas Repository Utama

Identitas repository utama **belum ditetapkan**. Nilai berikut adalah placeholder resmi dan tidak boleh diganti dengan nilai karangan.

| Atribut | Nilai |
|---|---|
| Nama Repository | [BELUM DITETAPKAN] |
| GitHub URL | [BELUM DITETAPKAN] |
| Owner / Organization | [BELUM DITETAPKAN] |
| Visibility | [BELUM DITETAPKAN] |
| License | [MENUNGGU KEPUTUSAN] |
| Deskripsi repository | [BELUM DITETAPKAN] |

**Ketentuan penetapan:**

-   Nama repository mengikuti keputusan nama dan domain final Krafio (Bab 41 butir 1). Selama nama produk masih sementara, nama repository tidak boleh dikunci.
-   Deskripsi singkat (maksimal satu kalimat) harus menjelaskan fungsi repository tanpa membocorkan detail infrastruktur.
-   Setelah ditetapkan, nilai di atas diperbarui di tabel ini, `README.md`, dan Bab 54.7 (register keputusan).

**Berkas standar yang wajib ada di root repository:**

| Berkas | Fungsi | Checklist |
|---|---|---|
| `README.md` | Ringkasan proyek, cara menjalankan, tautan dokumentasi | 7 |
| `CONTRIBUTING.md` | Alur kontribusi, branch, commit, PR, review | 8 |
| `CHANGELOG.md` | Riwayat perubahan per rilis | 9 |
| `CODE_OF_CONDUCT.md` | Aturan perilaku kontributor | 10 |
| `LICENSE` | Lisensi repository (menunggu keputusan) | 6 |
| `SECURITY.md` | Kebijakan pelaporan kerentanan (tambahan, direkomendasikan) | 53, 55 |
| `.github/CODEOWNERS` | Pemilik kode per path | 19 |
| `.github/PULL_REQUEST_TEMPLATE.md` | Template PR | 18 |
| `.github/dependabot.yml` | Pembaruan dependency otomatis | 39 |
| `.env.example` | Contoh variabel tanpa nilai rahasia | 50 |

**Lisensi:** pemilihan lisensi harus mempertimbangkan model bisnis SaaS komersial dan kewajiban lisensi dari dependency pihak ketiga (Bab 53.9). Repository privat tanpa berkas lisensi berarti seluruh hak dicadangkan; kondisi ini sah, tetapi keputusannya tetap harus dicatat.

------------------------------------------------------------------------

## 52.3 Repository Visibility

Visibility belum ditetapkan. Dasar pertimbangan:

| Aspek | Private | Public |
|---|---|---|
| Kerahasiaan logika bisnis (billing, entitlement, anti-abuse) | Terjaga | Terbuka bagi siapa pun, termasuk pelaku abuse |
| Risiko kebocoran secret | Lebih rendah, tetap ada | Tinggi; secret yang pernah ter-commit harus dianggap bocor |
| Kewajiban lisensi dependency | Tetap berlaku (distribusi dan network copyleft perlu diperiksa) | Sama, dengan eksposur lebih besar |
| Kontribusi pihak luar | Terbatas pada anggota | Mungkin, dengan beban moderasi |
| Ketersediaan fitur keamanan GitHub | Sebagian fitur bergantung pada plan; verifikasi ketersediaan sebelum mengandalkan | Umumnya tersedia lebih luas |

**Rekomendasi sementara (bukan keputusan final):** mulai dengan **Private** sampai audit lisensi, pembersihan secret, dan penetapan lisensi selesai. Mengubah repository privat menjadi publik di kemudian hari harus melalui checklist: scan riwayat Git untuk secret, tinjau seluruh dokumen internal di `docs/`, dan persetujuan tertulis pemilik produk.

Bila diputuskan **Public**, syarat tambahan wajib: secret scanning dan push protection aktif, `SECURITY.md` tersedia, tidak ada dokumen internal (runbook, topologi server, data vendor) di repository, dan lisensi telah dipilih secara sadar.

------------------------------------------------------------------------

## 52.4 Repository Ownership

-   Repository utama **direkomendasikan dimiliki GitHub Organization**, bukan akun personal, agar kepemilikan tidak bergantung pada satu individu.
-   Minimal **dua Owner** organisasi aktif untuk menghindari akun tunggal sebagai titik kegagalan.
-   Seluruh Owner dan Admin wajib mengaktifkan autentikasi dua faktor; organisasi dikonfigurasi untuk mewajibkan 2FA bagi semua anggota.
-   Kepemilikan organisasi harus selaras dengan entitas hukum atau merchant of record (Bab 41 butir 2).
-   Akun otomasi memakai **GitHub App** atau `GITHUB_TOKEN`, bukan akun personal anggota tim (52.41).
-   Prosedur transfer kepemilikan, pemulihan akun Owner, dan penyimpanan recovery code didokumentasikan dan disimpan di luar GitHub.
-   Perubahan Owner dicatat pada audit log (52.42).

------------------------------------------------------------------------

## 52.5 Source Code Structure

Repository utama menyimpan seluruh source code Krafio sebagai **monorepo**. Struktur berikut adalah rekomendasi dan difinalkan pada tahap architecture design. Struktur ini konsisten dengan stack Bab 43 (React + TypeScript + Tailwind + Vite di frontend, Laravel di backend) dan pemisahan panel pada Bab 43.2.

```text
[nama-repository]/                  # [BELUM DITETAPKAN]
├── .github/
│   ├── workflows/                  # CI, security, build, deploy, rollback, backup-verify
│   ├── ISSUE_TEMPLATE/
│   ├── CODEOWNERS
│   ├── PULL_REQUEST_TEMPLATE.md
│   └── dependabot.yml
├── frontend/                       # React + TypeScript + Tailwind + Vite
│   ├── src/
│   │   ├── public-site/            # Website utama / frontpage
│   │   ├── user-panel/             # User Panel
│   │   ├── admin-panel/            # Admin Panel
│   │   ├── shared/                 # Komponen, hook, design system, util bersama
│   │   └── api-client/             # Klien API tipe-aman dari OpenAPI
│   └── tests/                      # Unit, komponen, E2E
├── backend/                        # Laravel
│   ├── app/
│   │   ├── Http/                   # Controller tipis, Form Request, Resource
│   │   ├── Actions/ Services/      # Business logic
│   │   ├── Policies/               # Otorisasi
│   │   ├── Jobs/ Listeners/        # Pekerjaan asynchronous dan event
│   │   ├── Integrations/           # Adapter eksternal
│   │   │   ├── Ai/                 # AI Gateway dan provider adapters
│   │   │   ├── Social/             # Meta, TikTok, Threads, dll.
│   │   │   ├── Payment/            # Payment Gateway Adapter
│   │   │   └── Messaging/          # WhatsApp, email
│   │   └── Modules/
│   │       ├── WebsiteBuilder/
│   │       └── Seo/
│   ├── database/
│   │   ├── migrations/
│   │   ├── seeders/
│   │   └── factories/
│   ├── routes/  config/  tests/
├── workers/                        # Worker non-Laravel (mis. render Node), terisolasi
├── infra/                          # Nginx, Supervisor/systemd, Docker, skrip deploy dan backup (tanpa secret)
├── docs/                           # Dokumentasi developer, runbook, OpenAPI, ADR
├── scripts/
└── README.md  CONTRIBUTING.md  CHANGELOG.md  CODE_OF_CONDUCT.md  SECURITY.md  LICENSE
```

**Pemetaan komponen ke checklist:**

| Komponen | Lokasi (rekomendasi) | Subbab | Checklist |
|---|---|---|---|
| Frontend | `frontend/` | 52.6 | 21 |
| Backend | `backend/` | 52.7 | 22 |
| Admin Panel | `frontend/src/admin-panel/` + `backend/app/Http/**/Admin` | 52.8 | 23 |
| User Panel | `frontend/src/user-panel/` + `backend/app/Http/**/User` | 52.9 | 24 |
| API | `backend/routes/`, `docs/openapi/` | 52.10 | 25 |
| Database migrations | `backend/database/migrations/` | 52.11 | 26 |
| Database seeders | `backend/database/seeders/` | 52.12 | 27 |
| Background workers | `backend/app/Jobs/`, `workers/`, `infra/` | 52.13 | 28 |
| AI integration | `backend/app/Integrations/Ai/` | 52.14 | 29 |
| Social integration | `backend/app/Integrations/Social/` | 52.15 | 30 |
| Payment integration | `backend/app/Integrations/Payment/` | 52.16 | 31 |
| Website Builder | `backend/app/Modules/WebsiteBuilder/` + frontend editor | 52.17 | 32 |
| SEO modules | `backend/app/Modules/Seo/` | 52.18 | 33 |
| Shared components | `frontend/src/shared/` + `backend/app/Support/` | 52.19 | 34 |
| Configuration | `backend/config/`, `.env.example`, `infra/` | 52.20 | 35 |

**Aturan struktur:**

-   Satu direktori mempunyai satu tanggung jawab dan satu pemilik di CODEOWNERS.
-   Kode `admin-panel` tidak boleh diimpor oleh `public-site`, dan sebaliknya; komunikasi lewat `shared/`.
-   Kode pihak ketiga yang di-vendor tidak boleh ditempatkan bercampur dengan kode Krafio; gunakan direktori khusus dengan berkas `THIRD_PARTY` yang mencatat sumber, versi, dan lisensi (Bab 53.10).
-   Artefak build, `node_modules`, `vendor`, berkas `.env`, dan data runtime tidak boleh ter-commit.

------------------------------------------------------------------------

## 52.6 Frontend Repository Management

-   Stack: React + TypeScript + Tailwind CSS + Vite (Bab 43.1–43.2).
-   Versi Node.js dikunci melalui `.nvmrc` atau `engines`; lockfile (`package-lock.json` atau `pnpm-lock.yaml`) wajib di-commit dan satu package manager saja yang dipakai.
-   TypeScript dalam mode ketat; `tsc --noEmit` menjadi required check.
-   ESLint dan Prettier konsisten dengan konfigurasi di repository; tidak ada konfigurasi lokal yang menyimpang.
-   Klien API dibangkitkan dari spesifikasi OpenAPI (52.10) sehingga kontrak tipe tidak ditulis ulang manual.
-   Variabel `VITE_*` hanya berisi nilai publik (Bab 48). Pemeriksaan CI menolak commit yang memasukkan pola secret ke berkas frontend.
-   Tidak ada token sensitif di `localStorage` bila memakai Sanctum berbasis cookie (Bab 43.2).
-   Test: unit/komponen (Testing Library), E2E (Playwright); visual regression untuk template dan Website Builder (Bab 38.1).
-   Hasil build (`dist/`) tidak di-commit; dibuat oleh pipeline (52.32).

------------------------------------------------------------------------

## 52.7 Backend Repository Management

-   Stack: Laravel + PHP (Bab 43.3). Versi PHP dan Laravel dipilih sesuai dukungan keamanan saat implementasi dan dikunci pada `composer.json` serta `composer.lock`.
-   `composer.lock` wajib di-commit; instalasi di CI memakai `composer install` (bukan `update`).
-   Controller hanya menerima request, memanggil Action/Service, dan mengembalikan Resource (Bab 43.3). Business logic tidak boleh berada di Controller.
-   Standar: Form Request, Policies/Gates, API Resources, Jobs, Events/Listeners.
-   Fungsi `env()` hanya dipanggil dari berkas `config/` agar `config:cache` berfungsi.
-   Dependency baru harus melewati dependency review (52.31) dan, bila lisensinya copyleft atau khusus, review hukum (Bab 53.9).
-   Test: unit, feature, API contract, queue/worker, dan migration test (Bab 38.1).
-   Seluruh operasi finansial memakai transaksi database dan `DECIMAL` untuk nilai uang (Bab 43.4).

------------------------------------------------------------------------

## 52.8 Admin Panel Source Code

-   Admin Panel (Bab 13) berada pada modul terpisah di frontend dan namespace terpisah di backend, sehingga perubahan dapat diberi pemilik dan aturan review tersendiri.
-   Route Admin di-code-split agar bundle Admin tidak dimuat oleh pengunjung publik. Pemisahan bundle **bukan** kontrol keamanan; otorisasi tetap diperiksa di Laravel (Bab 43.2, Bab 10.3).
-   Perubahan pada path Admin Panel yang memengaruhi persetujuan, keuangan, atau pengaturan sistem memerlukan review sensitif (52.23).
-   Setiap aksi Admin penting harus menulis audit log aplikasi (Bab 36, Audit Logs).
-   **Klarifikasi istilah:** "Admin permissions" pada checklist GitHub (butir 48) adalah hak akses **GitHub** untuk mengelola repository. Itu berbeda dari akun **Super Admin Krafio** pada aplikasi (Bab 1.4, Bab 3.2). Keduanya dikelola terpisah dan tidak boleh disamakan.

------------------------------------------------------------------------

## 52.9 User Panel Source Code

-   User Panel (Bab 2.1 bagian B, Bab 25) berada pada modul `user-panel` dan namespace backend `User`.
-   Isolasi tenant: seluruh query dan endpoint User Panel memakai tenant scoping dan Policy (Bab 35.2). Test lintas-tenant (Bab 38.2 butir 1) wajib berada dalam suite CI.
-   Fitur yang bergantung entitlement tidak boleh hanya disembunyikan di UI; pemeriksaan entitlement wajib di backend (Bab 38.2 butir 2).
-   State penting (loading, validasi, sukses, error, retry) mengikuti Bab 43.2.
-   Komponen yang dipakai bersama User Panel dan Admin Panel dipindahkan ke `shared/` (52.19).

------------------------------------------------------------------------

## 52.10 API Source Code

-   API mengikuti REST + JSON (Bab 43.1, Bab 33). Spesifikasi **OpenAPI/Swagger** adalah artefak di repository (`docs/openapi/`) dan harus sinkron dengan implementasi.
-   Versi API pada path (`/api/v1/...`). Perubahan yang merusak kompatibilitas memerlukan versi baru atau masa transisi terdokumentasi.
-   CI menjalankan **contract test** dan memeriksa bahwa spesifikasi OpenAPI valid serta tidak menghapus endpoint tanpa persetujuan eksplisit.
-   Setiap endpoint mempunyai: validasi input, otorisasi, rate limit, idempotency key untuk operasi finansial dan webhook (Bab 33.1, Bab 38.2 butir 4), serta format response standar (Bab 33.3).
-   Endpoint baru wajib mempunyai test otorisasi (akses ditolak untuk user lain dan user tanpa entitlement).
-   Dokumentasi API dipublikasikan dari spesifikasi (52.65).

------------------------------------------------------------------------

## 52.11 Database Migration Management

-   Seluruh perubahan skema dilakukan melalui migration Laravel di `backend/database/migrations/`. Perubahan skema manual di server dilarang.
-   Migration yang sudah digabung ke `main` atau sudah dirilis **tidak boleh diedit**; koreksi dibuat sebagai migration baru.
-   Pola aman **expand/contract**: (1) tambahkan kolom/tabel baru yang kompatibel dengan kode lama, (2) deploy kode yang memakai skema baru, (3) hapus skema lama pada rilis berikutnya. Perubahan destruktif (drop, rename, ubah tipe) tidak boleh berlangsung dalam satu rilis yang sama dengan kode yang bergantung padanya.
-   Migration harus backward-compatible bila deployment memerlukan rolling release (Bab 34.4).
-   Nilai uang menggunakan `DECIMAL`; foreign key dan unique constraint untuk reference dan idempotency key (Bab 43.4).
-   Metode `down()` disediakan bila rollback aman; bila tidak aman, ditandai jelas dan strategi pemulihan tertulis pada deskripsi PR.
-   CI menjalankan migration pada MySQL 8.4 kosong dan pada snapshot skema sebelumnya (Bab 38.1: database migration test).
-   Migration berisiko (tabel besar, lock panjang, ubah tipe kolom) wajib melewati rehearsal di staging dan backup sebelum production (52.48).
-   Migration tidak boleh memuat data demo atau credential.

------------------------------------------------------------------------

## 52.12 Database Seeder Management

Seeder dibagi menjadi dua kelas yang tidak boleh tertukar:

| Kelas | Isi | Lingkungan | Syarat |
|---|---|---|---|
| Reference seeder | Katalog fitur, paket awal, template data referensi, pengaturan default | Semua termasuk production | Idempotent (aman dijalankan ulang), tidak menimpa data yang telah diubah Admin |
| Demo/development seeder | Data contoh, akun uji, dataset besar untuk performa | Local dan staging saja | Dijaga guard `APP_ENV`; gagal bila dijalankan di production |

-   Tidak ada kata sandi, token, atau data pribadi nyata di seeder.
-   Pembuatan akun Super Admin pertama dilakukan melalui perintah terkontrol (kata sandi dimasukkan saat eksekusi, MFA diwajibkan; Bab 35.1, 35.3), **bukan** melalui seeder dengan kredensial statis.
-   Nominal paket, harga, dan kuota pada seeder bersifat placeholder sampai keputusan Bab 41 butir 5 ditetapkan.

------------------------------------------------------------------------

## 52.13 Background Worker Management

-   Worker adalah bagian dari source code dan dikelola melalui repository: definisi queue, konfigurasi Horizon, unit Supervisor/systemd di `infra/`.
-   Nama queue dan process group mengikuti Bab 49 (`critical`, `notifications`, `webhooks`, `ai-text`, `ai-image`, `video-render`, `seo-crawl`, `social-publish`, `exports`).
-   Setiap Job harus idempotent, mempunyai timeout, jumlah retry, dan backoff eksplisit. Payload berisi ID dan metadata minimum, bukan media (Bab 43.5).
-   Perubahan struktur payload Job harus kompatibel mundur karena Job lama dapat masih berada di antrean saat deploy (52.50).
-   Worker non-PHP (mis. render Node untuk Remotion) ditempatkan di `workers/` dengan Dockerfile, batas resource, dan versi dependency terkunci. Worker yang menjalankan kode pihak ketiga mengikuti isolasi Bab 53.8.
-   Worker berat dipisahkan dari queue prioritas tinggi agar render video tidak menghambat OTP, webhook pembayaran, atau email transaksi (Bab 49).
-   Pengujian: queue/worker test (Bab 38.1) dan skenario job gagal mengembalikan reservation kuota (Bab 38.2 butir 10).

------------------------------------------------------------------------

## 52.14 AI Integration Modules

-   Seluruh akses AI melalui **AI Gateway** (Bab 7, Bab 26.1). Kode provider berada di `backend/app/Integrations/Ai/` sebagai adapter dengan kontrak yang seragam.
-   Kredensial provider tidak boleh ada di repository; hanya referensi secret (`AI_PROVIDER_SECRET_REFERENCE`, Bab 48).
-   Adapter wajib mempunyai timeout, retry terbatas, circuit breaker/fallback (Bab 7), pencatatan token dan biaya, serta redaksi data sensitif pada log.
-   Test memakai provider tiruan (mock) atau sandbox; test CI tidak boleh memanggil provider berbayar dengan kredensial produksi.
-   Integrasi repository eksternal terkait AI (ComfyUI, Jan, FREELLMAPI, Prompt Optimizer, dan lainnya) hanya boleh masuk sebagai adapter di balik AI Gateway setelah lolos Integration Gate (Bab 37.4, Bab 53.10).
-   Prompt library dan konfigurasi agent berversi dan memiliki mekanisme rollback (Bab 26.5).

------------------------------------------------------------------------

## 52.15 Social Media Integration Modules

-   Berada di `backend/app/Integrations/Social/` dengan satu adapter per platform (Meta/Facebook/Instagram, TikTok, Threads, WhatsApp melalui Bab 46).
-   Hanya API resmi dan izin yang disetujui platform (Bab 1.4, Bab 30.1). Tidak ada otomasi antarmuka yang melanggar kebijakan platform.
-   Token OAuth disimpan terenkripsi di database, tidak pernah di repository, log, atau response frontend (Bab 19: Token sosial bocor).
-   Webhook idempotent dan diverifikasi tanda tangan (Bab 38.2 butir 4).
-   Token kedaluwarsa menghasilkan status reconnect, bukan publikasi palsu (Bab 38.2 butir 12).
-   Test integrasi memakai sandbox atau fixture terekam; aplikasi uji platform dipisahkan dari aplikasi produksi.

------------------------------------------------------------------------

## 52.16 Payment Integration Modules

Modul ini paling sensitif dan mendapat aturan paling ketat:

-   Berada di `backend/app/Integrations/Payment/` melalui **Payment Gateway Adapter** (Bab 31.4). Nama provider belum ditetapkan (Bab 1.4, Bab 41 butir 3).
-   Perubahan pada path pembayaran, ledger, wallet, dan withdrawal wajib **dua approval** termasuk satu Security/Finance reviewer (52.23).
-   Tidak ada data kartu di sistem Krafio; hanya referensi transaksi dari gateway.
-   Webhook pembayaran wajib idempotent dan diverifikasi tanda tangan; top-up tidak bertambah sebelum payment final (Bab 38.2 butir 4–5).
-   Test wajib: ledger balance, refund reversal, pemisahan saldo top-up dan komisi, withdrawal paralel (Bab 38.2 butir 6–9). Kegagalan test ini memblokir merge dan rilis (Bab 38.3).
-   Kunci sandbox dipakai di CI/staging; kunci produksi hanya di production server atau secret manager (Bab 48).

------------------------------------------------------------------------

## 52.17 Website Builder Modules

-   Backend di `backend/app/Modules/WebsiteBuilder/`, editor di frontend, definisi template di data berversi (Bab 28, Bab 27.6).
-   Template dan aset harus punya catatan lisensi (Bab 41 butir 16); aset tanpa lisensi jelas tidak boleh di-commit.
-   Output situs pelanggan dan editor menerapkan sanitasi untuk mencegah XSS dan injeksi (Bab 35.1).
-   Visual regression test untuk template (Bab 38.1).
-   Perubahan aturan atau template tidak langsung memengaruhi seluruh pelanggan; gunakan versi dan rollout bertahap (Bab 29 prinsip rule baru, Bab 27).
-   Keputusan hosting hasil builder (Bab 41 butir 17) memengaruhi jalur deployment; sampai ditetapkan, modul tidak boleh mengasumsikan hosting tertentu.

------------------------------------------------------------------------

## 52.18 SEO Modules

-   Berada di `backend/app/Modules/Seo/` dengan **Rules Engine** Krafio (Bab 29). Definisi rule berversi dan dapat diuji.
-   Crawler mematuhi batas dan **tidak boleh menjadi SSRF proxy** (Bab 38.2 butir 16): blokir alamat internal, batasi redirect, ukuran respons, dan konkurensi.
-   Worker crawl memakai queue `seo-crawl` (Bab 49) dan bila memakai komponen eksternal (mis. kandidat OpenSEO pada Bab 53) dijalankan dengan isolasi.
-   Test: rule unit test, regresi hasil audit pada dataset tetap, dan test SSRF.

------------------------------------------------------------------------

## 52.19 Shared Components

-   **Frontend:** `frontend/src/shared/` memuat design system, komponen, hook, util, dan klien API. Aturan dependensi satu arah: modul panel boleh bergantung pada `shared`, `shared` tidak boleh bergantung pada modul panel.
-   **Backend:** utilitas lintas modul pada `backend/app/Support/` (atau setara) dengan antarmuka stabil; hindari ketergantungan melingkar antar modul.
-   Perubahan komponen bersama memerlukan test yang mencakup semua konsumen dan review dari pemilik `shared`.
-   Design token (warna, tipografi, spacing) terpusat; tidak ada nilai gaya yang diulang manual di banyak tempat.
-   Komponen bersama tidak boleh memuat kredensial, URL internal, atau logika otorisasi yang menggantikan backend.

------------------------------------------------------------------------

## 52.20 Configuration Management

-   Pemisahan tiga jenis konfigurasi: (1) **kode konfigurasi** non-secret di `backend/config/` dan `infra/` (di Git), (2) **variabel lingkungan** per environment, (3) **secret** (di secret manager atau berkas lingkungan production ber-permission ketat; Bab 48).
-   `.env.example` memuat seluruh nama variabel (Bab 48) tanpa nilai rahasia dan wajib diperbarui pada PR yang menambah variabel.
-   `.env` tidak pernah di-commit (Bab 48); `.gitignore` dan pemindaian secret menegakkan aturan ini.
-   Aplikasi memvalidasi variabel wajib saat boot dan gagal cepat bila ada yang hilang atau tidak valid.
-   Feature flag untuk fitur berisiko dan integrasi eksternal dikelola sebagai konfigurasi dan dapat dimatikan Admin bila relevan (Bab 37.4).
-   Setiap environment mempunyai database, credential, storage, dan callback URL terpisah; credential produksi tidak boleh dipakai di lokal atau staging (Bab 34.2).
-   Perubahan konfigurasi non-secret melalui PR; perubahan secret melalui prosedur secret manager dengan audit trail (Bab 48).

------------------------------------------------------------------------

## 52.21 Branch Management

**Model branch** (checklist 11–17, 20):

| Branch | Fungsi | Sumber | Digabung ke | Dilindungi | Deploy otomatis ke |
|---|---|---|---|---|---|
| `main` | Kode production; setiap commit adalah kandidat rilis yang sudah lolos gate | `release/*`, `hotfix/*` | — | Ya (ketat) | Production (hanya melalui tag + approval, 52.34) |
| `develop` | Integrasi pekerjaan harian; harus selalu dapat di-build | `feature/*`, `bugfix/*` | `release/*` | Ya | Staging (otomatis, 52.35) |
| `feature/<id>-<slug>` | Satu fitur atau satu perubahan terpisah | `develop` | `develop` | Tidak | — |
| `bugfix/<id>-<slug>` | Perbaikan bug non-darurat | `develop` | `develop` | Tidak | — |
| `release/<x.y.z>` | Stabilisasi rilis: hanya perbaikan bug, versi, dan dokumentasi | `develop` | `main` lalu back-merge ke `develop` | Ya | Staging (kandidat rilis) |
| `hotfix/<id>-<slug>` | Perbaikan darurat untuk production | tag/`main` | `main` lalu back-merge ke `develop` | Tidak | Staging untuk verifikasi, lalu production melalui approval |

```text
feature/* ─┐
bugfix/*  ─┴─► develop ─► release/x.y.z ─► main ─► tag vX.Y.Z ─► production
                  ▲                          │
hotfix/* ─────────┼──────────────────────────┘ (hotfix keluar dari main)
                  └──────── back-merge dari main/release ────────
```

**Aturan penamaan:** huruf kecil, pemisah `-`, diawali ID issue bila ada (contoh bentuk: `feature/123-subscription-entitlement`). Branch hidup singkat; branch yang sudah digabung dihapus otomatis (riwayat tetap tersimpan di pull request).

**Branch protection / ruleset** (checklist 17, 54):

| Aturan | `main` | `develop` | `release/*` |
|---|---|---|---|
| Wajib pull request (tidak ada push langsung) | Wajib | Wajib | Wajib |
| Jumlah approval minimum | 1; **2** untuk path sensitif (52.23) | 1 | 1 |
| Wajib review Code Owner | Wajib | Direkomendasikan | Wajib |
| Dismiss approval saat ada commit baru | Wajib | Direkomendasikan | Wajib |
| Required status checks (52.27–52.31) | Wajib, branch harus up-to-date | Wajib | Wajib |
| Wajib seluruh percakapan review terselesaikan | Wajib | Wajib | Wajib |
| Larang force push dan penghapusan branch | Wajib | Wajib | Wajib |
| Berlaku juga bagi Admin repository | Wajib | Direkomendasikan | Wajib |
| Commit bertanda tangan (signed) | Direkomendasikan | Opsional | Direkomendasikan |
| Riwayat linear | Direkomendasikan | Direkomendasikan | — |

**Catatan ketersediaan:** ketersediaan branch protection, rulesets, dan beberapa kontrol lain pada GitHub bergantung pada plan dan visibility repository (52.3). Ketersediaan **wajib diverifikasi sebelum** repository dinyatakan siap; bila suatu kontrol tidak tersedia, kompensasinya (mis. required check melalui workflow, CODEOWNERS, atau plan yang sesuai) dicatat di register keputusan (Bab 54.7).

**Strategi merge** (checklist 20):

| Alur | Strategi | Alasan |
|---|---|---|
| `feature/*`, `bugfix/*` → `develop` | **Squash merge** | Satu commit bermakna per perubahan; pesan mengikuti Conventional Commits (52.24) |
| `release/*` → `main` | **Merge commit** (tanpa fast-forward) | Titik rilis tercatat jelas pada riwayat |
| `main` → `develop` (back-merge) | Merge commit | Menjaga `develop` memuat perbaikan rilis dan hotfix |
| `hotfix/*` → `main` | Squash atau merge commit sesuai ukuran | Dicatat pada release notes |

Rebase pada branch pribadi diperbolehkan **sebelum** PR ditinjau; setelah ada review, riwayat tidak ditulis ulang tanpa memberi tahu reviewer.

------------------------------------------------------------------------

## 52.22 Pull Request Workflow

Seluruh perubahan masuk melalui pull request (checklist 18, 63).

**Alur:**

1.  Issue atau tugas dibuat dan dirujuk dalam PR.
2.  Developer membuat branch dari `develop` (atau `main` untuk hotfix) dan membuka PR, dimulai sebagai **Draft** bila belum siap.
3.  PR memakai `.github/PULL_REQUEST_TEMPLATE.md`.
4.  CI berjalan otomatis; PR tidak dapat digabung bila required check gagal.
5.  Reviewer ditetapkan otomatis oleh CODEOWNERS; review dilakukan sesuai 52.23.
6.  Seluruh komentar diselesaikan; approval terkini wajib ada.
7.  PR digabung sesuai strategi 52.21 oleh author atau maintainer (bukan oleh reviewer yang sekaligus author).
8.  Branch dihapus; deployment staging berjalan otomatis untuk `develop`.

**Template PR wajib memuat:** ringkasan perubahan; issue terkait; jenis perubahan (fitur, bugfix, refactor, dokumentasi, infrastruktur); dampak pada database (migration ada/tidak, destruktif/tidak); dampak pada API (breaking/tidak, OpenAPI diperbarui); dampak keamanan dan tenant isolation; dampak pada keuangan; variabel lingkungan baru (`.env.example` diperbarui); bukti pengujian; rencana rollback untuk perubahan berisiko; centang pengecekan mandiri.

**Aturan ukuran dan kualitas:**

-   PR sebaiknya kecil dan satu tujuan; PR besar dipecah, atau diberi penjelasan alasan tidak dapat dipecah.
-   Perubahan migration, keuangan, autentikasi, atau infrastruktur tidak digabung dengan refactor besar dalam satu PR.
-   Perubahan skema database wajib menyebut strategi expand/contract (52.11).
-   **Riwayat PR** (diskusi, approval, hasil check) adalah bagian dari jejak audit dan tidak dihapus; kebijakan retensi mengikuti 52.42.

------------------------------------------------------------------------

## 52.23 Code Review

**Prinsip:** author tidak dapat menyetujui PR miliknya sendiri; reviewer memeriksa kebenaran, keamanan, keterbacaan, pengujian, dan dampak operasional (checklist 19).

**Checklist reviewer:**

1.  Perubahan sesuai tujuan issue dan tidak mengandung perubahan tak terkait.
2.  Tidak ada secret, credential, atau data pribadi dalam kode, test, fixture, maupun log.
3.  Validasi input, otorisasi (Policy), dan tenant scoping lengkap (Bab 35.2).
4.  Entitlement diperiksa di backend, bukan hanya di UI (Bab 38.2 butir 2).
5.  Operasi finansial memakai transaksi, `DECIMAL`, dan idempotency key (Bab 43.4).
6.  Migration aman dan kompatibel dengan kode lama selama deploy (52.11).
7.  Job idempotent, memiliki timeout dan retry; payload kompatibel mundur (52.13).
8.  Test baru atau diperbarui, termasuk kasus gagal dan akses ditolak.
9.  Dependency baru: lisensi dan hasil scan diperiksa (52.31, Bab 53.9).
10. Dokumentasi, OpenAPI, `.env.example`, dan CHANGELOG diperbarui bila relevan.

**Path sensitif dan pemilik kode** (dikonfigurasi pada `.github/CODEOWNERS`; nama tim atau akun pemilik **[BELUM DITETAPKAN]**):

| Path (rekomendasi) | Alasan | Approval minimum |
|---|---|---|
| `backend/app/Integrations/Payment/`, modul ledger, wallet, withdrawal, komisi | Risiko finansial | **2**, termasuk satu reviewer Security/Finance |
| Autentikasi, OTP, sesi, MFA, kebijakan otorisasi | Risiko pengambilalihan akun | **2**, termasuk satu reviewer Security |
| `backend/database/migrations/` | Risiko kehilangan data dan downtime | **2** untuk migration destruktif atau pada tabel besar |
| `.github/workflows/`, `infra/`, `scripts/` deploy dan backup | Risiko rantai pasok dan akses server | **2**, termasuk satu reviewer infrastruktur |
| `frontend/src/admin-panel/`, namespace backend Admin yang memengaruhi persetujuan atau pengaturan sistem | Kendali operasional | **2** untuk perubahan alur approval dan keuangan |
| `workers/` dan adapter yang menjalankan kode pihak ketiga | Risiko eksekusi kode | **2** dan review isolasi (Bab 53.8) |
| `backend/app/Integrations/Ai/`, `Social/` (penanganan token dan secret) | Risiko kebocoran kredensial | 1 + reviewer Security bila menyentuh penyimpanan kredensial |

**Kondisi tim kecil:** bila belum tersedia dua reviewer yang memenuhi syarat, kebijakan pengecualian (mis. reviewer eksternal sementara, atau pengecualian tertulis pemilik produk dengan kompensasi seperti CI lebih ketat dan tinjauan pasca-merge) harus ditetapkan dan dicatat pada Bab 54.7 **[MENUNGGU KEPUTUSAN]**. Perubahan pada jalur pembayaran tidak boleh dikecualikan tanpa kompensasi tertulis.

**Waktu respons review** (target layanan internal) **[BELUM DITETAPKAN]**. Review tidak menggantikan test otomatis dan test otomatis tidak menggantikan review.

------------------------------------------------------------------------

## 52.24 Commit Convention

Repository memakai **Conventional Commits** (checklist 56) agar riwayat dapat dibaca, changelog dapat dibangkitkan, dan dampak versi dapat ditentukan.

**Format:**

```text
<type>(<scope>)<!>: <deskripsi singkat, imperatif, tanpa titik akhir>

[badan: apa dan mengapa; bukan sekadar bagaimana]

[footer: Refs #123 | BREAKING CHANGE: ... | Co-authored-by: ...]
```

| Type | Arti | Dampak versi (52.25) |
|---|---|---|
| `feat` | Fitur baru | MINOR |
| `fix` | Perbaikan bug | PATCH |
| `perf` | Peningkatan performa | PATCH |
| `refactor` | Perubahan struktur tanpa perubahan perilaku | — |
| `docs` | Dokumentasi | — |
| `test` | Menambah atau memperbaiki test | — |
| `build` | Sistem build dan dependency | — / PATCH |
| `ci` | Workflow dan pipeline | — |
| `chore` | Pemeliharaan lain | — |
| `revert` | Membatalkan commit sebelumnya | Mengikuti commit yang dibatalkan |
| `security` | Perbaikan keamanan (opsional sebagai tipe tambahan) | PATCH atau lebih tinggi |

**Scope yang direkomendasikan:** `frontend`, `backend`, `admin`, `user`, `api`, `db`, `worker`, `ai`, `social`, `payment`, `builder`, `seo`, `shared`, `infra`, `ci`, `docs`, `deps`.

**Aturan:**

-   Perubahan yang merusak kompatibilitas ditandai `!` dan footer `BREAKING CHANGE:`.
-   Commit merujuk issue (`Refs #123` atau `Closes #123`).
-   Pesan commit dan judul PR tidak memuat secret, token, atau data pelanggan.
-   Bahasa deskripsi commit (Indonesia atau Inggris) ditetapkan satu kali dan dipakai konsisten **[BELUM DITETAPKAN]**.
-   Karena strategi squash (52.21), judul PR menjadi pesan commit akhir; judul PR diperiksa oleh CI (commitlint atau setara).
-   Commit bertanda tangan (GPG/SSH) direkomendasikan, wajib bagi Owner dan Admin bila kebijakan rilis menuntutnya.

------------------------------------------------------------------------

## 52.25 Version Numbering

(Checklist 57.) Krafio memakai **Semantic Versioning** `MAJOR.MINOR.PATCH` untuk **aplikasi**.

| Komponen | Kapan naik | Contoh pemicu |
|---|---|---|
| MAJOR | Perubahan tidak kompatibel pada perilaku publik atau API | Penghapusan endpoint versi lama, perubahan model langganan yang memerlukan migrasi pelanggan |
| MINOR | Fitur baru yang kompatibel | Modul baru, adapter provider baru |
| PATCH | Perbaikan bug dan keamanan yang kompatibel | Perbaikan webhook, patch dependency |

**Ketentuan:**

-   Pra-rilis memakai sufiks `-rc.N` atau `-beta.N` (contoh bentuk: `1.0.0-rc.1`). Sebelum peluncuran publik, versi `0.y.z` diperbolehkan; versi `1.0.0` menandai kesiapan production menurut gate Bab 38.3 dan Bab 41.
-   Tag Git berformat `vMAJOR.MINOR.PATCH` dan **tidak berubah** setelah dibuat.
-   **Tiga nomor versi yang berbeda tidak boleh tertukar:** (1) versi aplikasi (SemVer, bab ini), (2) versi API pada path `/api/v1/` (52.10), dan (3) versi dokumen PRD (Riwayat Versi di akhir dokumen).
-   Versi skema database ditentukan oleh urutan migration, bukan oleh SemVer.
-   Build menyematkan versi dan commit SHA (metadata build) dan menampilkannya pada halaman internal Admin serta endpoint health internal (52.54) untuk mempermudah pelacakan.
-   Versi dependency dikunci melalui lockfile; pembaruan dependency mengikuti 52.31.

------------------------------------------------------------------------

## 52.26 Release Management

(Checklist 58–60, 62.)

**Alur rilis reguler:**

1.  Pembekuan fitur: `release/x.y.z` dibuat dari `develop`.
2.  Stabilisasi di staging: hanya bugfix, versi, dan dokumentasi.
3.  Regression test lengkap, E2E, security scan, migration rehearsal, uji restore terakhir yang masih valid.
4.  **Release gate** (Bab 38.3) diperiksa dan hasilnya dilampirkan pada PR rilis.
5.  PR `release/x.y.z` → `main`; setelah merge, tag beranotasi `vX.Y.Z` dibuat.
6.  Workflow rilis membangun artefak final, membuat **GitHub Release** dengan release notes, dan mengarsipkan artefak (52.32, 52.55).
7.  Deployment production melalui approval (52.34); pemantauan pasca-rilis.
8.  Back-merge `main` → `develop`.
9.  Tinjauan pasca-rilis dicatat bila terjadi insiden atau rollback.

**Pemetaan release gate (Bab 38.3) ke kontrol repository:**

| Syarat tidak boleh rilis | Kontrol |
|---|---|
| Ada vulnerability critical/high yang belum ditangani | Required check dependency scan dan code scanning (52.30–52.31) |
| Ada bug lintas-tenant | Suite test tenant isolation sebagai required check (Bab 38.2 butir 1) |
| Ledger tidak balance | Suite test finansial sebagai required check (Bab 38.2 butir 5–9) |
| Webhook tidak idempotent | Test webhook sebagai required check (Bab 38.2 butir 4) |
| Backup/restore belum diuji | Bukti uji restore terbaru dilampirkan; dicek pada approval production (52.56, 52.59) |
| Monitoring error/queue/payment belum aktif | Checklist pra-rilis pada environment approval (52.34) |
| Credential produksi berada di repository atau frontend | Secret scanning, push protection, dan pemeriksaan artefak build (52.32, 52.37) |
| API eksternal dipakai tanpa izin | Review hukum dan register Bab 53 (Bab 53.9–53.10) |

**Release notes** memuat: versi dan tanggal; fitur baru; perbaikan; perubahan yang merusak kompatibilitas; migration dan langkah khusus; perubahan variabel lingkungan; isu yang diketahui; instruksi rollback. Dibangkitkan dari Conventional Commits lalu disunting manusia.

**`CHANGELOG.md`** mengikuti format terstruktur (Added, Changed, Deprecated, Removed, Fixed, Security) per versi.

**Pelacakan perubahan dan riwayat versi** (checklist 60, 62): setiap rilis dapat ditelusuri ke tag, PR, issue, commit, build artefak, dan deployment record (52.42). Rilis di GitHub tidak dihapus; rilis bermasalah ditandai (pre-release/yanked) dengan catatan, bukan dihapus.

**Hotfix:** `hotfix/*` dari `main`, review dipercepat tetapi tidak menghilangkan required check dan approval; diverifikasi di staging; rilis sebagai PATCH; back-merge ke `develop` wajib.

**Kalender dan jendela rilis** (hari/jam aman, pembekuan menjelang event bisnis) **[BELUM DITETAPKAN]**.

------------------------------------------------------------------------

## 52.27 GitHub Actions

GitHub Actions adalah mesin otomasi CI/CD Krafio (checklist 36–45). Urutan logis pipeline mengikuti Bab 34.4; bab ini menetapkan bentuk workflow-nya.

**Inventaris workflow (rekomendasi):**

| Workflow | Pemicu | Fungsi | Required check? |
|---|---|---|---|
| `ci-frontend.yml` | PR dan push ke `develop`, `release/*`, `main` (filter path) | Lint, type check, unit/komponen test, build | Ya |
| `ci-backend.yml` | Sama | Lint, static analysis, unit/feature test, migration test, queue test | Ya |
| `ci-api-contract.yml` | PR yang menyentuh API/OpenAPI | Validasi OpenAPI, contract test, deteksi breaking change | Ya |
| `ci-e2e.yml` | PR ke `release/*`, `main`; nightly untuk `develop` | E2E, visual regression, accessibility | Ya untuk `release/*` dan `main` |
| `security.yml` | PR; jadwal mingguan | Dependency review, audit dependency, secret scan, code scanning, scan image | Ya |
| `build.yml` | Push ke `develop`, `release/*`; tag | Membangun artefak imutabel (52.32) | Ya |
| `deploy-staging.yml` | Setelah build `develop`/`release/*` sukses | Deploy staging + smoke test | — |
| `deploy-production.yml` | Tag `vX.Y.Z` atau dispatch manual dengan versi | Deploy production dengan approval | — |
| `rollback.yml` | Dispatch manual (dan dipanggil otomatis saat health check gagal) | Rollback ke rilis sebelumnya (52.36) | — |
| `release.yml` | Tag `vX.Y.Z` | GitHub Release, release notes, arsip artefak | — |
| `backup-verify.yml` | Jadwal | Memverifikasi mirror source, status backup, dan uji restore terjadwal (52.55–52.56) | — |
| `commitlint-pr-title.yml` | PR | Memeriksa judul PR/commit | Ya |

**Prinsip keamanan workflow (wajib):**

-   Blok `permissions:` eksplisit dan **default read-only**; izin tulis hanya per job yang membutuhkan.
-   Action pihak ketiga **dipin pada commit SHA lengkap**, bukan tag yang dapat berpindah; pembaruan melalui Dependabot. Daftar action yang diizinkan dikonfigurasi di level organisasi.
-   Tidak ada secret pada workflow yang dipicu PR dari fork; `pull_request_target` hanya dipakai dengan alasan tertulis dan tidak mengeksekusi kode PR.
-   Input yang dikendalikan pengguna (judul PR, nama branch, isi issue) tidak disisipkan langsung ke shell (`run:`); lewatkan melalui variabel lingkungan.
-   `concurrency` mencegah dua deployment berjalan bersamaan pada environment yang sama.
-   Setiap job memiliki `timeout-minutes`.
-   File workflow berada di bawah CODEOWNERS dan review sensitif (52.23).
-   Runner: **hosted runner** sebagai default. Self-hosted runner hanya untuk kebutuhan khusus, tidak untuk repository publik, dan harus ephemeral serta terisolasi dari server production.
-   Autentikasi ke layanan eksternal memakai OIDC bila didukung, agar tidak menyimpan kredensial jangka panjang.
-   Artefak dan log memiliki retensi terbatas dan tidak memuat secret.

**Contoh kerangka ilustratif** (bukan konfigurasi final; versi action dan perintah disesuaikan saat implementasi):

```yaml
name: ci-backend
on:
  pull_request:
    paths: ["backend/**", "docs/openapi/**", ".github/workflows/ci-backend.yml"]
permissions:
  contents: read
concurrency:
  group: ci-backend-${{ github.ref }}
  cancel-in-progress: true
jobs:
  test:
    runs-on: ubuntu-latest
    timeout-minutes: 30
    services:
      mysql:
        image: mysql:8.4
        # kredensial khusus CI; bukan kredensial staging/production
      redis:
        image: redis:7
    steps:
      - uses: actions/checkout@<SHA-COMMIT-LENGKAP>
      - name: Install dependency (lockfile)
        run: composer install --no-interaction --prefer-dist
        working-directory: backend
      - name: Lint dan static analysis
        run: echo "perintah lint/analisis dipilih saat implementasi"
      - name: Test (unit, feature, migration, queue)
        run: echo "perintah test dipilih saat implementasi"
```

------------------------------------------------------------------------

## 52.28 Automated Testing

(Checklist 36.) Pengujian mengikuti Bab 38. Tabel berikut memetakan jenis pengujian Bab 38.1 ke tahap pipeline.

| Jenis pengujian (Bab 38.1) | Dijalankan pada | Memblokir merge? |
|---|---|---|
| Unit test | Setiap PR | Ya |
| Feature test | Setiap PR | Ya |
| API contract test | PR yang menyentuh API | Ya |
| Database migration test (MySQL 8.4 kosong + snapshot skema sebelumnya) | PR yang menyentuh migration; seluruh rilis | Ya |
| Queue/worker test | Setiap PR backend | Ya |
| Integration test dengan provider sandbox/mock | PR terkait integrasi; nightly | Ya untuk mock; nightly untuk sandbox |
| End-to-end UI test | PR ke `release/*` dan `main`; nightly `develop` | Ya pada `release/*` dan `main` |
| Visual regression (template, Website Builder) | PR yang menyentuh template/builder | Ya |
| Security test | PR dan jadwal mingguan | Ya untuk temuan critical/high |
| Load/stress test | Pra-rilis mayor dan perubahan kapasitas (Bab 34.5) | Menjadi syarat rilis tertentu |
| Backup restore test | Terjadwal (52.56) | Bukti diperiksa pada approval production |
| Accessibility; cross-browser/mobile | Pra-rilis | Sesuai kebijakan rilis |

**Aturan:**

-   **Suite skenario kritis wajib** (Bab 38.2, 20 butir) ditandai dan menjadi required check; kegagalannya memblokir merge dan rilis (Bab 38.3).
-   Service container CI: MySQL 8.4 dan Redis; kredensial hanya untuk CI.
-   Test tidak memanggil provider berbayar dengan kredensial produksi; test tidak memakai data pelanggan nyata.
-   Test yang tidak stabil (flaky) dicatat sebagai issue dan diperbaiki dalam batas waktu; mematikan test tidak diperbolehkan tanpa issue dan persetujuan reviewer.
-   Ambang cakupan (coverage) minimum **[MENUNGGU KEPUTUSAN]**; direkomendasikan cakupan lebih ketat pada modul keuangan, entitlement, dan tenant isolation.

------------------------------------------------------------------------

## 52.29 Code Quality Checking

(Checklist 37.) Pemeriksaan kualitas bersifat otomatis dan seragam antara lokal dan CI.

| Area | Alat/ketentuan (pilihan final saat implementasi) |
|---|---|
| Frontend | ESLint, Prettier, `tsc --noEmit` (strict) |
| Backend | Formatter PHP standar komunitas Laravel (mis. Laravel Pint) dan aturan gaya yang disepakati |
| Konsistensi editor | `.editorconfig` |
| Commit/PR | Pemeriksaan Conventional Commits (52.24) |
| OpenAPI | Lint spesifikasi (valid, konsisten, tanpa breaking change tak sengaja) |
| Infrastruktur | Lint shell script, Dockerfile, workflow YAML, dan konfigurasi Nginx |
| Dokumentasi | Lint Markdown dan pemeriksaan tautan pada `docs/` |

**Ketentuan:**

-   Konfigurasi lint ada di repository; **hook pra-commit** lokal membantu tetapi **CI adalah penegak utama**.
-   Peringatan baru tidak boleh bertambah; pelanggaran yang sudah ada dicatat sebagai baseline dan dikurangi bertahap.
-   Kompleksitas, duplikasi, dan ukuran file/fungsi dipantau sebagai indikator; ambang ditetapkan saat implementasi.

------------------------------------------------------------------------

## 52.30 Static Code Analysis

(Checklist 38.)

-   **Backend:** analisis statik PHP bertipe (mis. PHPStan/Larastan). Level dinaikkan bertahap; baseline dikelola dan tidak boleh membesar tanpa persetujuan.
-   **Frontend:** TypeScript strict dan aturan ESLint keamanan (mis. larangan `dangerouslySetInnerHTML` tanpa sanitasi, `eval`).
-   **SAST:** code scanning (mis. CodeQL pada GitHub) atau alat setara; ketersediaan bergantung plan dan visibility repository (52.3), diverifikasi sebelum diandalkan.
-   **Pola berisiko yang dicari secara khusus:** SQL mentah dengan input pengguna, SSRF pada URL fetcher/crawler (Bab 38.2 butir 16), deserialisasi tak aman, pemakaian `env()` di luar `config/`, mass assignment, penulisan file ke jalur yang dikendalikan pengguna, dan kunci/token hardcoded.
-   **Infrastruktur sebagai kode:** pemindaian misconfiguration pada Dockerfile, workflow, dan konfigurasi server.
-   **Triase temuan:** critical/high memblokir; medium dan low menjadi issue dengan pemilik dan tenggat **[MENUNGGU KEPUTUSAN]**. False positive ditandai beserta alasan, bukan dihapus diam-diam.

------------------------------------------------------------------------

## 52.31 Dependency Vulnerability Scanning

(Checklist 39.)

| Kontrol | Keterangan |
|---|---|
| Dependabot alerts dan version updates | Aktif untuk ekosistem Composer, npm, GitHub Actions, dan Docker (`.github/dependabot.yml`) |
| Dependency review pada PR | Memblokir PR yang menambah dependency rentan atau berlisensi bermasalah |
| Audit dependency di CI | `composer audit` dan audit npm pada setiap PR dan jadwal mingguan |
| Scan image container | Bila memakai Docker (termasuk worker `workers/`) |
| SBOM | Dibangkitkan pada rilis (direkomendasikan) |
| Pemeriksaan lisensi | Daftar lisensi dependency diperiksa terhadap kebijakan Bab 53.9 |

**Kebijakan:**

-   Vulnerability **critical/high yang belum ditangani memblokir rilis** (Bab 38.3). Tenggat perbaikan untuk tingkat medium/low **[MENUNGGU KEPUTUSAN]**.
-   Pengecualian (waiver) harus tertulis, memiliki pemilik, alasan, kontrol kompensasi, dan **tanggal kedaluwarsa**.
-   Lockfile wajib; instalasi CI memakai perintah yang menghormati lockfile (`composer install`, `npm ci`/setara). Skrip instalasi dependency diaudit; `--ignore-scripts` dipakai bila memungkinkan.
-   Pembaruan dependency dilakukan rutin dan kecil, bukan menumpuk menjadi lompatan besar.
-   Waspadai risiko rantai pasok (typosquatting, paket yang diambil alih): dependency baru ditinjau pada PR.

------------------------------------------------------------------------

## 52.32 Automated Build

(Checklist 40.)

**Prinsip: build sekali, deploy banyak.** Artefak dibangun satu kali oleh pipeline dan artefak yang **sama** dipromosikan dari staging ke production. Server production tidak menjalankan `composer install` atau `npm install` dan tidak mengompilasi kode.

| Artefak | Isi | Catatan |
|---|---|---|
| Backend | Kode Laravel dengan dependency production (`--no-dev`, autoloader teroptimasi), tanpa `.env`, tanpa berkas tes | Config cache dibuat pada server setelah `.env` environment tersedia |
| Frontend | Hasil build Vite (`dist/`) | Variabel `VITE_*` ditetapkan saat build (lihat 52.47) |
| Worker non-PHP | Image container atau paket terkunci | Tag berdasarkan versi dan SHA |
| Infra | Konfigurasi Nginx, Supervisor/systemd, skrip deploy | Divalidasi (`nginx -t`, lint) |

**Ketentuan:**

-   Nama artefak memuat versi dan commit SHA; checksum dibuat dan diverifikasi pada saat deploy.
-   Artefak imutabel; perubahan berarti build baru dengan SHA baru.
-   Build **gagal** bila artefak memuat `.env`, kunci privat, atau pola secret.
-   Build tidak menyisipkan secret; nilai yang terlihat publik hanya `VITE_*` (Bab 48).
-   Provenance/attestation build direkomendasikan bila tersedia pada plan yang digunakan.
-   Retensi artefak pada GitHub terbatas; **arsip jangka panjang artefak rilis** disimpan di object storage (52.55) agar pemulihan tidak bergantung pada GitHub (52.60).

------------------------------------------------------------------------

## 52.33 Automated Deployment

(Checklist 41–42.)

**Pemetaan trigger ke environment:**

| Peristiwa | Environment | Persetujuan manusia |
|---|---|---|
| Push/merge ke `develop` (setelah build sukses) | Staging | Tidak |
| Push ke `release/*` | Staging (kandidat rilis) | Tidak |
| Tag `vX.Y.Z` pada `main` | Production | **Ya** (52.34) |
| Dispatch manual dengan versi tertentu | Staging atau production | Production tetap memerlukan approval |

**Urutan deployment** (detail dari Bab 34.4, langkah 8–13):

1.  Prasyarat: artefak dan checksum valid; required check lulus; environment tersedia; tidak ada deployment lain berjalan (`concurrency`).
2.  Backup pra-deploy bila rilis memuat migration berisiko (52.48).
3.  Unggah artefak ke server dan ekstrak ke `releases/<id>/` (52.46).
4.  Hubungkan `shared/` (`.env`, `storage/`) dan atur permission (52.49).
5.  Validasi konfigurasi: variabel wajib tersedia tanpa menampilkan nilai (52.47).
6.  Jalankan migration satu kali (52.48).
7.  Bangun cache konfigurasi, route, dan view.
8.  Alihkan symlink `current` secara atomik.
9.  Reload PHP-FPM dan Nginx (setelah `nginx -t`); restart worker secara graceful (52.50); pastikan scheduler tunggal (52.51).
10. Health check dan smoke test (52.54).
11. Bila gagal: rollback (52.36). Bila sukses: bersihkan rilis lama sesuai kebijakan retensi.
12. Catat deployment record dan kirim notifikasi hasil (52.42).

**Ketentuan:**

-   Skrip deployment **idempotent**, berada di repository (`infra/`, `scripts/`) dan di bawah review sensitif.
-   Deployment hanya dijalankan oleh pipeline; perubahan manual ke server production dilarang, kecuali tindakan insiden yang dicatat.
-   Log deployment disimpan dan tidak memuat secret (52.42).
-   Autentikasi ke VPS memakai SSH key terbatas (52.40); model push atau pull ditetapkan pada 52.43.
-   Notifikasi deployment (berhasil/gagal/rollback) dikirim ke kanal operasional yang ditetapkan **[BELUM DITETAPKAN]**.

------------------------------------------------------------------------

## 52.34 Production Deployment

(Checklist 43.)

**Prasyarat wajib:**

1.  Tag dibuat dari `main` dan artefak yang sama telah lulus staging dan smoke test.
2.  Seluruh item release gate (Bab 38.3, dipetakan pada 52.26) terpenuhi.
3.  Rencana rollback dan strategi migration tercantum pada PR rilis.
4.  Bukti uji restore backup terbaru tersedia dan masih dalam periode berlaku.
5.  Monitoring error, queue, dan pembayaran aktif (Bab 36.1).
6.  Release notes tersedia.

**Approval gate:** GitHub **Environment `production`** dengan:

-   *required reviewers* yang bukan author rilis (jumlah dan nama **[BELUM DITETAPKAN]**; direkomendasikan minimal dua reviewer berhak, yaitu satu bidang teknis dan satu bidang produk/operasional bila tim memungkinkan);
-   pembatasan deployment hanya dari tag rilis pada `main`;
-   secret deployment production hanya tersedia pada environment ini (52.39);
-   *wait timer* opsional sesuai kebijakan.

**Ketentuan operasional:**

-   Deployment di luar jendela rilis yang disetujui hanya untuk hotfix darurat (52.26) **[jendela rilis: BELUM DITETAPKAN]**.
-   Pasca-deploy dilakukan **jendela pemantauan** (error rate, queue depth, failed jobs, latensi, webhook pembayaran) dengan durasi **[BELUM DITETAPKAN]**.
-   Akses SSH manual ke production dicatat dan hanya untuk diagnosis/insiden.
-   Setiap deployment menghasilkan deployment record: versi, SHA, pemicu, approver, waktu mulai/selesai, hasil migration, hasil health check.
-   Migration destruktif tidak boleh berada dalam rilis yang sama dengan kode yang bergantung padanya (52.11).

------------------------------------------------------------------------

## 52.35 Staging Deployment

(Checklist 44.)

-   Staging adalah replika production dalam versi software dan topologi proses (Nginx, PHP-FPM, MySQL 8.4, Redis, Horizon, scheduler), dengan kapasitas yang boleh lebih kecil (Bab 34.2, 34.5).
-   Database, credential, storage, dan callback URL **terpisah** dari production; hanya kunci sandbox untuk pembayaran, WhatsApp, sosial, dan provider AI (Bab 34.2).
-   Data pada staging adalah data sintetis atau **hasil anonimisasi**; data pelanggan nyata tidak boleh disalin tanpa anonimisasi (Bab 35.4).
-   Email keluar diarahkan ke penampung (sink) atau domain uji; notifikasi nyata ke pelanggan dinonaktifkan.
-   Akses staging dibatasi (mis. autentikasi tambahan atau pembatasan IP/Cloudflare Access) dan tidak diindeks mesin pencari.
-   Deployment staging otomatis dari `develop` dan `release/*`; setelah deploy dijalankan smoke test (52.54) dan, untuk kandidat rilis, E2E dan **rehearsal migration** terhadap salinan data yang dianonimkan.
-   Rollback harus pernah dilatih di staging sebelum rilis mayor (52.36).
-   Penempatan staging pada VPS yang sama dengan production **tidak direkomendasikan** (52.45).

------------------------------------------------------------------------

## 52.36 Deployment Rollback

(Checklist 45, 61.)

**Pemicu rollback:** health check gagal (otomatis); lonjakan error atau kegagalan pembayaran/webhook; failed jobs abnormal; keputusan manual oleh penanggung jawab rilis.

**Mekanisme (rollback kode):**

1.  Alihkan symlink `current` ke rilis sebelumnya yang masih tersimpan (jumlah rilis yang dipertahankan **[direkomendasikan: beberapa rilis terakhir; nilai final BELUM DITETAPKAN]**).
2.  Bangun ulang cache konfigurasi sesuai rilis tersebut.
3.  Reload PHP-FPM; restart worker agar memuat kode lama (52.50).
4.  Jalankan health check dan smoke test.
5.  Catat rollback pada deployment record dan buat insiden bila relevan.

**Keputusan rollback terkait database** (migration **tidak** dibalik otomatis):

| Kondisi migration pada rilis | Tindakan |
|---|---|
| Hanya *expand* (kompatibel dengan kode lama) | Rollback kode aman; skema dibiarkan |
| Memuat perubahan yang tidak kompatibel | Jangan rollback kode secara buta; pilih *forward fix* atau pemulihan dari backup (52.59) |
| Migration gagal sebagian | Hentikan deployment, jangan alihkan `current`; evaluasi keadaan skema sebelum tindakan (52.48) |
| Data keuangan terdampak | Eskalasi; verifikasi ledger sebelum dan sesudah; jangan ubah data tanpa prosedur |

**Ketentuan tambahan:**

-   Payload job lama dan baru harus saling kompatibel selama jendela rollback (52.13).
-   Feature flag dapat dipakai untuk menonaktifkan fitur berisiko tanpa rollback penuh (Bab 37.4).
-   `rollback.yml` menerima input versi/rilis tujuan, memakai environment dan approval yang sama dengan deployment ke environment tersebut, kecuali rollback otomatis atas kegagalan health check.
-   **`git revert`** (membuat commit pembatalan) adalah cara membatalkan perubahan pada riwayat kode; *history rewrite* pada `main` dilarang. Rollback deployment dan revert kode adalah dua tindakan berbeda dan keduanya dicatat.
-   Rollback dilatih di staging secara berkala dan sebelum rilis mayor. Target waktu pemulihan mengikuti RTO yang akan ditetapkan (Bab 41 butir 19) **[MENUNGGU KEPUTUSAN]**.

------------------------------------------------------------------------

## 52.37 Repository Security

(Checklist 53, 54, 55.) Keamanan repository adalah kontrol rantai pasok: repository yang disusupi berarti production dapat disusupi.

**Kontrol wajib:**

| Ancaman | Kontrol | Rujukan |
|---|---|---|
| Secret ter-commit | **Secret scanning** dan **push protection** aktif; pemeriksaan pola secret di CI; hook pra-commit lokal sebagai bantuan | 52.31, 52.32 |
| Perubahan langsung ke production | Branch protection/ruleset pada `main`, `develop`, `release/*`; **tag ruleset** pada `v*` (tidak boleh dihapus, dipindah, atau ditimpa) | 52.21 |
| Workflow berbahaya | CODEOWNERS pada `.github/workflows/`; action dipin pada SHA; permission default read-only; daftar action yang diizinkan di level organisasi | 52.23, 52.27 |
| Dependency berbahaya | Dependabot, dependency review, audit di CI, lockfile | 52.31 |
| Kode rentan | Code scanning/SAST, static analysis | 52.30 |
| Akun developer diambil alih | 2FA wajib bagi seluruh anggota, commit bertanda tangan direkomendasikan, review akses berkala | 52.38 |
| Kebocoran logika bisnis | Visibility sesuai 52.3; fork dinonaktifkan untuk repository privat | 52.3 |
| Pelaporan kerentanan tidak terarah | `SECURITY.md` dan jalur pelaporan privat (private vulnerability reporting bila tersedia pada plan) | 52.2 |
| GitHub App/OAuth App pihak ketiga berlebihan | Aplikasi yang terpasang ditinjau dan hanya yang disetujui Owner yang diizinkan; izin minimum | 52.38 |
| Webhook palsu ke endpoint Krafio | Webhook repository memakai secret dan diverifikasi tanda tangan | 52.39 |

**Ketentuan tambahan:**

-   Ketersediaan secret scanning, push protection, code scanning, dan ruleset bergantung pada plan dan visibility (52.3); ketersediaan **diverifikasi sebelum diandalkan** dan kompensasinya dicatat pada Bab 54.7.
-   Tidak ada berkas `.env`, kunci privat, dump database, atau data pelanggan di repository; `.gitignore` dan pemindaian menegakkan aturan ini.
-   Repository tidak memuat dokumen sensitif (topologi internal rinci, kontak vendor, data keuangan) bila visibility dapat berubah (52.3).

**Prosedur bila secret ter-commit (wajib):**

1.  Anggap secret **sudah bocor**, sekalipun repository privat.
2.  **Rotasi/cabut** secret pada penyedia terkait segera; ini langkah utama dan tidak dapat digantikan oleh penghapusan riwayat.
3.  Tinjau log penggunaan secret pada penyedia untuk mendeteksi penyalahgunaan.
4.  Hapus secret dari kode dan, bila perlu, dari riwayat. *History rewrite* adalah pengecualian terhadap larangan pada 52.36, hanya dengan persetujuan Owner dan dicatat; semua clone dan fork diperlakukan tercemar.
5.  Catat insiden (52.42), perbaiki penyebab (hook, pelatihan, pola scan), dan perbarui inventaris secret (52.39).

------------------------------------------------------------------------

## 52.38 Access Control

(Checklist 46, 47, 48.) Prinsip: **least privilege**, akses melalui **tim** (bukan individu), dan segala akses dapat dicabut dan ditinjau.

**Peran akses GitHub (rekomendasi; nama tim dan anggota [BELUM DITETAPKAN]):**

| Peran fungsional | Hak GitHub (rekomendasi) | Keterangan |
|---|---|---|
| Owner organisasi | Owner (minimal dua, 52.4) | Kebijakan organisasi, billing, pemulihan; tidak dipakai untuk pekerjaan harian |
| Admin repository | Admin (jumlah dibatasi) | Pengaturan repository, ruleset, environment; perubahan tercatat di audit log |
| Maintainer | Maintain | Merge PR, kelola rilis dan label; tidak dapat mengubah ruleset |
| Developer | Write | Membuat branch dan PR; tidak dapat merge ke `main` tanpa ruleset terpenuhi |
| Reviewer Security / Finance / Infra | Write atau Triage melalui tim CODEOWNERS | Memenuhi approval path sensitif (52.23) |
| QA / Pengamat | Read atau Triage | Membaca kode, mengelola issue |
| Kontributor eksternal | Read atau Write terbatas pada repository tertentu, dengan **tanggal kedaluwarsa akses** | Wajib disetujui Owner; tidak mendapat akses environment production |
| Otomasi | GitHub App atau `GITHUB_TOKEN` | Bukan akun personal (52.4, 52.41) |

**Ketentuan:**

-   Pengaturan organisasi mewajibkan 2FA; SSO diaktifkan bila plan mendukung.
-   Izin dasar anggota organisasi diatur serendah mungkin (Read atau None); hak tambahan melalui tim.
-   Hanya Owner dan Admin repository yang dapat mengubah ruleset, environment, secret, dan pengaturan Actions.
-   Approver environment `production` adalah daftar terbatas (52.34) dan bukan author rilis.
-   **Tinjauan akses** berkala (frekuensi **[BELUM DITETAPKAN]**; direkomendasikan setiap kuartal) dan segera setelah perubahan tim.
-   **Offboarding:** akses GitHub, SSH server, secret manager, Cloudflare, provider AI/pembayaran, dan akses lain dicabut pada hari yang sama; secret yang pernah diketahui orang tersebut dirotasi bila relevan.
-   Akses ke server production **terpisah** dari akses GitHub (52.40, 52.44); keanggotaan GitHub tidak otomatis memberi akses server.
-   "Admin permissions" pada bab ini adalah hak **GitHub**, berbeda dari Super Admin Krafio di aplikasi (52.8).

------------------------------------------------------------------------

## 52.39 Repository Secrets

(Checklist 49, 50.) GitHub dipakai hanya untuk secret yang **dibutuhkan pipeline**, bukan sebagai penyimpan seluruh kredensial produk.

**Pembagian lokasi secret:**

| Jenis kredensial | Lokasi | Alasan |
|---|---|---|
| Kunci SSH deploy, host/target deploy | GitHub **Environment secrets** (`staging`, `production`) | Hanya dibutuhkan pipeline; dibatasi environment |
| Kredensial CI (database/Redis CI, kunci sandbox) | Repository/Environment secret `ci` | Tidak punya nilai di luar CI |
| Token akses object storage untuk arsip artefak dan mirror | Environment secret terbatas, hak tulis-saja bila memungkinkan | Arsip rilis (52.32, 52.55) |
| **Kunci produksi** (pembayaran, AI, WhatsApp, sosial, SMTP, `APP_KEY`, kredensial DB) | **Production server atau secret manager** (Bab 48) | Tidak boleh berada di GitHub bila dapat dihindari |
| Nilai non-rahasia (nama host, region, versi) | Actions **variables**, bukan secret | Dapat diaudit dan dibaca |

**Aturan:**

-   Secret production **hanya** pada environment `production` dengan pembatasan tag rilis dan approval (52.34).
-   Tidak ada secret pada workflow yang dipicu PR dari fork; secret tidak dicetak, tidak disisipkan ke argumen perintah yang tercatat, dan tidak diunggah sebagai artefak.
-   Setiap secret memiliki **inventaris**: nama, tujuan, lingkup, pemilik, tanggal dibuat, jadwal rotasi, prosedur cabut. Nilai tidak tercatat di inventaris.
-   Nama secret mengikuti konvensi seragam (contoh bentuk: `STAGING_SSH_PRIVATE_KEY`, `PRODUCTION_SSH_PRIVATE_KEY`, `ARTIFACT_STORAGE_ACCESS_KEY`).
-   Autentikasi OIDC ke penyedia yang mendukung diutamakan agar tidak menyimpan kredensial jangka panjang (52.27).
-   Rotasi terjadwal dan segera setelah dugaan kebocoran, offboarding, atau perubahan Owner; interval **[BELUM DITETAPKAN]**.
-   `.env.example` memuat nama variabel (Bab 48) tanpa nilai; nilai aktual tidak pernah masuk Git (52.20).
-   Perubahan secret dicatat pada audit log organisasi (52.42).

------------------------------------------------------------------------

## 52.40 SSH Deployment Key

(Checklist 51.)

-   **Satu kunci per environment** (staging dan production terpisah), jenis modern (mis. Ed25519), dibuat khusus untuk deployment dan tidak dipakai ulang sebagai kunci pribadi.
-   Pada server, kunci terikat pada **user `deploy`** non-root. Login root via SSH dan autentikasi kata sandi dinonaktifkan.
-   Hak `deploy` dibatasi: tulis ke direktori deployment (52.46) dan hanya perintah tertentu melalui `sudo` dengan daftar putih (reload PHP-FPM/Nginx, kontrol Supervisor/Horizon). Tidak ada `sudo` bebas.
-   `authorized_keys` memakai opsi pembatas (mis. `restrict`, tanpa agent/port forwarding/TTY) dan, bila memungkinkan, **forced command** ke skrip penerima deployment yang divalidasi. Pembatasan alamat sumber (`from=`) hanya efektif bila alamat runner tetap; untuk hosted runner dengan rentang IP besar, kompensasinya adalah forced command, user terbatas, dan model pull atau runner terisolasi (52.43).
-   **Host key server disematkan** (known_hosts) pada konfigurasi workflow; koneksi ke host dengan kunci tak dikenal ditolak.
-   Private key hanya ada sebagai environment secret (52.39); public key tercatat pada inventaris.
-   Rotasi terjadwal dan segera setelah kompromi atau perubahan Owner; prosedur rotasi tidak boleh menimbulkan downtime deployment (kunci baru ditambahkan sebelum yang lama dicabut).
-   Bila memakai **deploy key GitHub** untuk model pull, kunci tersebut **read-only** dan terikat pada satu repository.
-   Percobaan login SSH dan perintah `deploy` tercatat (log server); akses SSH manual dicatat sesuai 52.34.

------------------------------------------------------------------------

## 52.41 Personal Access Token Management

(Checklist 52.)

-   **Urutan preferensi:** `GITHUB_TOKEN` (izin per job) → **GitHub App** → fine-grained PAT → classic PAT. Classic PAT dilarang kecuali tidak ada alternatif yang mendukung kebutuhan, dengan pengecualian tertulis.
-   PAT tidak dipakai bila `GITHUB_TOKEN` atau GitHub App mencukupi; PAT tidak milik akun personal developer bila untuk otomasi yang berkelanjutan.
-   Fine-grained PAT: lingkup repository minimum, izin minimum, **masa berlaku pendek** (nilai final **[BELUM DITETAPKAN]**; direkomendasikan tidak melebihi 90 hari) dan pemilik yang jelas.
-   Organisasi mewajibkan persetujuan untuk PAT yang mengakses sumber daya organisasi (bila plan mendukung) dan memantau token yang aktif.
-   Register token: pemilik, tujuan, lingkup, kedaluwarsa, tanggal tinjau. Token yang tidak dipakai atau kedaluwarsa dicabut.
-   Token tidak ditempel di issue, PR, chat, log, atau berkas konfigurasi; push protection membantu mendeteksi pola token GitHub.
-   Saat developer keluar, seluruh token miliknya dicabut dan otomasi yang bergantung padanya dipindahkan ke GitHub App.
-   Token dengan hak ke repository di luar kebutuhan dianggap pelanggaran least privilege (52.38).

------------------------------------------------------------------------

## 52.42 Audit Log

(Checklist 55, 63.) Terdapat tiga jenis jejak yang dibedakan:

| Jejak | Sumber | Isi | Retensi |
|---|---|---|---|
| Audit log GitHub (organisasi/repository) | GitHub | Perubahan anggota dan izin, ruleset, environment, secret, SSH/deploy key, visibility, instalasi App, kebijakan Actions | **[MENUNGGU KEPUTUSAN]**; diekspor berkala ke penyimpanan terpisah bila retensi native pendek atau fitur streaming tersedia pada plan |
| Riwayat PR dan commit | GitHub | Diskusi, approval, hasil check, histori commit | Tidak dihapus (52.22); mirror dan arsip metadata (52.55) |
| **Deployment record** | Pipeline | Versi, SHA, pemicu, approver, waktu mulai/selesai, hasil migration, hasil health check, hasil rollback (52.34) | **[MENUNGGU KEPUTUSAN]**; disimpan pada GitHub Deployments dan salinan JSON diarsipkan ke object storage |

Audit log aplikasi (tindakan Admin dan keuangan Krafio; Bab 36) adalah jejak **terpisah** dan tidak digantikan oleh log GitHub.

**Peristiwa yang memicu peringatan kepada Owner:** perubahan visibility repository; penonaktifan/pengubahan ruleset `main`; penambahan Owner/Admin; perubahan atau pembuatan secret environment `production`; penambahan SSH/deploy key; instalasi GitHub App atau OAuth App; perubahan kebijakan Actions; deployment production di luar jendela rilis; kegagalan berulang autentikasi deploy.

**Ketentuan:**

-   Log deployment dan log workflow **tidak memuat secret** (52.33); artefak dan log memiliki retensi terbatas.
-   Insiden keamanan repository dicatat dengan linimasa, dampak, tindakan, dan perbaikan.
-   Audit log ditinjau berkala bersama tinjauan akses (52.38).

------------------------------------------------------------------------

## 52.43 VPS Integration

(Checklist 42, 64.) Bagian ini menetapkan cara pipeline GitHub menghubungi server aplikasi. Penyedia VPS dan spesifikasi **[BELUM DITETAPKAN]**; spesifikasi tidak ditebak sebelum benchmark (Bab 34.5), dan **tidak diasumsikan bahwa VPS aplikasi memiliki GPU**.

| Model | Cara kerja | Kelebihan | Kekurangan |
|---|---|---|---|
| **A. Push via SSH** dari GitHub Actions | Workflow mengunggah artefak dan memicu skrip deploy melalui user `deploy` | Sederhana; langsung terlihat di log pipeline | Server menerima koneksi SSH dari luar; kunci jangka panjang di GitHub |
| **B. Pull oleh agen di server** | Server (timer/agen) mengambil rilis bertanda tangan dari object storage atau GitHub Release dan memverifikasi checksum | Tidak ada kredensial server di GitHub; tidak perlu membuka SSH untuk runner | Perlu agen dan pelaporan status ke pipeline |
| **C. Self-hosted runner ephemeral** | Runner terisolasi di jaringan yang sama dengan server | Akses jaringan privat | Beban operasional dan risiko runner; tidak untuk repository publik (52.27) |

**Rekomendasi sementara (bukan keputusan final):** mulai dengan **Model A** dengan pembatasan 52.40; evaluasi **Model B** bila risiko kunci SSH di GitHub dinilai tinggi. Keputusan final dicatat pada Bab 54.7 **[MENUNGGU KEPUTUSAN]**.

**Ketentuan:**

-   Pipeline hanya menyentuh server melalui jalur ini; tidak ada deployment manual (52.33).
-   Artefak diverifikasi checksum sebelum diekstrak (52.32).
-   SSH tidak melewati proxy Cloudflare; port SSH dibatasi firewall (52.44).
-   Bila topologi memiliki beberapa node (app, worker, GPU), deployment diurutkan: persiapan semua node → migration satu kali → alih `current` → reload layanan → health check per node. Node GPU/AI dikelola dengan pipeline dan konfigurasi tersendiri sesuai Bab 34.1 dan Bab 53.8.
-   Seluruh koneksi, versi terpasang, dan hasil tercatat pada deployment record (52.42).

------------------------------------------------------------------------

## 52.44 Production Server Configuration

(Checklist 65.) Konfigurasi di bawah adalah baseline; nilai spesifik (versi OS, ukuran resource, jumlah worker) ditetapkan saat implementasi dan benchmark (Bab 34.5).

| Area | Ketentuan |
|---|---|
| OS | Distribusi Linux yang didukung jangka panjang dan menerima patch keamanan **[versi BELUM DITETAPKAN]**; pembaruan keamanan otomatis atau terjadwal |
| Akses | SSH berbasis kunci; root login dan password auth nonaktif; user `deploy` terbatas (52.40); akun admin server individual dengan 2FA/jump bila tersedia |
| Firewall | Hanya port 80/443 (idealnya dibatasi ke rentang Cloudflare), SSH dibatasi sumber; MySQL, Redis, Horizon, Reverb tidak terbuka ke publik |
| Layanan | Nginx, PHP-FPM, MySQL 8.4, Redis/Valkey, Supervisor/systemd (Bab 43.1, Bab 49); semua layanan aktif otomatis setelah reboot |
| PHP | OPcache aktif; `display_errors` mati; batas memori dan waktu eksekusi sesuai pool; pool terpisah per aplikasi bila perlu |
| MySQL | Bind ke localhost/jaringan privat; user aplikasi berhak DML saja; user migration terpisah (52.48); koneksi dibatasi |
| Redis | Bind privat, ACL/password, tanpa eksposur publik; kebijakan eviction terpisah untuk cache dan queue (Bab 43.5) |
| Waktu dan log | Waktu tersinkron (NTP), zona UTC; rotasi log; log tidak memuat secret (Bab 36.2) |
| Monitoring | Agen metrik server, MySQL, Redis, queue (Bab 36.1) dan alert |
| Resource worker | Worker berat dibatasi CPU/RAM/disk agar tidak menghambat web dan queue prioritas (Bab 49) |

**Peran node (topologi logis, Bab 34.1):** node app/API; database; Redis/queue; worker umum; worker AI/GPU; worker render video. Pada tahap awal beberapa peran dapat berbagi satu VPS bila hasil benchmark mendukung; pemisahan dilakukan saat beban meningkat. Konfigurasi server dikelola sebagai kode (`infra/`) bila memungkinkan, agar server dapat dibangun ulang (52.59).

------------------------------------------------------------------------

## 52.45 Staging Server Configuration

(Checklist 66.) Staging menirukan production dalam versi software dan topologi proses (52.35), dengan perbedaan yang disengaja berikut:

| Aspek | Staging | Production |
|---|---|---|
| Server | **VPS terpisah** dari production | VPS production |
| Data | Sintetis atau anonim | Data pelanggan nyata |
| Kredensial | Sandbox/uji saja | Kunci produksi |
| Domain/callback | Domain staging, callback terpisah | Domain produksi |
| Akses | Dibatasi (autentikasi tambahan atau pembatasan IP/Cloudflare Access), tidak diindeks | Publik sesuai produk |
| Notifikasi | Email ke sink, WhatsApp/sosial sandbox | Notifikasi nyata |
| Kapasitas | Boleh lebih kecil | Sesuai benchmark |
| Deployment | Otomatis dari `develop` dan `release/*` | Tag + approval |
| SSH deploy key | Kunci staging sendiri | Kunci production sendiri |

Penempatan staging dan production pada satu VPS **tidak direkomendasikan**: beban uji, kredensial, dan kegagalan staging tidak boleh memengaruhi production. Bila terpaksa untuk tahap awal, pemisahan user, database, direktori, dan resource limit wajib, dan pengecualian dicatat pada Bab 54.7.

------------------------------------------------------------------------

## 52.46 Deployment Directory

(Checklist 67.) Struktur direktori **rekomendasi** (path final **[BELUM DITETAPKAN]**), mendukung deployment atomik dan rollback cepat:

```text
/var/www/krafio/                  # path ilustratif
├── releases/
│   ├── 20261110-1830-<sha>/       # satu rilis imutabel per deployment
│   │   ├── backend/               # Laravel + vendor (dibangun di CI)
│   │   ├── frontend/dist/         # hasil build Vite
│   │   └── BUILD_INFO             # versi, SHA, checksum
│   └── ...
├── shared/
│   ├── .env                       # konfigurasi environment (tidak di Git)
│   ├── storage/                   # log, cache, file lokal sementara
│   └── runtime-config.json        # konfigurasi frontend non-rahasia (52.47)
├── current -> releases/<rilis aktif>   # symlink, dialihkan secara atomik
└── artifacts/                     # artefak terunduh sementara + checksum
```

**Ketentuan:**

-   `current` dialihkan **atomik** setelah semua persiapan selesai (52.33); Nginx dan PHP-FPM mengarah ke `current`.
-   Direktori rilis tidak diubah setelah dibuat; perubahan berarti rilis baru.
-   Beberapa rilis terakhir dipertahankan untuk rollback (jumlah **[BELUM DITETAPKAN]**, 52.36); rilis lebih lama dibersihkan oleh pipeline.
-   `shared/` tidak berada pada artefak dan tidak ditimpa deployment.
-   Kepemilikan dan permission mengikuti 52.49.

------------------------------------------------------------------------

## 52.47 Environment Configuration

(Checklist 50, 68.)

-   `.env` per environment dibuat saat **provisioning server**, disimpan di `shared/.env` dengan permission ketat; pipeline tidak membuat atau menimpanya dengan nilai dari repository.
-   Daftar variabel mengikuti Bab 48; `.env.example` adalah acuan nama. Nilai rahasia berada di secret manager atau berkas production ber-permission ketat.
-   **Validasi konfigurasi** pada deployment (52.33 langkah 5): skrip memeriksa bahwa variabel wajib ada dan valid, dan melaporkan **nama** yang hilang tanpa menampilkan nilai. Aplikasi juga gagal cepat saat boot (52.20).
-   `config:cache` dijalankan di server **setelah** `.env` tersedia; `env()` hanya dipanggil dari `config/` (52.7).
-   **Konfigurasi frontend dan prinsip "build sekali":** 52.32 menetapkan satu artefak untuk staging dan production, sedangkan nilai seperti URL API dan site key Turnstile berbeda per environment. Penyelesaian: variabel `VITE_*` yang berbeda antar environment **tidak dibakukan saat build**, melainkan dibaca saat runtime dari `runtime-config.json` yang dihasilkan pipeline per environment dari **nilai non-rahasia** (variabel Actions) dan dilayani oleh Nginx. `VITE_*` saat build hanya untuk nilai yang sama pada semua environment. Semua nilai yang terlihat publik tetap non-rahasia (Bab 48).
-   Perubahan konfigurasi non-secret melalui PR; perubahan secret melalui prosedur secret manager dengan audit trail (52.20).
-   Credential produksi tidak boleh ada pada staging atau lokal (Bab 34.2).

------------------------------------------------------------------------

## 52.48 Database Deployment

(Checklist 69.) Melengkapi 52.11 dan 52.33.

**Urutan:**

1.  Evaluasi migration pada rilis: *expand-only* atau berisiko (tabel besar, lock panjang, ubah tipe, drop).
2.  Untuk migration berisiko: backup pra-deploy yang diverifikasi dan **rehearsal di staging** terhadap salinan data yang dianonimkan (52.35).
3.  Migration dijalankan **satu kali dari satu node** (mis. `php artisan migrate --force`, dengan opsi isolasi/lock agar tidak berjalan paralel) setelah artefak terpasang dan sebelum `current` dialihkan.
4.  Hasil (migration yang dijalankan, durasi, error) masuk deployment record (52.42).
5.  Bila migration gagal: **hentikan deployment**, jangan alihkan `current`, evaluasi keadaan skema sebelum tindakan apa pun (52.36).

**Ketentuan:**

-   Migration harus kompatibel dengan kode **lama** selama jendela deployment dan rollback (expand/contract, 52.11); migration destruktif berada pada rilis terpisah dari kode yang bergantung padanya (52.34).
-   Pemisahan hak: user **migration** (DDL) berbeda dari user **runtime aplikasi** (DML saja); kredensial migration hanya tersedia saat deployment.
-   Mode pemeliharaan (maintenance mode) hanya dipakai bila perubahan benar-benar menuntutnya, dengan pengumuman (Bab 47); webhook pembayaran selama pemeliharaan harus tetap dapat diterima atau diulang penyedia (Bab 31.5).
-   Seeder **reference** dijalankan melalui perintah eksplisit dan idempotent; seeder demo dilarang di production (52.12). Akun Super Admin pertama dibuat dengan perintah terkontrol (52.12).
-   Setelah migration, verifikasi: jumlah tabel/kolom kritis, constraint, dan (untuk rilis finansial) pemeriksaan **ledger balance** (Bab 38.3).

------------------------------------------------------------------------

## 52.49 Storage Permission

(Checklist 70.)

| Item | Ketentuan |
|---|---|
| Kepemilikan | Berkas rilis dimiliki user `deploy` dan dibaca oleh grup layanan web; tidak ada permission 777 |
| Direktori tulis | Hanya `shared/storage` dan cache bootstrap yang dapat ditulis oleh user PHP-FPM/worker |
| Berkas rilis | Hanya-baca bagi proses web; kode tidak dapat menimpa dirinya sendiri |
| `.env` | Permission ketat (hanya pemilik/grup aplikasi) dan tidak dilayani Nginx |
| Upload pengguna | Tidak disimpan di webroot; file privat di object storage dengan signed URL (Bab 10.4); direktori sementara di luar webroot dengan kuota |
| Eksekusi | Direktori upload/temporary tidak dapat mengeksekusi skrip (Nginx menolak eksekusi PHP di jalur selain entry point) |
| Berkas tersembunyi | Akses ke dotfile, `.git`, backup, dan berkas konfigurasi ditolak oleh Nginx (52.52) |
| Pembersihan | Berkas temporary render dan unduhan dibersihkan terjadwal; pemantauan penggunaan disk (Bab 36.1) |
| Pengaturan setelah deploy | Skrip deploy menetapkan ulang kepemilikan dan permission secara deterministik dan idempotent (52.33 langkah 4) |

------------------------------------------------------------------------

## 52.50 Queue Worker Deployment

(Checklist 71.) Mengikuti Bab 49 dan 52.13.

-   Horizon/queue worker berjalan di bawah Supervisor atau systemd, aktif otomatis setelah reboot, dengan pengelompokan queue (`critical`, `notifications`, `webhooks`, `ai-text`, `ai-image`, `video-render`, `seo-crawl`, `social-publish`, `exports`).
-   **Restart graceful** setelah `current` dialihkan: worker menyelesaikan job berjalan lalu dimulai ulang dengan kode baru (mis. perintah terminate Horizon dan restart oleh supervisor). Batas waktu henti (`stopwaitsecs`) lebih besar daripada timeout job terpanjang di grup tersebut; `retry_after` lebih besar daripada timeout job.
-   Job lama di antrean harus tetap dapat diproses kode baru; payload kompatibel mundur (52.13, 52.36).
-   Worker berat (render video, AI image) berjalan pada proses/node terpisah dan tidak berbagi batas resource dengan `critical` dan `webhooks`.
-   Worker non-PHP (`workers/`) dijalankan sebagai container dengan batas resource dan tag versi/SHA (52.32); pembaruan mengikuti pola graceful yang sama.
-   Setelah deployment: status worker, queue depth, failed jobs, dan heartbeat diperiksa (52.54). Lonjakan failed jobs memicu rollback atau investigasi (52.36).
-   Pemulihan setelah deployment gagal atau reboot: supervisor menjalankan ulang worker otomatis; antrean yang tertahan diproses ulang tanpa menggandakan efek (idempotency, Bab 38.2 butir 10–11).
-   Redis queue bukan satu-satunya catatan job penting; job tercatat di database dan dapat direkonsiliasi (Bab 43.5).

------------------------------------------------------------------------

## 52.51 Scheduler Deployment

(Checklist 72.)

-   Scheduler Laravel dijalankan **tunggal per environment**: satu entri cron (`schedule:run` setiap menit) pada satu node yang ditetapkan, atau mekanisme *only-on-one-server* dengan lock terdistribusi (Redis) bila ada beberapa node (Bab 49).
-   Deployment **tidak menggandakan** scheduler: skrip memastikan hanya satu mekanisme aktif dan tidak menambahkan cron berulang pada setiap deploy.
-   Tugas terjadwal yang telah disebut dalam PRD dicatat pada daftar tugas scheduler: rekonsiliasi pembayaran dan ledger (Bab 31.5), kedaluwarsa langganan dan reservasi kuota, token sosial kedaluwarsa, pembersihan, serta `backup-verify` (52.55–52.56).
-   Tugas bersifat idempotent dengan `withoutOverlapping` atau lock setara dan batas waktu.
-   **Heartbeat scheduler**: waktu eksekusi terakhir dipantau; keterlambatan memicu alert dan terlihat pada health check (52.54).
-   Setelah server pulih atau deployment selesai, scheduler dipastikan aktif sebelum deployment dinyatakan berhasil.

------------------------------------------------------------------------

## 52.52 Nginx Configuration

(Checklist 73.) Konfigurasi dikelola di `infra/` dan divalidasi (`nginx -t`) sebelum reload (52.33). Contoh berikut **ilustratif**, bukan konfigurasi final.

```nginx
# Ilustrasi: blok server untuk aplikasi/API (nilai disesuaikan saat implementasi)
server {
    listen 443 ssl http2;
    server_name app.example.invalid;            # domain final BELUM DITETAPKAN

    root /var/www/krafio/current/frontend/dist;
    index index.html;

    # Header keamanan (nilai CSP disusun saat implementasi)
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    # Konfigurasi runtime frontend (52.47)
    location = /runtime-config.json {
        alias /var/www/krafio/shared/runtime-config.json;
        add_header Cache-Control "no-store";
    }

    location / { try_files $uri /index.html; }

    location /api/ {
        root /var/www/krafio/current/backend/public;
        try_files $uri /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME /var/www/krafio/current/backend/public/index.php;
        fastcgi_pass unix:/run/php/php-fpm.sock;
    }

    # Tolak berkas tersembunyi dan berkas konfigurasi
    location ~ /\.(?!well-known) { deny all; }
}
```

**Ketentuan:**

-   Server block terpisah untuk frontpage, app, admin, dan API sesuai rancangan domain Bab 34.3; subdomain admin bukan pengganti autentikasi (Bab 34.3) dan dapat diberi lapisan tambahan (Cloudflare Access/pembatasan IP) sebagai opsi.
-   IP klien asli dibaca dari header Cloudflare hanya untuk permintaan dari rentang Cloudflare yang sah (daftar rentang diperbarui, tidak dikodekan permanen).
-   `client_max_body_size` dan timeout ditetapkan per rute (unggah media vs API biasa) sesuai Bab 10.4; rate limiting dasar pada rute sensitif melengkapi Cloudflare dan Laravel.
-   Webhook pembayaran/WhatsApp/sosial memakai rute terpisah dengan batas ukuran dan tanpa cache.
-   Konfigurasi berubah melalui PR (52.23: `infra/` adalah path sensitif); reload hanya setelah `nginx -t` lulus.
-   Respons error tidak membocorkan versi server atau detail internal (Bab 33.3).

------------------------------------------------------------------------

## 52.53 SSL/TLS Configuration

(Checklist 74.)

-   Cloudflare pada mode **Full (strict)**: origin memiliki sertifikat valid (Cloudflare Origin Certificate atau sertifikat dari CA publik). Mode yang tidak memverifikasi origin tidak dipakai di production.
-   TLS minimum 1.2 (1.3 diaktifkan); cipher suite modern; HSTS diaktifkan setelah verifikasi bahwa seluruh subdomain mendukung HTTPS, dengan masa berlaku bertahap.
-   Pembaruan sertifikat otomatis bila memakai CA publik; **alert kedaluwarsa** pada ambang bertahap (mis. 30/14/7 hari; nilai final **[BELUM DITETAPKAN]**) dan pemeriksaan di health check eksternal (52.54).
-   Kunci privat sertifikat disimpan di server dengan permission ketat dan tidak pernah di repository atau artefak (52.32).
-   PWA mensyaratkan HTTPS (Bab 8.1); staging juga memakai HTTPS.
-   Domain kustom dan SSL untuk website pelanggan dari Website Builder (Bab 28.4) adalah **fitur tersendiri**; pendekatannya bergantung pada keputusan hosting (Bab 41 butir 17) dan tidak diasumsikan di bab ini.
-   Koneksi MySQL/Redis yang melintasi jaringan menggunakan TLS (Bab 43.5).

------------------------------------------------------------------------

## 52.54 Health Check

(Checklist 75.) Health check adalah **gerbang keberhasilan deployment** dan dasar rollback otomatis (52.36).

**Endpoint internal:**

| Endpoint | Fungsi | Isi |
|---|---|---|
| `/health/live` | Proses hidup | Status sederhana |
| `/health/ready` | Siap melayani | Database, Redis, penyimpanan, status migration, heartbeat queue dan scheduler |
| `/health/version` | Metadata build | Versi dan commit SHA (52.25) |

Endpoint **tidak membuka detail sensitif** (kredensial, topologi, pesan error mentah), dibatasi akses (internal/token/IP) dan tidak di-cache. Halaman versi pada Admin bersifat internal (52.25).

**Smoke test setelah deploy** (dijalankan pada staging dan production): halaman utama dan aset termuat; login dan sesi; satu endpoint API baca publik dan terautentikasi; antrean dapat memproses job uji; webhook uji (sandbox/mode uji) diterima dan idempotent; scheduler berjalan; versi yang tampil sama dengan rilis; tidak ada lonjakan error pada log.

**Kriteria dan aksi:**

-   Health check diulang dengan jeda dan batas percobaan; kegagalan melewati batas memicu **rollback otomatis** (52.36).
-   Smoke test pada production tidak membuat transaksi finansial nyata dan tidak mengirim notifikasi ke pelanggan.
-   Setelah lulus, dimulai **jendela pemantauan** (error rate, queue depth, failed jobs, latensi, webhook pembayaran; durasi **[BELUM DITETAPKAN]**, 52.34).
-   Uptime monitor eksternal memeriksa domain publik, sertifikat, dan endpoint kunci secara terus-menerus (Bab 36.1).
-   Hasil dicatat pada deployment record dan notifikasi (52.42, 52.33).

------------------------------------------------------------------------

## 52.55 Backup Source Code

(Checklist 76.) GitHub bukan satu-satunya salinan source code (52.1).

| Komponen | Metode | Catatan |
|---|---|---|
| Git (branch, tag, riwayat) | **Mirror terjadwal** (clone mirror atau bundle) ke lokasi di luar GitHub: layanan Git lain atau object storage, terenkripsi | Lokasi dan jadwal **[BELUM DITETAPKAN]**; frekuensi mengikuti toleransi kehilangan perubahan |
| Metadata (issue, PR, review, release notes, wiki) | Ekspor berkala melalui API/arsip GitHub | Git mirror tidak memuat PR dan issue |
| Artefak rilis final | Arsip jangka panjang di object storage dengan checksum | 52.32; memungkinkan deploy tanpa GitHub (52.60) |
| Konfigurasi GitHub (ruleset, environment, pengaturan Actions) | Didokumentasikan/diekspor bila memungkinkan | 52.58 |

**Ketentuan:**

-   `backup-verify.yml` memverifikasi bahwa mirror terbaru memuat semua ref dan SHA `main`, `develop`, dan tag rilis yang sama dengan GitHub (52.27).
-   Kredensial mirror terbatas hak, tidak dapat menghapus arsip, dan disimpan sebagai secret terpisah (52.39).
-   Clone lokal developer **bukan** backup yang diandalkan.
-   Retensi arsip mengikuti kebijakan yang ditetapkan **[MENUNGGU KEPUTUSAN]** dan tidak boleh lebih pendek daripada kebutuhan rollback dan audit.

------------------------------------------------------------------------

## 52.56 Backup Database

(Checklist 77.) Mengikuti Bab 15.3, Bab 34.6, dan Bab 43.4.

-   **Metode:** kombinasi backup penuh terjadwal (dump logis atau backup fisik sesuai ukuran data) dan **binary log** untuk *point-in-time recovery* bila tersedia; backup pra-deploy untuk rilis dengan migration berisiko (52.48).
-   **Frekuensi, retensi, RPO, dan RTO** **[MENUNGGU KEPUTUSAN]** (Bab 41 butir 19); tidak ditetapkan sepihak. Jadwal backup harus mendukung RPO yang dipilih.
-   Backup **terenkripsi** dan disimpan **di luar server database** (object storage terpisah), dengan akses terbatas dan pencatatan akses.
-   Setiap backup memiliki ID, waktu, ukuran, checksum, dan hasil verifikasi; kegagalan backup memicu alert (Bab 36.1).
-   **Uji restore terjadwal** ke lingkungan terisolasi: restore penuh, pemeriksaan integritas, dan untuk data keuangan pemeriksaan **ledger balance** (Bab 38.3). **Backup tidak dianggap valid sebelum pernah diuji restore** (Bab 34.6).
-   Bukti uji restore (tanggal, backup yang diuji, durasi, hasil, penanggung jawab) disimpan dan dilampirkan pada approval production (52.26, 52.34).
-   Redis bukan sumber kebenaran; kehilangan Redis ditangani dengan rekonsiliasi dari database (Bab 43.5), namun konfigurasi persistensi Redis tetap ditetapkan sesuai kebutuhan queue.
-   Data staging bukan hasil salinan production tanpa anonimisasi (52.35).

------------------------------------------------------------------------

## 52.57 Backup Uploaded Assets

(Checklist 78.)

-   Aset pengguna dan hasil generate berada di object storage (Bab 43.4); strategi backup bergantung pada fitur penyedia: **versioning**, perlindungan penghapusan, dan **replikasi** ke bucket/wilayah/penyedia lain **[penyedia BELUM DITETAPKAN]**.
-   Kebijakan lifecycle (kapan file dipindah ke penyimpanan murah atau dihapus) mengikuti retensi data (Bab 41 butir 18) dan perlu dihitung biayanya, terutama untuk video; retensi per jenis aset **[MENUNGGU KEPUTUSAN]**.
-   Konsistensi dengan database: metadata `file_records` dan referensi aset harus dapat direkonsiliasi dengan isi bucket; pemeriksaan terjadwal melaporkan berkas yatim atau hilang.
-   Soft delete dan masa pemulihan sesuai Bab 25.3; penghapusan permanen atas permintaan pengguna tidak boleh dibatalkan oleh backup yang dipertahankan melebihi kebijakan; masa kedaluwarsa backup dijelaskan pada kebijakan privasi (Bab 35.4).
-   Uji pemulihan sampel (restore file acak dan verifikasi checksum) dilakukan terjadwal bersama uji restore database (52.56).
-   Hak tulis dan hapus bucket backup dibatasi; kredensial berbeda dari kredensial aplikasi.

------------------------------------------------------------------------

## 52.58 Backup Configuration

(Checklist 79.)

| Konfigurasi | Penyimpanan cadangan |
|---|---|
| Kode konfigurasi non-secret (`infra/`, `backend/config/`, template Nginx/Supervisor) | Git dan mirror (52.55) |
| Salinan konfigurasi aktual server (Nginx, PHP, Supervisor/systemd, crontab, firewall) | Snapshot non-secret terjadwal ke object storage terenkripsi |
| **Secret** (`.env` production, kredensial pembayaran/AI/WhatsApp/sosial/SMTP, `APP_KEY`) | **Secret manager atau vault terenkripsi** dengan akses minimal dua pemegang berwenang; **tidak** disalin sebagai teks biasa ke bucket backup umum (Bab 34.6) |
| Konfigurasi DNS dan Cloudflare (record, WAF, rate limit, Turnstile) | Ekspor atau dokumentasi terstruktur |
| Konfigurasi GitHub (ruleset, environment, pengaturan Actions, daftar tim) | Dokumentasi/ekspor dan register Bab 54.7 |
| Konfigurasi provider eksternal (callback URL, webhook secret, template WhatsApp) | Inventaris di runbook; secret di vault |
| Kode pemulihan akun Owner dan akun kritis | Penyimpanan offline di luar GitHub (52.4) |

`APP_KEY` dan kunci enkripsi token sosial/API harus dapat dipulihkan; kehilangannya berarti data terenkripsi tidak dapat dibaca. Prosedur rotasi kunci mempertimbangkan data yang sudah terenkripsi.

------------------------------------------------------------------------

## 52.59 Recovery Procedure

(Checklist 80.) Prosedur pemulihan ditulis sebagai runbook di `docs/` (52.66) dan dilatih berkala. Urutan umum: **deteksi → tetapkan penanggung jawab → komunikasi → tindakan → verifikasi → tinjauan pasca-insiden**.

| Skenario | Tindakan utama |
|---|---|
| Deployment gagal (health check gagal) | Rollback kode otomatis atau manual (52.36); verifikasi smoke test |
| Migration gagal sebagian | Hentikan; jangan alihkan `current`; evaluasi skema; *forward fix* atau restore (52.48, 52.36) |
| Data database rusak atau terhapus tidak sengaja | Hentikan penulisan terkait; restore ke titik waktu (PITR) ke lingkungan terpisah; validasi; ekstrak/pulihkan data; verifikasi ledger |
| VPS aplikasi hilang atau tidak dapat diakses | Bangun server baru dari konfigurasi `infra/`; pasang rilis terakhir dari arsip artefak; pulihkan `.env` dari secret manager; sambungkan DB/Redis/storage; jalankan health check |
| Worker atau scheduler tidak berjalan | Periksa supervisor/systemd, restart, verifikasi heartbeat; proses ulang job tertahan secara idempotent (52.50, 52.51) |
| Redis hilang | Pulihkan layanan; rekonsiliasi job dari tabel database; lanjutkan queue (Bab 43.5) |
| Aset hilang atau terhapus | Pulihkan dari versioning/replikasi (52.57); rekonsiliasi metadata |
| Secret bocor atau diduga bocor | Prosedur 52.37: rotasi, tinjau penyalahgunaan, catat insiden |
| Kunci SSH deploy dikompromikan | Cabut kunci, audit log server, buat kunci baru, tinjau perubahan yang masuk (52.40) |
| Akun Owner GitHub tidak dapat diakses | Pemulihan melalui Owner kedua dan kode pemulihan offline (52.4) |

**Urutan pemulihan sistem penuh:** (1) infrastruktur dasar dan jaringan; (2) database dan verifikasi; (3) Redis; (4) object storage; (5) aplikasi dan konfigurasi; (6) worker dan scheduler; (7) health check; (8) buka akses bertahap; (9) **rekonsiliasi pembayaran dan webhook** karena penyedia dapat mengirim ulang event (Bab 31.5, Bab 38.2 butir 4); (10) komunikasi kepada pengguna bila perlu (Bab 47).

Setelah pemulihan: tinjauan pasca-insiden, pembaruan runbook, dan, bila terkait keuangan, rekonsiliasi hasil pada Admin Panel (Bab 31.5).

------------------------------------------------------------------------

## 52.60 Disaster Recovery

(Checklist 81.) Melengkapi Bab 15.3 dan Bab 34.6.

-   **RPO dan RTO** per komponen **[MENUNGGU KEPUTUSAN]** (Bab 41 butir 19). Tanpa nilai tersebut, rancangan backup dan latihan pemulihan tidak dapat dinyatakan memenuhi target.
-   **Kemandirian dari GitHub:** pemulihan darurat tidak boleh bergantung pada ketersediaan GitHub (52.1). Artefak rilis dan mirror kode tersimpan di luar GitHub (52.32, 52.55), dan runbook menyediakan **jalur deploy manual dari arsip artefak** untuk keadaan GitHub tidak tersedia; penggunaannya dicatat sebagai tindakan insiden (52.33).
-   **Skenario bencana yang dicakup:** kehilangan server atau penyedia VPS; kerusakan atau penghapusan data; kompromi kredensial atau ransomware (backup dengan salinan yang tidak dapat diubah/akses terpisah); GitHub tidak tersedia; provider pembayaran, AI, atau sosial tidak tersedia (fallback Bab 7, adapter terpisah Bab 30.6); kehilangan akun Owner atau domain.
-   **Dokumentasi DR disimpan di luar GitHub** (salinan offline atau penyimpanan terpisah) agar dapat diakses saat GitHub dan server tidak tersedia.
-   **Pohon eskalasi dan kontak insiden** **[BELUM DITETAPKAN]** (Bab 41 butir 20).
-   **Latihan DR** (restore penuh ke lingkungan baru, latihan rollback, latihan GitHub-tidak-tersedia) dijalankan berkala dengan frekuensi **[BELUM DITETAPKAN]** dan sebelum rilis mayor; hasil dan perbaikan dicatat.
-   Dependensi eksternal yang memengaruhi DR dicatat: DNS/Cloudflare, domain registrar, payment gateway, penyedia email/WhatsApp, penyedia AI.

------------------------------------------------------------------------

## 52.61 Installation Documentation

(Checklist 82.) Dokumentasi developer berada di `docs/` (versi bersama kode) dan diperbarui pada PR yang mengubah perilakunya (52.23 butir 10). Setiap dokumen memiliki pemilik dan tanggal tinjau.

`docs/installation.md` memuat minimal:

-   Prasyarat dan versi: PHP, Composer, Node.js, package manager, MySQL 8.4, Redis, Docker (bila dipakai); versi dikunci (52.6, 52.7).
-   Langkah instalasi dari clone sampai aplikasi berjalan, termasuk instalasi dependency berbasis lockfile.
-   Layanan pendukung (MySQL, Redis, mail sink) dan cara menjalankannya.
-   Verifikasi instalasi (health check, test dasar).
-   Masalah instalasi umum dan rujukan ke 52.67.

------------------------------------------------------------------------

## 52.62 Local Development Documentation

(Checklist 83.) `docs/local-development.md`:

-   Cara menjalankan frontend, backend, queue worker, dan scheduler secara lokal; opsi Docker Compose (Bab 43.1).
-   Penggunaan data seeder demo (hanya lokal, 52.12) dan akun uji; tidak ada data pelanggan nyata.
-   Mode sandbox/mock untuk pembayaran, AI, WhatsApp, dan sosial; **tidak memakai kredensial produksi** (Bab 34.2).
-   Menjalankan test, lint, static analysis yang sama dengan CI (52.28–52.30); hook pra-commit.
-   Alur kerja branch dan PR (52.21, 52.22) serta konvensi commit (52.24).
-   Debugging, log, dan cara melihat antrean.
-   Catatan bahwa lingkungan lokal tidak mengasumsikan GPU; fitur yang memerlukan GPU memakai mock atau provider sandbox.

------------------------------------------------------------------------

## 52.63 Environment Setup Documentation

(Checklist 84.) `docs/environment-setup.md`:

-   Daftar variabel lingkungan dan artinya (rujukan Bab 48 dan `.env.example`), ditandai publik/rahasia.
-   Perbedaan konfigurasi lokal, CI, staging, dan production (52.45).
-   Cara memperoleh nilai: secret manager atau pemilik secret; **nilai tidak ditulis di dokumen**.
-   Konfigurasi frontend runtime (52.47).
-   Validasi konfigurasi dan pesan galat umum.
-   Prosedur menambah variabel baru: update `.env.example`, validasi boot, dokumentasi, dan PR (52.22).

------------------------------------------------------------------------

## 52.64 Database Setup Documentation

(Checklist 85.) `docs/database-setup.md`:

-   Pembuatan database dan user (hak runtime vs migration, 52.48), charset/collation, dan pengaturan MySQL 8.4 yang disyaratkan.
-   Menjalankan migration dan seeder reference; membedakan seeder demo (52.12).
-   Pembuatan Super Admin pertama dengan perintah terkontrol dan MFA (52.12, Bab 35.3).
-   Aturan perubahan skema: expand/contract, larangan mengedit migration yang telah dirilis (52.11).
-   Referensi ERD dan baseline entitas (Bab 11.3, Bab 32).
-   Backup dan restore lokal untuk pengujian; data dianonimkan (52.35).

------------------------------------------------------------------------

## 52.65 API Documentation

(Checklist 86.) Selaras dengan 52.10 dan Bab 33.

-   Sumber kebenaran: spesifikasi **OpenAPI** di `docs/openapi/`, divalidasi dan diperiksa breaking change di CI (52.27, 52.29).
-   Dokumentasi interaktif dirender dari spesifikasi dan dipublikasikan pada lokasi yang aksesnya dikendalikan **[BELUM DITETAPKAN]**; endpoint Admin dan internal tidak dipublikasikan ke publik.
-   Memuat: autentikasi (Sanctum cookie/CSRF, Bab 43.2), format response dan error (Bab 33.3), pagination/filter, rate limit, idempotency key, kontrak webhook (tanda tangan dan idempotency), contoh request/response tanpa data nyata.
-   Kebijakan versi API `/api/v1/` dan masa transisi perubahan yang merusak (52.10, 52.25); changelog API di `CHANGELOG.md`.
-   Klien API frontend dibangkitkan dari spesifikasi (52.6).

------------------------------------------------------------------------

## 52.66 Deployment Documentation

(Checklist 87.) `docs/deployment.md` dan runbook terkait:

-   Arsitektur deployment dan topologi (52.43–52.45), model push/pull dan keputusan final (Bab 54.7).
-   Alur pipeline dan environment, approval production (52.33–52.35).
-   Langkah provisioning server baru; pengaturan user `deploy`, SSH, firewall (52.40, 52.44).
-   Daftar secret dan variabel yang dibutuhkan pipeline (nama saja, 52.39).
-   Prosedur rollback, *forward fix*, dan keputusan terkait migration (52.36, 52.48).
-   Prosedur pemulihan dan DR (52.59, 52.60), termasuk jalur deploy manual dari arsip.
-   Checklist pra-rilis dan pasca-deploy (52.26, 52.34, 52.54).
-   Disimpan juga salinan di luar GitHub (52.60).

------------------------------------------------------------------------

## 52.67 Troubleshooting Documentation

(Checklist 88.) `docs/troubleshooting.md` berisi pola masalah beserta gejala, penyebab umum, dan tindakan. Contoh daftar awal:

| Gejala | Penyebab umum | Tindakan awal |
|---|---|---|
| Error 500 setelah deploy | Config cache usang, `.env` tidak lengkap, permission | Periksa log aplikasi, validasi konfigurasi (52.47), bangun ulang cache, periksa permission (52.49) |
| Job menumpuk atau tidak diproses | Worker mati, Redis bermasalah, queue salah | Cek supervisor/Horizon, heartbeat, konektivitas Redis (52.50) |
| Scheduler tidak berjalan atau berjalan ganda | Cron hilang atau ganda | Verifikasi mekanisme tunggal dan heartbeat (52.51) |
| Migration gagal atau terkunci | Lock panjang, migration paralel, tipe data | Hentikan deploy; evaluasi skema (52.48) |
| Login SPA gagal (cookie/CSRF) | Domain cookie, SameSite, URL frontend/API salah | Periksa konfigurasi Sanctum dan domain (Bab 43.2) |
| Webhook ditolak | Signature/secret salah, waktu tidak sinkron, rute dicache | Periksa secret, log webhook, replay idempotent (Bab 31) |
| Frontend memakai API salah | Konfigurasi runtime keliru | Periksa `runtime-config.json` (52.47) |
| Sertifikat kedaluwarsa atau error SSL | Pembaruan gagal, mode Cloudflare tidak sesuai | Periksa sertifikat origin dan mode Full (strict) (52.53) |
| Disk penuh | Log, berkas temporary, rilis lama | Bersihkan sesuai kebijakan, periksa retensi rilis (52.46) |
| Redis memori penuh | Payload besar, TTL, eviction | Periksa pola key dan kebijakan (Bab 43.5) |
| CI gagal tiba-tiba | Dependency baru, test flaky, versi action | Periksa log, pin versi, catat flaky sebagai issue (52.28) |
| Pipeline tidak dapat terhubung ke server | Host key, kunci, firewall | Periksa known_hosts, kunci, aturan firewall (52.40, 52.44) |

Dokumen diperbarui setelah setiap insiden dan menjadi masukan tinjauan pasca-rilis (52.26).

------------------------------------------------------------------------

## 52.68 Developer Onboarding

(Checklist 89.) `docs/onboarding.md`:

-   **Sebelum hari pertama:** permintaan akses melalui Owner/Admin; akun GitHub dengan 2FA; keanggotaan tim sesuai peran (52.38); akses lain (secret manager, monitoring) sesuai kebutuhan.
-   **Hari pertama:** membaca README, PRD (Bab 43, 52), konvensi, dan `CONTRIBUTING.md`; menjalankan instalasi lokal (52.61–52.64).
-   **Minggu pertama:** PR pertama yang kecil melalui alur lengkap (branch → PR → CI → review → merge, 52.22); memahami area sensitif dan aturan review ganda (52.23); menjalankan test kritis (Bab 38.2).
-   Pengenalan prinsip kunci: tenant isolation, entitlement di backend, transaksi finansial, tidak ada secret di kode, idempotency.
-   Daftar kontak: pemilik modul (CODEOWNERS), reviewer Security/Finance/Infra, kanal operasional **[BELUM DITETAPKAN]**.
-   Offboarding checklist mengikuti 52.38.

------------------------------------------------------------------------

## 52.69 Release Management Documentation

(Checklist 90.) `docs/release-management.md`:

-   Alur rilis reguler dan hotfix (52.26) beserta peran dan penanggung jawab **[BELUM DITETAPKAN]**.
-   Penomoran versi dan tag (52.25); template release notes dan `CHANGELOG.md`.
-   Checklist release gate dan pemetaan kontrol (52.26, Bab 38.3).
-   Prosedur approval production (52.34), jendela rilis **[BELUM DITETAPKAN]**, dan jendela pemantauan.
-   Prosedur rollback dan pencatatan (52.36).
-   Kebijakan rilis bermasalah: ditandai, bukan dihapus (52.26).
-   Format tinjauan pasca-rilis.

------------------------------------------------------------------------

## 52.70 Acceptance Criteria

Bab 52 dianggap diterapkan dengan benar apabila seluruh butir berikut dapat dibuktikan. Status awal setiap butir adalah **belum terpenuhi** sampai ada bukti.

**Repository dan alur kerja**

-   [ ] Identitas repository utama ditetapkan dan tercatat di 52.2, `README.md`, dan Bab 54.7; tidak ada nilai karangan.
-   [ ] Berkas standar root ada (`README.md`, `CONTRIBUTING.md`, `CHANGELOG.md`, `CODE_OF_CONDUCT.md`, `SECURITY.md`, `.env.example`, `.github/*`).
-   [ ] Branch protection/ruleset aktif sesuai 52.21 dan ketersediaannya per plan telah diverifikasi.
-   [ ] Tidak ada push langsung ke `main`, `develop`, atau `release/*`; force push dan penghapusan dilarang.
-   [ ] PR template, CODEOWNERS, dan aturan dua approval pada path sensitif berfungsi.
-   [ ] Judul PR/commit diperiksa CI sesuai Conventional Commits.

**CI/CD**

-   [ ] Workflow inventaris 52.27 tersedia; `permissions` default read-only; action dipin SHA.
-   [ ] Required checks (test, kualitas, analisis statik, scan dependency, E2E untuk `release/*` dan `main`) memblokir merge.
-   [ ] Suite skenario kritis Bab 38.2 menjadi required check.
-   [ ] Build menghasilkan artefak imutabel ber-checksum dan gagal bila memuat `.env`/secret.
-   [ ] Deployment staging otomatis dan production hanya melalui tag + approval environment.
-   [ ] Health check dan smoke test berjalan; kegagalan memicu rollback.
-   [ ] Rollback telah dilatih di staging.

**Keamanan dan akses**

-   [ ] 2FA wajib; minimal dua Owner; akses melalui tim dengan least privilege.
-   [ ] Secret scanning dan push protection aktif atau kompensasi tercatat.
-   [ ] Inventaris secret, kunci SSH deploy per environment, dan register PAT tersedia.
-   [ ] Tidak ada kredensial produksi di repository, artefak, atau frontend.
-   [ ] Audit log GitHub dan deployment record disimpan sesuai kebijakan retensi.

**VPS dan operasi**

-   [ ] Server production dan staging terpisah dengan konfigurasi sesuai 52.44–52.45.
-   [ ] Struktur release/symlink, permission, Nginx, TLS, worker, dan scheduler tunggal berfungsi.
-   [ ] Migration dijalankan satu kali dengan strategi expand/contract dan pemisahan hak DB.

**Backup dan pemulihan**

-   [ ] Mirror kode, arsip artefak, backup database, backup aset, dan backup konfigurasi berjalan.
-   [ ] Uji restore database berhasil dan bukti tersedia; ledger balance terverifikasi.
-   [ ] Runbook pemulihan dan DR tersedia di luar GitHub; RPO/RTO ditetapkan atau tercatat sebagai keputusan tertunda.

**Dokumentasi**

-   [ ] Dokumen 52.61–52.69 tersedia di `docs/`, dengan pemilik dan tanggal tinjau.
-   [ ] OpenAPI tervalidasi dan sinkron dengan implementasi.

------------------------------------------------------------------------

------------------------------------------------------------------------

# 53. Repository Eksternal: Register, Audit, dan Metode Integrasi

Bab ini melengkapi Bab 12 (Rekomendasi Pemanfaatan Repository/Open Source) dan Bab 37 (Repository dan Open-Source Integration Register). Bab ini **tidak menggantikan** keduanya. Daftar 25 kandidat pada Bab 37.1 tetap berlaku; Bab 53 menambahkan **URL repository yang teridentifikasi, atribut register yang lengkap, sistem audit 20 kriteria, status audit dan implementasi, delapan metode integrasi, kontrak integrasi, isolasi eksekusi kode pihak ketiga, dan kebijakan lisensi**.

Repository eksternal pada bab ini adalah **kategori B** (lihat Bab 52, tabel tiga kategori). Repository eksternal tidak sama dengan repository utama Krafio (Bab 52) dan tidak sama dengan 90 butir checklist GitHub (Bab 54).

------------------------------------------------------------------------

## 53.1 Tujuan dan Hubungan dengan Bab 12 serta Bab 37

| Topik | Requirement yang sudah ada | Penambahan pada Bab 53 |
|---|---|---|
| Prinsip integrasi repository | Bab 12.1 (10 prinsip) | Sistem audit terstruktur 20 kriteria (53.4) dan siklus status (53.5) |
| Daftar kandidat | Bab 37.1 (25 kandidat, peran, pola, pemeriksaan wajib) | Register lengkap dengan URL, teknologi, lisensi, maintenance, risiko, dan status (53.3) |
| Pola integrasi | Bab 37.2 (empat pola A–D) | Delapan metode integrasi dan pemetaannya ke empat pola (53.6) |
| Checklist audit | Bab 37.3 (16 butir) | Diperluas menjadi 20 kriteria dengan pemetaan balik (53.4) |
| Integration Gate | Bab 37.4 | Gate tetap berlaku; ditambah syarat pengelolaan versi, THIRD_PARTY, dan exit strategy (53.10) |
| Adapter AI | Bab 7, Bab 26.1 | Adapter eksternal hanya di balik AI Gateway (53.6, 53.10) |
| Adapter WhatsApp | Bab 30.2, Bab 46 | Kandidat dashboard WhatsApp wajib berbasis API resmi (53.12) |
| Queue dan worker | Bab 11.5, Bab 49 | Penempatan worker eksternal dan isolasi (53.8, 53.13) |
| Penempatan kode eksternal di repository | Bab 52.5, 52.13, 52.14 | Direktori `THIRD_PARTY` dan aturan vendor (53.10) |

## 53.2 Prinsip Dasar

1.  **Masuk PRD bukan berarti terintegrasi.** Sebuah repository yang tercantum pada register hanya berstatus kandidat. Status **Implemented** tidak boleh diberikan sebelum repository benar-benar dipasang pada environment yang ditentukan dan diuji dengan bukti (53.5).
2.  **Tidak ada identitas yang dikarang.** Repository yang URL atau owner-nya belum pasti ditandai `[BELUM DITETAPKAN]` dan berstatus *Perlu klarifikasi identitas*. Kandidat alternatif tidak boleh dipilih secara otomatis; pemilihan dicatat sebagai keputusan pada Bab 54.7.
3.  **Identitas diverifikasi sebelum audit teknis.** Audit teknis atas repository yang salah menghasilkan keputusan yang salah.
4.  **Lisensi, keamanan, dan kebutuhan resource diperiksa sebelum satu baris kode eksternal dijalankan** pada environment yang terhubung dengan data nyata.
5.  **Kode pihak ketiga tidak dijalankan langsung di proses utama aplikasi** tanpa pemeriksaan keamanan; eksekusi kode eksternal mengikuti isolasi pada 53.8.
6.  **Tidak semua repository menjadi fitur pelanggan.** Beberapa kandidat hanya berfungsi sebagai alat internal developer atau referensi (Bab 37.2 pola D). Satu repository dapat berubah pola setelah audit; semua project tidak dipaksa masuk ke source code utama.
7.  **Tidak mengurangi scope.** Menetapkan sebuah kandidat sebagai *Reference Only* atau *Ditolak* tidak mengurangi komitmen fitur pada Bab 6, 26, 27, 28, 29, dan 30; fitur tersebut tetap dipenuhi dengan komponen lain, pengembangan internal, atau provider resmi.
8.  **Data hasil audit bersifat bukti.** Setiap nilai pada register (lisensi, tanggal commit, rilis, dan seterusnya) wajib mencantumkan sumber, tanggal pengecekan, dan versi/commit yang diperiksa.

## 53.3 Register 25 Repository Kandidat

Register dibagi dua tabel untuk keterbacaan: **Tabel A** (identitas dan teknis) dan **Tabel B** (peran, integrasi, risiko, dan status). Nomor urut sama pada kedua tabel dan sama dengan urutan Bab 37.1.

**Aturan pengisian nilai:**

-   `[BELUM DITETAPKAN]` — identitas repository belum dipastikan.
-   `[BELUM DIVERIFIKASI]` — repository dikenal, tetapi nilai belum diperiksa pada sumber resminya. Nilai ini **tidak boleh diganti dengan dugaan** dan diisi saat audit (53.4).
-   Kolom *Teknologi* hanya memuat **indikasi awal** dan wajib dikonfirmasi saat audit.

**Tabel A — Identitas dan atribut teknis**

| No | Nama | URL repository | Fungsi | Teknologi (indikasi awal) | Lisensi | Status maintenance |
|---|---|---|---|---|---|---|
| 1 | WhatsApp Business API Dashboard | **URL kandidat, belum diverifikasi**: [https://github.com/rmyndharis/OpenWA](https://github.com/rmyndharis/OpenWA) (OpenWA). Ejaan owner diambil dari tangkapan layar beresolusi kecil dan **wajib diverifikasi ulang** | Dashboard pengelolaan WhatsApp Business API, integrasi pesan, webhook, template pesan, dan monitoring komunikasi. *Menurut konten sumber:* proyek open-source self-hosted, multi-session, REST API dan webhook, dashboard manajemen, mendukung Docker dan n8n. Konten sumber sendiri menyatakan proyek ini **bukan API resmi Meta** dan ada risiko pembatasan akun | [BELUM DIVERIFIKASI] | [BELUM DIVERIFIKASI] | [BELUM DIVERIFIKASI] |
| 2 | Concat | [https://github.com/jub0t/Concat](https://github.com/jub0t/Concat) | Referensi engine/editor video dan workflow pengolahan video | [BELUM DIVERIFIKASI] | [BELUM DIVERIFIKASI] | [BELUM DIVERIFIKASI] |
| 3 | Modly | [https://github.com/lightningpixel/modly](https://github.com/lightningpixel/modly) | Referensi modul generasi model 3D | [BELUM DIVERIFIKASI] | [BELUM DIVERIFIKASI] | [BELUM DIVERIFIKASI] |
| 4 | PowerToys | [https://github.com/microsoft/PowerToys](https://github.com/microsoft/PowerToys) | Utilitas produktivitas dan pengembangan internal Windows | Aplikasi desktop Windows (C#/C++) | [BELUM DIVERIFIKASI] | [BELUM DIVERIFIKASI] |
| 5 | Jan | [https://github.com/janhq/jan](https://github.com/janhq/jan) | AI lokal dan alternatif runtime AI | [BELUM DIVERIFIKASI] | [BELUM DIVERIFIKASI] | [BELUM DIVERIFIKASI] |
| 6 | LosslessCut | [https://github.com/mifi/lossless-cut](https://github.com/mifi/lossless-cut) | Pemotongan dan penggabungan video tanpa proses re-encoding penuh | Aplikasi desktop berbasis FFmpeg | [BELUM DIVERIFIKASI] | [BELUM DIVERIFIKASI] |
| 7 | Prompt Optimizer | [https://github.com/linshenkx/prompt-optimizer](https://github.com/linshenkx/prompt-optimizer) | Optimasi, pengujian, dan pengelolaan prompt AI | [BELUM DIVERIFIKASI] | [BELUM DIVERIFIKASI] | [BELUM DIVERIFIKASI] |
| 8 | ComfyUI | [https://github.com/Comfy-Org/ComfyUI](https://github.com/Comfy-Org/ComfyUI) | Engine workflow generasi dan manipulasi gambar berbasis node | Python | [BELUM DIVERIFIKASI] | [BELUM DIVERIFIKASI] |
| 9 | Archify | **URL kandidat, belum diverifikasi**: [https://github.com/tt-ali/archify](https://github.com/tt-ali/archify) | Skill untuk coding agent yang menyusun diagram arsitektur berbentuk HTML interaktif dari deskripsi teks. Konten sumber menyebut diagram yang dihasilkan tetap perlu diverifikasi manual | [BELUM DIVERIFIKASI] | [BELUM DIVERIFIKASI] | [BELUM DIVERIFIKASI] |
| 10 | OpenSEO | **URL kandidat, belum diverifikasi**: [https://github.com/every-app/open-seo](https://github.com/every-app/open-seo) | Dashboard riset SEO (kata kunci, volume, CPC, posisi pencarian, backlink); referensi engine SEO, audit, analisis, dan otomasi SEO. *Menurut konten sumber:* self-host tetap membutuhkan API key DataForSEO berbayar sesuai pemakaian | [BELUM DIVERIFIKASI] | [BELUM DIVERIFIKASI] | [BELUM DIVERIFIKASI] |
| 11 | Screenshot to Code | [https://github.com/abi/screenshot-to-code](https://github.com/abi/screenshot-to-code) | Mengubah screenshot atau desain UI menjadi source code website | [BELUM DIVERIFIKASI] | [BELUM DIVERIFIKASI] | [BELUM DIVERIFIKASI] |
| 12 | FREELLMAPI | [BELUM DITETAPKAN] — **tiga** URL kandidat, belum diverifikasi, tidak ada yang dipilih: (a) [https://github.com/lov0u/freellmapi](https://github.com/lov0u/freellmapi) — daftar awal; (b) [https://github.com/xxy2026/freellmapi](https://github.com/xxy2026/freellmapi) — daftar awal; (c) [https://github.com/tashfeenahmed/freellmapi](https://github.com/tashfeenahmed/freellmapi) — **DIUSULKAN, menunggu verifikasi** (deskripsi konten sumber paling cocok dengan fungsi di PRD) | Abstraksi koneksi LLM dan penyatuan endpoint provider AI. *Menurut konten sumber untuk kandidat (c):* menyatukan kuota model gratis dari beberapa penyedia ke satu endpoint API yang kompatibel, dengan strategi routing berdasarkan reliability, speed, atau intelligence; membutuhkan akun dan API key di tiap penyedia; kuota dan batas dapat berubah | [BELUM DIVERIFIKASI] | [BELUM DIVERIFIKASI] | [BELUM DIVERIFIKASI] |
| 13 | AI Design Skills | [https://github.com/elayadesign/ai-design-skills](https://github.com/elayadesign/ai-design-skills) | Kumpulan skill dan workflow desain berbasis AI | [BELUM DIVERIFIKASI] | [BELUM DIVERIFIKASI] | [BELUM DIVERIFIKASI] |
| 14 | Postiz | [https://github.com/gitroomhq/postiz-app](https://github.com/gitroomhq/postiz-app) | Social media management, scheduling, dan pengelolaan publikasi media sosial | [BELUM DIVERIFIKASI] | [BELUM DIVERIFIKASI] | [BELUM DIVERIFIKASI] |
| 15 | Video Shotcraft | [https://github.com/Vincentwei1021/video-shotcraft](https://github.com/Vincentwei1021/video-shotcraft) | Workflow produksi video berbasis AI dan Remotion | [BELUM DIVERIFIKASI] | [BELUM DIVERIFIKASI] | [BELUM DIVERIFIKASI] |
| 16 | Security Audit Skill | [https://github.com/netresearch/security-audit-skill](https://github.com/netresearch/security-audit-skill) | Audit keamanan aplikasi, dependency, API, dan konfigurasi sistem | [BELUM DIVERIFIKASI] | [BELUM DIVERIFIKASI] | [BELUM DIVERIFIKASI] |
| 17 | Wordcraft | [https://github.com/storytold/wordcraft](https://github.com/storytold/wordcraft) | Referensi pembuatan dan pengolahan dokumen | [BELUM DIVERIFIKASI] | [BELUM DIVERIFIKASI] | [BELUM DIVERIFIKASI] |
| 18 | Deckcraft | [https://github.com/storytold/deckcraft](https://github.com/storytold/deckcraft) | Referensi pembuatan presentasi dan slide deck | [BELUM DIVERIFIKASI] | [BELUM DIVERIFIKASI] | [BELUM DIVERIFIKASI] |
| 19 | Gridcraft | [https://github.com/storytold/gridcraft](https://github.com/storytold/gridcraft) | Referensi pengolahan data berbasis spreadsheet dan grid | [BELUM DIVERIFIKASI] | [BELUM DIVERIFIKASI] | [BELUM DIVERIFIKASI] |
| 20 | Reclip | [https://github.com/averygan/reclip](https://github.com/averygan/reclip) | Referensi pemrosesan media dan video clipping berbasis web | [BELUM DIVERIFIKASI] | [BELUM DIVERIFIKASI] | [BELUM DIVERIFIKASI] |
| 21 | REA | [https://github.com/morluto/rea](https://github.com/morluto/rea) | Reverse Engineer Anything: referensi analisis aplikasi dan workflow reverse engineering | [BELUM DIVERIFIKASI] | [BELUM DIVERIFIKASI] | [BELUM DIVERIFIKASI] |
| 22 | AutoClip | [https://github.com/zhouxiaoka/autoclip](https://github.com/zhouxiaoka/autoclip) | Otomatisasi pemotongan video panjang menjadi klip pendek | [BELUM DIVERIFIKASI] | [BELUM DIVERIFIKASI] | [BELUM DIVERIFIKASI] |
| 23 | Remotion | [https://github.com/remotion-dev/remotion](https://github.com/remotion-dev/remotion) | Framework rendering video programatik berbasis React | React / TypeScript (Node) | [BELUM DIVERIFIKASI] | [BELUM DIVERIFIKASI] |
| 24 | OpenHands | [https://github.com/OpenHands/OpenHands](https://github.com/OpenHands/OpenHands) | Coding agent dan AI development agent untuk workflow pengembangan internal | [BELUM DIVERIFIKASI] | [BELUM DIVERIFIKASI] | [BELUM DIVERIFIKASI] |
| 25 | MuMuAINovel | **URL kandidat, belum diverifikasi**: [https://github.com/xiamuceer-j/MuMuAINovel](https://github.com/xiamuceer-j/MuMuAINovel) | Asisten penulisan novel berbasis AI; modul penulisan novel dan konten panjang | [BELUM DIVERIFIKASI] | [BELUM DIVERIFIKASI] — *menurut konten sumber:* GPL-3.0 (copyleft kuat; review hukum wajib, D-44) | [BELUM DIVERIFIKASI] |

**Tabel B — Potensi, metode integrasi, risiko, dan status**

| No | Nama | Potensi penggunaan dalam Krafio | Metode integrasi yang ditargetkan (53.6) | Risiko keamanan utama | Status evaluasi | Status implementasi |
|---|---|---|---|---|---|---|
| 1 | WhatsApp Business API Dashboard | Dashboard operasi WhatsApp pada adapter Bab 46 | API Integration (adapter) — **dipertanyakan**: kandidat menurut konten sumber bukan API resmi Meta, sedangkan Bab 30.2, Bab 46, dan 53.12 mensyaratkan API resmi/BSP. Metode akhir (termasuk kemungkinan *Reference Only* atau *Ditolak*) diputuskan pada D-38 | Pelanggaran kebijakan Meta bila berbasis otomasi WhatsApp Web; **risiko pembatasan/pemblokiran nomor** (disebut konten sumber); sesi multi-akun menyimpan kredensial sesi; kebocoran token dan webhook tanpa verifikasi tanda tangan; ejaan owner belum pasti (risiko typosquatting) | Perlu klarifikasi identitas | Blocked |
| 2 | Concat | Editing dan penggabungan video (Bab 27.4, 27.5) | Background Worker atau Library | Parsing media tidak tepercaya; kerentanan codec; konsumsi CPU/RAM | Belum dievaluasi | Planned |
| 3 | Modly | Generasi 3D opsional | Microservice / Background Worker (opsional) | Lisensi model; kebutuhan GPU; hak atas output; endpoint tanpa autentikasi | Belum dievaluasi | Planned |
| 4 | PowerToys | Utilitas internal developer Windows | Reference Only | Bukan dependency production web; risiko hanya pada mesin developer | Belum dievaluasi | Planned |
| 5 | Jan | Runtime AI lokal opsional di balik AI Gateway | API Integration / Microservice (opsional) | Lisensi model; server API lokal terbuka; konsumsi resource; isolasi | Belum dievaluasi | Planned |
| 6 | LosslessCut | Trim/cut video tanpa re-encode | CLI / Background Worker (bila mode headless terbukti ada; bila tidak, Reference Only) | Aplikasi desktop belum tentu layak untuk server; kompatibilitas codec; eksekusi biner eksternal | Belum dievaluasi | Planned |
| 7 | Prompt Optimizer | Optimasi dan evaluasi prompt (Bab 7.4, 26.5) | Library atau Plugin (modul internal/agent) | Kebocoran data prompt ke provider; kualitas hasil | Belum dievaluasi | Planned |
| 8 | ComfyUI | Engine workflow gambar sebagai AI service terpisah (Bab 7.3) | Microservice (dedicated AI service) + API | Custom node berisiko RCE; lisensi model/checkpoint; kebutuhan GPU; endpoint terbuka | Belum dievaluasi | Planned |
| 9 | Archify | Alat dokumentasi internal untuk menyusun diagram arsitektur di `docs/`; **bukan fitur produk** | Reference Only (internal dev tool) | Kode/HTML hasil generate tidak tepercaya (jangan disajikan ke pelanggan tanpa review); akurasi diagram harus diverifikasi manual; kebocoran deskripsi arsitektur ke provider AI | Perlu klarifikasi identitas | Blocked |
| 10 | OpenSEO | Referensi untuk Bab 29 (SEO Tools dan Custom Rules Engine) dan/atau layanan riset keyword | Reference Only atau API Integration / Microservice (diputuskan setelah audit) | SSRF pada crawler; biaya variabel API pihak ketiga (DataForSEO, berbayar sesuai pemakaian; D-45); kebijakan crawl; ketergantungan pada vendor data; akurasi hasil | Perlu klarifikasi identitas | Blocked |
| 11 | Screenshot to Code | Alat internal konversi referensi UI ke kode | Reference Only (internal dev tool) | Kode hasil generate tidak tepercaya; kebocoran desain ke provider AI | Belum dievaluasi | Planned |
| 12 | FREELLMAPI | Kandidat adapter abstraksi LLM di balik AI Gateway (Bab 26.1) | API Integration (adapter AI Gateway) | Penanganan API key tiap provider; ketidakpastian repository yang benar (tiga kandidat); stabilitas; **layanan model gratis tanpa SLA**; ketergantungan kuota gratis untuk fitur berbayar; kuota dan batas provider dapat berubah; kepatuhan ToS tiap provider atas penggunaan kuota gratis secara terpusat (D-46) | Perlu klarifikasi identitas | Blocked |
| 13 | AI Design Skills | Dukungan workflow desain (Bab 27.6) | Reference Only atau Plugin (tooling internal) | Konten skill tidak tepercaya; lisensi | Belum dievaluasi | Planned |
| 14 | Postiz | Social media management (Bab 30) | API Integration / Microservice | Isolasi tenant; token sosial; kepatuhan app review platform | Belum dievaluasi | Planned |
| 15 | Video Shotcraft | Workflow video AI berbasis Remotion | Background Worker | Eksekusi pipeline pihak ketiga; resource render | Belum dievaluasi | Planned |
| 16 | Security Audit Skill | Bantuan audit keamanan internal | Reference Only (internal dev tool) / CLI di lingkungan terisolasi | Tidak menggantikan pentest; hasil dapat memberi rasa aman palsu | Belum dievaluasi | Planned |
| 17 | Wordcraft | Referensi dokumen/copy | Library atau Reference Only | Keamanan berkas dokumen; lisensi; ekspor | Belum dievaluasi | Planned |
| 18 | Deckcraft | Referensi presentasi | Library atau Reference Only | Lisensi font/asset; rendering tidak tepercaya | Belum dievaluasi | Planned |
| 19 | Gridcraft | Referensi spreadsheet/grid | Library atau Reference Only | Formula injection; keamanan berkas | Belum dievaluasi | Planned |
| 20 | Reclip | Referensi pemrosesan media dan clipping | Background Worker / Microservice (kandidat) | Hak cipta dan ketentuan sumber media; unduhan media dari URL (SSRF); resource | Belum dievaluasi | Planned |
| 21 | REA | Referensi analisis workflow reverse engineering | Reference Only | Hanya untuk analisis yang sah dan berizin; risiko hukum | Belum dievaluasi | Planned |
| 22 | AutoClip | Otomatisasi klip pendek dari video panjang | Background Worker | Kualitas hasil; resource; model dan lisensi; hak atas konten | Belum dievaluasi | Planned |
| 23 | Remotion | Rendering video programatik (Bab 27.3) | Background Worker (Node render worker) / Library | Biaya render; lisensi penggunaan komersial; font/asset | Belum dievaluasi | Planned |
| 24 | OpenHands | Coding agent internal | Reference Only / alat developer dalam sandbox | Eksekusi perintah dan akses jaringan; kebocoran secret production | Belum dievaluasi | Planned |
| 25 | MuMuAINovel | Modul penulisan novel/konten panjang | Plugin / modul AI opsional atau Reference Only (diputuskan setelah audit dan review lisensi) | **Lisensi copyleft** (GPL-3.0 menurut konten sumber, belum diverifikasi; review hukum wajib sebelum integrasi ke SaaS komersial, D-44); kebijakan konten; lisensi model; UI/repo berbahasa Mandarin (kemungkinan perlu lokalisasi); identitas belum diverifikasi | Perlu klarifikasi identitas | Blocked |

**Ringkasan register (diperiksa ulang):**

| Indikator | Jumlah |
|---|---|
| Total repository kandidat | **25** |
| URL teridentifikasi (terdapat URL tunggal pada sumber) | **20** (No. 2, 3, 4, 5, 6, 7, 8, 11, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24) |
| Masih perlu klarifikasi identitas | **5** (No. 1, 9, 10, 12, 25) |
| Memiliki URL kandidat dari tangkapan layar, belum diverifikasi (v2.3.1) | **5** (No. 1, 9, 10, 25 masing-masing satu URL; No. 12 tiga URL kandidat) |
| Status evaluasi *Belum dievaluasi* | 20 |
| Status evaluasi *Perlu klarifikasi identitas* | 5 |
| Status implementasi *Planned* | 20 |
| Status implementasi *Blocked* | 5 |
| Status implementasi *Implemented* atau *Tested* | **0** |

**Catatan:** URL pada register adalah URL yang diberikan sebagai kandidat; **validitas dan kepemilikan resminya belum diverifikasi**. Verifikasi dilakukan pada kriteria audit nomor 1 dan 2 (53.4). Nomor 12 memiliki dua URL kandidat; **tidak satu pun dipilih** sampai verifikasi dilakukan, sehingga nomor 12 dihitung sebagai belum teridentifikasi.

**Catatan v2.3.1:** No. 1, 9, 10, dan 25 kini memiliki URL kandidat, dan No. 12 memiliki kandidat ketiga. Seluruhnya berasal dari tangkapan layar konten media sosial dan **belum diverifikasi**. Kelima nomor ini tetap dihitung *perlu klarifikasi identitas* dan **tidak** ditambahkan ke hitungan "URL teridentifikasi" sampai kriteria audit 1–3 (53.4) terpenuhi dan keputusan D-38 sampai D-42 dicatat (54.7.5). Rincian sumber ada pada 53.3.1.

### 53.3.1 Sumber dan Status Verifikasi Data Kandidat (v2.3.1)

| No | Nama | URL kandidat | Sumber data | Diverifikasi langsung ke GitHub? | Nilai yang hanya berasal dari konten sumber (belum diverifikasi) | Keputusan terkait |
|---|---|---|---|---|---|---|
| 1 | WhatsApp Business API Dashboard | https://github.com/rmyndharis/OpenWA | Tangkapan layar konten TikTok; ejaan owner dari gambar kecil | Belum — [BELUM DIVERIFIKASI] | Self-hosted, multi-session, REST API, webhook, dashboard, Docker, n8n; **bukan API resmi Meta**; risiko pembatasan akun | D-38 |
| 9 | Archify | https://github.com/tt-ali/archify | Tangkapan layar konten TikTok | Belum — [BELUM DIVERIFIKASI] | Skill coding agent untuk diagram arsitektur HTML interaktif; hasil perlu diverifikasi manual | D-39 |
| 10 | OpenSEO | https://github.com/every-app/open-seo | Tangkapan layar konten TikTok | Belum — [BELUM DIVERIFIKASI] | Dashboard riset SEO; self-host membutuhkan API key DataForSEO berbayar sesuai pemakaian | D-40, D-45 |
| 12a | FREELLMAPI (kandidat a) | https://github.com/lov0u/freellmapi | Daftar awal PRD v2.3 | Belum — [BELUM DIVERIFIKASI] | — | D-41 |
| 12b | FREELLMAPI (kandidat b) | https://github.com/xxy2026/freellmapi | Daftar awal PRD v2.3 | Belum — [BELUM DIVERIFIKASI] | — | D-41 |
| 12c | FREELLMAPI (kandidat c, **DIUSULKAN, menunggu verifikasi**) | https://github.com/tashfeenahmed/freellmapi | Tangkapan layar konten TikTok | Belum — [BELUM DIVERIFIKASI] | Menyatukan kuota model gratis multi-penyedia ke satu endpoint kompatibel; routing berdasarkan reliability/speed/intelligence; butuh akun dan API key tiap penyedia; kuota dapat berubah | D-41, D-46 |
| 25 | MuMuAINovel | https://github.com/xiamuceer-j/MuMuAINovel | Tangkapan layar konten TikTok | Belum — [BELUM DIVERIFIKASI] | Asisten penulisan novel AI; lisensi GPL-3.0; konten berbahasa Mandarin | D-42, D-44 |

**Aturan untuk data pada tabel ini:**

1.  Nilai dari konten media sosial adalah **petunjuk awal**, bukan bukti audit (53.2 butir 8). Nilai itu tidak boleh dipindahkan ke kolom *Lisensi*, *Teknologi*, atau *Status maintenance* sebagai fakta sebelum diperiksa pada repository resmi, dengan tanggal pemeriksaan dan commit/tag yang dicatat.
2.  Saat verifikasi, catat: owner yang benar, keaslian repository (bukan fork, mirror, atau nama tiruan), lisensi pada berkas LICENSE, tanggal commit terakhir, status archived, dan isi README. Hasilnya dicatat pada kartu audit (53.5.3).
3.  Bila URL tidak dapat dibuka atau owner berbeda dari tangkapan layar, URL kandidat ditandai tidak valid. Pemilik produk lalu memberi sumber baru atau kandidat ditandai *Ditolak* (53.12, prosedur butir 4).
4.  Kandidat 12c **tidak dipilih otomatis**, meskipun deskripsinya paling cocok. Pemilihan hanya melalui keputusan D-41.

## 53.4 Sistem Audit Repository (20 Kriteria)

Setiap repository kandidat diaudit terhadap 20 kriteria berikut. Hasilnya dicatat pada satu **kartu audit** per repository (53.5) dan menjadi bukti bagi Integration Gate (53.10).

| No | Kriteria | Yang dicatat | Rujukan Bab 37.3 |
|---|---|---|---|
| 1 | Validitas URL | URL resmi dapat dibuka; bukan fork, mirror, atau repository tiruan | Butir 1 |
| 2 | Identitas owner | Pemilik/organisasi, maintainer, dan keterkaitan dengan proyek resmi | Butir 1 |
| 3 | Deskripsi proyek | Tujuan proyek menurut maintainer dan kesesuaiannya dengan fungsi yang dicari | Butir 1 |
| 4 | README | Kelengkapan, keterbatasan yang dinyatakan, dan peringatan keamanan | Butir 7 |
| 5 | Dokumentasi instalasi | Langkah instalasi dapat direproduksi pada environment uji | Butir 8 |
| 6 | Bahasa pemrograman | Bahasa utama dan kompatibilitas dengan stack Krafio (Bab 43) | Butir 6 |
| 7 | Framework | Framework/runtime dan versinya | Butir 6 |
| 8 | Dependency | Daftar dependency langsung dan transitif; dependency berisiko atau tidak dipelihara | Butir 6, 12 |
| 9 | Lisensi | Lisensi kode, model, checkpoint, font, dataset, dan asset (53.9) | Butir 3 |
| 10 | Status maintenance | Aktif, lambat, tidak aktif, atau ditinggalkan; respons terhadap issue | Butir 2 |
| 11 | Aktivitas commit terakhir | Tanggal dan frekuensi commit | Butir 2 |
| 12 | Release terakhir | Versi, tanggal, dan kebijakan versioning | Butir 2, 7 |
| 13 | Status archived | Apakah repository diarsipkan (read-only) | Butir 2 |
| 14 | Security vulnerabilities | Security advisory, CVE pada dependency, dan hasil pemindaian | Butir 12 |
| 15 | Potensi konflik lisensi | Benturan dengan model bisnis SaaS komersial atau lisensi komponen lain (53.9) | Butir 4, 5 |
| 16 | Kebutuhan server | OS, container support, CPU, RAM, disk, dan jaringan | Butir 8, 9 |
| 17 | Kebutuhan GPU | Ada atau tidak; VRAM minimum; alternatif CPU atau external API | Butir 9 |
| 18 | Kebutuhan database | Jenis database dan hak akses yang dibutuhkan; apakah memerlukan database sendiri | Butir 6, 11 |
| 19 | Kebutuhan API key | Kredensial pihak ketiga yang dibutuhkan, cara menyimpannya, dan biayanya | Butir 10, 15 |
| 20 | Potensi integrasi ke Krafio | Metode integrasi (53.6), pola Bab 37.2, fase implementasi, dan exit strategy | Butir 7, 11, 13–16 |

**Pemetaan balik:** seluruh 16 butir Bab 37.3 tercakup. Butir 13 (kualitas output), 14 (observability, retry, timeout), dan 15 (biaya) dipenuhi pada tahap *benchmark* Integration Gate (53.10) dan kriteria nomor 20.

**Aturan audit:**

-   Audit dilakukan pada **commit atau tag tertentu** yang dicatat, bukan pada "branch terbaru" yang berubah.
-   Pemeriksaan keamanan (kriteria 14) dilakukan dengan pemindaian dependency dan peninjauan kode untuk area berisiko (eksekusi perintah, deserialisasi, upload berkas, permintaan jaringan). Audit tidak menggantikan *penetration test*.
-   Repository yang berstatus archived, tidak aktif, atau berubah lisensi mendapat **catatan risiko** (53.11).
-   Pemeriksaan lisensi untuk penggunaan **komersial dan SaaS multi-tenant** dilakukan secara khusus, bukan hanya membaca nama lisensi (53.9).
-   Audit dijalankan di environment terisolasi (53.8), bukan di staging atau production.

## 53.5 Status Audit, Status Implementasi, dan Kartu Audit

### 53.5.1 Status audit

| Status | Arti | Syarat masuk |
|---|---|---|
| Belum dievaluasi | Identitas ada tetapi audit belum dimulai | Kondisi awal |
| Sedang diaudit | Audit 20 kriteria berjalan | Penanggung jawab audit ditetapkan |
| Lolos evaluasi | Seluruh kriteria memenuhi syarat tanpa pengecualian | Kartu audit lengkap dan ditandatangani reviewer teknis, keamanan, dan hukum |
| Lolos dengan syarat | Layak dengan batasan tertulis (mis. hanya internal, hanya worker terisolasi, hanya sebagai referensi) | Syarat dan pemilik syarat tercatat |
| Ditolak | Tidak layak (mis. lisensi tidak kompatibel, ditinggalkan, risiko tidak dapat dimitigasi) | Alasan tercatat; alternatif disarankan |
| Perlu klarifikasi identitas | Repository yang dimaksud belum dapat dipastikan | Informasi yang dibutuhkan tercatat (53.12) |

### 53.5.2 Status implementasi

| Status | Arti | Bukti minimum |
|---|---|---|
| Planned | Masuk rencana; belum ada pekerjaan | Entri register |
| Not Started | Audit selesai dan layak, tetapi pekerjaan integrasi belum dimulai | Status audit *Lolos* atau *Lolos dengan syarat* |
| In Progress | Integrasi sedang dikerjakan di branch/environment pengembangan | Branch atau PR aktif |
| Implemented | Telah **dipasang** pada environment yang ditentukan dan berfungsi | Bukti deployment pada staging atau lebih tinggi |
| Tested | Telah lolos pengujian fungsional, keamanan, resource, dan isolasi tenant | Laporan uji dan Integration Gate (53.10) |
| Blocked | Terhenti karena identitas, lisensi, keamanan, resource, atau keputusan belum jelas | Penyebab dan pemilik tindakan |

**Aturan transisi:**

1.  Status **Implemented** tidak boleh diberikan hanya karena kode ditambahkan ke repository atau karena PRD memuatnya.
2.  Status **Tested** mensyaratkan Implemented dan bukti uji; status Tested tidak otomatis berarti boleh masuk production (Integration Gate tetap berlaku).
3.  Kandidat berstatus audit *Perlu klarifikasi identitas*, *Ditolak*, atau *Belum dievaluasi* tidak boleh melewati *Planned* atau *Blocked*.
4.  Perubahan status dicatat pada register beserta tanggal, penanggung jawab, dan bukti.

### 53.5.3 Kartu audit per repository

Setiap repository memiliki satu kartu audit pada `docs/third-party/audits/<nama-repository>.md` di repository utama (52.5). Kartu tidak memuat secret.

```markdown
# Kartu Audit: <nama repository>

| Atribut | Nilai |
|---|---|
| URL resmi | |
| Owner / maintainer | |
| Commit/tag yang diaudit | |
| Tanggal audit dan auditor | |
| Status audit | |
| Status implementasi | |
| Metode integrasi | |
| Lisensi kode / model / asset | |
| Status maintenance (commit/rilis/archived) | |
| Hasil pemindaian kerentanan | |
| Kebutuhan resource (CPU/RAM/GPU/disk) | |
| Kebutuhan API key dan database | |
| Isolasi yang diwajibkan | |
| Catatan risiko | |
| Keputusan dan syarat | |
| Exit strategy | |
```

------------------------------------------------------------------------

## 53.6 Metode Integrasi Repository

Terdapat delapan metode integrasi. Metode ini **memperinci** empat pola pada Bab 37.2 (A. Embedded/library, B. API/service, C. Worker/CLI, D. Internal development tool) dan tidak menggantikannya.

| No | Metode | Deskripsi | Pola Bab 37.2 | Kapan dipakai | Kapan tidak dipakai |
|---|---|---|---|---|---|
| 1 | Library Integration | Kode/paket menjadi dependency aplikasi utama melalui package manager (Composer/npm) | A | Lisensi permisif atau kompatibel; framework sama; paket dipelihara aktif | Lisensi copyleft kuat/jaringan tanpa review hukum; paket tidak dipelihara; mengeksekusi kode tidak tepercaya |
| 2 | SDK Integration | Memakai SDK resmi suatu layanan untuk memanggil layanan tersebut | A | Layanan memiliki SDK resmi dan dipelihara | SDK tidak resmi atau menyedot dependency berlebihan |
| 3 | API Integration | Aplikasi memanggil layanan melalui API terautentikasi; layanan berjalan di luar proses utama | B | Engine memiliki server mode atau API stabil | API tidak terautentikasi dan tidak dapat diamankan |
| 4 | Microservice Integration | Repository dijalankan sebagai layanan terpisah (container) dengan runtime, deployment, dan skala sendiri | B | Runtime berbeda dari Laravel; butuh resource atau GPU khusus | Beban kecil yang cukup dilayani library |
| 5 | Background Worker Integration | Job dari queue (Bab 49) dikirim ke worker khusus yang menjalankan pipeline lalu menyimpan hasil ke object storage | C | Pekerjaan lama dan berat (video, gambar, clipping) | Operasi sinkron singkat |
| 6 | CLI Integration | Aplikasi atau worker menjalankan alat baris perintah dengan argumen yang dikendalikan ketat | C | Alat memiliki mode headless yang stabil | Argumen berasal dari input pengguna tanpa sanitasi |
| 7 | Plugin Integration | Modul tambahan yang dimuat melalui titik ekstensi resmi (mis. adapter AI Gateway, adapter pembayaran/sosial) | A atau B | Titik ekstensi terdefinisi dan terversi | Plugin memerlukan akses penuh ke proses utama atau secret |
| 8 | Reference Only | Repository hanya dipelajari sebagai referensi desain atau dipakai developer sebagai alat internal; tidak ada kode yang dijalankan pada platform pelanggan | D | Alat developer; lisensi tidak kompatibel; kualitas belum terbukti | Tidak ada kendala; bila dibutuhkan sebagai fitur pelanggan, pilih metode lain |

**Prinsip pemilihan metode:**

1.  Pilih metode dengan **isolasi paling kuat yang masih praktis**. Prioritas umum: Reference Only → API/Microservice/Worker (terisolasi) → Plugin → Library (tanpa isolasi).
2.  Library Integration pada proses utama hanya setelah lolos Integration Gate penuh (53.10) dan bukan untuk kode yang memproses input tidak tepercaya secara langsung.
3.  Integrasi AI eksternal hanya sebagai **adapter di balik AI Gateway** (Bab 7.1, Bab 26.1) sehingga routing, kuota, logging, dan kill switch tetap terpusat.
4.  Integrasi sosial dan WhatsApp mengikuti adapter Bab 30 dan Bab 46, dengan isolasi tenant.
5.  Metode dapat berubah setelah audit; perubahan dicatat pada kartu audit.

## 53.7 Kontrak Wajib Setiap Integrasi

Setiap integrasi (kecuali *Reference Only*) wajib memiliki dokumen kontrak pada `docs/third-party/integrations/<nama>.md`. Kontrak memuat seluruh butir berikut. Nilai angka (timeout, retry, dan sejenisnya) **ditetapkan per integrasi berdasarkan benchmark** dan tidak ditentukan secara umum pada PRD ini.

| No | Butir kontrak | Isi minimum |
|---|---|---|
| 1 | Deskripsi integrasi | Tujuan, fitur Krafio yang dilayani, metode (53.6), versi/commit yang dipakai |
| 2 | Arsitektur komunikasi | Diagram alur, arah panggilan, protokol (HTTP, queue, stdin/stdout), batas jaringan |
| 3 | Input dan output data | Skema input/output, batas ukuran, format, validasi, dan penyimpanan hasil |
| 4 | Authentication | Cara layanan memverifikasi pemanggil (token layanan, mTLS, atau jaringan privat) |
| 5 | Authorization | Hak yang dimiliki integrasi (tenant, workspace, scope) dan larangan akses lintas tenant (Bab 35.2) |
| 6 | API endpoint (bila ada) | Daftar endpoint, versi, dan referensi ke dokumentasi (selaras Bab 33) |
| 7 | Error handling | Kode error, pemetaan ke error Krafio, dan perilaku fallback |
| 8 | Timeout | Timeout koneksi dan eksekusi per operasi |
| 9 | Retry mechanism | Kebijakan retry dengan backoff, batas percobaan, dan idempotensi (pekerjaan berbiaya tidak diulang tanpa kontrol) |
| 10 | Logging | Apa yang dicatat; tanpa secret dan tanpa data pribadi berlebih (Bab 36.2) |
| 11 | Monitoring | Metrik (latensi, error, antrean, durasi job), alert, dan dashboard (Bab 36.1) |
| 12 | Resource requirements | CPU, RAM, GPU/VRAM, disk, bandwidth, dan batas per job hasil benchmark |
| 13 | Security requirements | Isolasi (53.8), pemindaian, pembatasan jaringan, dan penanganan input tidak tepercaya |
| 14 | Deployment requirements | Cara build/deploy, node tempat berjalan, process group (Bab 49), flag aktif/nonaktif, dan rollback |

**Arsitektur komunikasi umum (ilustratif):**

```text
Aplikasi Krafio (Laravel)
   │  Permintaan pengguna → validasi, entitlement, kuota, reservasi biaya
   ▼
Queue (Redis, Bab 49) ──► Worker terisolasi / Layanan eksternal (container)
                              │  menjalankan kode pihak ketiga
                              │  tanpa secret produksi, egress dibatasi
                              ▼
                     Object storage (hasil) ──► Webhook/Status ke aplikasi
```

**Persyaratan menurut metode:**

| Metode | Authentication & authorization | Timeout & retry | Isolasi | Monitoring |
|---|---|---|---|---|
| Library / SDK | Mengikuti aplikasi; secret melalui Bab 48 | Mengikuti pemanggil | Tidak ada isolasi proses; wajib audit dan lockfile | Error aplikasi; pemindaian dependency (52.31) |
| API Integration | Token layanan per environment; jaringan privat; allowlist | Timeout eksplisit; retry hanya operasi idempoten | Layanan di luar proses utama | Latensi, error rate, ketersediaan |
| Microservice | Seperti API + identitas layanan; tidak ada akses langsung ke DB utama | Circuit breaker dan fallback | Container dengan batas resource | Health endpoint, resource, log terpusat |
| Background Worker | Worker hanya menerima payload job; kredensial sementara berskala sempit | Timeout job, batas retry, dead-letter queue | Container/sandbox (53.8) | Kedalaman antrean, durasi, tingkat gagal |
| CLI | Tidak ada kredensial di argumen; kerja pada direktori kerja sementara | Timeout proses dan pembunuhan proses | Container tanpa jaringan bila memungkinkan | Kode keluar, stderr, durasi |
| Plugin | Hak sesuai titik ekstensi; tidak membaca secret lain | Mengikuti host | Batasan antarmuka plugin; review kode | Metrik per plugin; flag nonaktif |
| Reference Only | Tidak berlaku | Tidak berlaku | Tidak dijalankan pada platform pelanggan | Tidak berlaku |

## 53.8 Isolasi Eksekusi Kode Pihak Ketiga

**Aturan inti:** repository pihak ketiga tidak boleh langsung dijalankan di proses utama aplikasi tanpa pemeriksaan keamanan. Kode yang memproses input pengguna, mengeksekusi perintah, memuat plugin/custom node, atau mengunduh sumber daya dari jaringan harus diisolasi.

**Tingkat isolasi:**

| Tingkat | Nama | Cakupan | Kontrol minimum | Contoh kandidat (indikatif) |
|---|---|---|---|---|
| 0 | Reference Only | Tidak dijalankan pada platform | Dibaca dan dipelajari; tidak ada kode disalin tanpa review lisensi | PowerToys, REA, AI Design Skills, Archify (v2.3.1, setelah verifikasi identitas) |
| 1 | Library terverifikasi | Dijalankan di proses aplikasi setelah audit | Lockfile; pemindaian dependency; review kode jalur kritis; tidak memproses input tidak tepercaya secara langsung | Prompt Optimizer, Wordcraft, Deckcraft, Gridcraft (bila lolos) |
| 2 | Container worker/service | Proses terpisah dalam container | Pengguna non-root; filesystem read-only bila memungkinkan; batas CPU/RAM/waktu; tanpa secret produksi; egress jaringan dibatasi (allowlist); tanpa akses ke database utama | Remotion, Concat, AutoClip, Reclip, Video Shotcraft, Postiz |
| 3 | Sandbox khusus / node terpisah | Kode yang paling tidak tepercaya atau berpotensi mengeksekusi kode arbitrer | VM atau runtime sandbox; jaringan terisolasi; tidak ada kredensial produksi; penghapusan lingkungan setelah pekerjaan; node GPU terpisah | ComfyUI (custom node), OpenHands, Modly, Jan (bila dijalankan sebagai layanan) |

Penempatan pada tabel di atas adalah **indikasi awal** dan ditetapkan final saat audit.

**Kontrol wajib untuk Tingkat 2 dan 3:**

1.  **Tanpa secret produksi.** Kredensial produksi, kunci SSH deploy, dan token pembayaran/sosial tidak ada di lingkungan worker (Bab 48, 52.39). Worker menerima kredensial sementara berskala sempit bila diperlukan.
2.  **Pembatasan jaringan.** Egress memakai allowlist; akses ke jaringan internal, metadata server cloud, dan layanan basis data diblokir untuk mencegah SSRF dan pergerakan lateral.
3.  **Pembatasan resource.** Batas CPU, RAM, disk sementara, jumlah proses, dan waktu eksekusi; job yang melampaui batas dihentikan.
4.  **Validasi input dan berkas.** Tipe, ukuran, dan struktur berkas divalidasi; media dan dokumen dari pengguna diperlakukan sebagai tidak tepercaya (Bab 10.4).
5.  **Tanpa akses langsung ke database utama dan storage produksi.** Komunikasi hanya melalui API/queue; hasil ditulis ke lokasi output yang sudah ditentukan.
6.  **Image dan dependency terkunci.** Versi image/dependency dipin (tag/digest/commit); pembaruan melalui PR dan pemindaian (52.31).
7.  **Pembersihan.** Direktori kerja sementara dihapus setelah job; hasil disaring sebelum dibagikan ke pengguna.
8.  **Pencatatan.** Eksekusi tercatat dengan identitas job, tenant, versi, dan hasil, tanpa secret (53.7).
9.  **Kill switch.** Admin dapat menonaktifkan adapter dan menghentikan antrean terkait tanpa deploy (Bab 37.4).

**Penempatan resource:**

-   **Tidak diasumsikan bahwa VPS aplikasi memiliki GPU** (Bab 34.5, 52.43). Beban AI dapat memakai external API atau worker/node khusus.
-   Pemrosesan video dan gambar mempertimbangkan CPU, RAM, storage, dan antrean; worker berat dipisahkan dari proses web (Bab 49).
-   Node AI/GPU memiliki pipeline dan konfigurasi tersendiri (52.43).

## 53.9 Kebijakan Lisensi dan Kepatuhan

**Prinsip:** lisensi tidak boleh diabaikan. Tidak ada kode, model, font, atau asset eksternal yang dipakai dalam produk komersial tanpa kejelasan lisensi. Bab 12.1 menegaskan bahwa lisensi open source tidak otomatis mengizinkan semua bentuk penggunaan SaaS.

**Kategori dan perlakuan awal:**

| Kategori lisensi | Perlakuan awal | Catatan |
|---|---|---|
| Permisif (MIT, BSD, Apache-2.0, dan sejenisnya) | Umumnya dapat dipakai; kewajiban atribusi dan pemberitahuan lisensi dipenuhi | Periksa lisensi dependency transitif |
| Copyleft lemah | Boleh dengan syarat penggunaan yang dipahami; review hukum | Perhatikan modifikasi pada komponen berlisensi |
| Copyleft kuat (mis. keluarga GPL) | **Review hukum wajib** sebelum digabung ke kode Krafio; pertimbangkan integrasi melalui proses/API terpisah | Keputusan pemisahan kode dicatat |
| Copyleft jaringan (mis. AGPL) dan source-available terbatas (mis. SSPL, BSL) | **Review hukum wajib**; kewajiban pembukaan sumber pada layanan jaringan dapat berlaku | Jangan dimasukkan ke source code utama sebelum keputusan |
| Lisensi khusus/komersial (mis. memerlukan lisensi perusahaan, pembatasan jumlah pengguna/karyawan) | **Wajib** menghitung kelayakan dan biaya; keputusan oleh pemilik produk dan legal | Rencana lisensi dicatat (Bab 37.1: "license plan") |
| Tanpa lisensi / tidak jelas | **Tidak boleh dipakai** (semua hak dicadangkan) kecuali pemilik memberi izin tertulis | Dapat dijadikan referensi konsep saja |

**Komponen lisensi yang diperiksa terpisah:** kode, **model dan checkpoint AI**, dataset, font, musik, template, ikon, dan asset visual. Lisensi kode yang permisif tidak berarti lisensi model atau asset-nya permisif (Bab 12.1, Bab 41 butir 16).

**Aturan:**

1.  Penggunaan komersial dan SaaS multi-tenant diperiksa secara khusus dan dicatat pada kartu audit.
2.  Kewajiban atribusi, pemberitahuan lisensi, dan pengungkapan sumber dicatat dan dipenuhi dalam berkas `THIRD_PARTY` (53.10) serta halaman legal produk bila diperlukan.
3.  Hasil review hukum dicatat sebagai keputusan pada Bab 54.7; dokumen ini bukan nasihat hukum dan tidak menggantikan peninjauan oleh penasihat hukum.
4.  Output yang dihasilkan alat eksternal (media, kode, teks) diperiksa hak penggunaannya bagi pelanggan sebelum fitur dirilis.
5.  Perubahan lisensi pada versi baru repository memicu audit ulang sebelum upgrade.
6.  Kandidat yang berhubungan dengan media pihak ketiga (mis. Reclip, AutoClip) tunduk pada hak cipta dan ketentuan layanan sumber media; fitur pelanggan wajib mengikuti kebijakan konten dan abuse (Bab 35.5).

## 53.10 Integration Gate, THIRD_PARTY, dan Pengelolaan Versi

**Integration Gate (Bab 37.4) tetap berlaku.** Syarat berikut **menambah**, bukan menggantikan, sembilan syarat pada Bab 37.4:

| Syarat tambahan | Bukti |
|---|---|
| Identitas repository terverifikasi (53.4 No. 1–2) | Kartu audit |
| Kartu audit lengkap dan status audit *Lolos* atau *Lolos dengan syarat* | Kartu audit (53.5.3) |
| Kontrak integrasi tersedia (53.7) | `docs/third-party/integrations/` |
| Versi dikunci (tag/commit/digest) dan tercatat | `THIRD_PARTY`, lockfile |
| Pemindaian dependency dan lisensi bersih atau risiko dicatat | Hasil CI (52.31) |
| Isolasi sesuai tingkat (53.8) diuji | Laporan uji |
| Exit strategy dan fallback/manual alternatif tersedia | Kartu audit |
| Flag aktif/nonaktif tersedia pada Admin Panel | Konfigurasi (Bab 37.4, 52.20) |

**Berkas `THIRD_PARTY`** (Bab 52.5) dibuat untuk setiap kode pihak ketiga yang di-vendor atau diadaptasi dan memuat: nama, URL resmi, versi/commit, lisensi, perubahan yang dilakukan, tanggal masuk, penanggung jawab, dan kewajiban atribusi. Kode pihak ketiga ditempatkan pada direktori khusus, tidak bercampur dengan kode Krafio.

**Pengelolaan versi dan siklus hidup:**

1.  Versi dipin ke tag atau commit SHA, bukan branch bergerak.
2.  Pembaruan masuk melalui PR dengan audit ulang bila terdapat perubahan lisensi, arsitektur, atau dependency besar.
3.  Fork hanya dibuat bila ada alasan teknis; fork dipelihara dengan rencana sinkronisasi dan tinjauan keamanan.
4.  Setiap integrasi memiliki mekanisme **rollback** (kembali ke versi sebelumnya) dan **exit strategy** (pengganti bila proyek ditinggalkan atau lisensinya berubah) (Bab 12.1 butir 9–10, Bab 37.3 butir 16).
5.  Pemindaian kerentanan terhadap dependency kandidat yang telah dipasang berjalan rutin; temuan kritis menghentikan rilis (Bab 38.3).
6.  Integrasi yang gagal tidak boleh membuat fitur inti Krafio tidak dapat dipakai; fallback dan degradasi anggun diutamakan (Bab 37.4).

## 53.11 Repository Archived, Tidak Aktif, dan Perubahan Lisensi

Repository yang archived atau tidak aktif tidak otomatis ditolak, tetapi wajib mendapat **catatan risiko** pada kartu audit.

| Kondisi | Catatan risiko dan tindakan |
|---|---|
| Archived (read-only) | Tidak ada perbaikan keamanan lanjutan; hanya boleh dipakai bila diisolasi kuat dan ada rencana pengganti; umumnya tidak untuk jalur kritis |
| Tidak aktif (tanpa commit/rilis dalam periode panjang) | Ambang "tidak aktif" **[MENUNGGU KEPUTUSAN]** (usulan awal: tanpa aktivitas berarti selama 12 bulan); risiko dependency usang; wajib fallback |
| Maintainer tunggal | Risiko kontinuitas dan rantai pasok; pertimbangkan fork internal dengan kapasitas pemeliharaan |
| Perubahan lisensi pada rilis baru | Berhenti pada versi terakhir yang lisensinya telah ditinjau; audit ulang sebelum upgrade |
| Kerentanan yang diketahui belum diperbaiki | Tidak boleh masuk production; mitigasi atau tolak |
| Repository berubah nama/pindah owner | Verifikasi ulang identitas untuk mencegah pembajakan nama (typosquatting) |

## 53.12 Pemeriksaan Khusus per Kandidat dan Prosedur Klarifikasi Identitas

**Pemeriksaan khusus** melengkapi 20 kriteria audit dan pemeriksaan wajib pada Bab 37.1.

| No | Kandidat | Pemeriksaan khusus sebelum audit lanjut |
|---|---|---|
| 1 | WhatsApp Business API Dashboard | Verifikasi URL kandidat OpenWA dan ejaan owner (`rmyndharis`, dari tangkapan layar kecil); pastikan berbasis API resmi Meta/BSP, **bukan** otomasi WhatsApp Web. **Catatan v2.3.1:** konten sumber menyatakan kandidat ini bukan API resmi Meta dan berisiko pembatasan akun, sehingga **berpotensi tidak memenuhi** syarat Bab 30.2 dan Bab 46. Keputusan metode (adapter, *Reference Only*, atau *Ditolak*) dicatat pada D-38 setelah verifikasi |
| 2 | Concat | Peran (editor atau library concat), dukungan codec, mode server/worker, stabilitas |
| 3 | Modly | Lisensi model, hak atas output, kebutuhan GPU, endpoint |
| 4 | PowerToys | Hanya utilitas developer Windows; **bukan** fitur SaaS dan bukan dependency production |
| 5 | Jan | API server lokal, lisensi model, isolasi, resource |
| 6 | LosslessCut | Ketersediaan mode headless/CLI; bila hanya aplikasi desktop, tetapkan *Reference Only* dan evaluasi penggunaan FFmpeg secara langsung |
| 7 | Prompt Optimizer | Penanganan data prompt, kualitas evaluasi, dan kelayakan sebagai modul internal |
| 8 | ComfyUI | API, keamanan custom node, lisensi model/checkpoint, GPU; dijalankan sebagai layanan terpisah |
| 9 | Archify | Verifikasi URL kandidat dan owner (`tt-ali`); pastikan penggunaannya hanya sebagai alat dokumentasi internal; diagram dan HTML hasil generate direview manual sebelum masuk `docs/`; deskripsi arsitektur yang sensitif tidak dikirim ke provider AI tanpa persetujuan |
| 10 | OpenSEO | Verifikasi URL kandidat dan owner (`every-app`); kebijakan crawl, akurasi, dan risiko SSRF; model biaya API DataForSEO (variabel, sesuai pemakaian) dan batas anggaran (D-45); ketergantungan pada vendor data |
| 11 | Screenshot to Code | Kualitas keluaran, ketergantungan provider AI, kebocoran desain; alat internal |
| 12 | FREELLMAPI | Verifikasi **mana dari tiga kandidat** yang dimaksud (atau tidak satu pun); kandidat (c) `tashfeenahmed/freellmapi` DIUSULKAN tetapi tidak dipilih otomatis; penanganan secret dan API key tiap provider; dukungan provider; ToS tiap provider atas pemakaian kuota gratis secara terpusat; tidak adanya SLA (D-41, D-46) |
| 13 | AI Design Skills | Cakupan skill, kompatibilitas dengan workflow, lisensi |
| 14 | Postiz | Isolasi tenant, kepatuhan terhadap API resmi platform, dan app review (Bab 30) |
| 15 | Video Shotcraft | Identitas repo, pipeline, ketergantungan Remotion, lisensi |
| 16 | Security Audit Skill | Cakupan dan sumber; **tidak menggantikan pentest** |
| 17 | Wordcraft | Lisensi, ekspor, integrasi editor |
| 18 | Deckcraft | Lisensi, rendering, font/asset |
| 19 | Gridcraft | Lisensi, formula, keamanan berkas |
| 20 | Reclip | Kepatuhan hukum, hak cipta, dan ketentuan sumber media; kualitas dan resource |
| 21 | REA | Hanya untuk analisis yang sah dan memiliki izin; maintainer dan tujuan |
| 22 | AutoClip | Kualitas klip, resource, model, dan lisensi |
| 23 | Remotion | Rencana lisensi untuk penggunaan komersial, biaya render, font/asset (Bab 37.1 "License plan") |
| 24 | OpenHands | Tanpa secret produksi; isolasi perintah dan jaringan (Tingkat 3, 53.8) |
| 25 | MuMuAINovel | Verifikasi URL kandidat dan owner (`xiamuceer-j`); periksa lisensi pada berkas LICENSE (konten sumber menyebut GPL-3.0, belum diverifikasi), lalu review hukum sebelum integrasi ke SaaS komersial (D-44); model, kebijakan konten, dan kebutuhan lokalisasi dari bahasa Mandarin |

**Prosedur klarifikasi identitas** (untuk kandidat No. 1, 9, 10, 12, 25):

1.  Pemilik produk atau developer memberikan **URL repository, nama owner, atau tautan sumber** kandidat yang dimaksud. *(Status v2.3.1: URL kandidat untuk kelima nomor sudah diberikan dari tangkapan layar konten media sosial; lihat 53.3.1. Langkah berikutnya adalah butir 2.)*
2.  Tim memverifikasi kriteria audit 1–3 (URL, owner, deskripsi) pada sumber resmi.
3.  Bila ditemukan lebih dari satu kandidat (No. 12, kini tiga kandidat), pemilik produk memilih satu dan alasan dicatat; kandidat lain ditandai tidak dipilih. Label "DIUSULKAN" pada kandidat (c) bukan pemilihan.
4.  Bila tidak ada repository yang cocok, kandidat ditandai *Ditolak* atau diganti dengan alternatif, tanpa mengurangi scope fitur (53.2 butir 7).
5.  Setelah identitas pasti, nilai pada register 53.3, `THIRD_PARTY`, dan Bab 54.7 diperbarui; status berubah menjadi *Belum dievaluasi* dan implementasi menjadi *Planned*.

## 53.13 Kebutuhan Resource Indikatif dan Penempatan Worker

Tabel berikut bersifat **indikatif** untuk perencanaan awal dan **harus divalidasi dengan benchmark** pada audit (kriteria 16–18, Bab 34.5). Tabel ini bukan hasil pengukuran.

| Kelompok | Kandidat | Beban dominan | Penempatan yang disarankan |
|---|---|---|---|
| Generasi gambar/3D | ComfyUI, Modly | GPU/VRAM, storage model | Node AI terpisah atau penyedia eksternal; bukan VPS aplikasi |
| AI lokal | Jan | CPU/GPU, RAM, storage model | Layanan opsional terisolasi di balik AI Gateway |
| Video | Remotion, Concat, LosslessCut, AutoClip, Reclip, Video Shotcraft | CPU, RAM, disk sementara, antrean | Worker video terpisah dari web (Bab 49) dengan batas job |
| Sosial/komunikasi | Postiz, WhatsApp Dashboard | Jaringan, database sendiri bila ada | Layanan terpisah dengan isolasi tenant |
| SEO dan riset keyword (v2.3.1) | OpenSEO | Jaringan (crawler), panggilan API data pihak ketiga berbayar | Layanan terpisah dengan egress allowlist dan batas anggaran API; atau *Reference Only* |
| Abstraksi LLM (v2.3.1) | FREELLMAPI | Jaringan; panggilan ke banyak provider | Adapter di balik AI Gateway; tidak menjadi jalur tunggal untuk fitur berbayar |
| Modul teks/dokumen | Prompt Optimizer, Wordcraft, Deckcraft, Gridcraft, MuMuAINovel | CPU ringan; panggilan provider AI | Library atau worker ringan |
| Alat developer | PowerToys, Screenshot to Code, Security Audit Skill, OpenHands, AI Design Skills, REA, Archify | Mesin developer/sandbox | Tidak di infrastruktur pelanggan |

**Aturan:** setiap integrasi berat menentukan batas job (durasi, ukuran, concurrency), kebijakan antrean (Bab 11.5, Bab 49), biaya per tugas (Bab 37.3 butir 15), serta jalur fallback ke external API bila kapasitas tidak mencukupi.

## 53.14 Acceptance Criteria Bab 53

Bab 53 dianggap diterapkan dengan benar apabila:

-   [ ] Register memuat **25** kandidat yang selaras dengan Bab 37.1; tidak ada kandidat yang hilang atau ditambah tanpa keputusan.
-   [ ] Setiap kandidat memiliki URL resmi terverifikasi, URL kandidat berlabel *belum diverifikasi* beserta sumbernya (53.3.1), atau berstatus `[BELUM DITETAPKAN]`; **tidak ada URL yang dikarang**.
-   [ ] Kandidat No. 1, 9, 10, 12, 25 berstatus *Perlu klarifikasi identitas* sampai identitas dipastikan.
-   [ ] Kartu audit 20 kriteria tersedia untuk setiap kandidat yang diaudit dengan sumber, tanggal, dan commit/tag.
-   [ ] Tidak ada kandidat berstatus **Implemented** atau **Tested** tanpa bukti pemasangan dan pengujian.
-   [ ] Setiap integrasi memiliki metode (53.6) dan kontrak lengkap (53.7).
-   [ ] Kode pihak ketiga yang dijalankan memenuhi isolasi sesuai tingkat (53.8); tidak ada secret produksi pada worker eksternal.
-   [ ] Lisensi kode, model, font, dan asset ditinjau; keputusan hukum dicatat pada Bab 54.7.
-   [ ] Integration Gate (Bab 37.4 dan 53.10) lengkap sebelum production; adapter dapat dinonaktifkan Admin.
-   [ ] Kandidat archived/tidak aktif memiliki catatan risiko dan fallback.
-   [ ] Berkas `THIRD_PARTY` tersedia untuk setiap kode eksternal yang di-vendor.
-   [ ] Tidak ada asumsi bahwa VPS aplikasi memiliki GPU.

------------------------------------------------------------------------

------------------------------------------------------------------------

# 54. Checklist GitHub (90 Poin), Keterlacakan, Ketentuan Teknis Wajib, dan Register Keputusan

Bab ini melengkapi Bab 52 (repository utama, source code, CI/CD) dan Bab 53 (repository eksternal). Bab ini **tidak menggantikan** bab mana pun. Isinya: (1) checklist GitHub 90 poin, (2) matriks keterlacakan checklist ke spesifikasi, (3) 20 ketentuan teknis dan keamanan wajib, (4) kriteria penerimaan pembaruan dokumen, dan (5) register keputusan yang menampung seluruh nilai `[BELUM DITETAPKAN]` dan `[MENUNGGU KEPUTUSAN]` dari Bab 52 dan 53.

**Peringatan pembacaan:** 90 butir pada bab ini adalah **kategori C**, yaitu persyaratan pengelolaan repository utama Krafio. **Bukan 90 repository tambahan.** Repository eksternal berjumlah **25** dan terdaftar pada Bab 53.3.

------------------------------------------------------------------------

## 54.1 Tujuan dan Hubungan dengan Bab Lain

| Topik | Lokasi spesifikasi | Fungsi Bab 54 |
|---|---|---|
| Repository utama, source code, CI/CD, VPS, backup, dokumentasi | Bab 52 | Memastikan setiap persyaratan memiliki butir checklist dan lokasi spesifikasi |
| Repository eksternal (25 kandidat) | Bab 12, Bab 37, Bab 53 | Memuat keputusan yang tertunda akibat identitas belum pasti (54.7) |
| Gate rilis dan pengujian | Bab 38 | Checklist menjadi bukti kesiapan, bukan pengganti gate |
| Keputusan sebelum production | Bab 41 | Register 54.7 merujuk butir Bab 41 yang relevan (mis. butir 18, 19, 20) |
| Stack dan CI/CD wajib | Bab 43 | Platform GitHub dan GitHub Actions ditetapkan melalui Bab 52 |
| Secret | Bab 48 | Aturan secret CI/CD pada 52.39 tunduk pada Bab 48 |

**Cara memakai checklist:** setiap butir berstatus awal `[ ]` (belum dipenuhi). Butir hanya boleh dicentang bila **bukti** tersedia: tautan konfigurasi, tangkapan layar atau ekspor pengaturan, hasil workflow, atau dokumen pada `docs/`. Mencentang butir tanpa bukti tidak sah. Butir yang bergantung pada keputusan tertunda tidak boleh dicentang sebelum keputusan dicatat pada 54.7.

------------------------------------------------------------------------

## 54.2 Checklist GitHub dan Repository (90 Poin)

### A. IDENTITAS REPOSITORY

- [ ] 1. Nama repository utama Krafio
- [ ] 2. URL GitHub repository
- [ ] 3. Deskripsi repository
- [ ] 4. Repository visibility (Private/Public)
- [ ] 5. Owner / Organization GitHub
- [ ] 6. Repository license
- [ ] 7. README.md
- [ ] 8. CONTRIBUTING.md
- [ ] 9. CHANGELOG.md
- [ ] 10. CODE_OF_CONDUCT.md

### B. BRANCH MANAGEMENT

- [ ] 11. Main branch (production)
- [ ] 12. Development branch
- [ ] 13. Feature branch
- [ ] 14. Bugfix branch
- [ ] 15. Hotfix branch
- [ ] 16. Release branch
- [ ] 17. Branch protection rules
- [ ] 18. Pull request workflow
- [ ] 19. Code review requirements
- [ ] 20. Merge strategy

### C. SOURCE CODE STRUCTURE

- [ ] 21. Frontend source code
- [ ] 22. Backend source code
- [ ] 23. Admin Panel source code
- [ ] 24. User Panel source code
- [ ] 25. API source code
- [ ] 26. Database migrations
- [ ] 27. Database seeders
- [ ] 28. Background workers
- [ ] 29. AI integration modules
- [ ] 30. Social media integration modules
- [ ] 31. Payment integration modules
- [ ] 32. Website Builder modules
- [ ] 33. SEO modules
- [ ] 34. Shared components
- [ ] 35. Configuration files

### D. GITHUB ACTIONS & CI/CD

- [ ] 36. Automated testing
- [ ] 37. Code quality checking
- [ ] 38. Static code analysis
- [ ] 39. Dependency vulnerability scanning
- [ ] 40. Automated build
- [ ] 41. Automated deployment
- [ ] 42. Deployment ke VPS
- [ ] 43. Production deployment workflow
- [ ] 44. Staging deployment workflow
- [ ] 45. Deployment rollback

### E. SECURITY & ACCESS CONTROL

- [ ] 46. GitHub organization access
- [ ] 47. Developer permissions
- [ ] 48. Admin permissions
- [ ] 49. Repository secrets
- [ ] 50. Environment variables
- [ ] 51. SSH deployment key
- [ ] 52. Personal Access Token management
- [ ] 53. Secret scanning
- [ ] 54. Branch protection
- [ ] 55. Audit log

### F. VERSION CONTROL

- [ ] 56. Commit convention
- [ ] 57. Version numbering
- [ ] 58. Release tagging
- [ ] 59. Release notes
- [ ] 60. Version history
- [ ] 61. Code rollback
- [ ] 62. Change tracking
- [ ] 63. Pull request history

### G. VPS & DEPLOYMENT INTEGRATION

- [ ] 64. GitHub-to-VPS connection
- [ ] 65. Production server configuration
- [ ] 66. Staging server configuration
- [ ] 67. Deployment directory
- [ ] 68. Application environment configuration
- [ ] 69. Database deployment handling
- [ ] 70. Storage and file permission handling
- [ ] 71. Queue worker deployment
- [ ] 72. Scheduler deployment
- [ ] 73. Nginx configuration
- [ ] 74. SSL configuration
- [ ] 75. Health check after deployment

### H. BACKUP & RECOVERY

- [ ] 76. Source code backup
- [ ] 77. Database backup
- [ ] 78. Uploaded asset backup
- [ ] 79. Configuration backup
- [ ] 80. Recovery procedure
- [ ] 81. Disaster recovery documentation

### I. DOCUMENTATION

- [ ] 82. Installation guide
- [ ] 83. Local development guide
- [ ] 84. Environment setup guide
- [ ] 85. Database setup guide
- [ ] 86. API documentation
- [ ] 87. Deployment guide
- [ ] 88. Troubleshooting guide
- [ ] 89. Developer onboarding guide
- [ ] 90. Release management guide

**TOTAL: 90 CHECKLIST** (A: 10, B: 10, C: 15, D: 10, E: 10, F: 8, G: 12, H: 6, I: 9).

------------------------------------------------------------------------

## 54.3 Rekap Checklist per Kategori

| Kategori | Rentang | Jumlah | Bab 52 terkait |
|---|---|---|---|
| A. Identitas Repository | 1–10 | 10 | 52.2–52.5 |
| B. Branch Management | 11–20 | 10 | 52.21–52.23 |
| C. Source Code Structure | 21–35 | 15 | 52.5–52.20 |
| D. GitHub Actions & CI/CD | 36–45 | 10 | 52.27–52.36 |
| E. Security & Access Control | 46–55 | 10 | 52.37–52.42 |
| F. Version Control | 56–63 | 8 | 52.24–52.26, 52.36 |
| G. VPS & Deployment Integration | 64–75 | 12 | 52.43–52.54 |
| H. Backup & Recovery | 76–81 | 6 | 52.55–52.60 |
| I. Documentation | 82–90 | 9 | 52.61–52.69 |
| **Total** | 1–90 | **90** | |

------------------------------------------------------------------------

## 54.4 Matriks Keterlacakan (Checklist ke Spesifikasi)

Matriks menunjukkan lokasi spesifikasi setiap butir dan kategori kewajibannya (konvensi label pada Bab 52). Kolom *Kategori* adalah **sifat butir**, bukan status pemenuhan.

| No | Butir | Spesifikasi (Bab 52) | Kategori |
|---|---|---|---|
| 1 | Nama repository utama Krafio | 52.2 | Keputusan tertunda |
| 2 | URL GitHub repository | 52.2 | Keputusan tertunda |
| 3 | Deskripsi repository | 52.2 | Keputusan tertunda |
| 4 | Repository visibility (Private/Public) | 52.3 | Keputusan tertunda |
| 5 | Owner / Organization GitHub | 52.4 | Keputusan tertunda |
| 6 | Repository license | 52.2, 52.3 | Keputusan tertunda |
| 7 | README.md | 52.2, 52.5 | Wajib |
| 8 | CONTRIBUTING.md | 52.2, 52.5, 52.23 | Wajib |
| 9 | CHANGELOG.md | 52.5, 52.24, 52.26 | Wajib |
| 10 | CODE_OF_CONDUCT.md | 52.2, 52.5 | Wajib |
| 11 | Main branch (production) | 52.21 | Wajib |
| 12 | Development branch | 52.21 | Wajib |
| 13 | Feature branch | 52.21 | Wajib |
| 14 | Bugfix branch | 52.21 | Wajib |
| 15 | Hotfix branch | 52.21, 52.26 | Wajib |
| 16 | Release branch | 52.21, 52.26 | Wajib |
| 17 | Branch protection rules | 52.21, 52.37 | Wajib |
| 18 | Pull request workflow | 52.22 | Wajib |
| 19 | Code review requirements | 52.23 | Wajib |
| 20 | Merge strategy | 52.21 | Wajib |
| 21 | Frontend source code | 52.5, 52.6 | Wajib |
| 22 | Backend source code | 52.5, 52.7 | Wajib |
| 23 | Admin Panel source code | 52.5, 52.8 | Wajib |
| 24 | User Panel source code | 52.5, 52.9 | Wajib |
| 25 | API source code | 52.5, 52.10 | Wajib |
| 26 | Database migrations | 52.11 | Wajib |
| 27 | Database seeders | 52.12 | Wajib |
| 28 | Background workers | 52.13 | Wajib |
| 29 | AI integration modules | 52.14 | Wajib |
| 30 | Social media integration modules | 52.15 | Wajib |
| 31 | Payment integration modules | 52.16 | Wajib |
| 32 | Website Builder modules | 52.17 | Wajib |
| 33 | SEO modules | 52.18 | Wajib |
| 34 | Shared components | 52.19 | Wajib |
| 35 | Configuration files | 52.20 | Wajib |
| 36 | Automated testing | 52.27, 52.28 | Wajib |
| 37 | Code quality checking | 52.27, 52.29 | Wajib |
| 38 | Static code analysis | 52.27, 52.30 | Wajib |
| 39 | Dependency vulnerability scanning | 52.27, 52.31 | Wajib |
| 40 | Automated build | 52.27, 52.32 | Wajib |
| 41 | Automated deployment | 52.27, 52.33 | Wajib |
| 42 | Deployment ke VPS | 52.33, 52.43 | Wajib |
| 43 | Production deployment workflow | 52.34 | Wajib |
| 44 | Staging deployment workflow | 52.35 | Wajib |
| 45 | Deployment rollback | 52.36 | Wajib |
| 46 | GitHub organization access | 52.38 | Wajib |
| 47 | Developer permissions | 52.38 | Wajib |
| 48 | Admin permissions | 52.38 | Wajib |
| 49 | Repository secrets | 52.39 | Wajib |
| 50 | Environment variables | 52.39, 52.47 | Wajib |
| 51 | SSH deployment key | 52.40 | Wajib |
| 52 | Personal Access Token management | 52.41 | Wajib |
| 53 | Secret scanning | 52.37 | Wajib |
| 54 | Branch protection | 52.21, 52.37 | Wajib |
| 55 | Audit log | 52.37, 52.42 | Wajib |
| 56 | Commit convention | 52.24 | Wajib |
| 57 | Version numbering | 52.25 | Wajib |
| 58 | Release tagging | 52.26 | Wajib |
| 59 | Release notes | 52.26 | Wajib |
| 60 | Version history | 52.26 | Wajib |
| 61 | Code rollback | 52.36 | Wajib |
| 62 | Change tracking | 52.26 | Wajib |
| 63 | Pull request history | 52.22, 52.42 | Wajib |
| 64 | GitHub-to-VPS connection | 52.43 | Wajib |
| 65 | Production server configuration | 52.44 | Wajib |
| 66 | Staging server configuration | 52.45 | Wajib |
| 67 | Deployment directory | 52.46 | Wajib |
| 68 | Application environment configuration | 52.47 | Wajib |
| 69 | Database deployment handling | 52.48 | Wajib |
| 70 | Storage and file permission handling | 52.49 | Wajib |
| 71 | Queue worker deployment | 52.50 | Wajib |
| 72 | Scheduler deployment | 52.51 | Wajib |
| 73 | Nginx configuration | 52.52 | Wajib |
| 74 | SSL configuration | 52.53 | Wajib |
| 75 | Health check after deployment | 52.54 | Wajib |
| 76 | Source code backup | 52.55 | Wajib |
| 77 | Database backup | 52.56 | Wajib |
| 78 | Uploaded asset backup | 52.57 | Wajib |
| 79 | Configuration backup | 52.58 | Wajib |
| 80 | Recovery procedure | 52.59 | Wajib |
| 81 | Disaster recovery documentation | 52.60 | Wajib |
| 82 | Installation guide | 52.61 | Wajib |
| 83 | Local development guide | 52.62 | Wajib |
| 84 | Environment setup guide | 52.63 | Wajib |
| 85 | Database setup guide | 52.64 | Wajib |
| 86 | API documentation | 52.65 | Wajib |
| 87 | Deployment guide | 52.66 | Wajib |
| 88 | Troubleshooting guide | 52.67 | Wajib |
| 89 | Developer onboarding guide | 52.68 | Wajib |
| 90 | Release management guide | 52.69 | Wajib |

**Butir yang bergantung pada keputusan tertunda (diperiksa ulang):** butir 1, 2, 3, 4, 5, 6 (identitas, visibility, ownership, lisensi) tidak dapat dicentang sebelum keputusan D-01 sampai D-06 pada 54.7 dicatat. Nilai tidak boleh dikarang.

**Butir yang terhubung ke requirement lama (tidak diduplikasi):**

| Butir | Requirement lama | Catatan |
|---|---|---|
| 36–45 | Bab 34.4 (urutan 13 langkah pipeline), Bab 38 | Bab 52 menetapkan bentuk workflow, bukan mengganti urutan |
| 49, 50, 68 | Bab 48 | Secret dan variabel environment tunduk pada Bab 48 |
| 69–72 | Bab 49, Bab 11.5 | Worker dan scheduler tunggal mengikuti Bab 49 |
| 76–81 | Bab 15.3, Bab 34.6, Bab 41 butir 19 | RPO/RTO tetap keputusan Bab 41 |
| 86 | Bab 33 | Dokumentasi API selaras dengan API contract |

------------------------------------------------------------------------

## 54.5 Ketentuan Teknis dan Keamanan Wajib (20 Butir)

Ketentuan berikut berlaku bagi seluruh implementasi Bab 52 dan Bab 53. Kolom *Penerapan* menunjuk spesifikasi yang menjabarkannya.

| No | Ketentuan wajib | Penerapan |
|---|---|---|
| 1 | Repository utama Krafio adalah sumber utama source code (*source of truth*) | 52.1, 52.2 |
| 2 | Repository eksternal wajib melewati audit sebelum digunakan | 53.4, 53.10 |
| 3 | Integrasi tidak boleh mengabaikan lisensi (kode, model, font, asset) | 53.9 |
| 4 | Credential tidak boleh disimpan langsung di source code | 52.37, 52.39, Bab 48 |
| 5 | API key memakai environment secrets atau secret manager | 52.39, 52.47, Bab 48 |
| 6 | Deployment memakai autentikasi yang aman | 52.40, 52.43 |
| 7 | Akses GitHub memakai prinsip *least privilege* | 52.38, 52.41 |
| 8 | Branch production dilindungi | 52.21, 52.37 |
| 9 | Perubahan production melalui proses review | 52.22, 52.23 |
| 10 | Automated testing dijalankan sebelum deployment | 52.27, 52.28 |
| 11 | Production deployment memiliki approval gate sesuai kebijakan | 52.34 |
| 12 | Setiap deployment memiliki health check | 52.54 |
| 13 | Mekanisme rollback tersedia | 52.36 |
| 14 | Database migration memiliki strategi aman (expand/contract, pemisahan hak DB) | 52.11, 52.48 |
| 15 | Backup database diuji melalui proses restore | 52.56, 52.59 |
| 16 | Uploaded assets memiliki strategi backup | 52.57 |
| 17 | Log deployment tersedia (deployment record) | 52.42, 52.34 |
| 18 | Monitoring error disediakan | 52.34, 52.54, Bab 36 |
| 19 | Worker dan scheduler dapat dipulihkan setelah deployment | 52.50, 52.51 |
| 20 | Proses recovery jika deployment gagal didokumentasikan | 52.36, 52.59, 52.66 |

### 54.5.1 Perhatian Tambahan

1.  **GPU tidak diasumsikan.** VPS aplikasi tidak diasumsikan memiliki GPU. Generasi AI memakai API eksternal atau worker khusus yang dapat berada di host terpisah (52.43, 53.13). Kapasitas GPU tetap keputusan Bab 41 butir 13.
2.  **Beban video dan gambar** dihitung berdasarkan CPU, RAM, storage, dan kedalaman queue; tidak dijalankan pada proses web (Bab 49, 53.13).
3.  **Kode pihak ketiga yang tidak tepercaya diisolasi** (container atau sandbox sesuai tingkat risiko; 53.8). Secret produksi tidak dipasang pada worker eksternal.
4.  **Repository archived atau tidak aktif** wajib mendapat catatan risiko dan fallback (53.11).
5.  **Lisensi penggunaan komersial diperiksa secara khusus** untuk setiap kandidat sebelum integrasi (53.9). Dokumen ini bukan nasihat hukum.
6.  **Status integrasi tidak diasumsikan.** Masuknya repository ke PRD tidak berarti terintegrasi. Status *Implemented* dan *Tested* hanya sah dengan bukti pemasangan dan pengujian (53.5).

------------------------------------------------------------------------

## 54.6 Kriteria Penerimaan Pembaruan Dokumen (v2.2 ke v2.3)

Kriteria ini menilai **dokumen PRD**, bukan kesiapan implementasi (kesiapan implementasi dinilai oleh Bab 52.70 dan Bab 53.14).

- [x] PRD v2.2 telah dibaca seluruhnya sebelum penambahan.
- [x] Isi lama dipertahankan; perubahan pada bagian lama hanya berupa penandaan versi, catatan integrasi, penunjuk silang, dan baris riwayat versi.
- [x] Bab 37 tetap tersedia dan tidak diubah isinya.
- [x] Seluruh 25 repository kandidat dimasukkan (53.3).
- [x] Setiap repository memiliki URL atau status `[BELUM DITETAPKAN]`.
- [x] Tidak ada URL yang dikarang; URL hanya yang diberikan sebagai kandidat.
- [x] Identitas yang belum pasti ditandai: No. 1, 9, 10, 12, 25.
- [x] Audit repository dijelaskan (53.4, 53.5).
- [x] Metode integrasi dijelaskan (53.6, 53.7, 53.8).
- [x] Bab GitHub Repository, Source Code Management & CI/CD ditambahkan (Bab 52).
- [x] Seluruh 90 checklist dimasukkan (54.2).
- [x] GitHub Actions dan CI/CD dijelaskan (52.27–52.36).
- [x] VPS deployment dijelaskan (52.43–52.54).
- [x] Security dan access control dijelaskan (52.37–52.42).
- [x] Backup dan recovery dijelaskan (52.55–52.60).
- [x] Dokumentasi developer dijelaskan (52.61–52.69).
- [x] Catatan Integrasi Versi 2.3 dan peta bab baru ditambahkan.
- [x] Penomoran bab diperiksa: Bab 52, 53, 54 berurutan setelah Bab 51.
- [x] Referensi silang diperiksa (lihat 54.8).
- [x] Tidak ada bagian lama yang dihapus.
- [x] Tidak ada duplikasi requirement yang membingungkan (anti-duplikasi pada 52 dan 53.1).

**Verifikasi kuantitatif:** repository kandidat **25**; checklist **90**; URL teridentifikasi **20**; perlu klarifikasi identitas **5**.

### 54.6.1 Kriteria Penerimaan Pembaruan Dokumen (v2.3 ke v2.3.1)

- [x] PRD v2.3 telah dibaca seluruhnya sebelum pembaruan.
- [x] Isi lama dipertahankan; perubahan hanya pada bagian yang tercantum pada Catatan Integrasi Versi 2.3.1.
- [x] Bab 12 dan Bab 37 tidak diubah.
- [x] No. 1, 9, 10, dan 25 memiliki URL kandidat; No. 12 memiliki tiga URL kandidat. Seluruhnya berlabel *belum diverifikasi* dan sumbernya dicatat (53.3.1).
- [x] Fungsi No. 9 Archify yang sebelumnya "belum teridentifikasi" telah diisi berdasarkan konten sumber.
- [x] Lisensi, teknologi, jumlah bintang, dan status maintenance tidak dicatat sebagai fakta; kolom tersebut tetap `[BELUM DIVERIFIKASI]`.
- [x] Kandidat 12c ditandai *DIUSULKAN, menunggu verifikasi*; tidak ada pemilihan otomatis (D-41).
- [x] Lisensi GPL-3.0 No. 25 dicatat sebagai klaim konten sumber dan risiko copyleft; konsekuensi hukumnya tidak disimpulkan (D-44).
- [x] Pernyataan konten sumber bahwa No. 1 bukan API resmi Meta dicatat sebagai risiko dan diteruskan ke D-38.
- [x] Status evaluasi kelima kandidat tetap *Perlu klarifikasi identitas*; status implementasi tetap *Blocked*; tidak ada *Implemented* atau *Tested*.
- [ ] Verifikasi langsung ke GitHub (owner, lisensi, commit terakhir, status archived, README) **belum dilakukan** pada pembaruan ini.

**Verifikasi kuantitatif v2.3.1:** repository kandidat **25**; checklist **90**; URL teridentifikasi dan terverifikasi tetap **20**; perlu klarifikasi identitas tetap **5**; URL kandidat belum diverifikasi **7** (satu untuk No. 1, 9, 10, dan 25, serta tiga untuk No. 12); keputusan terbuka **46**.

------------------------------------------------------------------------

## 54.7 Register Keputusan Repository, CI/CD, dan Operasional

Register ini menampung seluruh keputusan yang ditandai `[BELUM DITETAPKAN]` atau `[MENUNGGU KEPUTUSAN]` pada Bab 52 dan 53. Nilai **tidak diisi oleh dokumen ini**. Setiap keputusan diisi oleh pemilik produk, dicatat dengan tanggal dan penanggung jawab, lalu nilai pada bagian rujukan diperbarui.

**Status keputusan:** `Terbuka` (belum diputuskan), `Diputuskan` (nilai tercatat), `Dikompensasi` (kontrol tidak tersedia, kompensasi dicatat).

### 54.7.1 Identitas dan tata kelola repository utama

| ID | Keputusan | Rujukan | Butir checklist | Nilai | Status |
|---|---|---|---|---|---|
| D-01 | Nama repository utama | 52.2 | 1 | [BELUM DITETAPKAN] | Terbuka |
| D-02 | GitHub URL | 52.2 | 2 | [BELUM DITETAPKAN] | Terbuka |
| D-03 | Owner / Organization | 52.4 | 5 | [BELUM DITETAPKAN] | Terbuka |
| D-04 | Visibility (Private/Public) | 52.3 | 4 | [BELUM DITETAPKAN] | Terbuka |
| D-05 | License | 52.2 | 6 | [MENUNGGU KEPUTUSAN] | Terbuka |
| D-06 | Deskripsi repository | 52.2 | 3 | [BELUM DITETAPKAN] | Terbuka |
| D-07 | Verifikasi ketersediaan fitur GitHub sesuai plan dan visibility (branch protection, ruleset, secret scanning, push protection, code scanning) beserta kompensasinya | 52.21, 52.37 | 17, 53, 54 | [MENUNGGU KEPUTUSAN] | Terbuka |
| D-08 | Nama tim dan akun pemilik pada CODEOWNERS; peran dan anggota akses GitHub | 52.23, 52.38 | 46–48 | [BELUM DITETAPKAN] | Terbuka |

### 54.7.2 Proses pengembangan dan rilis

| ID | Keputusan | Rujukan | Butir checklist | Nilai | Status |
|---|---|---|---|---|---|
| D-09 | Kebijakan pengecualian bila reviewer kurang dari dua (pembayaran tidak boleh dikecualikan tanpa kompensasi tertulis) | 52.23 | 19 | [MENUNGGU KEPUTUSAN] | Terbuka |
| D-10 | Target waktu respons review | 52.23 | 19 | [BELUM DITETAPKAN] | Terbuka |
| D-11 | Bahasa deskripsi commit (Indonesia atau Inggris) | 52.24 | 56 | [BELUM DITETAPKAN] | Terbuka |
| D-12 | Kalender dan jendela rilis, termasuk pembekuan menjelang event bisnis | 52.26, 52.34 | 43, 58 | [BELUM DITETAPKAN] | Terbuka |
| D-13 | Ambang cakupan (coverage) minimum | 52.28 | 36 | [MENUNGGU KEPUTUSAN] | Terbuka |
| D-14 | Triase dan tenggat temuan medium/low static analysis | 52.30 | 38 | [MENUNGGU KEPUTUSAN] | Terbuka |
| D-15 | Tenggat perbaikan vulnerability dependency medium/low | 52.31 | 39 | [MENUNGGU KEPUTUSAN] | Terbuka |
| D-16 | Kanal notifikasi deployment (berhasil/gagal/rollback) | 52.33 | 41 | [BELUM DITETAPKAN] | Terbuka |
| D-17 | Jumlah dan nama *required reviewers* production | 52.34 | 43 | [BELUM DITETAPKAN] | Terbuka |
| D-18 | Durasi jendela pemantauan pasca-deploy | 52.34, 52.54 | 43, 75 | [BELUM DITETAPKAN] | Terbuka |
| D-19 | Jumlah rilis yang dipertahankan untuk rollback | 52.36, 52.46 | 45, 67 | [BELUM DITETAPKAN] | Terbuka |

### 54.7.3 Keamanan dan akses

| ID | Keputusan | Rujukan | Butir checklist | Nilai | Status |
|---|---|---|---|---|---|
| D-20 | Frekuensi tinjauan akses | 52.38 | 46–48 | [BELUM DITETAPKAN] | Terbuka |
| D-21 | Interval rotasi secret | 52.39 | 49, 51 | [BELUM DITETAPKAN] | Terbuka |
| D-22 | Masa berlaku fine-grained PAT | 52.41 | 52 | [BELUM DITETAPKAN] | Terbuka |
| D-23 | Retensi audit log GitHub dan deployment record | 52.42 | 55, 63 | [MENUNGGU KEPUTUSAN] | Terbuka |
| D-24 | Model koneksi deployment: Model A atau Model B (rekomendasi sementara Model A) | 52.43 | 42, 64 | [MENUNGGU KEPUTUSAN] | Terbuka |

### 54.7.4 Infrastruktur, backup, dan recovery

| ID | Keputusan | Rujukan | Butir checklist | Nilai | Status |
|---|---|---|---|---|---|
| D-25 | Penyedia VPS dan spesifikasi (setelah benchmark; tidak diasumsikan ada GPU) | 52.43 | 64 | [BELUM DITETAPKAN] | Terbuka |
| D-26 | Versi OS server | 52.44 | 65 | [BELUM DITETAPKAN] | Terbuka |
| D-27 | Path direktori deployment final | 52.46 | 67 | [BELUM DITETAPKAN] | Terbuka |
| D-28 | Domain final dan ambang alert kedaluwarsa sertifikat | 52.52, 52.53 | 73, 74 | [BELUM DITETAPKAN] | Terbuka |
| D-29 | Lokasi dan jadwal mirror source code di luar GitHub | 52.55 | 76 | [BELUM DITETAPKAN] | Terbuka |
| D-30 | Frekuensi, retensi, RPO, dan RTO backup database dan komponen lain (terkait Bab 41 butir 19) | 52.56, 52.60 | 77, 80, 81 | [MENUNGGU KEPUTUSAN] | Terbuka |
| D-31 | Penyedia object storage, replikasi, dan retensi aset per jenis (terkait Bab 41 butir 18) | 52.57 | 78 | [MENUNGGU KEPUTUSAN] | Terbuka |
| D-32 | Retensi arsip artefak | 52.55 | 76 | [MENUNGGU KEPUTUSAN] | Terbuka |
| D-33 | Pohon eskalasi dan kontak insiden (terkait Bab 41 butir 20) | 52.60 | 81 | [BELUM DITETAPKAN] | Terbuka |
| D-34 | Frekuensi latihan disaster recovery | 52.60 | 81 | [BELUM DITETAPKAN] | Terbuka |
| D-35 | Lokasi publikasi dokumentasi API dengan akses terkendali | 52.65 | 86 | [BELUM DITETAPKAN] | Terbuka |
| D-36 | Kontak dan kanal operasional pada onboarding dan release management | 52.68, 52.69 | 89, 90 | [BELUM DITETAPKAN] | Terbuka |
| D-37 | Penempatan staging dan production (terpisah direkomendasikan; pengecualian dicatat) | 52.45 | 66 | [MENUNGGU KEPUTUSAN] | Terbuka |

### 54.7.5 Repository eksternal

| ID | Keputusan | Rujukan | Nilai | Status |
|---|---|---|---|---|
| D-38 | Identitas repository No. 1 WhatsApp Business API Dashboard, serta kelayakannya terhadap syarat API resmi (Bab 30.2, Bab 46) | 53.3, 53.3.1, 53.12 | [BELUM DITETAPKAN] — URL kandidat (v2.3.1): https://github.com/rmyndharis/OpenWA, belum diverifikasi; ejaan owner wajib dicek ulang; konten sumber menyatakan bukan API resmi Meta | Terbuka |
| D-39 | Identitas repository No. 9 Archify | 53.3, 53.3.1, 53.12 | [BELUM DITETAPKAN] — URL kandidat (v2.3.1): https://github.com/tt-ali/archify, belum diverifikasi; metode yang diusulkan: Reference Only | Terbuka |
| D-40 | Identitas repository No. 10 OpenSEO dan metode integrasinya | 53.3, 53.3.1, 53.12 | [BELUM DITETAPKAN] — URL kandidat (v2.3.1): https://github.com/every-app/open-seo, belum diverifikasi | Terbuka |
| D-41 | Pemilihan repository No. 12 FREELLMAPI di antara **tiga** kandidat; tidak ada yang dipilih otomatis | 53.3, 53.3.1, 53.12 | [BELUM DITETAPKAN] — (a) lov0u/freellmapi; (b) xxy2026/freellmapi; (c) tashfeenahmed/freellmapi (DIUSULKAN, menunggu verifikasi). Semuanya belum diverifikasi | Terbuka |
| D-42 | Identitas repository No. 25 MuMuAINovel | 53.3, 53.3.1, 53.12 | [BELUM DITETAPKAN] — URL kandidat (v2.3.1): https://github.com/xiamuceer-j/MuMuAINovel, belum diverifikasi | Terbuka |
| D-43 | Ambang "tidak aktif" repository eksternal (usulan awal 12 bulan, bukan keputusan) | 53.11 | [MENUNGGU KEPUTUSAN] | Terbuka |
| D-44 | Keputusan hukum lisensi per kandidat (kode, model, font, asset; penggunaan komersial). **Catatan v2.3.1:** No. 25 MuMuAINovel disebut berlisensi GPL-3.0 oleh konten sumber (belum diverifikasi). Lisensi copyleft ini wajib direview hukum sebelum integrasi ke SaaS komersial; konsekuensinya tidak disimpulkan oleh dokumen ini | 53.9 | [MENUNGGU KEPUTUSAN] | Terbuka |
| D-45 | Penerimaan biaya variabel API data SEO pihak ketiga (DataForSEO, berbayar sesuai pemakaian) bila No. 10 OpenSEO dipakai, beserta batas anggaran dan pemilik biaya | 53.3, 53.12 | [MENUNGGU KEPUTUSAN] | Terbuka |
| D-46 | Kebijakan pemakaian kuota model AI gratis melalui No. 12 FREELLMAPI: boleh tidaknya dipakai untuk fitur berbayar, kepatuhan ToS tiap provider, penanganan API key, dan fallback karena tidak ada SLA | 53.3, 53.12, Bab 26.1 | [MENUNGGU KEPUTUSAN] | Terbuka |

**Rekap:** 44 keputusan terbuka pada saat v2.3 diterbitkan; **46** keputusan terbuka pada v2.3.1 (tambahan D-45 dan D-46). Setelah keputusan diambil, kolom *Nilai* dan *Status* diperbarui, dan nilai pada bagian rujukan diselaraskan.

------------------------------------------------------------------------

## 54.8 Pemeriksaan Referensi Silang

| Rujukan | Memuat | Hasil pemeriksaan |
|---|---|---|
| Bab 52 ke Bab 53 | Repository eksternal | Bab 53 tersedia |
| Bab 52 ke Bab 54 | Checklist 90, register keputusan 54.7 | Bab 54.2 dan 54.7 tersedia |
| Bab 53 ke Bab 54.7 | Keputusan identitas, lisensi, biaya API, dan kuota gratis | D-38 sampai D-46 |
| Bab 53.3.1 ke 53.12 dan 54.7.5 (v2.3.1) | Sumber URL kandidat No. 1, 9, 10, 12, 25 | Setiap URL kandidat memiliki baris keputusan (D-38 sampai D-42) |
| Bab 52 ke Bab 34.4, 15.3, 34.6, 35.1, 38.3, 43.1, 48, 49 | Requirement lama | Dirujuk, tidak diduplikasi |
| Bab 53 ke Bab 12, 37 | Daftar 25 kandidat | Bab 37.1 tetap berlaku; Bab 53.3 menambahkan atribut |
| Bab 54.4 ke Bab 52 | Lokasi 90 butir | Seluruh nomor 52.x pada matriks tersedia |

------------------------------------------------------------------------

## 54.9 Ringkasan Verifikasi Akhir

| Indikator | Nilai |
|---|---|
| Repository kandidat eksternal | **25** |
| Checklist GitHub | **90** |
| Repository dengan URL teridentifikasi | **20** (No. 2, 3, 4, 5, 6, 7, 8, 11, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24) |
| Repository perlu klarifikasi identitas | **5** (No. 1, 9, 10, 12, 25) |
| URL kandidat belum diverifikasi (v2.3.1) | **7** (No. 1, 9, 10, 25: satu URL; No. 12: tiga URL) |
| Repository berstatus Implemented atau Tested | **0** |
| Identitas repository utama | Belum ditetapkan (D-01 sampai D-06) |
| Keputusan terbuka (54.7) | **46** (v2.3: 44) |

**Status dokumen setelah pembaruan:** Master PRD **v2.4** (nama produk Krafio; Bab 55 dan 56 ditambahkan). Bab 52 sampai 54 menambah isi v2.2; v2.3.1 mengisi URL kandidat dan risiko untuk No. 1, 9, 10, 12, dan 25 tanpa menghapus isi lama.


---

# 55. Pricing, Sistem Kredit, Paket Berlangganan, dan Promo

> **Status:** Usulan (draft). Seluruh nominal pada bab ini dihitung dari angka ilustrasi biaya AI pada Bab 7 (chatbot ±Rp25/request, gambar ±Rp700, video ±Rp7.500) dan **wajib dihitung ulang** setelah AI provider per capability ditetapkan (Bab 41 poin 11). Bab ini menjawab keputusan terbuka Bab 20 poin 5, 6, dan 18 serta Bab 41 poin 5–7.
>
> Sesuai prinsip Bab 1.3 (Admin-configurable), seluruh angka di bab ini adalah **nilai awal konfigurasi**, bukan nilai yang ditanam di kode.

------------------------------------------------------------------------

## 55.1 Satuan Kuota: Sistem Kredit Hybrid

Krafio menggunakan tiga jenis satuan kuota:

1. **Kredit Krafio**: mata uang internal untuk fitur media berat (foto, video, edit AI, clipper).
2. **Limit pesan/request**: untuk fitur teks (chatbot, brainstorming).
3. **Limit jumlah**: untuk fitur struktural (website, audit SEO, akun sosial, proyek, storage).

Alasan: biaya foto dan video berbeda sangat jauh, sehingga kuota per fitur yang terpisah ("50 foto + 10 video") menyulitkan user dan tidak sebanding dengan biaya provider (sesuai Bab 6.2).

**Patokan nilai kredit:**

- Harga jual 1 kredit: ±Rp150–250 (lebih murah di dalam paket, lebih mahal sebagai add-on).
- Biaya modal maksimal per kredit: ±Rp90.

### 55.1.1 Tabel Biaya Kredit per Aksi

| Aksi | Kredit |
|---|---|
| Generate foto standar (≤1024px) | 8 |
| Generate foto HD / model premium | 15 |
| Remove background | 2 |
| Upscale | 4 |
| Inpainting / generative fill / edit via prompt | 8 |
| Generate video 720p, per 5 detik | 80 |
| Generate video 1080p / model premium, per 5 detik | 150 |
| Video clipper, per menit video sumber | 10 |
| Auto subtitle / edit video AI, per menit | 5 |
| Resize, crop, trim manual (non-AI) | 0 |

**Catatan:** biaya kredit video adalah komponen paling berisiko. Harga API video generation di pasaran dapat jauh di atas ilustrasi Rp7.500 per video, sehingga tabel ini wajib divalidasi terlebih dahulu terhadap tarif provider yang dipilih.

------------------------------------------------------------------------

## 55.2 Paket Berlangganan Bulanan

| | Free | Starter | Pro | Business | Enterprise |
|---|---|---|---|---|---|
| Harga/bulan | Rp0 | Rp59.000 | Rp179.000 | Rp449.000 | Custom |
| Kredit/bulan | 50 | 300 | 1.000 | 2.500 | Nego |
| Setara foto standar | ±6 | ±37 | ±125 | ±312 | — |
| Chatbot | 10 pesan/hari, model ekonomis | 30/hari | 80/hari + 20 premium/hari | 200/hari + 60 premium/hari | Nego |
| Brainstorming | 5/bulan | 30/bulan | 150/bulan | 500/bulan | Nego |
| AI Agent | — | — | Terbatas | Ya | Ya |
| Template | Free saja | Free + premium tertentu | Semua | Semua | Semua + request |
| Watermark | Ya | Tidak | Tidak | Tidak | Tidak |
| Resolusi foto | 1024px | 2K | 4K (upscale) | 4K | 4K |
| Generate video | — | 720p, maks 5 dtk/job | 1080p, maks 10 dtk/job | 1080p, maks 15 dtk/job | Nego |
| Clipper (durasi sumber) | — | Maks 15 menit | Maks 60 menit | Maks 120 menit | Nego |
| Website builder | 1 draft, preview saja | 1 web di subdomain Krafio | 3 web + 1 custom domain | 10 web + custom domain + export | Nego |
| SEO Tools | 3 audit basic/bulan | 20 audit basic | 100 audit + advanced | 300 audit + advanced, crawl 50 halaman | Nego |
| Akun sosial | — | 2 | 5 + jadwal posting | 15 + WhatsApp Business | Nego |
| Proyek | 1 | 3 | 15 | 50 | Nego |
| Storage | 500 MB | 5 GB | 25 GB | 100 GB | Nego |
| Job paralel | 1 | 2 | 3 | 5 | Nego |
| Prioritas antrean | Rendah | Normal | Tinggi | Tertinggi | Dedicated |

### 55.2.1 Pemetaan ke Entitlement (Bab 4.5)

| Entitlement | Free | Starter | Pro | Business |
|---|---|---|---|---|
| `photo.generate` | Ya (kredit) | Ya | Ya | Ya |
| `photo.edit` | Ya (kredit) | Ya | Ya | Ya |
| `video.generate` | — | Ya | Ya | Ya |
| `video.edit` | — | Ya | Ya | Ya |
| `video.clipper` | — | Ya | Ya | Ya |
| `content.brainstorm` | Ya | Ya | Ya | Ya |
| `templates.premium` | — | Sebagian | Ya | Ya |
| `website.builder` | Preview | Ya | Ya | Ya |
| `seo.basic` | Ya | Ya | Ya | Ya |
| `seo.advanced` | — | Add-on | Ya | Ya |
| `social.integrations` | — | Ya | Ya | Ya |
| `chatbot.basic` | Ya | Ya | Ya | Ya |
| `chatbot.premium` | — | — | Ya | Ya |
| `exports.download` | Watermark | Ya | Ya | Ya |

### 55.2.2 Paket Tahunan

- Skema: bayar 10 bulan, dapat 12 bulan.
- Harga: Starter Rp590.000, Pro Rp1.790.000, Business Rp4.490.000.
- Kredit tetap dicairkan **per bulan**, tidak sekaligus 12 bulan di depan, untuk mencegah pola beli tahunan → habiskan kredit → minta refund.

### 55.2.3 Enterprise

Enterprise menggunakan alur Custom Package Request pada Bab 4 (user mengajukan → Approval Management → admin menentukan harga → penawaran → user setuju → pembayaran → paket aktif).

### 55.2.4 Kontrol Margin

Rumus pemeriksaan untuk setiap paket (skenario user memakai seluruh jatah):

``` text
(kredit_bulanan × Rp90) + (limit_chat_bulanan × biaya_per_pesan)
    ≤ 55% × (harga_paket ÷ 1,11 − fee_payment_gateway)
```

Contoh Pro: Rp179.000 ÷ 1,11 ≈ Rp161.000. Biaya maksimal 1.000 kredit = Rp90.000 (±56%). Nilai ini adalah batas atas; jatah kredit Pro tidak boleh dinaikkan tanpa menaikkan harga.

Rata-rata pemakaian riil diasumsikan 30–60% dari jatah. Admin wajib memantau rasio biaya per paket melalui AI Cost Monitoring (Bab 7, bagian D) dan memasang alert apabila rasio melewati ambang yang ditetapkan.

------------------------------------------------------------------------

## 55.3 Add-on

| Add-on | Harga | Ketentuan |
|---|---|---|
| Kredit 200 | Rp49.000 | Berlaku 90 hari |
| Kredit 500 | Rp109.000 | Berlaku 90 hari |
| Kredit 1.500 | Rp299.000 | Berlaku 90 hari |
| Storage +20 GB | Rp25.000/bulan | Ikut masa langganan |
| Akun sosial +5 | Rp35.000/bulan | Minimal Starter |
| Website tambahan | Rp29.000/bulan per web | Minimal Starter |
| SEO Advanced | Rp49.000/bulan | Khusus Starter |
| Pack template premium | Rp29.000/bulan | Khusus Starter |
| WhatsApp | Sesuai pemakaian + markup | Mengikuti tarif Meta/BSP (Bab 46.6), tidak dibundel flat |

### 55.3.1 Aturan Kredit

1. Kredit paket dipakai terlebih dahulu, kemudian kredit add-on (kredit paket hangus lebih dulu saat reset).
2. Kredit paket tidak di-rollover ke periode berikutnya.
3. Harga per kredit add-on lebih tinggi daripada harga per kredit di dalam paket.
4. **Saldo top up (Rupiah) dan kredit (unit pemakaian) dipisahkan.** Saldo dipakai untuk membeli paket/add-on/kredit; kredit dipakai untuk menjalankan job. Keduanya memiliki ledger terpisah (melengkapi Bab 5.1 dan 31.1).
5. Bonus promosi sebaiknya diberikan dalam bentuk kredit atau diskon, bukan saldo Rupiah, agar ledger keuangan tetap bersih.

------------------------------------------------------------------------

## 55.4 Sistem Kupon dan Promo

Melengkapi menu "Coupons & Promotions" (Struktur Sidebar Admin) dan tabel `coupons`/`promotions` (Bab 32.2) yang sebelumnya belum memiliki spesifikasi.

### 55.4.1 Jenis Promo

| Jenis | Mekanisme | Contoh |
|---|---|---|
| Kode kupon | User memasukkan kode | Diskon persen (dengan batas maksimal), diskon nominal, bonus kredit, bonus masa aktif |
| Promo otomatis | Tanpa kode, diterapkan sistem | Diskon tahunan, early bird saat launching, flash sale |
| Trial Pro 7 hari | Aktivasi sekali | 100 kredit (bukan 1.000), 1x per nomor HP terverifikasi OTP, tanpa auto-charge |
| Diskon referral | Lewat link affiliate | Diskon 10% bulan pertama bagi user baru |
| Win-back | Kupon otomatis | Untuk user yang expired > 14 hari |
| Promo upgrade | Otomatis saat upgrade | Starter → Pro diskon 20% bulan pertama |

### 55.4.2 Atribut Kupon

- Kode.
- Tipe: `percent`, `fixed`, `bonus_credit`, `bonus_days`.
- Nilai.
- Batas maksimal potongan (untuk tipe persen).
- Minimal pembelian.
- Paket dan siklus tagihan yang berlaku.
- Khusus pembelian pertama (ya/tidak).
- Kuota total pemakaian.
- Kuota pemakaian per user.
- Periode aktif (mulai–berakhir).
- Durasi efek: `once`, `first_n_months`, `forever`.
- Status aktif/nonaktif.

### 55.4.3 Aturan Promo

1. Maksimal 1 kupon per transaksi. Kupon tidak digabung dengan promo otomatis; sistem menerapkan harga terbaik bagi user.
2. Kupon **tidak berlaku untuk top up saldo**.
3. Kupon tidak berlaku untuk custom package kecuali diaktifkan admin secara eksplisit.
4. Pemakaian kupon di-reserve saat order dibuat dan dilepas apabila invoice kedaluwarsa atau gagal (pola yang sama dengan reservasi kuota pada BR-017).
5. Invoice menyimpan snapshot harga normal, kode kupon, nilai diskon, harga akhir, dan PPN (sesuai BR-024).
6. Komisi affiliate dihitung dari nominal yang benar-benar dibayar, bukan harga normal.
7. Kupon tidak dapat dipakai untuk self-referral.
8. Promo berdurasi (misalnya "3 bulan pertama") disimpan pada data langganan agar tetap diterapkan saat perpanjangan.
9. Harga coret di frontpage hanya boleh menampilkan harga normal yang memang pernah berlaku.
10. Seluruh pembuatan, perubahan, dan penonaktifan kupon tercatat di audit log.

------------------------------------------------------------------------

## 55.5 Perpanjangan, Grace Period, Upgrade, dan Downgrade

### 55.5.1 Perpanjangan

- Versi awal menggunakan perpanjangan manual dengan pengingat H-7, H-3, dan H-1 (melalui Notification Center, Bab 47).
- Tersedia opsi "perpanjang otomatis menggunakan saldo top up" yang dapat diaktifkan user.
- Status `past_due` hanya dipakai apabila perpanjangan otomatis dari saldo gagal karena saldo tidak cukup.

### 55.5.2 Grace Period

- Durasi: 3 hari setelah masa aktif berakhir.
- Selama grace period: generate dan fitur berbayar dikunci; aset tetap dapat dilihat dan diunduh.

### 55.5.3 Upgrade

- Berlaku langsung.
- Sisa nilai paket lama dihitung prorata harian dan dipotong dari harga paket baru.
- Siklus tagihan dimulai ulang pada hari upgrade.
- Kredit diganti penuh dengan kredit paket baru (sisa kredit paket lama tidak ditambahkan).

### 55.5.4 Downgrade

- Berlaku pada periode tagihan berikutnya.

------------------------------------------------------------------------

## 55.6 Aturan Bisnis Tambahan

| ID | Aturan |
|---|---|
| BR-026 | Fitur media berat menggunakan Kredit Krafio; fitur teks menggunakan limit pesan; fitur struktural menggunakan limit jumlah. |
| BR-027 | Kredit paket dipakai sebelum kredit add-on dan tidak di-rollover. |
| BR-028 | Saldo top up (Rupiah) dan kredit memiliki ledger terpisah. |
| BR-029 | Kredit paket tahunan dicairkan per bulan. |
| BR-030 | Maksimal satu kupon per transaksi; kupon tidak berlaku untuk top up. |
| BR-031 | Pemakaian kupon di-reserve saat order dan dilepas jika pembayaran gagal/kedaluwarsa. |
| BR-032 | Komisi affiliate dihitung dari nominal yang dibayar setelah diskon. |
| BR-033 | Trial hanya sekali per nomor HP terverifikasi. |
| BR-034 | Upgrade berlaku langsung dengan prorata; downgrade berlaku pada periode berikutnya. |
| BR-035 | Grace period 3 hari: fitur berbayar dikunci, aset tetap dapat diakses. |

------------------------------------------------------------------------

## 55.7 Keputusan yang Masih Harus Ditetapkan

| ID | Keputusan | Status |
|---|---|---|
| P-01 | Validasi tabel biaya kredit terhadap tarif AI provider final, terutama video | Terbuka |
| P-02 | Harga yang ditampilkan sudah termasuk PPN atau belum, serta format invoice | Terbuka |
| P-03 | Fitur tim/multi-user (seat) untuk paket Business dan Enterprise, terutama bila target pasar mencakup agensi | Terbuka |
| P-04 | Penanganan downgrade yang melebihi limit (storage, jumlah website, akun sosial): read-only, unpublish, atau tenggat waktu; harus selaras dengan BR-025 | Terbuka |
| P-05 | Status website hasil builder saat langganan berakhir: tetap online, offline, atau tenggat waktu | Terbuka |
| P-06 | Pengembalian kredit untuk job gagal karena kesalahan user (misalnya prompt diblokir moderasi); BR-017 hanya mengatur kegagalan sistem/provider | Terbuka |
| P-07 | Masa berlaku saldo top up dan kredit add-on (Bab 41 poin 7) | Terbuka |
| P-08 | Verifikasi wajib dan pembatasan anti-abuse untuk free tier dan trial (per nomor HP/perangkat) | Terbuka |
| P-09 | Kebijakan refund paket, add-on, kredit, dan top up | Terbuka |
| P-10 | Larangan menjadikan kuota AI gratis (FREELLMAPI, D-46) sebagai dasar perhitungan harga paket berbayar | Terbuka |

## 55.11 Catatan Implementasi (Build Awal v2.4)

> Bagian ini mencatat bagaimana Bab 55 diterapkan pada build awal di repository `dbnewgrup-afk/krafio`. Nilai tetap mengikuti status **Usulan**; Admin dapat mengubah seluruh angka tanpa perubahan kode (Bab 1.3).

| Area | Implementasi | Lokasi |
|---|---|---|
| Kredit hybrid (55.1) | Kredit disimpan per *pack* (`plan`, `trial`, `bonus`, `addon`) dengan ledger append-only. Pemakaian mengambil kredit paket lebih dulu, lalu pack dengan masa berlaku terdekat. Refund job gagal/batal dikembalikan ke pack asal (`ai_jobs.credit_alloc`). | `Services/Credits.php`, tabel `credit_packs`, `credit_ledger` |
| Limit teks dan struktural | Chatbot per hari, brainstorming dan audit SEO per bulan (`usage_counters`); limit struktural dari `plans.limits` (job paralel, resolusi foto, durasi video, sumber clipper, website, akun sosial, proyek, storage). | `Services/Entitlements.php`, `StudioController::enforceLimits` |
| Biaya kredit per fitur | Setting `credits.costs` (foto standar/HD, remove background, upscale, inpaint, video 720p/1080p per 5 detik, edit video per menit, clipper per menit). | Admin › Product & Billing › Pengaturan |
| Paket (55.2) | Free, Starter, Pro, Business, Enterprise (custom) di-seed sebagai nilai awal dan dapat diubah di Admin. | `DatabaseSeeder`, Admin › Paket |
| Add-on (55.3) | Paket kredit, slot website/akun sosial, storage, SEO lanjutan; pemeriksaan konflik add-on. | Admin › Add-on |
| Kupon dan promo (55.4) | Tipe persen, nominal, bonus kredit, dan bulan gratis; durasi `once`/`first_n_months`/`forever`; batas pemakaian global dan per user; pembelian pertama; plan/siklus yang berlaku; reservasi kupon selama pembayaran tertunda dan pelepasan otomatis saat kedaluwarsa. Promo otomatis (referral, upgrade, winback) hanya berlaku bila pengguna tidak memasukkan kode valid; kupon tidak ditumpuk. | `Services/Billing.php`, Admin › Kupon |
| Upgrade, downgrade, perpanjangan (55.5) | Upgrade langsung dengan prorata sisa periode; downgrade dijadwalkan pada akhir periode; perpanjangan otomatis dari saldo top up; status `past_due` dan grace period; kembali ke paket Gratis setelah grace period. Pengingat H-7/H-3/H-1. | `Billing::renewals` (scheduler per jam) |
| Trial (55.6) | Trial Pro 7 hari dengan 100 kredit; sekali per nomor HP (hash) dan per akun. Paket Gratis berjalan digantikan trial selama masa trial. | `Billing::startTrial`, tabel `trials` |
| Komisi | Komisi afiliasi dihitung dari nominal yang benar-benar dibayar setelah diskon. | `Billing::commission` |
| PPN | `billing.ppn_percent` dan `billing.price_includes_ppn`; rincian PPN ditampilkan pada quote. | Setting billing |
| Keputusan P-01 sampai P-10 | Tetap **Terbuka**. Build memakai nilai awal yang dapat dikonfigurasi dan tidak menutup keputusan tersebut. | — |

------------------------------------------------------------------------

# 56. Integrasi Layanan via Admin Panel, Log Pesan, Laporan, dan Backup Data

> **Status:** Ditambahkan pada v2.4 berdasarkan permintaan pemilik produk. Melengkapi Bab 5 (pembayaran), Bab 13 (Admin Panel), Bab 44 (OTP), dan Bab 52 (operasional).

## 56.1 Tujuan

1. Credential payment gateway, SMTP, dan WhatsApp Business dapat diisi dan diubah oleh Admin dari Admin Panel tanpa mengubah berkas `.env` atau kode.
2. Isi pesan email dan WhatsApp dapat diatur dari Admin Panel.
3. Setiap pengiriman email dan WhatsApp tercatat sehingga dapat diaudit.
4. Laporan keuangan dan laporan pesan tersedia di Admin Panel dan dapat diekspor.
5. Data operasional tidak menumpuk tanpa batas melalui backup terjadwal, rotasi, dan retensi log.

## 56.2 Integrasi yang Dapat Dikonfigurasi

| Jenis | Driver | Field utama | Uji koneksi |
|---|---|---|---|
| Payment gateway | Manual (simulasi, hanya lokal), Midtrans Snap, Xendit Invoice, Tripay | Server/secret key, client key, callback token, merchant code, private key, mode sandbox/production | Permintaan API ringan ke gateway |
| SMTP | SMTP | Host, port, enkripsi, username, password, alamat dan nama pengirim | Mengirim email uji ke alamat Admin atau alamat yang diisi |
| WhatsApp | WhatsApp Business Platform (Cloud API) | Phone number ID, WABA ID, access token, versi Graph API, webhook verify token | Membaca profil nomor; kirim pesan template uji |

Aturan:

1. Credential disimpan terenkripsi (enkripsi aplikasi) dan **tidak pernah dikirim kembali** ke browser; UI hanya menampilkan status "tersimpan".
2. Menyimpan ulang tanpa mengisi field rahasia mempertahankan nilai lama.
3. Integrasi tidak dapat diaktifkan bila field wajib kosong.
4. Hanya satu payment gateway aktif pada satu waktu. Bila tidak ada yang aktif, sistem memakai mode manual; tombol simulasi pembayaran hanya tersedia di lingkungan lokal.
5. Webhook gateway diverifikasi sesuai mekanisme masing-masing (signature SHA-512 Midtrans, callback token Xendit, HMAC-SHA256 Tripay), idempoten, dan dicatat di `webhook_logs`.
6. Setiap perubahan integrasi dan hasil uji koneksi masuk audit log.

## 56.3 Pengaturan Isi Pesan

1. **Email:** template per event dan bahasa (ID/EN) dengan variabel `{{name}}`, `{{code}}`, `{{amount}}`, dan sejenisnya, dikelola di Admin › Komunikasi › Template Email.
2. **WhatsApp:** pemetaan event ke template yang disetujui Meta (nama template, bahasa, urutan variabel) di Admin › Komunikasi › Template WhatsApp. Status persetujuan template dapat disinkronkan dari Meta.
3. Event yang didukung: OTP, selamat datang, perubahan kata sandi, top up berhasil, langganan aktif, langganan akan berakhir, withdrawal diajukan, withdrawal dibayar, withdrawal ditolak, dan pesan uji.

## 56.4 Log Pesan

| Log | Isi | Status |
|---|---|---|
| Email (`mail_logs`) | Penerima, subjek, template, mailer, user terkait, pesan error | `sent`, `failed`, `skipped` (template tidak ada/nonaktif) |
| WhatsApp (`wa_logs`) | Nomor tujuan (dinormalisasi), event, template, ID pesan provider, user terkait, pesan error | `sent`, `failed`, `skipped` (integrasi belum aktif) |

Kode OTP tidak disimpan dalam log dalam bentuk terbaca.

## 56.5 Laporan

1. **Laporan keuangan:** penjualan (langganan dan add-on), top up, withdrawal dibayar, dan komisi per hari atau per bulan dalam rentang tanggal; kewajiban berjalan (total saldo top up pengguna, total saldo komisi, withdrawal dalam proses). Ekspor CSV.
2. **Laporan pesan:** jumlah email dan WhatsApp terkirim, gagal, dan dilewati selama 30 hari terakhir.
3. Laporan dibaca dari ledger dan tabel pembayaran (sumber kebenaran), bukan dari angka yang disimpan terpisah.

## 56.6 Backup dan Retensi Data

| Komponen | Aturan nilai awal |
|---|---|
| Backup database | Harian pukul 02.00 WIB (`mysqldump` terkompresi `.sql.gz`) |
| Backup berkas unggahan | Harian bila diaktifkan (`.zip`) |
| Rotasi | Simpan 7 backup harian terakhir dan 4 backup mingguan |
| Retensi log email/WhatsApp/webhook | 90 hari |
| Retensi audit log | 365 hari |
| Retensi security event | 180 hari |
| Notifikasi yang sudah dibaca | 90 hari |
| Pembersihan | Harian pukul 03.00 WIB |

Aturan:

1. Data keuangan (wallet ledger, credit ledger, pembayaran, withdrawal, komisi) **tidak pernah** dipangkas oleh proses retensi.
2. Backup dapat dijalankan manual, diunduh, dan dihapus dari Admin › Sistem › Backup; setiap tindakan masuk audit log.
3. Seluruh angka retensi dapat diubah Admin.
4. Untuk produksi, backup **wajib** disalin ke penyimpanan di luar server (object storage atau server lain); menyimpan backup hanya di server yang sama tidak dianggap cukup.

## 56.7 Acceptance Criteria Bab 56

1. Admin dapat mengisi, menyimpan, menguji, dan mengaktifkan integrasi payment, SMTP, dan WhatsApp tanpa akses ke server.
2. Credential tidak pernah muncul pada respons API maupun di database dalam bentuk teks biasa.
3. Webhook dengan signature salah ditolak (401); webhook valid yang dikirim ulang tidak menambah saldo dua kali.
4. Setiap email dan WhatsApp tercatat dengan status yang benar.
5. Laporan keuangan sesuai dengan ledger dan dapat diekspor CSV.
6. Rotasi backup dan retensi log berjalan terjadwal tanpa menghapus data keuangan.
7. Seluruh kriteria di atas dicakup test otomatis (`apps/backend/tests/Feature`).

------------------------------------------------------------------------

## Riwayat Versi Dokumen

| Versi | Tanggal | Perubahan |
|---|---|---|
| 2.1 | 10 November 2026 | Master PRD dasar dengan 51 bab. |
| 2.2 | 10 November 2026 | Integrasi seluruh 24 bagian Spesifikasi Admin Panel ke bab-bab Master PRD yang relevan; penambahan peta integrasi dan penyelarasan istilah Super Admin. |
| 2.3 | 10 November 2026 | Penambahan Bab 52 (GitHub Repository, Source Code Management & CI/CD), Bab 53 (Repository Eksternal: Register, Audit, dan Metode Integrasi), dan Bab 54 (Checklist GitHub 90 poin, keterlacakan, ketentuan teknis, register keputusan); Catatan Integrasi Versi 2.3. Isi v2.2 dipertahankan. |
| 2.3.1 | 10 November 2026 | Klarifikasi repository eksternal No. 1, 9, 10, 12, dan 25 pada Bab 53 dan 54: URL kandidat dari tangkapan layar konten media sosial (belum diverifikasi), fungsi Archify, risiko tambahan, sub-bab 53.3.1 dan 54.6.1, pembaruan D-38 sampai D-44, serta keputusan baru D-45 dan D-46. Bab 12 dan Bab 37 tidak diubah. Status kelima kandidat tetap *Perlu klarifikasi identitas* dan *Blocked*. |
| 2.3.2 | 10 November 2026 | Perubahan nama produk dari ToolsMP menjadi Krafio di seluruh dokumen, termasuk contoh subdomain, email, dan direktori deployment (domain final tetap D-28). Tidak ada perubahan requirement. Berkas PRD v2.2, v2.3, dan v2.3.1 dihapus karena telah digantikan. |
| 2.4 | 11 November 2026 | Penambahan Bab 55 (Pricing, Sistem Kredit, Paket Berlangganan, dan Promo; status Usulan) beserta catatan implementasi 55.11, dan Bab 56 (Integrasi layanan via Admin Panel, log pesan, laporan, backup dan retensi data). Isi v2.3.2 dipertahankan. Berkas PRD v2.3.2 dihapus karena telah digantikan. |
