# Aset Foto & Logo Krafio

Sumber: `assets/krafio_website_assets/` (56 foto + `00_audit_foto_dan_prompt_krafio.md`).
Dioptimalkan ke `apps/frontend/public/assets/img/` (JPEG q80–82, sisi terpanjang ≤ 1280px; total ±4 MB).
Aturan: satu foto boleh dipakai di beberapa halaman, **tidak berulang di halaman yang sama**.
Konstanta di kode: `PHOTO`, `featurePhoto(slug)`, `templatePhoto(art)` di `src/shared/lib/assets.ts`.

## Brand

| Sumber | Hasil | Dipakai di |
|---|---|---|
| `01_logo_utama_krafio.jpg` | `brand/logo-mark.png` (256), `logo-mark-96.png`, `favicon-16/32/48.png`, `favicon.ico`, `/favicon.svg`, `icon-192/512.png`, `apple-touch-icon.png`, `icon-maskable-512.png`, `logo-email.png`, `logo-wordmark-light/dark.png` | Header & footer semua halaman (`LogoMark` default), login, tab browser, PWA, email |
| `02_beranda_hero_slide_1…` | `brand/og-image.jpg` (1200×630) | Pratinjau share link (Open Graph) |

`LogoMark` dengan `bg`/`fg` kustom (kartu dompet, loader) tetap versi vektor agar mengikuti warna konteks.

## Halaman publik

| Foto | Halaman |
|---|---|
| `photos/hero-generate.jpg` (02) | Beranda hero slide 1 · intro halaman Fitur |
| `photos/hero-templates.jpg` (04) | Beranda hero slide 2 (website) · intro halaman Template |
| `photos/hero-seo.jpg` (03) | Beranda hero slide 3 (SEO) |
| `photos/login-credit.jpg` (05) | Login/daftar user, slide 1 |
| `features/<slug>.jpg` (06–16) | Kartu halaman Fitur, intro halaman detail fitur, kartu utama tiap tab di Beranda |
| `pages/pricing.jpg` (17) | Harga · Add-on |
| `pages/affiliate.jpg` (18) | Affiliate |
| `pages/faq.jpg` (19) | FAQ / Pusat bantuan |
| `photos/admin-hero-inbox.jpg` (admin 04) | Kontak |
| `photos/member-hero-photo.jpg` (member 01) | Tentang |
| `photos/wallet-cards.jpg` (member 06) | Beranda bagian "saldo terpisah" · login user slide 2 |
| `photos/admin-login-mfa.jpg` (admin 03) | Login user slide 3 · login admin slide 3 |

## Katalog template (24)

`templates/<art>.jpg` — nama = slug judul template, dipakai `TemplateCard` di Beranda, katalog publik, Template user, dan Template Studio admin.
Urutan sumber kartu: cover unggahan admin → foto desain → ilustrasi SVG lama → bentuk palet.
Rasio 4 template diselaraskan dengan desain (migration `2026_11_12_000400`): batik 16:9, skincare 9:16, testimoni 1:1, tutorial 9:16.

## Dashboard member

| Foto | Tempat |
|---|---|
| `photos/member-hero-photo/templates/website.jpg` (01–03) | Hero dashboard user (default `ShowcaseController`) |
| `samples/kopi-susu-meja-kayu.jpg` (04) + 5 foto produk template | Pratinjau simulasi Studio AI → Galeri Aset |
| `photos/studio-placeholder.jpg` (05) | Panel "Hasil" Studio AI saat kosong |
| `states/empty-user.jpg` (07) | Empty state "belum ada data" di area user & publik |

## Panel admin

| Foto | Tempat |
|---|---|
| `photos/admin-login-control/wallet/mfa.jpg` (01–03) | Login admin, slide 1–3 |
| `photos/admin-hero-inbox.jpg`, `admin-hero-templates.jpg`, `admin-login-wallet.jpg` | Hero dashboard admin (default) |
| `states/empty-admin.jpg` (06) | Empty state "belum ada data" di /admin (mis. Asset Storage) |
| Semua foto di atas | Pustaka gambar Hero Editor admin |

Slide hero yang sudah tersimpan di pengaturan dan masih memakai ilustrasi bawaan lama otomatis dipetakan ke foto baru (`ShowcaseController::LEGACY_IMAGES`).

## Regenerasi

Script PowerShell (System.Drawing, tanpa dependensi) dipakai saat implementasi; untuk foto baru cukup resize ke ≤1280px JPEG q82 dan simpan dengan nama yang sama.
