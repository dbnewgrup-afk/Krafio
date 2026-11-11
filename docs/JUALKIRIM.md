# Integrasi JualKirim API v1

Klien Laravel untuk [JualKirim API v1](https://app.jualkirim.com/api/v1/docs) (pengiriman, invoice, pembayaran, disbursement).
Sumber kebenaran: [`docs/jualkirim-openapi.yaml`](jualkirim-openapi.yaml) (diunduh dari `https://app.jualkirim.com/api/v1/docs/openapi.yaml`).

## 1. Setup

Isi di `apps/backend/.env` (jangan pernah di-commit; `.env.example` hanya berisi nama variabel):

| Variabel | Keterangan |
|---|---|
| `JUALKIRIM_BASE_URL` | Default `https://app.jualkirim.com` |
| `JUALKIRIM_EMAIL` / `JUALKIRIM_PASSWORD` | Akun dashboard JualKirim (dipakai untuk login → JWT) |
| `JUALKIRIM_API_KEY` | API key organisasi — lihat di dashboard JualKirim, atau jalankan `php artisan jualkirim:test` untuk melihat daftar organisasi (key ditampilkan termask) |
| `JUALKIRIM_TIMEOUT` | Detik, default 30 |
| `JUALKIRIM_MONEY_ENABLED` | Default `false`. Lihat §4 |

Lalu:

```bash
php artisan config:clear
php artisan jualkirim:test          # login + GET /api/v1/me + cek API key cocok dengan organisasi
php artisan jualkirim:test --fresh  # paksa login ulang
php artisan jualkirim:clear-token   # hapus JWT dari cache
```

`jualkirim:test` hanya memanggil endpoint baca.

## 2. Cara pakai

```php
use App\Facades\JualKirim;

$saldo   = JualKirim::wallet()->balance();                    // ['message' => ..., 'data' => ['balance' => ...]]
$kurir   = JualKirim::couriers()->list();
$kodepos = JualKirim::shipping()->postalCodes('Tebet');       // publik, tanpa auth → ['results' => [...]]
$ongkir  = JualKirim::shipping()->checkCost([
    'weight' => 1000, 'postal_code_id' => $tujuanId, 'send_postal_id' => $asalId,
]);
$orders  = JualKirim::orders()->list(['status' => 'PENDING', 'per_page' => 50]);
$me      = JualKirim::me();                                   // DTO MeData
```

Atau injeksi `App\Services\JualKirim\JualKirimClient` (singleton) lewat constructor.

Semua method mengembalikan body JSON sebagai array (`message` + `data`, kecuali `postalCodes` yang memakai `results`). `login()` dan `me()` mengembalikan DTO (`LoginResult`, `MeData`).

## 3. Endpoint yang dicakup (33 operasi)

| Resource | Method | Endpoint |
|---|---|---|
| `auth()` | `login()`, `me()` | `POST /auth/login`, `GET /me` |
| `addresses()` | `list`, `create`, `update`, `delete` | `/addresses`, `/addresses/{id}` |
| `wallet()` | `balance` | `GET /wallet/balance` |
| `couriers()` | `list` | `GET /couriers` |
| `shipping()` | `postalCodes`, `checkCost` | `GET /shipping/postal-codes`, `POST /shipping/check-cost` |
| `orders()` | `create` ⚠, `list`, `find`, `cancel` | `/orders`, `/orders/{id}`, `/orders/{id}/cancel` |
| `products()` | `list`, `create` | `/products` |
| `categories()` | `list`, `subcategories` | `/categories`, `/subcategories` |
| `invoices()` | `list`, `create`, `find`, `update`, `delete` | `/invoices`, `/invoices/{id}` |
| `payments()` | `list`, `create`, `find`, `update`, `delete`, `checkout` ⚠, `resumeCheckout`, `status`, `methods` | `/payments…`, `/payment-methods` |
| `disbursements()` | `validate`, `validateCsv`, `list`, `create` ⚠, `find`, `cancel` ⚠ | `/disbursement-batches…` |

Semua path diawali `/api/v1`. ID di path di-`rawurlencode`.

## 4. Endpoint uang (⚠)

| Method | Efek |
|---|---|
| `orders()->create()` dengan order `payment_method` ≠ `COD` | Ongkir NON_COD memotong saldo wallet |
| `payments()->checkout()` | Membayar (wallet langsung terpotong, atau membuat QRIS/VA) |
| `disbursements()->create()` | Transfer ke rekening bank (1–100 item) |
| `disbursements()->cancel()` | Membatalkan batch terjadwal |

Aturannya:

- **Terkunci secara default.** Selama `JUALKIRIM_MONEY_ENABLED=false`, method di atas melempar `JualKirimMoneyLockedException` tanpa mengirim request. Biarkan `false` di development, testing, dan staging.
- **Tidak ada idempotency key di spec.** Karena itu request POST tidak pernah diulang otomatis. Jika terjadi timeout atau 5xx pada endpoint uang, **cek dulu** lewat `orders()->list()`, `payments()->status()` atau `disbursements()->list()` sebelum mengirim ulang.
- Satu-satunya pengulangan adalah setelah 401 (token kedaluwarsa). Ini aman karena 401 berarti request ditolak sebelum diproses.

## 5. Perilaku klien

- **Token**: JWT disimpan di cache `jualkirim.jwt` dengan TTL `expires_at − 5 menit` (fallback 23 jam). Login dilindungi `Cache::lock('jualkirim.login', 10)` agar proses paralel tidak login bersamaan. Di production, gunakan cache store yang mendukung lock (redis, database, memcached).
- **Header**: `Authorization: Bearer <jwt>` + `X-API-KEY` di semua endpoint ber-auth. `postalCodes` tanpa auth. `checkCost` mengirim auth bila kredensial ada (diskon organisasi), atau `checkCost($data, false)` untuk tarif publik.
- **401** → hapus token, login ulang, ulangi **sekali**. Jika masih 401, `JualKirimAuthException`.
- **Retry**: hanya GET/PUT/DELETE, maksimal 2× untuk gagal koneksi/5xx.

### Exception

| Kelas | Kapan | Isi berguna |
|---|---|---|
| `JualKirimAuthException` | 401 / kredensial kosong | Pesan menyebut variabel `.env` yang perlu dicek |
| `JualKirimForbiddenException` | 403 | User tidak punya permission (mis. `payment-read`, `multi-disbursement-create`) |
| `JualKirimValidationException` | 400 (validasi field) & 422 (aturan bisnis) | `errors()` = map field → pesan (400); `responseData` = detail mentah (422) |
| `JualKirimMoneyLockedException` | Endpoint uang saat terkunci | — |
| `JualKirimException` | Basis semua di atas; 5xx, koneksi, respon non-JSON | `status`, `endpoint`, `responseData` |

```php
try {
    JualKirim::addresses()->create($data);
} catch (JualKirimValidationException $e) {
    return back()->withErrors($e->errors() ?: ['jualkirim' => $e->getMessage()]);
}
```

## 6. Logging

Request gagal dicatat ke channel `jualkirim` (`storage/logs/jualkirim-YYYY-MM-DD.log`, simpan 30 hari): method, path, status, pesan. Header dan body request **tidak pernah** dicatat. JWT (`eyJ…`), API key, dan password yang muncul di pesan respon diganti `***`.

## 7. Testing

```bash
php artisan test tests/Feature/JualKirim
```

Semua test memakai `Http::fake()`, tanpa request ke API asli. Mencakup login + cache token, reuse token, TTL, 401 → relogin, 401 berulang, kredensial salah, 403, 400/422, 5xx, retry GET vs tanpa retry POST, header, endpoint publik, masking log, kunci endpoint uang, satu test per resource, dan kedua command Artisan.

## 8. Catatan spec yang belum pasti

- **400 vs 422**: spec memakai 400 untuk validasi field dan 422 untuk kegagalan bisnis. Keduanya dipetakan ke `JualKirimValidationException`.
- **Tidak ada webhook** di spec. Status order/pembayaran harus di-poll (`orders()->find()`, `payments()->status()`).
- **`postal_code_id`** (diuji ke API asli, 11 Nov 2026): `postalCodes()` mengembalikan `id` **integer** (mis. `171104`), bukan UUID seperti di spec. `check-cost` menolak id integer dengan 400 "Invalid request format" dan menerima versi string. `checkCost()` otomatis mengubah `postal_code_id`, `send_postal_id`, dan `pickup_address_id` menjadi string. Skema Address (integer) dan order (string) belum diuji dengan request asli, jadi nilainya masih diteruskan apa adanya.
- **Satuan `weight` di `check-cost`** (diuji ke API asli): spec menyebut gram, tetapi API membacanya sebagai **kilogram**. `weight: 1` Jakarta–Bandung JNE REG = Rp12.000, sedangkan `weight: 1000` = Rp12.000.000. Klien tidak mengonversi apa pun. Kirim berat dalam kg sampai JualKirim mengonfirmasi. Satuan `total_weight` dan `weight` item di create order belum diuji.
- Skema Product dan Category tidak bertipe di spec, jadi payload diteruskan apa adanya.
- `GET /me` hanya butuh Bearer. Klien tetap mengirim `X-API-KEY` bila terisi (tidak berpengaruh).
