# Generator aset visual

Semua gambar di `public/assets/img/**` dibuat secara prosedural (SVG vektor datar mengikuti token design system), jadi bisa diubah dan dibuat ulang tanpa alat desain.

```sh
# 1. Tulis ulang semua SVG (logo, favicon, sampel produk, fitur, template, auth, state, halaman)
node scripts/assets/gen-assets.mjs public/assets/img

# 2. Render PNG/ICO (favicon 16/32/48, apple-touch 180, ikon PWA 192/512 + maskable, wordmark, logo email, OG image 1200x630)
#    Butuh playwright-core + Microsoft Edge/Chrome terpasang: npm i -D playwright-core
node scripts/assets/render-brand.mjs public/assets/img
cp public/assets/img/brand/favicon.svg public/favicon.svg
cp public/assets/img/brand/favicon.ico public/favicon.ico
```

| Folder | Isi | Dipakai di |
|---|---|---|
| `brand/` | logo-mark (light/dark/white/mono), favicon (svg/ico/png), apple-touch-icon, icon-192/512, icon-maskable, splash, wordmark, logo-email, og-image | `index.html`, `manifest.webmanifest`, `LogoMark`, loader, OG |
| `samples/` | 6 ilustrasi produk UMKM (1:1) + 4 mock website (16:9) | hero beranda, pratinjau hasil job simulasi, template |
| `features/` | 11 ilustrasi fitur (4:3), satu per slug di `shared/features.ts` | halaman Fitur, detail fitur, kartu unggulan beranda |
| `templates/` | 24 pratinjau template, nama = slug `title_id` (`Present::template()['art']`) | `TemplateCard` (fallback ke bentuk palet bila berkas tidak ada) |
| `auth/` | 3 ilustrasi slide panel login/daftar (tone gelap) | `AuthLayout` |
| `states/` | empty, filter, locked, error, clock, notfound, offline, maintenance | `Illustration` / `EmptyState` / `ErrorState` / 404 |
| `pages/` | about, affiliate, contact, faq, pricing, templates | `PageIntro art=` |

Menambah template baru: tambahkan entri di array `T` (slug, tipe, rasio, sampel, label) lalu jalankan langkah 1.
