# Checklist audit per modul

Untuk setiap modul: **baca kode → uji API end-to-end (`api.py`) → uji UI (`browser.mjs`) → perbaiki → test regresi
(`tests/Feature`) → `check.sh`**. Laporkan dengan bukti (perintah + hasil), pisahkan temuan modul lain.

## Umum (semua endpoint)
- [ ] Otorisasi: data user lain → 404/403 (ganti akun: `api.py --as demo` vs `--as qa`); admin-only tidak bisa diakses user.
- [ ] Validasi: field wajib, tipe, panjang maksimal, nilai aneh (`???`, emoji, HTML, 100k karakter, angka negatif).
- [ ] Kuota/kredit: `ensure` sebelum kerja, `consume` di transaksi yang sama dengan penyimpanan; gagal → tidak terpotong.
- [ ] Idempotensi & klik ganda pada aksi uang/job (header `Idempotency-Key`).
- [ ] Rate limit ada di aksi mahal/sensitif; pesan 429 jelas.
- [ ] Respons error konsisten `{code, message, errors}`; tidak membocorkan stack/secret.
- [ ] Audit log untuk aksi admin & uang (`Services/Audit.php`).
- [ ] i18n: kunci baru ada di id & en; tidak ada kunci mentah tampil.
- [ ] UI: desktop 1280 & mobile 390 tanpa scroll horizontal; tidak ada error JS; state tidak hilang saat pindah tab.

## Uang
- [ ] Harga & PPN (`billing.ppn_percent`, `price_includes_ppn`) dihitung server, bukan dari klien.
- [ ] Kupon: batas pakai, per user, masa berlaku, paket yang berlaku, tidak bisa negatif.
- [ ] Webhook pembayaran: tanda tangan diverifikasi, idempoten, status tidak mundur.
- [ ] Saldo: ledger seimbang (`wallet_ledger`), tidak bisa minus, withdrawal minimal & FIFO komisi.
- [ ] Refund: batas hari, efek ke paket/kredit/komisi.

## Auth & keamanan
- [ ] OTP: kedaluwarsa, batas percobaan, kirim ulang.
- [ ] MFA admin wajib; reauth untuk aksi keuangan.
- [ ] SSRF di semua fetch URL (pakai `SeoAuditor::fetchHtml`/`assertPublicUrl`).
- [ ] Unggahan: mimetype, ukuran, disimpan privat, hanya pemilik.
- [ ] XSS: konten user ditampilkan sebagai teks (React escape); `dangerouslySetInnerHTML` diperiksa.

## AI & job
- [ ] Kredit dikembalikan sekali bila job gagal; job macet dipulihkan.
- [ ] Prompt library dipakai; fallback provider.
- [ ] Hasil privat per pemilik; watermark paket Gratis.
