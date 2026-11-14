# Jebakan yang sudah ditemukan (Mac lokal)

| Gejala | Penyebab | Solusi |
|---|---|---|
| `php`/`node` tidak ditemukan | shell non-interaktif tidak memuat `~/.zshrc` | `source .claude/skills/krafio-qa/scripts/env.sh` |
| HTTPS keluar dari PHP gagal: `cURL error 60` | PHP statis tanpa bundel CA | `~/.krafio-tools/php/php.ini` memuat `curl.cainfo` (cacert.pem dari keychain macOS); server & worker dijalankan `php -c` file itu — cek `env.sh status` |
| Unggah > 2 MB gagal di lokal | PHP tanpa php.ini (default 2M) | php.ini lokal di atas (210M) |
| Skrip `.sh` gagal diam-diam setelah `cd` | `${0:A:h}` dihitung setelah `cd` dari path relatif | simpan `HERE="${0:A:h}"` di baris pertama |
| Tugas berkala (perpanjangan, komisi, kedaluwarsa bayar) tidak jalan | `krafio-start` tidak menyalakan scheduler | `jobs.sh scheduler` (atau minta user menambahkan ke krafio-start) |
| Job AI tertahan `queued` | worker mati (`KRAFIO_JOBS_INLINE=false`) | `jobs.sh worker` / `jobs.sh drain` |
| Log membengkak oleh test | test menulis ke `laravel.log` | sudah: `phpunit.xml` `LOG_CHANNEL=null`; ApiException 4xx tidak dilaporkan |
| Unduh binary/instal PHP/ubah `~/.zshrc` ditolak | pengaman sistem | jangan dicoba ulang; minta user melakukannya |
| MCP chrome-devtools gagal di Mac | `.mcp.json` memakai `cmd /c` (khusus Windows) | pakai `scripts/browser.mjs`, atau user menambah server lokal (lihat SKILL.md) |
| `php artisan tinker <file>` menggantung | tinker menunggu input | `php artisan tinker --execute="require '/path/file.php';" < /dev/null` |
| `timeout` tidak ada | macOS | pakai batas waktu di skrip/Node |
| Test GET dengan parameter tidak terbaca | `json_('GET', uri, $data)` = body | baca dengan `$r->input()` atau tulis query di URI |
| Dua `Http::fake()` berurutan, stub kedua tak dipakai | stub pertama yang cocok menang | satu `Http::fake([...])` dengan pola berbeda |
| Python `str \| None` / f-string `\"` error | Python sistem 3.9 | `from __future__ import annotations`, hindari backslash di f-string |
| Login API 422 "login field is required" | field bernama `login`, bukan `email` | `{"login": ..., "password": ...}` |
| Analisis/impor 429 | throttle (mis. impor SEO 10/menit) | beri jeda; itu perilaku benar |
| Audit URL ke `http://127.0.0.1:8000` → `invalid_url` | port selain 80/443 ditolak sebelum cek IP | uji blokir dengan `http://127.0.0.1/` |
| Headless Chrome tampil bahasa Inggris | locale browser | `"lang": "id"` di skenario (localStorage `krafio.lang`) |
| Ada perubahan yang bukan buatan Anda / test lain tiba-tiba gagal | **sesi agen lain bekerja paralel** di repo | cek `git status`/`git diff` sebelum menyalahkan kode sendiri; jangan menyunting file yang sedang mereka ubah |
| Kuota akun demo habis setelah uji | uji memakai akun demo | pakai akun QA; bila terlanjur, hapus audit & reset `usage_counters` lewat tinker |
| Build produksi tidak bisa login di `vite preview` (:4173) | Sanctum stateful domain hanya :5173 | sudah ditambahkan `localhost:4173` di `.env` & `.env.example` |
| Halaman publik di preview menampilkan HTML beranda | plugin build dengan `apply: 'build'` tidak aktif saat `vite preview` | plugin `krafio-public-seo` kini juga memasang rewrite preview (`/rute` → `dist/rute/index.html`, sisanya `spa.html`) |
| Banyak 429 saat uji beruntun | rate limit API global (`KRAFIO_API_RATE_USER`=300, `KRAFIO_API_RATE_GUEST`=120 per menit) + throttle per rute | beri jeda / naikkan sementara lewat `.env`; webhook & media dikecualikan |
| `echo "$out"` merusak JSON di zsh | `echo` zsh menerjemahkan `\n`/`\\` | pakai `print -r -- "$out"` |

