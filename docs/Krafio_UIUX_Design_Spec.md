# Krafio — Spesifikasi Desain UI/UX Detail

**Dokumen:** UI/UX Design Specification & Rules
**Pendamping:** Master PRD v2.2 · Technical Blueprint
**Stack UI:** React + TypeScript + Tailwind CSS + Vite (PWA)
**Versi:** 1.0 · **Tanggal:** 10 November 2026
**Bahasa dokumen:** Indonesia (istilah teknis tetap Inggris)

> Dokumen ini adalah aturan yang **mengikat** untuk semua halaman Frontpage, User Panel, dan Admin Panel. Kata **WAJIB** = tidak boleh dilanggar. **SEBAIKNYA** = default kuat, boleh menyimpang dengan alasan tercatat. **BOLEH** = opsional.

---

## Daftar Isi

1. Prinsip Desain & Aturan Anti-"AI Slop"
2. Breakpoint, Grid, dan Aturan Responsif (Mobile / Tablet / Desktop)
3. Design Tokens (Warna, Tipografi, Spacing, Radius, Shadow, Z-index)
4. Tema Light & Dark
5. Bahasa Indonesia ↔ English (i18n)
6. Navbar, Sidebar, dan Sub-sidebar
7. Aturan Panel Admin
8. Aturan User Panel
9. Autentikasi: Login, Register, Lupa Password, OTP (termasuk Show/Hide Password)
10. Sistem Modal / Pop-up
11. Terms & Conditions (modal & halaman)
12. Loading Screen (2 skema: Transparan & Dark) dan Loading State lain
13. Footer (selalu horizontal)
14. Gambar, Slider, dan Carousel
15. Tombol CS (Customer Support) Mengambang
16. Tombol Back-to-Top
17. Toast, Banner, Empty State, Error State
18. Form, Tabel, Kartu, dan Komponen Data (responsif)
19. Skema UX Fitur Inti (locked feature, job AI, wallet, withdrawal, dll.)
20. Aksesibilitas, Motion, Performa
21. Peta Z-index & Stacking
22. Penyimpanan Preferensi Lokal
23. Checklist Penerimaan UI/UX

---

## 1. Prinsip Desain & Aturan Anti-"AI Slop"

### 1.1 Prinsip

| # | Prinsip | Arti praktis |
|---|---|---|
| 1 | Jelas sebelum cantik | Setiap layar punya **satu aksi utama** yang terlihat dalam 2 detik. |
| 2 | Konsisten | Satu design system untuk 3 panel; layout dibedakan menurut konteks. |
| 3 | Aman | Tindakan destruktif/keuangan selalu ada konfirmasi. |
| 4 | Transparan | Biaya/kuota ditampilkan **sebelum** job dijalankan. |
| 5 | Responsif sungguhan | Mobile bukan "desktop yang ditumpuk ke bawah". |
| 6 | Dapat diakses | Keyboard, screen reader, kontras, `prefers-reduced-motion`. |

### 1.2 Aturan "Anti-AI-Slop" (desain generik yang harus dihindari)

Tampilan hasil AI yang generik biasanya: semua blok bertumpuk vertikal satu kolom, kartu seragam tanpa hirarki, gradien ungu-biru di mana-mana, ikon emoji sebagai dekorasi, teks tengah semua, dan jarak seragam. **WAJIB dihindari:**

- **Dilarang** menumpuk lebih dari **3 section konten berurutan** dalam satu kolom tanpa pemecah (tab, carousel, accordion, atau grid 2 kolom).
- **Dilarang** gradien dekoratif sebagai satu-satunya identitas. Warna aksen dipakai untuk **makna** (aksi, status), bukan hiasan.
- **Dilarang** emoji sebagai ikon UI. Gunakan satu set ikon konsisten (Lucide/Heroicons outline, stroke 1.5–2px).
- **Dilarang** kartu identik berulang >6 tanpa variasi ukuran/hirarki (gunakan 1 featured card + sisanya kecil).
- **Dilarang** teks rata tengah untuk paragraf >2 baris. Rata kiri untuk bacaan; tengah hanya untuk hero singkat dan empty state.
- **Dilarang** placeholder "Lorem ipsum" atau stok ilustrasi generik di rilis.
- **WAJIB** hirarki visual jelas: 1 judul halaman, subjudul/ringkasan, lalu konten. Ukuran & berat huruf berbeda per level.
- **WAJIB** gunakan data nyata/representatif dalam preview, bukan konten dummy berulang.

### 1.3 Aturan Anti-Vertical-Sprawl (Mobile Tidak Boleh "Terlalu Panjang ke Bawah")

Tujuan: layar mobile tidak menjadi satu kolom panjang tanpa akhir.

| Pola | Aturan |
|---|---|
| **Chips scroll horizontal** | Filter, kategori, tab >4 item di mobile → baris chip yang bisa digeser kiri-kanan (scroll-snap), bukan ditumpuk. |
| **Carousel** | Daftar kartu (template, paket, fitur, testimoni) di mobile → carousel geser, 1,15–1,3 kartu terlihat (peek) agar jelas bisa digeser. |
| **Grid 2 kolom** | Kartu kecil (template, aset, ikon fitur) di mobile → **2 kolom**, bukan 1. |
| **Accordion** | Konten sekunder (FAQ, detail kuota, syarat) → accordion tertutup default. |
| **Tab / segmented control** | Dua-empat kelompok data setara → tab, bukan section bertumpuk. |
| **Bottom sheet** | Aksi sekunder/filter/pilihan → bottom sheet, bukan halaman baru. |
| **Sticky action bar** | Aksi utama (Bayar, Generate, Simpan) → bar tetap di bawah layar, bukan di akhir halaman panjang. |
| **Batas tinggi** | Satu layar mobile idealnya ≤ **4 viewport tinggi** per halaman utama; lebih dari itu pecah (tab/accordion/halaman). |
| **Tabel** | Tabel mobile → mode kartu ringkas atau scroll horizontal dengan kolom pertama sticky (lihat §18). |
| **Densitas** | Padding vertikal section mobile 24–32px (bukan 64–96px desktop). |

---

## 2. Breakpoint, Grid, dan Aturan Responsif

### 2.1 Breakpoint

| Nama | Lebar | Tailwind | Perangkat |
|---|---|---|---|
| `xs` | < 480px | (default) | Ponsel kecil |
| `mobile` | 480–767px | `sm`≈640 | Ponsel |
| `tablet` | 768–1023px | `md` | Tablet portrait / ponsel landscape |
| `desktop` | 1024–1439px | `lg`/`xl` | Laptop |
| `wide` | ≥ 1440px | `2xl` | Monitor besar |

Desain **mobile-first**; gaya dasar untuk mobile, ditambah di breakpoint lebih besar.

### 2.2 Grid & Container

| Properti | Mobile | Tablet | Desktop | Wide |
|---|---|---|---|---|
| Kolom | 4 | 8 | 12 | 12 |
| Gutter | 12px | 16px | 24px | 24px |
| Margin sisi | 16px | 24px | 32px | auto |
| Lebar maks konten | 100% | 100% | 1200px (frontpage) / fluid (panel) | 1360px frontpage |
| Lebar maks teks bacaan | — | — | 72 karakter (~680px) | sama |

### 2.3 Matriks Perilaku per Perangkat

| Elemen | Mobile (<768) | Tablet (768–1023) | Desktop (≥1024) |
|---|---|---|---|
| Navbar | Tinggi 56px; logo + ikon aksi + tombol ☰ | Tinggi 60px | Tinggi 64px; menu penuh |
| Sidebar | **Drawer** off-canvas, tertutup default | **Rail** ikon 64px, bisa diperluas | **Penuh** 264px, bisa diciutkan ke 72px |
| Sub-sidebar | Menjadi **level kedua di dalam drawer** (slide-in) | Panel mengambang di samping rail | Kolom tetap 240px di samping sidebar |
| Konten | 1 kolom + pola §1.3 | 2 kolom | 2–3 kolom |
| Modal | **Bottom sheet** / full-screen | Dialog tengah (maks 560px) | Dialog tengah (maks 640px) |
| Tabel | Kartu / scroll-x | Scroll-x atau kolom prioritas | Tabel penuh |
| Footer | Horizontal (lihat §13) | Horizontal | Horizontal |
| Tombol CS | 48px, kanan-bawah, di atas bottom bar | 52px | 56px |
| Hit target | ≥ 44×44px | ≥ 44px | ≥ 32px (pointer halus) |

### 2.4 Aturan Umum Responsif

- **WAJIB** tidak ada horizontal scroll pada body di breakpoint manapun (kecuali area yang jelas scrollable: tabel, chips, carousel).
- **WAJIB** teks input ≥ 16px di mobile agar iOS tidak auto-zoom.
- **WAJIB** gunakan `100dvh` (bukan `100vh`) untuk layout full-height di mobile.
- **WAJIB** hormati safe area: `padding-bottom: env(safe-area-inset-bottom)` pada bar bawah, `padding-top: env(safe-area-inset-top)` pada navbar saat PWA standalone.
- **WAJIB** orientasi landscape ponsel tidak merusak layout (tinggi modal dibatasi `max-height: 90dvh` + scroll internal).
- Gambar & video memakai `aspect-ratio` untuk mencegah layout shift (CLS < 0,1).

---

## 3. Design Tokens

Semua nilai di bawah adalah **token**, dideklarasikan sebagai CSS variable dan dipetakan ke `tailwind.config`. Komponen **dilarang** memakai nilai hex/px mentah.

### 3.1 Warna (Light)

| Token | Nilai | Pemakaian |
|---|---|---|
| `--bg` | `#FFFFFF` | Latar halaman |
| `--bg-subtle` | `#F8FAFC` | Latar section/sidebar |
| `--surface` | `#FFFFFF` | Kartu, modal |
| `--surface-2` | `#F1F5F9` | Input, hover |
| `--border` | `#E2E8F0` | Garis |
| `--text` | `#0F172A` | Teks utama |
| `--text-muted` | `#475569` | Teks sekunder (kontras ≥ 4.5:1) |
| `--primary` | `#4338CA` | Aksi utama |
| `--primary-hover` | `#3730A3` | Hover/pressed |
| `--primary-contrast` | `#FFFFFF` | Teks di atas primary |
| `--success` | `#16A34A` | Berhasil |
| `--warning` | `#D97706` | Peringatan |
| `--danger` | `#DC2626` | Error/destruktif |
| `--info` | `#2563EB` | Informasi |
| `--overlay-dark` | `rgba(15,23,42,0.72)` | Scrim modal / loading gelap |
| `--overlay-clear` | `rgba(255,255,255,0.55)` | Scrim loading transparan |

### 3.2 Warna (Dark)

| Token | Nilai |
|---|---|
| `--bg` | `#0B1020` |
| `--bg-subtle` | `#0F172A` |
| `--surface` | `#151C32` |
| `--surface-2` | `#1E2742` |
| `--border` | `#2A3558` |
| `--text` | `#E5E9F5` |
| `--text-muted` | `#A3ADC8` |
| `--primary` | `#818CF8` |
| `--primary-hover` | `#A5B4FC` |
| `--primary-contrast` | `#0B1020` |
| `--success` | `#4ADE80` |
| `--warning` | `#FBBF24` |
| `--danger` | `#F87171` |
| `--info` | `#60A5FA` |
| `--overlay-dark` | `rgba(2,6,23,0.80)` |
| `--overlay-clear` | `rgba(11,16,32,0.55)` |

**Aturan warna:** kontras teks normal ≥ 4.5:1, teks besar & ikon ≥ 3:1 di **kedua** tema. Status tidak boleh hanya dibedakan oleh warna — sertakan ikon/label.

### 3.3 Tipografi

| Token | Ukuran / Line-height (mobile → desktop) | Berat | Pemakaian |
|---|---|---|---|
| `display` | 32/38 → 56/62 | 700 | Hero frontpage saja |
| `h1` | 24/30 → 32/40 | 700 | Judul halaman |
| `h2` | 20/26 → 24/32 | 600 | Judul section |
| `h3` | 17/24 → 18/26 | 600 | Judul kartu |
| `body` | 15/22 → 16/24 | 400 | Teks utama |
| `small` | 13/18 | 400 | Meta, caption |
| `label` | 13/16 | 500 | Label form |
| `mono` | 13/20 | 400 | OTP, kode, ID |

Font: satu keluarga sans-serif variabel (mis. Inter/Plus Jakarta Sans) + mono sistem. `font-display: swap`. Maksimal **2 keluarga** font.

### 3.4 Spacing, Radius, Shadow

- **Spacing** skala 4px: 4, 8, 12, 16, 20, 24, 32, 40, 48, 64.
- **Radius:** `sm` 6px (input kecil), `md` 10px (tombol, input), `lg` 14px (kartu), `xl` 20px (modal, bottom sheet atas), `full` 9999px (chip, avatar, FAB).
- **Shadow:** `sm` (kartu), `md` (dropdown), `lg` (modal), `fab` (tombol mengambang). Di dark mode shadow dikurangi, diganti border 1px.

### 3.5 Ikon & Ilustrasi

Satu set ikon outline konsisten, ukuran 16/20/24px. Ilustrasi empty state: SVG ringan (<20KB), mengikuti warna token (bukan warna kaku).

---

## 4. Tema Light & Dark

### 4.1 Mode yang Tersedia

| Mode | Perilaku |
|---|---|
| `light` | Selalu terang |
| `dark` | Selalu gelap |
| `system` (default) | Mengikuti `prefers-color-scheme`, berubah live saat OS berubah |

### 4.2 Tombol Ganti Tema

- Lokasi: **navbar** (ikon matahari/bulan) pada semua panel; di mobile boleh masuk menu profil/drawer bila navbar penuh.
- Perilaku: klik = siklus **system → light → dark → system** *atau* menu kecil 3 pilihan (SEBAIKNYA menu 3 pilihan agar jelas).
- Ikon menunjukkan **mode aktif**, bukan mode berikutnya. `aria-label` berubah, mis. "Tema: Gelap. Ubah tema".
- Transisi warna 200ms pada `background-color`, `color`, `border-color`. Nonaktif bila `prefers-reduced-motion`.

### 4.3 Implementasi

- Atribut `data-theme="light|dark"` di `<html>`; Tailwind `darkMode: ["class", '[data-theme="dark"]']`.
- **Cegah flash tema salah (FOUC):** skrip inline kecil di `<head>` membaca preferensi dari `localStorage` (`krafio.theme`) dan memasang `data-theme` **sebelum render pertama**.
- Pengguna login: preferensi disimpan juga ke profil (`user_preferences.theme`) dan disinkronkan lintas perangkat; `localStorage` menjadi cache awal.
- Gambar/logo punya varian dark (mis. logo putih) atau memakai `currentColor`.
- Chart, peta, editor, preview template memakai token — **WAJIB** diuji di kedua tema.
- Preview template milik user **tidak** ikut tema (menampilkan hasil asli); beri bingkai netral.

---

## 5. Bahasa Indonesia ↔ English (i18n)

### 5.1 Aturan

| Aspek | Aturan |
|---|---|
| Bahasa tersedia | `id` (default), `en` |
| Struktur | Semua string UI di file terjemahan (`/locales/id.json`, `/locales/en.json`); **dilarang** hardcode teks di komponen. |
| Library | `react-i18next` (atau setara) dengan namespace per modul. |
| Fallback | Jika kunci `en` tidak ada → tampil `id`, lalu catat ke log dev. Tidak boleh tampil kunci mentah. |
| Format | `Intl.NumberFormat`, `Intl.DateTimeFormat`, `Intl.RelativeTimeFormat` sesuai locale. Rupiah: `Rp50.000` (id) / `IDR 50,000` atau `Rp50,000` (en) — satu aturan konsisten ditetapkan di config. |
| Waktu | Disimpan UTC, ditampilkan sesuai zona waktu akun/platform (default `Asia/Jakarta`). |
| Panjang teks | Layout harus tahan teks EN/ID yang lebih panjang ±35% (tidak ada lebar tetap pada tombol/label). |
| Plural | Gunakan aturan plural i18n, bukan penambahan "s" manual. |
| Konten dinamis | Konten dari DB (paket, template, FAQ, blog) punya field per bahasa (`name_id`, `name_en` atau tabel terjemahan). Bila terjemahan kosong → fallback ke `id`. |
| Email/WA/Notifikasi | Mengikuti bahasa preferensi user. |

### 5.2 Tombol Ganti Bahasa

- Lokasi: navbar (ikon globe + kode `ID` / `EN`), juga di footer frontpage dan halaman auth.
- Bentuk: dropdown kecil dengan dua pilihan + tanda centang pada yang aktif. Label bahasa ditulis **dalam bahasa aslinya**: "Bahasa Indonesia", "English".
- Perilaku: ganti bahasa **tanpa reload halaman penuh**, tidak menghapus isian form yang sedang diketik, mempertahankan scroll.
- Prioritas penentuan bahasa awal: (1) preferensi akun → (2) `localStorage` (`krafio.lang`) → (3) `Accept-Language` browser → (4) `id`.
- `<html lang="id|en">` diperbarui; URL frontpage memakai prefiks `/en/...` (default tanpa prefiks) untuk SEO, dengan tag `hreflang`.
- Pesan error validasi dari server **WAJIB** ikut terlokalisasi (server mengembalikan kode error; klien memetakan ke teks).

---

## 6. Navbar, Sidebar, dan Sub-sidebar

### 6.1 Terminologi

```
┌──────────────────────────────────────────────────────────┐
│ NAVBAR  (logo · search · bahasa · tema · notif · profil)  │
├────────┬──────────┬───────────────────────────────────────┤
│SIDEBAR │SUB-SIDE  │  KONTEN                               │
│(level1)│BAR(lvl 2)│  (breadcrumb · judul · aksi · isi)    │
│        │          │                                       │
└────────┴──────────┴───────────────────────────────────────┘
```

| Komponen | Fungsi |
|---|---|
| **Navbar** | Bar atas global: identitas, pencarian, utilitas (bahasa, tema, notifikasi, profil). Selalu terlihat (sticky). |
| **Sidebar** | Navigasi level 1 (grup/modul). |
| **Sub-sidebar** | Navigasi level 2 (halaman dalam modul terpilih). Hanya ada saat modul punya >3 halaman anak. |

### 6.2 Navbar

**Desktop (≥1024)**

```
[☰ ciutkan] [Logo]  [🔍 Cari … ⌘K]            [ID▾] [☾▾] [🔔 3] [Avatar▾]
```

**Mobile (<768)**

```
[☰] [Logo]                                   [🔍] [🔔3] [Avatar]
```

Aturan navbar:

- Tinggi 56 (mobile) / 60 (tablet) / 64 (desktop). `position: sticky; top: 0`, z-index `navbar`.
- Latar `--surface` + border-bottom 1px. Saat scroll >8px: tambah shadow `sm`.
- Pencarian di mobile: ikon → membuka **overlay pencarian layar penuh** (bukan input sempit).
- Notifikasi: badge angka (maks "99+"), klik → dropdown (desktop) / bottom sheet (mobile) berisi 10 terbaru + "Lihat semua".
- Menu profil: nama, paket aktif, Profil, Pengaturan, Bahasa, Tema, Bantuan, Keluar. Di Admin Panel tampil label **ADMIN** dan indikator MFA aktif.
- Navbar **tidak boleh** menyembunyikan diri saat scroll di Admin Panel; di Frontpage mobile **BOLEH** auto-hide saat scroll ke bawah dan muncul saat scroll ke atas.

### 6.3 Sidebar — Aturan Perilaku

| Kondisi | Desktop ≥1024 | Tablet 768–1023 | Mobile <768 |
|---|---|---|---|
| Bentuk default | Penuh (264px) | Rail ikon (64px) | Tersembunyi (drawer) |
| Tombol toggle | ☰ di navbar: Penuh ↔ Ringkas (72px) | ☰: rail ↔ overlay penuh | ☰: buka/tutup drawer |
| Cara tutup | Toggle | Klik di luar / Esc | Klik scrim, swipe kiri, tombol ✕, Esc, pilih menu |
| Scrim | Tidak ada | Ada saat overlay | **Ada** (`--overlay-dark`) |
| Fokus | Normal | Trap fokus saat overlay | **Trap fokus** di drawer |
| Status tersimpan | Ya (`krafio.sidebar`) | Tidak | Tidak (selalu tertutup saat load) |

**Aturan drawer mobile (WAJIB):**

1. Lebar `min(86vw, 320px)`, tinggi `100dvh`, geser dari kiri (transform, 240ms ease-out).
2. Saat terbuka: scroll body dikunci (`overflow: hidden` pada `<html>`), scrim menutup konten.
3. **Gestur:** swipe kanan dari tepi kiri layar (≤24px) → buka; swipe kiri pada drawer → tutup; ambang 30% lebar atau kecepatan >0,3px/ms. (Pada iOS Safari, jangan bentrok dengan gestur "back" — jadikan opsional via tombol ☰.)
4. Memilih item **tanpa anak** → navigasi + drawer menutup otomatis.
5. Memilih item **dengan anak** → membuka sub-level (lihat §6.5); drawer **tidak** menutup.
6. Tombol Back (browser/hardware Android) saat drawer terbuka → **menutup drawer**, bukan meninggalkan halaman (push history state).
7. Fokus pindah ke tombol tutup/item pertama saat dibuka; kembali ke tombol ☰ saat ditutup.
8. `aria-modal="true"`, `role="dialog"` pada drawer mobile; `aria-expanded` pada tombol ☰.

### 6.4 Sidebar — Anatomi Item

- Ikon 20px + label + (opsional) badge angka + panah `›` bila punya anak.
- Item aktif: latar `--primary` 12% + garis kiri 3px + teks `--primary`. Aktif ditentukan oleh route (bukan klik terakhir).
- Mode ringkas (ikon saja): tooltip label muncul saat hover/fokus (delay 300ms).
- Grup berjudul kecil (uppercase 11px, `--text-muted`) dengan garis pemisah; grup dapat dilipat; status lipat disimpan.
- Bagian bawah sidebar: info paket/kuota ringkas (user) atau status sistem (admin) + tautan Bantuan.
- Fitur terkunci (user): ikon gembok + label; klik membuka **modal fitur terkunci** (§10.5), bukan error.

### 6.5 Sub-sidebar

| Perangkat | Bentuk |
|---|---|
| Desktop ≥1024 | Kolom tetap 240px di kanan sidebar; berisi anak dari modul aktif; bisa disembunyikan (tombol «). |
| Tablet | Panel flyout 260px yang muncul di samping rail saat modul dipilih; menutup saat klik di luar. |
| Mobile | **Level kedua di drawer**: panel slide-in dari kanan menggantikan daftar level 1, dengan header `‹ Kembali` + nama modul. |

Aturan:

- Sub-sidebar hanya muncul bila modul punya **≥ 3** halaman anak; jika kurang, anak ditampilkan sebagai tab di halaman.
- Kedalaman maksimum navigasi: **2 level sidebar** + tab di halaman. Dilarang level ke-3 di sidebar.
- Breadcrumb di konten selalu mencerminkan: `Modul › Halaman › Detail`. Di mobile, breadcrumb dipadatkan menjadi `‹ Halaman induk` (satu tautan kembali).
- Rute aktif otomatis membuka modul induk dan menggulir item ke area terlihat.

---

## 7. Aturan Panel Admin

### 7.1 Struktur Layout (WAJIB)

Admin Panel **wajib** memiliki tiga lapisan navigasi:

1. **Navbar** (global, sticky).
2. **Sidebar** (16 grup sesuai PRD: Overview, User Mgmt, Approval, Product & Billing, Financial, AI Mgmt, Template Studio, Media, Website Builder, SEO, Sosial, Komunikasi, Konten, System & Security, Support, Settings).
3. **Sub-sidebar** (halaman anak dari grup yang dipilih; mis. AI Management → Providers, Models, Gateway, Agents, Prompt Library, Usage, Token, Limits, Cost, Health, Fallback).

### 7.2 Aturan Khusus Admin

| # | Aturan |
|---|---|
| 1 | Admin Panel **tidak** memiliki mode publik; semua route di balik autentikasi + MFA. |
| 2 | Banner tipis (warna `--warning`/merah lembut) di bawah navbar bila environment ≠ production ("STAGING") agar tidak tertukar. |
| 3 | Label **ADMIN** + avatar berbeda warna dengan user biasa. |
| 4 | Sidebar memiliki **badge hitung** untuk antrean tindakan: Pending Approvals, Withdrawal Approval, Support Tickets, Failed Jobs. |
| 5 | Halaman daftar wajib: pencarian, filter, sort server-side, pagination (10/25/50/100), kolom bisa disembunyikan, export (mengikuti filter aktif). |
| 6 | Tindakan keuangan (approve withdrawal, adjust saldo, refund) → **modal konfirmasi + alasan wajib + re-autentikasi** bila sesi >15 menit. |
| 7 | Tindakan destruktif → modal konfirmasi merah; untuk hapus massal wajib ketik kata kunci (mis. `HAPUS`). |
| 8 | Setiap detail data sensitif di-mask (email `a***@mail.com`, rekening `****1234`) dengan tombol "Tampilkan" yang **tercatat ke audit log**. |
| 9 | API key/secret **tidak pernah** ditampilkan ulang; hanya status + tombol "Rotasi". |
| 10 | Halaman detail memakai **tab** (mis. User Detail: Overview, Profile, Subscription, Wallet, Transactions, AI Usage, Media Jobs, Websites, Affiliate, Security, Activity Logs). |
| 11 | Dashboard memakai kartu KPI yang **bisa diklik** menuju daftar terfilter. |
| 12 | Aksi massal (checkbox) muncul sebagai **toolbar kontekstual** di atas tabel. |
| 13 | Setiap form konfigurasi punya status **Draft / Aktif**, tombol "Uji" (mis. Test Connection), dan "Simpan" yang hanya aktif saat ada perubahan (dirty state). |
| 14 | Keluar halaman dengan perubahan belum tersimpan → modal "Buang perubahan?". |
| 15 | Timeout sesi admin: peringatan modal 60 detik sebelum habis dengan tombol "Perpanjang". |

### 7.3 Layout Admin per Perangkat

- **Desktop:** Navbar + Sidebar penuh + Sub-sidebar 240px + konten fluid (maks 1600px).
- **Tablet:** Navbar + Rail ikon + sub-sidebar flyout; tabel dengan kolom prioritas.
- **Mobile:** Navbar + drawer 2-level; tabel → kartu; aksi massal → bottom sheet. Admin Panel **dapat dipakai** di mobile untuk tugas cepat (approve, lihat alert), tetapi tugas kompleks (Template Editor, rule SEO builder) menampilkan saran "Lebih nyaman di layar lebar" **tanpa memblokir**.

### 7.4 Pola Halaman Admin Standar

```
Breadcrumb
Judul halaman                         [Aksi sekunder] [+ Aksi utama]
Ringkasan KPI (maks 4 kartu; mobile: carousel)
[Tab 1] [Tab 2] [Tab 3]
Toolbar: [🔍 Cari] [Filter ▾] [Kolom ▾] [Export ▾]      [Menampilkan 25 ▾]
Tabel / Kartu
Pagination
```

---

## 8. Aturan User Panel

| # | Aturan |
|---|---|
| 1 | Navigasi mengikuti §6 (sidebar + sub-sidebar bila perlu). Menu user lebih sedikit; sub-sidebar jarang dipakai (cukup tab). |
| 2 | Dashboard memuat: status paket + masa berlaku, sisa kuota per fitur, saldo **Top-up** dan **Komisi** (dua kartu berbeda warna/label), ringkasan job, proyek & aset terakhir, notifikasi penting, shortcut fitur. |
| 3 | Mobile: dashboard = **kartu ringkas horizontal scroll** (kuota, saldo) + daftar job terbaru; hindari 10+ kartu bertumpuk. |
| 4 | Fitur di luar paket tampil **terkunci** (ikon gembok) dengan CTA upgrade — bukan disembunyikan. |
| 5 | Sebelum job berbayar: tampilkan **estimasi biaya/kuota** dan sisa kuota; tombol utama label jelas ("Generate — 3 kredit"). |
| 6 | Job berjalan: indikator status (queued/processing/completed/failed) + progress + tombol batal bila memungkinkan; user boleh meninggalkan halaman, job tetap jalan (notifikasi saat selesai). |
| 7 | Bottom navigation (mobile) **BOLEH** untuk 4–5 tujuan utama (Beranda, Proyek, Buat ➕, Aset, Akun); bila dipakai, sidebar drawer tetap ada untuk menu lengkap. |
| 8 | Saldo dan nominal selalu format Rupiah penuh; komisi dan top-up tidak pernah digabung di satu angka. |

---

## 9. Autentikasi: Login, Register, Lupa Password, OTP

### 9.1 Layout Halaman Auth

| Perangkat | Layout |
|---|---|
| Desktop ≥1024 | **Split 2 kolom**: kiri panel visual/benefit (carousel 3 slide, auto 6 dtk, bisa pause), kanan kartu form lebar maks 440px, rata tengah vertikal. |
| Tablet | Kartu form tengah (maks 480px) di atas latar `--bg-subtle`; panel visual disembunyikan. |
| Mobile | Form **layar penuh** tanpa kartu/bayangan; logo kecil di atas; konten dimulai ≤ 24px dari navbar; tombol utama sticky di bawah bila form panjang. |

Di semua ukuran: toggle **Bahasa** dan **Tema** tersedia di pojok kanan atas; tautan "← Kembali ke beranda".

### 9.2 Skema Panel Login

```
┌──────────────────────────────────────────┐
│  [Logo Krafio]                 [ID▾][☾] │
│                                          │
│  Masuk ke akun Anda                      │  ← h1
│  Belum punya akun? Daftar                │
│                                          │
│  Email atau nomor telepon                │
│  [ nama@email.com                      ] │
│                                          │
│  Kata sandi            Lupa kata sandi?  │
│  [ ••••••••••                     👁  ]  │  ← tombol show/hide
│                                          │
│  ☐ Ingat saya di perangkat ini           │
│                                          │
│  [ Cloudflare Turnstile ]  (bila risiko) │
│                                          │
│  [            Masuk               ]      │  ← primary, full width
│                                          │
│  ───────────── atau ─────────────        │  (hanya bila SSO diaktifkan)
│  [ Lanjutkan dengan Google ]             │
│                                          │
│  Dengan masuk, Anda menyetujui           │
│  Syarat & Ketentuan dan Kebijakan Privasi│  ← buka modal §11
└──────────────────────────────────────────┘
```

**Field & validasi**

| Field | Aturan |
|---|---|
| Email/telepon | `autocomplete="username"`, `inputmode="email"`; trim spasi; email lowercase untuk dikirim; validasi format saat **blur** (bukan tiap ketikan). |
| Kata sandi | `autocomplete="current-password"`, tanpa batas atas panjang di klien; validasi hanya "wajib diisi". |
| Ingat saya | Memperpanjang masa sesi (mis. 30 hari) — default **tidak tercentang** pada perangkat publik. |
| Turnstile | Muncul setelah ≥3 gagal atau skor risiko tinggi; token divalidasi di backend. |
| Tombol Masuk | Disabled **hanya** saat request berjalan (state loading + spinner + teks "Memproses…"); **tidak** disabled karena form kosong (agar error bisa dijelaskan). |

**Perilaku**

- Enter pada field mana pun = submit.
- Error ditampilkan di bawah field + ringkasan `role="alert"` di atas form; fokus pindah ke field bermasalah pertama.
- Pesan gagal login **generik** ("Email atau kata sandi salah") — tidak membocorkan apakah email terdaftar.
- Rate limit: setelah N gagal → pesan "Terlalu banyak percobaan. Coba lagi dalam mm:ss" dengan hitung mundur dan tombol dinonaktifkan.
- Akun `Pending Verification` → arahkan ke layar verifikasi OTP; `Pending Approval` → layar status menunggu persetujuan admin (bukan error); `Suspended/Banned/Deactivated` → pesan spesifik + tautan Bantuan.
- Sukses → redirect ke halaman tujuan semula (`?next=`, tervalidasi *same-origin*) atau Dashboard.
- Admin login: halaman terpisah (`admin.` / route terproteksi), setelah password → langkah **MFA (TOTP)** wajib.

### 9.3 Show / Hide Password (Detail Lengkap)

| Aspek | Aturan |
|---|---|
| Komponen | Satu komponen `PasswordField` dipakai di **semua** form kata sandi (login, register, ganti, reset, konfirmasi). |
| Posisi tombol | Di dalam input, sisi kanan, area sentuh **44×44px**; input diberi `padding-right: 48px` agar teks tidak tertutup. |
| Ikon | Mata (`eye`) = "Tampilkan"; mata dicoret (`eye-off`) = "Sembunyikan". Ikon menunjukkan **aksi** yang akan terjadi. |
| Tipe elemen | `<button type="button">` (bukan `submit`), **bukan** `tabindex="-1"` — harus dapat difokus keyboard. |
| ARIA | `aria-label` "Tampilkan kata sandi" / "Sembunyikan kata sandi" (terlokalisasi); `aria-pressed` mencerminkan status; `aria-controls` menunjuk id input. Perubahan diumumkan via `aria-live="polite"` ("Kata sandi ditampilkan"). |
| Perilaku | Toggle mengubah `type` antara `password` ↔ `text`; **posisi kursor dan seleksi dipertahankan**; fokus tetap di input setelah toggle (kecuali pengguna fokus ke tombol via keyboard). |
| Default | Selalu **tersembunyi** saat halaman dimuat dan setelah submit gagal. |
| Auto-hide | Otomatis kembali tersembunyi setelah **30 detik** atau saat tab kehilangan fokus (`visibilitychange`) atau saat form dikirim. |
| Konfirmasi password | Field "Ulangi kata sandi" memiliki toggle **sendiri**; **BOLEH** satu toggle mengontrol keduanya pada form register (opsi: "Tampilkan kedua"). |
| Anti-regresi | Browser bawaan (Edge/IE) ikon reveal bawaan dinonaktifkan (`::-ms-reveal { display:none }`) agar tidak dobel. |
| Password manager | `autocomplete` benar (`current-password` / `new-password`) agar manajer sandi bekerja; **dilarang** `autocomplete="off"` pada field password. |
| Paste | **Diizinkan** (jangan blokir paste). |
| Caps Lock | Jika terdeteksi aktif saat mengetik → tampilkan peringatan kecil "Caps Lock aktif" di bawah field. |
| Layar | Pada layar sentuh, ikon tidak mengganggu keyboard virtual (field tetap terlihat di atas keyboard: `scrollIntoView({block:'center'})`). |
| Dark mode | Ikon & fokus ring mengikuti token; kontras ≥ 3:1. |
| Keamanan | Nilai password **tidak** disimpan di state global, URL, log analytics, atau session replay (input ditandai `data-sensitive`/dimasking). |

### 9.4 Indikator Kekuatan Password (Register / Ganti / Reset)

- Meter 4 segmen + label teks (Lemah / Cukup / Kuat / Sangat kuat), **bukan hanya warna**.
- Checklist aturan hidup (✓/✗): min 8 karakter (rekomendasi 10+), kombinasi huruf & angka, tidak sama dengan email/nama, tidak ada di daftar password umum (cek sisi server).
- Pesan bantu muncul saat fokus, bukan sebelum pengguna menyentuh field.
- Tidak ada aturan yang memaksa karakter spesial berlebihan; prioritaskan panjang.

### 9.5 Register

```
Buat akun Krafio
[ Nama lengkap          ]
[ Email                 ]
[ Nomor WhatsApp  +62 ▾ ]   (kode negara dropdown, default +62; normalisasi ke E.164)
[ Kata sandi        👁  ] + meter
[ Ulangi kata sandi 👁  ]
☐ Saya menyetujui Syarat & Ketentuan dan Kebijakan Privasi   ← WAJIB dicentang (modal §11)
[ Turnstile ]
[        Daftar         ]
```

- Checkbox persetujuan **tidak** tercentang default; teks tautan membuka **modal T&C** tanpa meninggalkan form; isian form tidak hilang.
- Setelah daftar → layar **Verifikasi** (OTP/link) → bila mode *Manual Approval*: layar "Menunggu persetujuan" → email/notifikasi saat disetujui.
- Mode registrasi (auto/manual) memengaruhi **teks dan layar akhir**, bukan formulirnya.

### 9.6 Lupa Password & Reset

1. Layar "Lupa kata sandi": input email → pesan netral "Jika email terdaftar, tautan reset dikirim" (selalu sama).
2. Tautan sekali pakai + kedaluwarsa → halaman set password baru (dua field dengan toggle + meter).
3. Sukses → semua sesi lain dicabut → layar sukses + tombol Masuk.
4. Tautan kedaluwarsa/dipakai → layar ramah dengan tombol "Minta tautan baru".

### 9.7 Layar OTP

```
Verifikasi email Anda
Kami mengirim kode 6 digit ke a***@mail.com

[ _ ][ _ ][ _ ][ _ ][ _ ][ _ ]      ← 6 kotak

Kode berlaku 04:32
[ Verifikasi ]
Belum menerima? Kirim ulang (59 dtk)   ·   Ganti alamat
```

| Aspek | Aturan |
|---|---|
| Input | 6 kotak, `inputmode="numeric"`, `autocomplete="one-time-code"`, auto-advance, backspace mundur, **paste 6 digit mengisi semua kotak**. |
| Submit | Otomatis saat 6 digit terisi (tetap ada tombol manual). |
| Timer | Hitung mundur masa berlaku (5 menit); setelah habis → kotak dinonaktifkan + "Kode kedaluwarsa. Kirim kode baru." |
| Kirim ulang | Cooldown 60 dtk dengan hitung mundur; batas pengiriman per jam → pesan jelas bila tercapai. |
| Salah kode | Kotak bergetar singkat (nonaktif saat reduced-motion) + sisa percobaan ("Sisa 3 percobaan"). Setelah 5 gagal → cooldown. |
| Kanal | Email default; opsi "Kirim lewat WhatsApp" hanya bila kanal aktif & nomor terverifikasi. |
| Keamanan | Tujuan ditampilkan ter-mask; kode tidak pernah tampil di UI/log. |
| Aksesibilitas | Setiap kotak `aria-label="Digit 1 dari 6"`; status & error via `aria-live`. |

### 9.8 Status Akun — Layar Khusus

| Status | Tampilan |
|---|---|
| Pending Verification | Ilustrasi + tombol "Verifikasi sekarang" |
| Pending Approval | Ilustrasi jam + "Akun Anda sedang ditinjau" + estimasi + tautan Bantuan |
| Request Revision | Daftar catatan admin + tombol "Perbarui data" |
| Rejected | Alasan (dari admin) + tautan Bantuan |
| Suspended / Banned | Pesan spesifik + tombol "Hubungi Support" |

---

## 10. Sistem Modal / Pop-up

### 10.1 Jenis Modal

| Tipe | Pemakaian | Ukuran desktop | Mobile |
|---|---|---|---|
| **Dialog informasi** | Pesan singkat | 400px | Bottom sheet |
| **Konfirmasi** | Hapus, keluar, batal | 440px | Bottom sheet |
| **Konfirmasi destruktif** | Hapus permanen, ban | 480px, aksen merah | Bottom sheet |
| **Form** | Tambah/ubah singkat | 560px | Full-screen / sheet tinggi |
| **Fitur terkunci / upsell** | Fitur di luar paket | 560px | Bottom sheet |
| **Syarat & Ketentuan** | Baca & setuju | 720px (scroll internal) | Full-screen |
| **Pratinjau media** | Gambar/video/template | 90vw × 90dvh | Full-screen |
| **Wizard** | Multi-langkah | 640px | Full-screen |
| **Drawer samping** | Detail cepat/filter | 420px kanan | Bottom sheet |
| **Toast** | Umpan balik ringan | — | — (lihat §17) |

### 10.2 Anatomi

```
┌─────────────────────────────────────────┐
│ [ikon]  Judul modal                  ✕ │  ← header (sticky)
├─────────────────────────────────────────┤
│  Isi / form / konten (scroll internal)  │  ← body
├─────────────────────────────────────────┤
│              [Batal]  [Aksi utama]      │  ← footer (sticky)
└─────────────────────────────────────────┘
```

Mobile bottom sheet:

```
        ────  ← handle (drag)
  Judul                              ✕
  Isi…
  [        Aksi utama (full width)  ]
  [        Batal (teks)             ]
```

### 10.3 Aturan Perilaku (WAJIB)

| # | Aturan |
|---|---|
| 1 | Dirender lewat **portal** di akhir `<body>`; z-index mengikuti §21. |
| 2 | Scrim: `--overlay-dark`; klik scrim menutup **hanya** untuk modal non-kritis. Modal form dengan perubahan belum tersimpan, konfirmasi keuangan, dan T&C wajib → **tidak** menutup lewat scrim (ada getar kecil sebagai petunjuk). |
| 3 | **Esc** menutup modal non-kritis; untuk modal kritis Esc memunculkan konfirmasi "Batalkan?" bila ada perubahan. |
| 4 | **Focus trap**: Tab/Shift+Tab berputar di dalam modal; fokus awal ke elemen interaktif pertama (atau judul bila isi hanya teks); saat tutup, fokus kembali ke elemen pemicu. |
| 5 | Scroll body dikunci selama modal terbuka; mencegah *layout shift* dengan kompensasi lebar scrollbar. |
| 6 | `role="dialog"` (atau `alertdialog` untuk konfirmasi destruktif), `aria-modal="true"`, `aria-labelledby` → judul, `aria-describedby` → isi. |
| 7 | Animasi: desktop fade + scale 0.96→1 (180ms); mobile slide-up (240ms). Nonaktif/diganti fade cepat saat `prefers-reduced-motion`. |
| 8 | **Satu modal aktif** pada satu waktu. Modal bertingkat (mis. T&C dari form) → modal kedua ditumpuk dengan scrim lebih gelap, **maksimal 2 level**; tutup level 2 kembali ke level 1. |
| 9 | Tombol utama berada di **kanan** (desktop) dan **atas** tombol sekunder (mobile). Tombol destruktif tidak menjadi tombol default berfokus. |
| 10 | Tombol Back browser/Android menutup modal teratas (push state), bukan meninggalkan halaman. |
| 11 | Aksi async: tombol utama menampilkan spinner + disabled; modal tidak menutup sebelum sukses; error tampil **di dalam modal**. |
| 12 | Tinggi maks `90dvh`; isi scroll internal dengan bayangan gradient di tepi atas/bawah saat ada konten tersembunyi. |
| 13 | Bottom sheet dapat di-**drag ke bawah** untuk menutup (ambang 30% tinggi); modal kritis menonaktifkan drag. |
| 14 | Judul ≤ 60 karakter, kalimat aksi jelas. Label tombol berupa **kata kerja spesifik** ("Hapus proyek", bukan "OK"). |
| 15 | Deep link: modal tertentu (mis. detail, T&C) dapat dibuka via query `?modal=terms`; refresh mempertahankan keadaan. |
| 16 | Muncul otomatis (promo, pengumuman) dibatasi: **maks 1 per sesi**, tidak muncul di halaman auth/pembayaran, ada "Jangan tampilkan lagi", dan **dilarang** menutupi tombol CS tanpa alasan. |

### 10.4 Konfirmasi Destruktif

```
┌──────────────────────────────────────┐
│ ⚠ Hapus proyek "Katalog Produk"?     │
│ Semua aset dan versi akan dipindah   │
│ ke tempat sampah selama 30 hari.     │
│                                      │
│ [Batal]            [Hapus proyek]    │  ← merah
└──────────────────────────────────────┘
```

Untuk tindakan **tidak dapat dibatalkan** (hapus permanen akun/data, hapus massal admin): tambahkan input "Ketik **HAPUS** untuk melanjutkan"; tombol baru aktif bila cocok.

### 10.5 Modal Fitur Terkunci / Upsell

Muncul saat user mengklik fitur di luar entitlement (atau kuota habis).

```
┌─────────────────────────────────────────┐
│ 🔒  Generate Video                     ✕ │
│ Fitur ini belum termasuk paket Anda.     │
│                                          │
│ Tersedia di:                             │
│  • Paket Pro   — Rp…/bulan               │
│  • Add-on "Video 60 menit" — Rp…         │
│                                          │
│ Anda memakai: Paket Basic                │
│                                          │
│ [Lihat paket]            [Beli add-on]   │
│ Nanti saja                               │
└─────────────────────────────────────────┘
```

Varian **kuota habis**: tampil sisa 0/…, tanggal reset kuota, opsi "Beli kuota tambahan". Varian **langganan kedaluwarsa**: tombol "Perpanjang". Harga diambil dari API (tidak hardcode).

### 10.6 Modal Konfirmasi Keuangan (Top-up / Withdrawal / Bayar)

- Menampilkan ringkasan: item, nominal, biaya (bila ada), total, sumber dana (Saldo top-up / Payment gateway).
- Withdrawal: sumber = **Saldo Komisi** saja; tampil tujuan (ter-mask), nominal, estimasi 1–3 hari kerja, catatan biaya/pajak bila berlaku.
- Tombol final menggunakan label nominal ("Bayar Rp150.000", "Ajukan Rp100.000").
- Mencegah klik ganda (disabled + idempotency key).

### 10.7 Modal Sesi Habis / Offline

- **Sesi berakhir:** modal tidak dapat ditutup, tombol "Masuk lagi" (mempertahankan konteks/`next`).
- **Offline (PWA):** banner (bukan modal) "Anda sedang offline"; tindakan yang butuh jaringan dinonaktifkan dengan penjelasan.
- **Pembaruan aplikasi tersedia:** toast persisten "Versi baru tersedia — Muat ulang" (tidak memaksa bila ada draft belum tersimpan).

---

## 11. Terms & Conditions (T&C) dan Kebijakan

### 11.1 Dokumen Hukum

| Dokumen | Halaman publik | Modal |
|---|---|---|
| Syarat & Ketentuan (T&C) | `/terms` | Ya |
| Kebijakan Privasi | `/privacy` | Ya |
| Kebijakan Refund | `/refund` | Ya |
| Ketentuan Affiliate | `/affiliate-terms` | Ya |
| Kebijakan Cookie | `/cookies` | Ya |

Seluruh dokumen punya **versi** (`v1.0`, tanggal efektif) dan dapat dikelola admin (Content Management). Teks hukum final berasal dari tinjauan legal — dokumen ini hanya mengatur **perilaku UI**.

### 11.2 Modal T&C — Skema

```
┌───────────────────────────────────────────────┐
│ Syarat & Ketentuan  · v1.2 · berlaku 10 Nov 2026 ✕│
│ [Daftar isi ▾]  (mobile: dropdown / desktop: kiri)│
├───────────────────────────────────────────────┤
│ 1. Definisi …                                  │
│ 2. Akun …                                      │
│ … (scroll internal)                            │
│                                                │
├───────────────────────────────────────────────┤
│ ☐ Saya telah membaca dan menyetujui            │  ← aktif setelah scroll ke bawah
│ [Tolak]                     [Setuju & Lanjut]  │
└───────────────────────────────────────────────┘
```

**Aturan**

1. **Konteks pemicu:**
   - *Register* → modal **opsional dibaca** dari tautan; persetujuan utama adalah checkbox di form.
   - *Perubahan versi T&C* (login setelah versi baru) → modal **wajib setujui** (tidak bisa ditutup lewat scrim/Esc) dengan ringkasan perubahan di atas.
   - *Aktivasi Affiliate / Withdrawal pertama / Integrasi sosial* → modal ketentuan terkait wajib disetujui sekali per versi.
2. Tombol **"Setuju"** aktif setelah pengguna **menggulir sampai bawah** (untuk persetujuan wajib versi baru). Untuk bacaan dari register, tombol "Tutup" saja.
3. Daftar isi: desktop sebagai kolom kiri 220px (sticky, menyorot bagian aktif); mobile sebagai dropdown/accordion di atas konten.
4. Teks: lebar baca maks 72 karakter, ukuran 15–16px, line-height 1.6, heading bernomor; dapat dicetak/di-download PDF.
5. Mobile: **full-screen** dengan header sticky (judul + ✕) dan footer sticky (tombol).
6. Bahasa mengikuti pilihan i18n; bila terjemahan belum tersedia → tampil `id` dengan label "Versi bahasa Indonesia".
7. Menolak persetujuan wajib → layar "Anda perlu menyetujui untuk melanjutkan" dengan opsi Keluar / Hubungi Support.
8. **Pencatatan persetujuan** (`user_consents`): user ID, dokumen, versi, waktu UTC, IP hash, perangkat, bahasa. Tidak ada persetujuan "implisit".
9. Tautan T&C di footer, form auth, checkout, dan halaman paket.

### 11.3 Persetujuan Cookie / Consent

- Banner bawah (desktop: bar lebar; mobile: **bottom sheet kecil**) pada kunjungan pertama frontpage.
- Tiga tombol sejajar setara: **Terima semua**, **Tolak non-esensial**, **Atur pilihan** (membuka modal kategori: Esensial [terkunci], Analitik, Fungsional, Marketing — masing-masing toggle).
- Tombol "Tolak" **wajib** sama menonjol dengan "Terima" (tidak boleh disembunyikan).
- Pilihan disimpan (`krafio.consent`, kedaluwarsa 12 bulan) dan dapat diubah dari footer ("Pengaturan cookie").
- Skrip non-esensial **tidak dimuat** sebelum persetujuan.
- Banner tidak menutupi tombol CS (CS bergeser naik sementara banner terlihat).

### 11.4 Persetujuan Pengiriman Data ke AI & Integrasi

- Saat pertama memakai fitur AI yang mengirim data ke penyedia pihak ketiga → modal ringkas "Data Anda diproses oleh penyedia AI" + tautan Kebijakan Privasi + "Mengerti & lanjut" (dicatat).
- Integrasi sosial: layar izin menampilkan **daftar izin yang diminta** dalam bahasa sederhana sebelum diarahkan ke OAuth resmi.

---

## 12. Loading Screen

### 12.1 Jenis Loading & Kapan Dipakai

| Jenis | Pemicu | Komponen |
|---|---|---|
| **Page transition** | Pindah halaman/route | Loading screen **dua skema** (§12.2) |
| **Initial app load** | Pertama membuka aplikasi / PWA | Splash (§12.5) |
| **Section/skeleton** | Memuat data di dalam halaman | Skeleton (§12.6) |
| **Button/inline** | Submit form, aksi cepat | Spinner di tombol (§12.7) |
| **Progress** | Upload, job panjang | Progress bar/ring (§12.8) |
| **Blocking operation** | Proses kritis (pembayaran, checkout) | Overlay terkunci (§12.3) |

### 12.2 Dua Skema Loading Screen untuk Pergantian Halaman

Pengguna/konfigurasi dapat memilih salah satu skema; keduanya **WAJIB** diimplementasikan dan diuji di light & dark.

#### Skema A — **Transparan** (overlay bening)

- Latar: `--overlay-clear` (putih 55% di light / navy 55% di dark) + `backdrop-filter: blur(2px)`.
- Konten halaman lama **tetap terlihat samar** di belakang.
- Indikator di tengah: spinner/logo mini berdenyut (tanpa teks, atau teks kecil bila >2 dtk).
- Cocok untuk: perpindahan antar halaman dalam panel yang sama (navigasi cepat).
- Tidak menutup navbar/sidebar (hanya area konten) → pengguna tetap merasa "di dalam aplikasi".

```
┌ NAVBAR ────────────────────────────┐
│ ┌SIDE┐ ┌── konten lama (samar) ──┐ │
│ │BAR │ │     ◜  spinner  ◝       │ │
│ └────┘ └─────────────────────────┘ │
└────────────────────────────────────┘
```

#### Skema B — **Dark / Solid** (latar gelap)

- Latar: `--overlay-dark` (hampir solid, opasitas 0.92–1.0) menutup **seluruh viewport termasuk navbar**.
- Indikator: logo Krafio + bar progres tipis (atau animasi logo) di tengah; teks status kecil di bawah ("Memuat…").
- Cocok untuk: perpindahan antar **panel/area** (Frontpage → User Panel → Admin), login/logout, pergantian bahasa/tema berat, pemuatan awal.
- Selalu gelap pada **kedua tema** agar konsisten (di dark mode, gunakan hitam kebiruan lebih pekat).

```
┌────────────────────────────────────┐
│█████████████████████████████████████│
│█████████     [LOGO]      ███████████│
│█████████  ▬▬▬▬▬░░░░░░░   ███████████│
│█████████   Memuat…       ███████████│
└────────────────────────────────────┘
```

#### Aturan Pemilihan Otomatis (default)

| Peristiwa | Skema |
|---|---|
| Navigasi di dalam panel yang sama | **A (Transparan)** |
| Pindah panel (Front ↔ User ↔ Admin), login, logout | **B (Dark)** |
| Ganti bahasa | A |
| Pemuatan awal aplikasi / PWA launch | B (splash) |
| Operasi blocking kritis (checkout) | B dengan pesan eksplisit |

Preferensi manual: Pengaturan → Tampilan → "Gaya loading halaman": *Otomatis (default)* · *Selalu transparan* · *Selalu gelap*. Disimpan di `krafio.loader`.

### 12.3 Aturan Teknis Loading Screen (WAJIB)

| # | Aturan |
|---|---|
| 1 | **Ambang tampil 150ms:** jika route/data siap <150ms, loading **tidak ditampilkan** (mencegah kedip). |
| 2 | **Durasi minimum 400ms** setelah tampil (mencegah berkedip cepat). |
| 3 | Fade-in 120ms, fade-out 180ms. Transisi dimatikan saat `prefers-reduced-motion` (tampil/hilang instan, spinner statis atau teks "Memuat…"). |
| 4 | Diimplementasikan via React Router `navigation.state` + `Suspense`; lazy-loaded route chunk memicu loader yang sama. |
| 5 | Loader menandai area `aria-busy="true"`; indikator memiliki `role="status"` dan teks tersembunyi "Memuat halaman…" (terlokalisasi). |
| 6 | **Timeout bertahap:** >5 dtk → teks "Masih memuat…"; >15 dtk → pesan + tombol "Coba lagi" dan "Kembali"; >30 dtk → state error dengan ID permintaan. Loader tidak boleh berputar tanpa batas. |
| 7 | Loading **tidak memblokir tombol Back** browser; membatalkan navigasi yang sedang berjalan bila pengguna menekan Back/klik tautan lain (cancel request via `AbortController`). |
| 8 | Fokus dikelola: setelah halaman baru siap, fokus dipindah ke `<h1>`/`main` dan judul dokumen diperbarui; pengumuman `aria-live` "Halaman X dimuat". |
| 9 | Tidak ada loader ganda: satu loader global per waktu; loader lokal (skeleton) dan global tidak tampil bersamaan pada area yang sama. |
| 10 | Pada koneksi lambat (`navigator.connection.saveData` / 2g) → gunakan loader paling ringan (tanpa blur, tanpa animasi logo). |
| 11 | Loader B tidak menyembunyikan tombol CS secara permanen — CS dapat disembunyikan saat loader B tampil, dan kembali setelahnya. |
| 12 | Aset loader (logo SVG, CSS) **inline** di HTML awal sehingga tampil sebelum bundle JS selesai. |

### 12.4 Pemuatan Awal Aplikasi (Splash)

- HTML awal sudah memuat splash inline (logo + bar tipis) → dihapus setelah React `mount` dan data sesi awal siap.
- PWA: `manifest` memuat `background_color` & ikon splash yang cocok dengan Skema B.
- Jika pengecekan sesi >3 dtk → tampil "Menyambungkan…".

### 12.5 Skeleton

- Dipakai untuk daftar, kartu, tabel, dan dashboard; bentuk menyerupai konten akhir (mencegah layout shift).
- Animasi shimmer 1,4 dtk; dimatikan saat reduced-motion (diganti blok statis).
- Jumlah skeleton = jumlah item perkiraan (mis. 6 kartu, 8 baris tabel).
- Skeleton >10 dtk tanpa data → ganti state error/empty (lihat §17).

### 12.6 Loading di Tombol

- Spinner 16px menggantikan ikon kiri; teks berubah ("Memproses…"); lebar tombol **tetap** (tanpa lompat); `aria-busy="true"`; tombol disabled.
- Tombol tidak boleh ditekan ulang selama loading (cegah transaksi ganda).

### 12.7 Progress (Upload & Job)

| Kasus | Tampilan |
|---|---|
| Upload file | Bar progres + persen + ukuran + tombol batal; banyak file → daftar dengan status per file. |
| Job AI | Status chip (Antri / Diproses / Selesai / Gagal), posisi antrean/estimasi bila tersedia, progres tahap; bisa ditinggalkan. |
| Proses tak tentu | Indeterminate bar + teks jelas apa yang terjadi. |
| Selesai | Toast + notifikasi + tombol "Lihat hasil". |

---

## 13. Footer — Aturan Horizontal di Semua Perangkat

### 13.1 Aturan Inti (WAJIB)

> **Footer WAJIB tersusun horizontal di mobile, tablet, dan desktop.** Tidak boleh berubah menjadi kolom panjang bertumpuk vertikal.

| Perangkat | Cara menjaga horizontal |
|---|---|
| Desktop | 4–5 kolom tautan sejajar + baris bawah (hak cipta kiri · tautan hukum tengah · bahasa/tema/sosial kanan). |
| Tablet | 3 kolom tautan sejajar + baris bawah horizontal. |
| Mobile | Kelompok tautan menjadi **baris horizontal yang dapat digeser** (chips/scroll-snap) atau **grid 2–3 kolom kompak**; baris bawah (hak cipta + tautan hukum) dalam **satu baris** dengan pemisah `·` dan boleh *wrap* ke baris berikutnya hanya bila tidak muat. **Dilarang** accordion vertikal panjang atau daftar tautan satu kolom. |

### 13.2 Struktur

```
DESKTOP
┌──────────────────────────────────────────────────────────────┐
│ [Logo]   Produk        Sumber daya     Perusahaan    Legal    │
│ Slogan   Fitur         Blog            Tentang       T&C      │
│ [sosial] Harga         Panduan         Kontak        Privasi  │
│          Template      FAQ             Karier        Refund   │
├──────────────────────────────────────────────────────────────┤
│ © 2026 Krafio · T&C · Privasi · Refund      [ID▾] [☾] [IG][X]│
└──────────────────────────────────────────────────────────────┘

MOBILE (horizontal, ringkas)
┌──────────────────────────────────────┐
│ [Logo]  Slogan singkat      [ID▾][☾] │
│ Fitur · Harga · Template · FAQ · … → │  ← baris chip digeser
│ Blog · Panduan · Kontak · Karier → │
│ [IG] [TikTok] [FB] [WA] [Threads]    │  ← ikon sejajar
│ © 2026 Krafio · T&C · Privasi · Refund│
└──────────────────────────────────────┘
```

### 13.3 Aturan Tambahan

- Footer tersedia di **Frontpage** lengkap; di **User/Admin Panel** versi **ringkas satu baris** (versi aplikasi · status layanan · Bantuan · T&C).
- Tautan legal (T&C, Privasi, Refund, Cookie) selalu ada, membuka halaman atau modal §11.
- Tombol "Pengaturan cookie" tersedia di baris bawah.
- Footer tidak boleh tertutup tombol CS atau back-to-top: beri `padding-bottom` setara tinggi FAB (+ safe-area) agar tautan paling kanan tetap bisa diklik.
- Footer tidak menampilkan "sticky" kecuali bottom navigation pada User Panel mobile (footer panel diletakkan **di atas** bottom nav).
- Kontras footer minimal 4.5:1 pada kedua tema.

---

## 14. Gambar, Slider, dan Carousel

### 14.1 Aturan Umum Gambar

| Aspek | Aturan |
|---|---|
| Format | AVIF/WebP dengan fallback JPEG/PNG; SVG untuk ikon/ilustrasi. |
| Responsif | `srcset` + `sizes` (320/640/960/1280/1920); `loading="lazy"` di bawah fold, `fetchpriority="high"` untuk gambar hero. |
| Rasio | Wajib `aspect-ratio`/atribut `width`+`height` (cegah CLS). Rasio standar: 16:9 (hero/video), 4:3 (kartu), 1:1 (template/produk), 9:16 (reels/stories). |
| Alt text | Wajib; dekoratif → `alt=""`. Alt terlokalisasi. |
| Placeholder | Blur-up/dominant color sebelum gambar termuat. |
| Gagal muat | Fallback blok netral + ikon + tombol "Muat ulang" (bukan ikon rusak). |
| Zoom/lightbox | Klik gambar galeri → lightbox (modal pratinjau media §10.1) dengan zoom pinch/double-tap, navigasi panah, swipe, thumbnail strip, tombol unduh (bila diizinkan). |
| Hak & watermark | Aset pihak ketiga hanya yang berlisensi; preview template memakai watermark halus bila berstatus premium untuk non-pelanggan. |

### 14.2 Carousel / Slider — Aturan Inti

Dipakai untuk: hero frontpage, galeri template, paket, testimoni, fitur, kartu dashboard mobile, panel visual auth, preview hasil generate.

| Aspek | Aturan |
|---|---|
| Geser | **Swipe/drag** (touch & mouse), scroll horizontal dengan **scroll-snap** (`scroll-snap-type: x mandatory`), momentum alami. |
| Navigasi | Tombol panah ‹ › (tersembunyi di mobile sentuh, tampil di hover desktop), **titik/pagination**, dan **keyboard** (← →). |
| Loop | Default **tanpa loop** untuk daftar kartu; **loop** boleh untuk hero (maks 5 slide). |
| Peek | Mobile: tampak 1,15–1,3 item agar jelas dapat digeser. Tablet 2,2 / Desktop 3–4. |
| Autoplay | Hanya untuk hero/promosi: interval 5–7 dtk; **jeda** saat hover, fokus, sentuh/drag, tab tersembunyi; **tombol Pause/Play** wajib tersedia; **nonaktif** saat `prefers-reduced-motion`. Autoplay **dilarang** pada konten informasi penting (harga, syarat). |
| Indikator | Titik aktif jelas (bukan warna saja: lebar memanjang); jumlah slide ≤ 7 titik, selebihnya penghitung "3 / 12". |
| Aksesibilitas | `role="region"` + `aria-roledescription="carousel"`, tiap slide `role="group"` `aria-label="3 dari 8"`; konten tak terlihat `aria-hidden`/`inert`; fokus tidak masuk ke slide off-screen. |
| Performa | Hanya slide aktif ±1 dimuat (lazy); gambar slide berikutnya di-*preload* saat idle; video slide hanya diputar saat terlihat (muted, `playsinline`). |
| Gestur vs scroll | Swipe horizontal tidak boleh mengunci scroll vertikal halaman (`touch-action: pan-y`). Ambang kunci arah 8px. |
| Drag desktop | Kursor `grab/grabbing`; klik setelah drag tidak memicu tautan (cegah salah klik). |
| RTL-ready | Gunakan properti logis (`inline-start/end`). |
| Tinggi stabil | Tinggi carousel ditetapkan oleh rasio; tidak lompat saat slide berganti. |

### 14.3 Varian Carousel

| Varian | Pemakaian | Catatan |
|---|---|---|
| **Hero carousel** | Frontpage | Penuh lebar, 3–5 slide, teks overlay dengan kontras ≥ 4.5:1 (scrim gradient bila perlu), CTA tetap sama di setiap slide. |
| **Card carousel** | Template, paket, fitur | Snap per kartu; kartu aktif tidak diperbesar berlebihan. |
| **Thumbnail carousel** | Detail template/galeri | Strip thumbnail di bawah gambar utama; sinkron dua arah. |
| **Testimoni** | Frontpage | Autoplay lambat (≥8 dtk) atau manual saja. |
| **Before/After slider** | Edit foto AI | Handle geser vertikal/horizontal; fokus keyboard + panah; label "Sebelum/Sesudah". |
| **Dashboard strip** | Kuota/saldo di mobile | Tanpa titik; hanya peek + scroll. |
| **Auth visual** | Panel kiri login desktop | Autoplay + pause, dekoratif (`aria-hidden` bila tidak informatif). |

### 14.4 Skema Hero Carousel

```
┌──────────────────────────────────────────────┐
│ ‹                                          › │
│        Judul slide besar                     │
│        Subjudul singkat                      │
│        [ Mulai gratis ]  [ Lihat template ]  │
│                                              │
│   ●━━  ○  ○  ○             [⏸ Jeda]          │
└──────────────────────────────────────────────┘
```

---

## 15. Tombol CS (Customer Support) Mengambang

### 15.1 Spesifikasi

| Properti | Nilai |
|---|---|
| Posisi | **Kanan bawah**, `position: fixed`; `right: 16px` (mobile) / `24px` (desktop); `bottom: 16px + env(safe-area-inset-bottom)`. |
| Ukuran | 48px (mobile) / 52px (tablet) / 56px (desktop); bentuk bulat penuh. |
| Ikon | Headset / chat bubble (bukan emoji). Badge titik/angka bila ada pesan baru/balasan tiket. |
| Warna | `--primary` + ikon `--primary-contrast`; shadow `fab`. |
| z-index | `fab` (di atas konten, **di bawah** modal & loader). |
| Tersedia di | Frontpage, User Panel. Admin Panel: **tidak** ada tombol CS pelanggan (diganti pintasan "Support Tickets" di navbar). |

### 15.2 Perilaku

1. **Klik** → membuka **panel CS**: desktop = popover 360×520 di atas tombol; mobile = bottom sheet tinggi 75dvh.
2. Isi panel (tab/segmen): **Chat/Pesan cepat** · **Buat Tiket** · **Pusat Bantuan (FAQ)** · **WhatsApp** (bila dikonfigurasi).
3. Sapaan otomatis + jam operasional + estimasi balasan; di luar jam operasional → tampil formulir tiket + email follow-up.
4. Chatbot bantuan awal (AI Assistant terbatas FAQ) → eskalasi ke tiket/manusia bila perlu.
5. Tombol WhatsApp membuka `wa.me` / WhatsApp Business resmi pada tab/app baru dengan teks awal terisi (halaman saat ini + ID user) — **tanpa** membagikan data sensitif.
6. Pengguna login: tiket otomatis terkait akun; tamu: minta nama + email (validasi + Turnstile bila perlu).
7. Label tooltip "Butuh bantuan?" muncul sekali per sesi pada kunjungan awal (hilang otomatis 6 dtk, bisa ditutup).
8. Tombol dapat **ditutup sementara** (long-press/menu ⋯ → "Sembunyikan hari ini") — pilihan tersimpan.

### 15.3 Interaksi dengan Elemen Lain (Aturan Tumpukan)

| Situasi | Aturan |
|---|---|
| Ada **Back-to-top** | Back-to-top di **atas** CS (jarak 12px), ukuran lebih kecil (40px); tidak saling menimpa. |
| Banner cookie / sticky action bar | CS naik di atas elemen tersebut (`bottom` dinamis) — tidak tertutup. |
| Bottom navigation (mobile) | CS berada di atas bottom nav (`bottom: 64px + safe-area + 16px`). |
| Modal / bottom sheet / loader B | CS disembunyikan (fade) selama aktif. |
| Keyboard virtual muncul | CS disembunyikan agar tidak menutup input. |
| Halaman checkout/pembayaran | Tetap tersedia (bantuan penting). |
| Editor penuh layar (Template Editor/Video editor) | Berpindah ke **menu bantuan** di toolbar; FAB dapat disembunyikan. |

### 15.4 Aksesibilitas CS

`<button aria-label="Hubungi dukungan pelanggan" aria-haspopup="dialog" aria-expanded>`; panel `role="dialog"` dengan focus trap; Esc menutup dan mengembalikan fokus; area sentuh ≥ 48px; kontras ikon ≥ 3:1.

---

## 16. Tombol Back-to-Top (Naik ke Atas Otomatis)

### 16.1 Aturan Muncul

| Aturan | Detail |
|---|---|
| Pemicu | Muncul **otomatis setelah pengguna menggulir ≥ 1,5 tinggi viewport** *atau* ketika sudah **mendekati dasar halaman** (≤ 400px dari bawah) — mana yang lebih dulu; halaman pendek (<2 viewport) tidak menampilkannya. |
| Arah | Opsional **smart show**: muncul saat scroll **ke atas** setelah melewati ambang (menunjukkan niat kembali), tersembunyi saat scroll ke bawah — **atau** tetap tampil setelah melewati ambang. Default: **tetap tampil setelah ambang**, menghilang saat kembali ≤ 300px dari atas. |
| Posisi | Kanan bawah, **tepat di atas tombol CS** (jarak 12px) — selaras sumbu vertikal; lihat §15.3. |
| Ukuran | 40px (mobile) / 44px (desktop), bulat, ikon panah atas; latar `--surface`, border 1px, shadow `md` (lebih ringan dari CS agar hierarki jelas). |
| Animasi | Fade + translateY 8px (160ms). Reduced-motion → fade saja. |
| Aksi | Klik → gulir mulus ke atas (`behavior: 'smooth'`, durasi maks 600ms; instan saat reduced-motion); setelah tiba, **fokus dipindah ke elemen paling atas** (`<body>`/skip-link/`h1`) agar pengguna keyboard tidak "tertinggal". |
| Container scroll | Pada layout panel yang memiliki **area konten scroll sendiri** (bukan window), tombol mengamati & menggulir **container konten** tersebut, bukan window. |
| Aksesibilitas | `aria-label="Kembali ke atas"`; dapat difokus; muncul dalam urutan tab setelah konten utama; tidak menjadi *keyboard trap*. |
| Halaman dengan infinite scroll | Tampilkan sejak awal ambang; label tambahan "Kembali ke atas" bersama penghitung item. |
| Disembunyikan | Saat modal/sheet/loader B aktif, saat keyboard virtual muncul, di editor penuh layar. |

### 16.2 Pola Penumpukan FAB (Kanan Bawah)

```
                       ┌────┐
                       │ ↑  │   ← Back-to-top (40–44px)
                       └────┘
                         12px
                       ┌─────┐
                       │ 🎧  │   ← CS (48–56px)
                       └─────┘
                       ↕ 16px dari tepi/safe area
```

Total tinggi tumpukan dihitung otomatis; saat footer terlihat, tumpukan **tidak menutupi tautan footer** (footer memiliki padding-bottom setara tumpukan FAB).

---

## 17. Toast, Banner, Empty State, Error State

### 17.1 Toast

| Aspek | Aturan |
|---|---|
| Posisi | Desktop: kanan atas (di bawah navbar). Mobile: **atas** tengah (bukan bawah, agar tidak bentrok dengan FAB & bottom nav). |
| Durasi | Sukses/info 4 dtk; peringatan 6 dtk; error **persisten** sampai ditutup. Berhenti saat hover/fokus. |
| Isi | Ikon status + 1 kalimat + (opsional) 1 aksi ("Lihat", "Urungkan"). |
| Tumpukan | Maks 3 terlihat; yang lama diringkas. |
| Aksesibilitas | `role="status"` (info/sukses) atau `role="alert"` (error); tidak mencuri fokus. |
| Swipe | Mobile: swipe untuk menutup. |

### 17.2 Banner

- **Pengumuman sistem** (maintenance, gangguan provider AI, perubahan harga): strip di bawah navbar, warna sesuai tingkat, tombol tutup (kecuali kritis), tautan "Selengkapnya".
- **Status pembayaran/langganan** (akan berakhir, kedaluwarsa): banner persisten dengan CTA.
- **Offline / koneksi buruk:** banner tipis, hilang otomatis saat pulih.
- Maksimum **1 banner** terlihat; prioritas: keamanan > sistem > langganan > promo.

### 17.3 Empty State

Struktur: ilustrasi ringan + judul + 1 kalimat + **1 aksi utama** ("Buat proyek pertama"). Berbeda untuk: belum ada data, hasil filter kosong ("Reset filter"), fitur terkunci, tidak ada izin.

### 17.4 Error State

| Jenis | Tampilan |
|---|---|
| Error halaman (500) | Pesan manusiawi + ID permintaan + "Coba lagi" + "Ke Beranda" + "Hubungi Support". |
| 404 | Ilustrasi + pencarian + tautan populer. |
| 403 | "Anda tidak memiliki akses" + penjelasan paket/izin + CTA yang relevan. |
| Jaringan | "Tidak dapat terhubung" + tombol coba lagi; data tersimpan lokal dipertahankan. |
| Validasi | Inline di bawah field, jelas dan dapat ditindaklanjuti. |
| Kegagalan job AI | Alasan aman (tanpa detail internal) + status kuota ("Kuota dikembalikan") + "Coba lagi" / "Ubah prompt". |

Pesan error **tidak pernah** menampilkan stack trace, SQL, atau secret.

---

## 18. Form, Tabel, Kartu, Komponen Data

### 18.1 Form

- Label **di atas** input (bukan hanya placeholder); placeholder hanya contoh format.
- Tanda wajib: label menyertakan "(wajib)" atau bintang + legenda; opsional ditulis "(opsional)".
- Validasi: saat **blur** dan saat submit; error di bawah field (`aria-describedby`), warna + ikon + teks.
- Form panjang → bagi menjadi langkah/section dengan stepper atau accordion; progres tersimpan sebagai draft (autosave) untuk wizard (Website Builder, Custom Package).
- Input angka/uang: `inputmode="numeric"`, pemisah ribuan otomatis, prefix "Rp"; batas min/maks ditampilkan sebagai helper (mis. "Rp50.000 – Rp1.000.000").
- Tanggal/waktu: picker native di mobile; tampilkan zona waktu.
- Upload: area drop + tombol; validasi tipe/ukuran **sebelum** unggah; pratinjau; progres; hapus/ganti.
- Tombol submit di akhir form; di mobile untuk form panjang → **sticky action bar** bawah.
- Cegah kehilangan data: peringatan keluar bila ada perubahan belum tersimpan.

### 18.2 Tabel

| Perangkat | Pola |
|---|---|
| Desktop | Tabel penuh; header sticky; baris hover; kolom dapat diurutkan; aksi baris di kanan (menu ⋯). |
| Tablet | Kolom prioritas + sisanya di baris perluasan (expand) atau scroll-x. |
| Mobile | **Mode kartu ringkas** (judul + 2–3 field kunci + status chip + menu ⋯) *atau* scroll-x dengan kolom pertama sticky. Tidak ada tabel yang menyebabkan horizontal scroll pada body. |

Aturan: pagination server-side; status memakai chip (ikon + teks); angka rata kanan; format mata uang konsisten; baris kosong → empty state.

### 18.3 Kartu

- Hirarki: gambar/ikon → judul → meta → aksi. Maks 2 aksi per kartu.
- Seluruh kartu dapat diklik bila hanya ada satu aksi utama (tautan stretched); fokus ring jelas.
- Kartu paket: satu paket "Direkomendasikan" ditonjolkan; **harga & siklus** jelas; fitur terkunci/tersedia dibedakan ikon ✓/—.
- Grid: mobile 2 kolom (kartu kecil) / carousel (kartu besar), tablet 2–3, desktop 3–4.

### 18.4 Chip & Status

| Status | Warna | Ikon |
|---|---|---|
| Aktif / Berhasil / Paid | success | ✓ |
| Menunggu / Pending / Queued | warning | ◷ |
| Diproses | info | ⟳ |
| Gagal / Ditolak / Banned | danger | ✕ |
| Nonaktif / Draft / Arsip | netral | ⏸ |

---

## 19. Skema UX Fitur Inti

### 19.1 Fitur Terkunci

- Tampil di sidebar & dashboard dengan gembok; klik → modal §10.5. Tidak pernah error 403 mentah bagi pengguna biasa.

### 19.2 Generate (Foto/Video/Edit)

```
[Form: prompt · parameter · template]            [Panel kanan/bawah]
                                                 Estimasi: 3 kredit
                                                 Sisa kuota: 47
                                                 [ Generate — 3 kredit ]
─────────────────────────────────────────────
Hasil / Riwayat (grid 2 kolom mobile)
```

- Desktop: 2 panel (input | hasil). Mobile: input di atas, hasil sebagai **tab** "Hasil" + sticky tombol Generate; hasil baru memicu toast "Selesai — Lihat".
- Setelah selesai: preview, unduh, variasi, edit lanjutan, simpan ke proyek.

### 19.3 Wallet & Withdrawal

- Dua kartu bersebelahan: **Saldo Top-up** (biru) dan **Saldo Komisi** (hijau) dengan label dan bantuan "Tidak dapat ditarik" / "Dapat ditarik (min. Rp100.000)".
- Top-up: stepper nominal + chip nominal cepat (50rb, 100rb, 250rb, 500rb, 1jt) + input bebas dengan validasi rentang.
- Withdrawal: pilih tujuan (rekening/e-wallet ter-mask) → nominal → ringkasan → konfirmasi (§10.6) → status tracker (Requested → Under review → Approved → Processing → Paid).

### 19.4 Template Library

- Filter chip horizontal (kategori), pencarian, sort; grid 2 kolom mobile / 4 desktop; kartu menampilkan preview, label Free/Premium, rasio.
- Klik → modal pratinjau (carousel bila banyak contoh) + "Pakai template".

### 19.5 Website Builder & Editor Visual

- Desktop: 3 panel (struktur kiri · kanvas tengah · properti kanan). Mobile: kanvas penuh + **bottom toolbar** + panel properti sebagai bottom sheet; pratinjau perangkat (Desktop/Tablet/Mobile) berupa segmented control.
- Autosave + riwayat versi; "Publikasikan" melewati validasi (link, gambar, metadata SEO, aksesibilitas dasar).

### 19.6 SEO Tools

- Skor ditampilkan sebagai **"Skor evaluasi Krafio"** (bukan skor mesin pencari) + keterangan versi ruleset.
- Temuan dikelompokkan per severity (tab/accordion), masing-masing dengan lokasi & rekomendasi; status Resolved/Ignored.

### 19.7 Integrasi Sosial

- Kartu per platform: status koneksi, masa token, izin; tombol Connect/Reconnect/Disconnect; fitur yang tidak didukung API **tidak** ditampilkan aktif (label "Belum tersedia").

### 19.8 Notifikasi & Preferensi

- Pusat notifikasi: daftar dikelompokkan (Hari ini/Kemarin/Lebih lama), filter (Semua/Belum dibaca), tandai dibaca, tindakan langsung.
- Preferensi per kanal; kategori **wajib** (keamanan/transaksi) ditandai dan tidak dapat dimatikan.

---

## 20. Aksesibilitas, Motion, Performa

### 20.1 Aksesibilitas (target WCAG 2.2 AA)

- Semua fungsi dapat dioperasikan **keyboard**; urutan fokus logis; ring fokus terlihat (2px + offset) di kedua tema.
- **Skip link** "Lewati ke konten" sebagai elemen fokus pertama.
- Landmark: `header`, `nav`, `main`, `aside`, `footer`; satu `h1` per halaman, hirarki heading berurutan.
- Target sentuh ≥ 44px (24px minimum mutlak untuk elemen padat desktop dengan jarak cukup).
- Status/validasi tidak hanya warna; kontras sesuai §3.2.
- Perubahan dinamis diumumkan (`aria-live`); modal & drawer memakai focus trap + pengembalian fokus.
- Dukung zoom hingga 200% & reflow 320px tanpa kehilangan fungsi.
- Teks alternatif untuk gambar bermakna; transkrip/caption untuk video instruksional.

### 20.2 Motion

| Jenis | Durasi | Easing |
|---|---|---|
| Hover/fokus | 120ms | ease-out |
| Modal/drawer | 180–240ms | cubic-bezier(.2,.8,.2,1) |
| Page/loader | 120–180ms | ease |
| Carousel | 300–400ms | ease-in-out |

**Wajib** hormati `prefers-reduced-motion: reduce`: matikan parallax, autoplay, shimmer, getar, dan skala; ganti fade singkat.

### 20.3 Performa UI

| Metrik | Target (diuji pada perangkat menengah, 4G) |
|---|---|
| LCP | ≤ 2,5 dtk (frontpage) |
| INP | ≤ 200 ms |
| CLS | ≤ 0,1 |
| JS awal | Code-splitting per route + per panel (admin tidak ikut terunduh oleh user) |
| Gambar | Lazy, responsif, format modern |
| Font | Subset + `swap`; preload font utama |
| Daftar panjang | Virtualisasi bila >100 baris |

Angka target final ditetapkan setelah pemilihan infrastruktur dan diverifikasi lewat pengujian beban/real-user monitoring.

---

## 21. Peta Z-index & Stacking

| Layer | Token | Nilai |
|---|---|---|
| Konten | `base` | 0 |
| Sticky header tabel / toolbar | `sticky` | 10 |
| Sidebar/Sub-sidebar (desktop) | `sidebar` | 30 |
| Navbar | `navbar` | 40 |
| Back-to-top & CS (FAB) | `fab` | 50 |
| Dropdown / popover / tooltip | `popover` | 60 |
| Scrim drawer mobile | `scrim` | 70 |
| Drawer mobile | `drawer` | 80 |
| Modal / bottom sheet | `modal` | 90 |
| Modal level 2 | `modal-2` | 95 |
| Toast | `toast` | 100 |
| Loading screen (A & B) | `loader` | 110 |
| Banner cookie | `consent` | 55 (di bawah popover, di atas FAB tidak menimpa) |

Aturan: tidak ada `z-index` mentah di komponen; hanya token.

---

## 22. Penyimpanan Preferensi Lokal

| Key | Isi | Sinkron ke akun |
|---|---|---|
| `krafio.theme` | `system` / `light` / `dark` | Ya (`user_preferences`) |
| `krafio.lang` | `id` / `en` | Ya |
| `krafio.sidebar` | `full` / `compact` (desktop) + grup yang dilipat | Ya (opsional) |
| `krafio.loader` | `auto` / `transparent` / `dark` | Ya |
| `krafio.consent` | Pilihan cookie + versi + waktu | Ya bila login |
| `krafio.cs.hidden` | Tanggal sembunyikan CS | Tidak |
| `krafio.tooltips` | Tooltip onboarding yang sudah dilihat | Ya |

**Dilarang** menyimpan token auth, password, saldo, data pembayaran, atau hasil privat di `localStorage`. Autentikasi memakai cookie HttpOnly (Sanctum).

---

## 23. Checklist Penerimaan UI/UX

### Navigasi & Layout
- [ ] Navbar sticky di semua panel; tinggi 56/60/64.
- [ ] Sidebar: penuh/ringkas (desktop), rail (tablet), drawer (mobile) dengan scrim, swipe, Esc, Back menutup.
- [ ] Sub-sidebar: kolom (desktop), flyout (tablet), level-2 di drawer (mobile); muncul hanya bila ≥3 anak.
- [ ] Admin Panel memiliki navbar + sidebar + sub-sidebar; badge antrean berfungsi.
- [ ] Tidak ada horizontal scroll body di 320 / 375 / 768 / 1024 / 1440px.

### Anti-slop & Mobile
- [ ] Tidak ada halaman mobile > 4 viewport tanpa pemecah (tab/accordion/carousel/grid 2 kolom).
- [ ] Chips/filter horizontal scroll; kartu kecil 2 kolom; sticky action bar pada form panjang.
- [ ] Tidak memakai emoji sebagai ikon UI; satu set ikon konsisten.

### Tema & Bahasa
- [ ] Light/Dark/System; tanpa FOUC; tersimpan lokal + akun.
- [ ] Kontras ≥ 4.5:1 di kedua tema (diuji otomatis).
- [ ] ID/EN tanpa reload, form tidak hilang; fallback ke ID; format mata uang/tanggal sesuai locale.

### Auth
- [ ] Login/Register/Lupa/OTP sesuai skema; pesan error generik dan terlokalisasi.
- [ ] Show/Hide password: tombol 44px, `aria-pressed`, kursor dipertahankan, auto-hide 30 dtk/blur, ikon reveal bawaan browser dimatikan, paste diizinkan, Caps Lock warning.
- [ ] OTP: 6 kotak, paste, timer 5 menit, cooldown 60 dtk, sisa percobaan.
- [ ] Layar khusus status akun (Pending Verification/Approval/Revision/Rejected/Suspended).

### Modal & T&C
- [ ] Modal: portal, focus trap, Esc, scrim, kembalikan fokus, Back menutup, bottom sheet di mobile, maks 2 level.
- [ ] Modal kritis tidak tertutup lewat scrim; konfirmasi destruktif dengan ketik kata kunci untuk aksi permanen.
- [ ] T&C: versi + tanggal, daftar isi, scroll-to-agree untuk versi baru, penyimpanan consent ber-versi, full-screen di mobile.
- [ ] Banner cookie: Terima/Tolak setara, atur pilihan, skrip non-esensial tertahan.

### Loading
- [ ] Skema A (transparan) dan B (dark) berfungsi; pemilihan otomatis + override pengguna.
- [ ] Ambang 150ms, minimum 400ms, timeout bertahap (5/15/30 dtk), cancel via Back.
- [ ] Skeleton tanpa layout shift; spinner tombol tanpa lompat lebar; progress upload/job.
- [ ] Reduced-motion dihormati.

### Footer, Carousel, FAB
- [ ] Footer **horizontal** di mobile/tablet/desktop (tanpa kolom vertikal panjang).
- [ ] Carousel: swipe, scroll-snap, panah, titik, keyboard, peek mobile, autoplay dengan pause & reduced-motion.
- [ ] Lightbox galeri dengan zoom & swipe.
- [ ] Tombol CS kanan bawah: panel (chat/tiket/FAQ/WA), tidak menutupi footer, naik di atas banner/bottom nav, hidden saat modal/loader.
- [ ] Tombol back-to-top: muncul otomatis setelah ambang scroll/dekat bawah, di atas CS, smooth scroll, pindah fokus ke atas, mengamati container scroll yang benar.

### Aksesibilitas & Performa
- [ ] Navigasi keyboard penuh + skip link + ring fokus.
- [ ] Landmark & heading benar; `aria-live` untuk toast/validasi.
- [ ] LCP/INP/CLS memenuhi target pada perangkat menengah.
- [ ] Bundle admin tidak terunduh oleh user biasa.

---

**Akhir dokumen — Krafio UI/UX Design Specification v1.0**
