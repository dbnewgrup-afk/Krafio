# Integrasi JualKirim API v1

Krafio menjual jasa berlangganan, jadi JualKirim dipakai **hanya untuk pembayaran**: pelanggan membayar paket, add-on, atau top-up lewat **Invoice JualKirim** (tagihan ke customer), lalu langganan diaktifkan. Fitur pengiriman JualKirim (ongkir, kurir, alamat, order, produk, kategori) sengaja tidak diintegrasikan.

Sumber kebenaran: [`docs/jualkirim-openapi.yaml`](jualkirim-openapi.yaml) (dari `https://app.jualkirim.com/api/v1/docs/openapi.yaml`).

## 1. Setup

Isi di `apps/backend/.env` (jangan pernah di-commit; `.env.example` hanya berisi nama variabel):

| Variabel | Keterangan |
|---|---|
| `JUALKIRIM_BASE_URL` | Default `https://app.jualkirim.com` |
| `JUALKIRIM_EMAIL` / `JUALKIRIM_PASSWORD` | Akun dashboard JualKirim (login → JWT) |
| `JUALKIRIM_API_KEY` | API key organisasi — ada di respons `POST /api/v1/auth/login` (`data.organizations[].api_key`) |
| `JUALKIRIM_TIMEOUT` | Detik, default 30 |
| `JUALKIRIM_MONEY_ENABLED` | Default `false`. Mengunci endpoint pemindah uang keluar (lihat §5) |

```bash
php artisan config:clear
php artisan jualkirim:test          # login + GET /api/v1/me + cek API key cocok dengan organisasi
php artisan jualkirim:test --fresh  # paksa login ulang
php artisan jualkirim:clear-token   # hapus JWT dari cache (mis. setelah ganti password)
```

Pastikan scheduler Laravel berjalan di server (`php artisan schedule:work` atau cron `schedule:run` tiap menit) — status lunas diambil dengan polling.

## 2. Mengaktifkan sebagai payment gateway Krafio

Admin Panel → **Integrasi → Payment** → driver **JualKirim Invoice** → aktifkan. Kredensial tetap dari `.env`; form hanya berisi opsi:

| Opsi | Nilai | Arti |
|---|---|---|
| Dana masuk ke | `wallet` (default) / `bank` | Pencairan dana invoice ke saldo wallet JualKirim atau rekening bank organisasi |
| ID rekening bank | UUID | Wajib bila `bank` |
| Biaya admin ditanggung | `merchant` (default) / `customer` | Siapa yang membayar biaya admin JualKirim |
| **Konfirmasi pembayaran** | `auto` (default) / `manual` | Lihat di bawah |

Tombol **Uji koneksi** memanggil `/me` dan mencocokkan API key, tanpa membuat transaksi.

### Alur pembayaran

1. Pelanggan checkout paket/add-on/top-up dengan sumber "payment gateway".
2. Krafio membuat payment `pending` lalu `POST /api/v1/invoices` (1 item seharga nominal final, `notes` = referensi Krafio, jatuh tempo = batas bayar Krafio). JualKirim mengirim email berisi link bayar ke pelanggan.
3. Pelanggan diarahkan ke `payment_link` (tab baru). Modal Checkout/Top-up mem-polling `GET /api/app/payments/{id}` tiap 5 detik.
4. Konfirmasi lunas — dua opsi:
   - **`auto`**: begitu `GET /api/v1/invoices/{id}` berstatus `paid` dan `grand_total` = nominal, `Billing::markPaid` dijalankan → langganan/add-on aktif atau saldo bertambah. Dicek oleh scheduler tiap menit dan saat pelanggan mem-polling (maks. sekali per 10 detik per pembayaran).
   - **`manual`**: setiap tagihan masuk **Approval → Approval pembayaran**. Polling tetap berjalan tetapi hanya memberi label "Lunas di JualKirim" di approval dan notifikasi admin. Admin mengecek dana di dashboard JualKirim lalu **Setujui** (aktif, termasuk bila tagihan Krafio sudah kedaluwarsa) atau **Tolak** dengan alasan (pembayaran gagal, kupon dilepas, pelanggan diberi notifikasi). Approval tertutup otomatis bila tagihan kedaluwarsa atau dibatalkan tanpa pembayaran.
5. Webhook: spec belum menyediakan webhook. Rute `POST /api/webhooks/payment/jualkirim` sudah ada (verifikasi HMAC `X-Signature` dengan secret setting `payment.webhook_secret.jualkirim`, field `reference`/`status`/`amount`) dan **menghormati mode auto/manual**. Bila JualKirim kelak merilis webhook dengan format berbeda, cukup sesuaikan verifikasinya di `BillingController::webhook`.

Pengaman:
- Nominal invoice berbeda dari nominal Krafio → tidak pernah diaktifkan otomatis; dicatat di audit log `payment.mismatch` dan approval diberi label "Nominal tidak cocok".
- Pelanggan membayar setelah tagihan Krafio kedaluwarsa (mode auto) → tidak diaktifkan otomatis; admin diberi notifikasi `payment.late_paid` untuk tindak lanjut manual.
- Invoice dibatalkan di JualKirim → payment `failed`, reservasi kupon dilepas.
- Pelanggan tanpa nomor HP diminta melengkapi profil (field `customer_phone` wajib di spec). Nomor `+62…` dikirim sebagai `0…`.

## 3. Klien API (untuk developer)

```php
use App\Facades\JualKirim;

$saldo   = JualKirim::wallet()->balance();
$invoice = JualKirim::invoices()->find($id);
$me      = JualKirim::me();   // DTO MeData
```

| Resource | Method | Endpoint |
|---|---|---|
| `auth()` | `login()`, `me()` | `POST /auth/login`, `GET /me` |
| `wallet()` | `balance` | `GET /wallet/balance` |
| `invoices()` | `list`, `create`, `find`, `update`, `delete` | `/invoices`, `/invoices/{id}` |
| `payments()` | `list`, `create`, `find`, `update`, `delete`, `checkout` ⚠, `resumeCheckout`, `status`, `methods` | `/payments…`, `/payment-methods` — pembayaran **ke vendor** (uang keluar) |
| `disbursements()` | `validate`, `validateCsv`, `list`, `create` ⚠, `find`, `cancel` ⚠ | `/disbursement-batches…` — transfer saldo ke rekening |

Payment (vendor) dan Disbursement belum dipakai alur Krafio; disediakan untuk kebutuhan admin keuangan nanti (mis. pencairan komisi afiliasi).

## 4. Perilaku klien

- **Token**: JWT di cache `jualkirim.jwt`, TTL `expires_at − 5 menit` (fallback 23 jam). Login dilindungi `Cache::lock('jualkirim.login', 10)`. Di production gunakan cache store yang mendukung lock (redis/database/memcached).
- **Header**: `Authorization: Bearer <jwt>` + `X-API-KEY` di semua endpoint.
- **401** → hapus token, login ulang, ulangi **sekali**; masih 401 → `JualKirimAuthException`.
- **Retry**: hanya GET/PUT/DELETE, maks. 2× untuk gagal koneksi/5xx. POST tidak pernah diulang otomatis (spec tidak punya idempotency key) — membuat invoice dua kali berarti pelanggan menerima dua email tagihan.

| Exception | Kapan |
|---|---|
| `JualKirimAuthException` | 401 / kredensial kosong (pesan menyebut variabel `.env`) |
| `JualKirimForbiddenException` | 403 — role user tidak punya permission |
| `JualKirimValidationException` | 400 (validasi field, `errors()`) & 422 (aturan bisnis, `responseData`) |
| `JualKirimMoneyLockedException` | Endpoint uang keluar saat `JUALKIRIM_MONEY_ENABLED=false` |
| `JualKirimException` | Basis semua di atas; 5xx, koneksi, respons non-JSON |

## 5. Endpoint uang keluar (⚠)

`payments()->checkout()`, `disbursements()->create()`, `disbursements()->cancel()` memindahkan saldo organisasi. Terkunci sampai `JUALKIRIM_MONEY_ENABLED=true`. Tidak ada idempotency key → setelah timeout/5xx cek dulu lewat `status()`/`list()` sebelum mengirim ulang. Membuat **invoice** (uang masuk) tidak terkena kunci ini.

## 6. Logging

Kegagalan dicatat ke channel `jualkirim` (`storage/logs/jualkirim-YYYY-MM-DD.log`, 30 hari): method, path, status, pesan, referensi pembayaran Krafio. Header, body request, JWT, API key, dan password tidak pernah dicatat (dimask `***` bila muncul di pesan respons).

## 7. Testing

```bash
php artisan test tests/Feature/JualKirim
```

Semua memakai `Http::fake()`. `JualKirimClientTest`: token/cache/lock, 401 → relogin, 403, 400/422, retry GET vs POST, header, masking log, kunci uang, tiap resource, command Artisan. `JualKirimPaymentTest`: pembuatan invoice, mode auto (top-up & langganan), mode manual (approve/reject/kedaluwarsa), nominal tidak cocok, invoice batal, pelanggan tanpa HP, otorisasi status, uji koneksi.

## 8. Catatan yang belum terverifikasi ke API asli

- Bentuk `GET /api/v1/invoices/{id}` → `data.invoice` tidak bertipe di spec. Klien membaca `data.invoice.status` (`draft|sent|paid|cancelled`) dan `data.invoice.grand_total`. **Verifikasi dengan satu invoice nyata** sebelum memakai mode `auto` di production; sampai saat itu mode `manual` lebih aman.
- Apakah invoice JualKirim masih bisa dibayar setelah `due_date` belum diketahui (ditangani lewat notifikasi late-paid / approval manual).
- Belum ada webhook di spec (lihat §2 butir 5).
- Temuan saat uji API asli (11 Nov 2026, sebelum fitur pengiriman dihapus): `postal-codes` mengembalikan `id` integer (spec: UUID) dan `check-cost` membaca `weight` sebagai kg (spec: gram). Tidak relevan selama fitur pengiriman tidak dipakai.
