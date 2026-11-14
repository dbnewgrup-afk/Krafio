---
name: krafio-qa
description: Uji, validasi, dan audit aplikasi Krafio di Mac lokal — cek lingkungan (MySQL, API :8000, Vite :5173, HTTPS PHP), jalankan test backend + tsc/lint/build frontend dengan satu perintah, panggil API sebagai user yang sudah login (alur Sanctum SPA), dan uji UI sungguhan di Chrome headless (login, isi form, klik, screenshot, tangkap error JS & respons API gagal). Pakai setiap kali diminta menguji, memvalidasi, mengaudit, mereproduksi bug, atau memastikan perubahan Krafio benar-benar jalan di browser — sebelum melaporkan "selesai".
---

# Krafio QA

Alat uji end-to-end untuk repo Krafio (Laravel API + React SPA) di Mac dengan toolchain `~/.krafio-tools`.
Semua skrip ada di `.claude/skills/krafio-qa/scripts/` (sebut `$Q` di bawah). Jalankan dari root repo.

## 0. Mulai sesi uji
```bash
Q=.claude/skills/krafio-qa/scripts
$Q/env.sh status          # PHP/Node, MySQL, API, Vite, HTTPS PHP, PHPRC server, akun QA
$Q/qa-account.sh          # sekali saja / bila login QA gagal: akun qa@krafio.test (kredensial di ~/.krafio-tools)
$Q/admin-account.sh --mfa-off   # akun admin QA lokal (qa-admin@krafio.test) + MFA admin mati sampai live
$Q/jobs.sh status         # queue & scheduler; jobs.sh worker / scheduler / drain / tick / run <nama>
```
Bila ada ❌: minta user menjalankan `krafio-start`. Jangan mengunduh/memasang PHP/Node, jangan mengubah `~/.zshrc`
(ditolak pengaman sistem) — minta user. Untuk perintah shell lain: `source $Q/env.sh` dulu (PATH + PHPRC).

## 1. Pemeriksaan otomatis
```bash
$Q/check.sh                       # semua: test backend + tsc + oxlint + vite build
$Q/check.sh backend SeoContent    # filter test
$Q/check.sh frontend
$Q/check.sh security              # composer audit + npm audit
```
Test backend memakai DB `krafio_test` (di-refresh). Bila test gagal di modul yang tidak Anda sentuh, cek
`git status`/`git diff` dulu — sesi agen lain bisa sedang bekerja paralel (lihat `references/pitfalls.md`).

## 2. Uji API sebagai user nyata
```bash
python3 $Q/api.py GET /api/me                                  # default akun QA
python3 $Q/api.py --as demo GET /api/app/seo/config
python3 $Q/api.py POST /api/app/seo/analyze @/tmp/form.json --max 3000
python3 $Q/api.py GET /api/app/seo/audits -q kind=content
python3 $Q/api.py --guest GET /api/app/seo/config              # harus 401
python3 $Q/api.py --as admin GET /api/admin/r/seo-rules        # Admin API (reauth aksi keuangan otomatis)
```

### Email/OTP, pembayaran, data uji
```bash
python3 $Q/inbox.py --to user@x.test            # email terkirim (mailer log): subjek, isi, kode, tautan
OTP=$(python3 $Q/inbox.py --otp --to user@x.test)   # ambil OTP untuk alur daftar/verifikasi
python3 $Q/inbox.py --logs                      # mail_logs & wa_logs (sent/failed/skipped)
$Q/pay.sh                                       # webhook "paid" bertanda tangan untuk tagihan pending terbaru akun QA
$Q/pay.sh --tamper ; $Q/pay.sh --replay         # tanda tangan palsu harus 401; kirim ganda tidak boleh dobel
$Q/cleanup.sh                                   # hapus user uji e2e-*@krafio.test
$Q/golive-check.sh                              # cek kesiapan rilis (MFA, debug, mailer, gateway, akun QA)
python3 $Q/central-check.py                     # 19 cek keterhubungan: Admin→Publik, Admin→User, User→Admin (data dikembalikan)
```
Pola user uji baru: `e2e-<waktu>@krafio.test` (daftar via `api.py --guest POST /api/auth/register`, lalu OTP dari
`inbox.py`, lalu `api.py --guest POST /api/auth/otp/verify`) → `cleanup.sh` setelah selesai.
Baris pertama `HTTP <status>`; exit 0 bila 2xx. Uji otorisasi dengan membandingkan `--as qa` vs `--as demo`.

## 3. Uji UI di browser (Chrome headless)
```bash
node $Q/browser.mjs .claude/skills/krafio-qa/examples/seo-flow.json
node $Q/browser.mjs skenario.json /tmp/hasil-uji        # folder output opsional
```
Skenario JSON: `as` (qa|admin|demo|guest), `lang` (id|en), `viewport` (desktop|mobile), `steps`:
`goto`, `waitFor` (teks), `fill`/`select` (berdasarkan `label` atau `selector`, nilai dari `value` atau `file`),
`click` (teks tombol/link/tab atau `{selector}`), `upload` (`{selector|label, file}`), `expectText`, `expectNoText`, `eval` (+`expect`), `wait`,
`shot` (screenshot). Detail lengkap di kepala `scripts/browser.mjs`; contoh di `examples/`:
`seo-flow`, `upload-flow`, `admin-tour`, `mobile-smoke`, `forum-subscriber`, `forum-free-locked`, `forum-admin`, `forum-mobile`.
Audit seluruh halaman: buat skenario per rute (goto + eval cek i18n mentah/layar error/scroll-x/h1/gambar rusak,
`"stopOnFail": false`). Mode dev (Vite HMR) bisa memunculkan error React palsu saat file sedang diubah sesi lain →
konfirmasi di build produksi: `npx vite build && npx vite preview --port 4173` lalu `KRAFIO_WEB=http://localhost:4173`.
Regresi UI cepat: `for s in .claude/skills/krafio-qa/examples/*.json; do node $Q/browser.mjs $s; done`

Laporan otomatis memuat: langkah gagal (+ screenshot `NN-GAGAL.png`), error JS (exception & `console.error`),
respons `/api` 5xx atau 4xx tak terduga. **Selalu buka screenshot dengan Read** untuk menilai tampilan, lalu cek
juga `viewport: mobile` (tidak boleh ada scroll horizontal).

## 4. Alur kerja audit/validasi yang disarankan
1. `env.sh status` → `check.sh` (baseline; catat kegagalan yang sudah ada sebelumnya).
2. Baca kode modul + `references/app-map.md`; susun daftar periksa dari `references/audit-checklist.md`.
3. Probe kasus tepi langsung ke service lewat tinker
   (`php artisan tinker --execute="require '/tmp/x.php';" < /dev/null`).
4. Uji API end-to-end (`api.py`) termasuk otorisasi, validasi, kuota, rate limit.
5. Uji UI (`browser.mjs`) desktop + mobile; lihat screenshot.
6. Perbaiki, tambah test regresi di `apps/backend/tests/Feature`, ulangi `check.sh`.
7. **Bersihkan data uji** (`cleanup.sh`) di DB dev (terutama bila memakai akun demo) dan laporkan apa yang dibersihkan.
8. Laporkan dengan bukti; pisahkan temuan di luar cakupan dan perubahan sesi lain.

## Opsional: MCP chrome-devtools di Mac
`.mcp.json` repo memakai `cmd /c` (Windows). User bisa menambah server lokal (tidak di-commit):
```bash
claude mcp add -s local -e PATH="$HOME/.krafio-tools/node/bin:/usr/bin:/bin" chrome-devtools-mac -- \
  "$HOME/.krafio-tools/node/bin/npx" -y chrome-devtools-mcp@1.12.0 --isolated
```
Tanpa itu, `browser.mjs` sudah cukup untuk login, isi form, klik, screenshot, dan menangkap error.

## Referensi
- `references/app-map.md` — arsitektur, prefix API, modul & risiko, entitlement, akun, cara test backend.
- `references/pitfalls.md` — jebakan lingkungan yang sudah ditemukan beserta solusinya.
- `references/audit-checklist.md` — checklist audit umum, uang, auth/keamanan, AI.
