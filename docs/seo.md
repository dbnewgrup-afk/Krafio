# Pedoman SEO Krafio Berbasis Yoast SEO

> **Status:** Draf v1.0 untuk ditinjau
> **Tanggal:** 14 November 2026
> **Acuan utama:** source code resmi mesin analisis Yoast SEO (repo `Yoast/wordpress-seo`, paket `packages/yoastseo`, commit `dde860a` tanggal 11 November 2026) beserta dokumen skoring resmi di dalam repo tersebut.
> **Isi:** semua cek, ambang batas, skor, dan pesan yang dipakai Yoast SEO untuk analisis SEO dan keterbacaan, ditambah pengaturan teknis yang dikelola Yoast. Bab 10 disediakan untuk pedoman tambahan dari tim Krafio.

## Daftar Isi

1. Cara membaca dokumen ini
2. Cara Yoast menghitung skor
3. Focus keyphrase dan aturan pencocokan
4. Analisis SEO (24 cek)
5. Analisis keterbacaan (readability)
6. Perbedaan menurut jenis konten
7. Pengaturan teknis yang dikelola Yoast
8. Checklist cepat sebelum publikasi
9. Usulan pemetaan ke Rules Engine Krafio
10. Pedoman tambahan (diisi tim Krafio)
11. Sumber dan catatan versi

---

## 1. Cara Membaca Dokumen Ini

- **Lampu**: setiap cek Yoast menghasilkan lampu hijau (baik), oranye (cukup), merah (buruk), atau abu-abu (informasi saja).
- **Skor**: angka internal Yoast per cek, maksimal 9. Skor ini dipakai untuk menghitung skor total (Bab 2).
- **Nama Yoast**: nama cek persis seperti di plugin (bahasa Inggris), agar mudah dicocokkan dengan tampilan Yoast.
- **Pesan Yoast**: teks umpan balik asli Yoast. Huruf X adalah angka yang diisi otomatis.
- **Kode rule**: usulan kode untuk tabel `seo_rules` Krafio (Bab 9).
- **[Premium]**: hanya tersedia di Yoast SEO Premium.
- **Kata**: dihitung per kata. Untuk Bahasa Indonesia, tanda hubung **tidak** memisahkan kata, jadi "anak-anak" dihitung 1 kata.
- **Cornerstone**: artikel pilar atau terpenting di situs. Yoast memakai ambang yang lebih ketat untuk konten ini (Bab 6.1).

---

## 2. Cara Yoast Menghitung Skor

### 2.1 Dari skor ke lampu (per cek)

| Skor cek | Lampu | Arti |
|---|---|---|
| Di bawah 0 | Merah | Sangat buruk (penalti berat) |
| 0 | Abu-abu | Informasi, tidak dinilai |
| 1 sampai 4 | Merah | Buruk |
| 5 sampai 7 | Oranye | Cukup |
| 8 sampai 9 | Hijau | Baik |

### 2.2 Skor SEO total

Rumus: **jumlah skor semua cek yang tampil ÷ (jumlah cek × 9) × 100**, lalu dibulatkan.

Contoh: tiga cek dengan skor 3, 6, dan 9 menghasilkan (3 + 6 + 9) ÷ (3 × 9) × 100 = 66,67, dibulatkan menjadi **67**.

- Cek yang tidak tampil (misalnya cek alt text ketika tidak ada masalah) tidak ikut dihitung.
- Skor negatif langsung menjatuhkan skor total. Contoh: tanpa focus keyphrase skornya −999, keyphrase stuffing skornya −50.
- Indikator total di plugin: **merah** (*Needs improvement*) bila skor ≤ 40, **oranye** (*OK*) bila 41–70, **hijau** (*Good*) bila ≥ 71.

### 2.3 Skor keterbacaan total

Skor keterbacaan tidak dirata-rata, tetapi memakai **poin penalti**. Bahasa Indonesia termasuk bahasa dengan **dukungan parsial**. Dukungan penuh hanya untuk Inggris, Belanda, Jerman, Italia, Rusia, Prancis, dan Spanyol. Akibatnya, penalti untuk Bahasa Indonesia lebih berat.

| Lampu per cek | Penalti Bahasa Indonesia | Penalti bahasa dukungan penuh |
|---|---|---|
| Merah | 4 | 3 |
| Oranye | 2 | 2 |
| Hijau | 0 | 0 |

| Total penalti (Bahasa Indonesia) | Total penalti (dukungan penuh) | Skor | Lampu |
|---|---|---|---|
| Lebih dari 4 | Lebih dari 6 | 30 | Merah (*Needs improvement*) |
| Lebih dari 2 sampai 4 | Lebih dari 4 sampai 6 | 60 | Oranye (*OK*) |
| 2 atau kurang | 4 atau kurang | 90 | Hijau (*Good*) |

Dampaknya untuk konten Bahasa Indonesia:

- **Satu lampu merah saja** sudah membuat keterbacaan menjadi oranye.
- Satu merah ditambah satu oranye, atau tiga oranye, membuat keterbacaan menjadi merah.
- Hanya satu oranye yang masih boleh agar keterbacaan tetap hijau.
- Skor hanya dihitung bila ada minimal 2 hasil cek yang valid.

### 2.4 Set cek menurut jenis konten

| Jenis konten | Cek SEO | Cek keterbacaan |
|---|---|---|
| Post atau page biasa | Set standar (Bab 4) | Set standar (Bab 5) |
| Cornerstone | Set standar dengan ambang lebih ketat (Bab 6.1) | Set standar dengan ambang lebih ketat (Bab 6.1) |
| Taksonomi (kategori, tag) | Set terbatas (Bab 6.2) | Set standar |
| Halaman produk (WooCommerce, Shopify) | Set produk (Bab 6.3) | Set produk (Bab 6.3) |
| Related keyphrase [Premium] | Set terbatas (Bab 6.4) | Tidak ada |

---

## 3. Focus Keyphrase dan Aturan Pencocokan

### 3.1 Aturan dasar

- Setiap halaman punya **satu focus keyphrase**, yaitu frasa utama yang ingin dijadikan peringkat. Tanpa focus keyphrase, sebagian besar cek SEO tidak bisa dinilai.
- **Pencocokan per kata** (*per-word matching*): keyphrase dianggap ditemukan bila **semua kata konten** keyphrase muncul **dalam satu kalimat**. Urutannya bebas dan kata-katanya tidak harus berdampingan.
- **Function word dibuang** (Bahasa Indonesia didukung): kata seperti *yang, di, dan, untuk, dengan, ini, itu, adalah* tidak ikut dicari.
- **Exact match**: bungkus keyphrase dengan tanda kutip ganda, misalnya `"jasa pembuatan website"`. Yoast lalu hanya menerima frasa yang persis sama dan berurutan. Function word tidak dibuang dan variasi bentuk kata tidak dikenali.
- **Sinonim** [Premium]: dapat ditambahkan dan dipakai oleh sebagian cek (ditandai "Sinonim: ya" di Bab 4).
- **Bentuk kata** [Premium]: Yoast Premium mengenali imbuhan Bahasa Indonesia (stemmer tersedia), misalnya *desain*, *mendesain*, dan *didesain*. Versi Free hanya mengenali bentuk yang sama persis.

| | Semua bahasa | Bahasa dengan dukungan function word (termasuk Indonesia) | Bahasa dengan dukungan bentuk kata (termasuk Indonesia) |
|---|---|---|---|
| Free | Pencocokan per kata | Per kata + function word dibuang | Per kata + function word dibuang |
| Premium | Di atas + sinonim | Di atas + sinonim | Di atas + sinonim + semua bentuk kata |

### 3.2 Function word Bahasa Indonesia yang perlu diwaspadai

Daftar function word Yoast untuk Bahasa Indonesia cukup luas. Beberapa kata yang sering dipakai dalam keyphrase justru **dibuang** saat pencocokan:

| Kelompok | Contoh kata yang dibuang Yoast |
|---|---|
| Kata benda samar | cara, barang, masalah, bagian, aspek, ide, item, tema, hal, faktor, perbedaan |
| Kata kerja umum | ada, punya, membuat, dibuat, menunjukkan, mencoba, memastikan, termasuk, ingin |
| Kata sifat dan keterangan umum | baik, terbaik, bagus, mudah, cepat, besar, kecil, baru, terbaru, penting, utama, paling |
| Kata depan dan kata sambung | di, ke, dari, untuk, dengan, bagi, dan, atau, serta, yang |
| Kata ganti dan penunjuk | saya, kamu, anda, kita, ini, itu, tersebut |
| Kata bantu dan kopula | dapat, bisa, harus, akan, adalah, merupakan |
| Kata tanya | apa, bagaimana, mengapa, kapan, berapa |
| Bilangan dan waktu | satu, dua, pertama, kedua, hari, minggu, bulan, tahun |

Contoh dampaknya:

| Keyphrase | Kata yang benar-benar dinilai Yoast | Jumlah kata konten |
|---|---|---|
| cara membuat website | website | 1 |
| jasa pembuatan website terbaik | jasa, pembuatan, website | 3 |
| tips desain logo untuk UMKM | tips, desain, logo, umkm | 4 |
| apa itu SEO | seo | 1 |

Bila keyphrase **hanya** berisi function word (misalnya "cara membuat"), Yoast menampilkan cek *Function words in keyphrase* (Bab 4.2) dan hasil cek lain menjadi tidak bermakna.

### 3.3 Dukungan Yoast untuk Bahasa Indonesia

| Fitur | Status untuk Bahasa Indonesia |
|---|---|
| Function word (pencocokan keyphrase, internal linking, insights) | Didukung |
| Bentuk kata atau stemmer [Premium] | Didukung |
| Transition words | Didukung |
| Passive voice | Didukung (berbasis awalan "di-") |
| Consecutive sentences (awal kalimat berulang) | Didukung |
| Sentence length | Didukung, batas 20 kata per kalimat |
| Redundant phrase in alt text | Didukung (pola "gambar", "foto") |
| Flesch reading ease | Tidak tersedia (tidak ada rumus Flesch untuk Bahasa Indonesia) |
| Word complexity [Premium] | Tidak tersedia |
| Inclusive language | Tidak tersedia (hanya bahasa Inggris) |

---

## 4. Analisis SEO (24 Cek)

Bab ini berisi seluruh cek SEO pada set standar (post dan page). Urutan mengikuti alur kerja penulisan: keyphrase, judul dan meta, struktur, tautan, gambar, lalu panjang teks.

### 4.1 Panjang keyphrase

- **Nama Yoast:** Keyphrase length
- **Kode rule:** `yoast_keyphrase_length`
- **Berlaku:** selalu
- **Sinonim:** tidak
- **Yang dicek:** jumlah kata konten di focus keyphrase. Untuk Bahasa Indonesia, function word tidak ikut dihitung.

| Lampu | Skor | Kriteria | Pesan Yoast |
|---|---|---|---|
| Merah | −999 | Focus keyphrase belum diisi | No focus keyphrase was set for this page. Set a focus keyphrase in order to calculate your SEO score. |
| Merah | 3 | Lebih dari 8 kata konten | The keyphrase contains X content words. That's way more than the recommended maximum of X content words. Make it shorter! |
| Oranye | 6 | 5 sampai 8 kata konten | The keyphrase contains X content words. That's more than the recommended maximum of X content words. Make it shorter! |
| Hijau | 9 | 1 sampai 4 kata konten | Good job! |

Catatan: bahasa tanpa dukungan function word memakai batas 1–6 (hijau), 7–9 (oranye), lebih dari 9 (merah).

**Pedoman praktis:** pakai 1–4 kata konten. Skor −999 membuat skor SEO total anjlok, jadi focus keyphrase **wajib** diisi.

### 4.2 Function word di keyphrase

- **Nama Yoast:** Function words in keyphrase
- **Kode rule:** `yoast_keyphrase_function_words`
- **Berlaku:** hanya bila keyphrase seluruhnya terdiri dari function word

| Lampu | Skor | Kriteria | Pesan Yoast |
|---|---|---|---|
| Abu-abu | 0 | Keyphrase hanya berisi function word | Your keyphrase X contains function words only. Learn more about what makes a good keyphrase. |

**Pedoman praktis:** pastikan keyphrase memuat minimal satu kata bermakna (lihat Bab 3.2).

### 4.3 Keyphrase yang pernah dipakai

- **Nama Yoast:** Previously used keyphrase
- **Kode rule:** `yoast_keyphrase_previously_used`
- **Berlaku:** selalu
- **Sinonim:** tidak
- **Yang dicek:** apakah keyphrase yang sama sudah menjadi focus keyphrase di konten lain pada situs yang sama.

| Lampu | Skor | Kriteria | Pesan Yoast |
|---|---|---|---|
| Merah | 1 | Keyphrase belum diisi | No focus keyphrase was set for this page. Please add a focus keyphrase you haven't used before on other content. |
| Merah | 1 | Sudah dipakai lebih dari sekali sebelumnya | You've used this keyphrase X times before. Do not use your keyphrase more than once. |
| Oranye | 6 | Sudah dipakai sekali sebelumnya | You've used this keyphrase once before. Do not use your keyphrase more than once. |
| Hijau | 9 | Belum pernah dipakai | You've not used this keyphrase before, very good. |

**Pedoman praktis:** satu keyphrase untuk satu halaman. Ini mencegah halaman-halaman di situs yang sama bersaing untuk kata kunci yang sama (*keyword cannibalization*).

### 4.4 Keyphrase di paragraf pembuka

- **Nama Yoast:** Keyphrase in introduction
- **Kode rule:** `yoast_keyphrase_in_introduction`
- **Berlaku:** selalu
- **Sinonim:** ya
- **Yang dicek:** apakah semua kata konten keyphrase (atau sinonimnya) ada di paragraf pertama.

| Lampu | Skor | Kriteria | Pesan Yoast |
|---|---|---|---|
| Merah | 3 | Keyphrase atau konten belum ada | Please add both a keyphrase and an introduction containing the keyphrase. |
| Merah | 3 | Tidak semua kata konten ada di paragraf pertama | Your keyphrase or its synonyms do not appear in the first paragraph. Make sure the topic is clear immediately. |
| Oranye | 6 | Semua kata konten ada di paragraf pertama, tetapi tidak dalam satu kalimat | Your keyphrase or its synonyms appear in the first paragraph of the copy, but not within one sentence. Fix that! |
| Hijau | 9 | Semua kata konten ada dalam **satu kalimat** di paragraf pertama | Well done! |

**Pedoman praktis:** tulis keyphrase utuh di kalimat pertama atau kedua paragraf pembuka.

### 4.5 Kepadatan keyphrase

- **Nama Yoast:** Keyphrase density
- **Kode rule:** `yoast_keyphrase_density`
- **Berlaku:** selalu
- **Sinonim:** tidak
- **Yang dicek:** berapa kali keyphrase muncul dibanding panjang teks. Satu kecocokan berarti semua kata konten ada dalam satu kalimat. Bila muncul berulang dalam satu kalimat, dihitung berulang.

**Teks 100 kata atau lebih:**

| Lampu | Skor | Kriteria (kepadatan tersesuaikan, *kd*) | Pesan Yoast |
|---|---|---|---|
| Merah | −50 | Keyphrase atau teks belum ada | Please add both a keyphrase and some text containing the keyphrase. |
| Merah | 4 | Keyphrase tidak ditemukan, atau hanya 1 kali | The keyphrase was found X times. That's less than the recommended minimum of X times for a text of this length. Focus on your keyphrase! |
| Merah | 4 | 0 < kd < 0,5% | Pesan sama dengan baris di atas |
| Hijau | 9 | 0,5% ≤ kd ≤ 3% (3,5% bila Premium mengenali bentuk kata) | The keyphrase was found X times. This is great! |
| Merah | −10 | 3% < kd ≤ 4% (3,5% < kd ≤ 4% bila bentuk kata dikenali) | The keyphrase was found X times. That's more than the recommended maximum of X times for a text of this length. Don't overoptimize! |
| Merah | −50 | kd > 4% | The keyphrase was found X times. That's way more than the recommended maximum of X times for a text of this length. Don't overoptimize! |

**Teks 51–99 kata:** 1–2 kali hijau (9), 3 kali merah (−10), 4 kali atau lebih merah (−50).
**Teks 50 kata atau kurang:** 1 kali hijau (9), 2 kali merah (−10), 3 kali atau lebih merah (−50).

**Rumus Yoast:**

- Kepadatan dasar = jumlah kemunculan ÷ jumlah kata × 100.
- Kepadatan tersesuaikan (*kd*) = kepadatan dasar × (0,7 + *n* ÷ 3), dengan *n* = jumlah kata konten keyphrase. Keyphrase yang lebih panjang lebih sulit diulang, jadi faktor ini menyesuaikan targetnya.
- Jumlah minimum = 0,5 × jumlah kata ÷ (100 × faktor), dibulatkan ke atas. Jumlah maksimum = 3 × jumlah kata ÷ (100 × faktor), dibulatkan ke bawah. Keduanya minimal 2.
- Pengecualian: tepat 2 kemunculan dianggap hijau bila minimum yang direkomendasikan ≤ 2.

**Tabel jumlah kemunculan yang direkomendasikan** (hasil rumus Yoast, versi Free dengan batas atas 3%):

| Panjang teks | Keyphrase 1 kata | 2 kata | 3 kata | 4 kata |
|---|---|---|---|---|
| 300 kata | 2–8 kali | 2–6 kali | 2–5 kali | 2–4 kali |
| 500 kata | 3–14 kali | 2–10 kali | 2–8 kali | 2–7 kali |
| 700 kata | 4–20 kali | 3–15 kali | 3–12 kali | 2–10 kali |
| 1.000 kata | 5–29 kali | 4–21 kali | 3–17 kali | 3–14 kali |
| 1.500 kata | 8–43 kali | 6–32 kali | 5–26 kali | 4–22 kali |
| 2.000 kata | 10–58 kali | 8–43 kali | 6–35 kali | 5–29 kali |

**Pedoman praktis:** cukup capai batas bawah, lalu tambah secara alami. Jangan dekati batas atas karena skornya negatif (−10 atau −50).

### 4.6 Sebaran keyphrase [Premium]

- **Nama Yoast:** Keyphrase distribution
- **Kode rule:** `yoast_keyphrase_distribution`
- **Berlaku:** selalu (Premium)
- **Sinonim:** ya
- **Yang dicek:** apakah keyphrase dan sinonimnya tersebar merata di seluruh teks. Ukurannya adalah deretan kalimat terpanjang yang tidak memuat keyphrase dibanding total kalimat.

| Lampu | Skor | Kriteria | Pesan Yoast |
|---|---|---|---|
| Merah | 1 | Keyphrase belum diisi atau tidak ditemukan di teks | Please add both a keyphrase and some text containing the keyphrase or its synonyms. |
| Merah | 1 | Teks ≥ 15 kalimat, rasio di atas 0,5 | Very uneven. Large parts of your text do not contain the keyphrase or its synonyms. Distribute them more evenly. |
| Oranye | 6 | Teks ≥ 15 kalimat, rasio 0,3–0,5 | Uneven. Some parts of your text do not contain your keyphrase or its synonyms. Distribute them more evenly. |
| Hijau | 9 | Teks ≥ 15 kalimat, rasio di bawah 0,3 | Good job! |
| Hijau | 9 | Teks di bawah 15 kalimat dan keyphrase muncul minimal sekali | Good job! |

Contoh: dari 15 kalimat, deretan terpanjang tanpa keyphrase ada 6 kalimat. Rasionya 6 ÷ 15 = 0,4, jadi oranye.

**Pedoman praktis:** munculkan keyphrase atau sinonimnya di pembuka, di tengah (misalnya di subjudul), dan di penutup.

### 4.7 Keyphrase di SEO title

- **Nama Yoast:** Keyphrase in SEO title
- **Kode rule:** `yoast_keyphrase_in_seo_title`
- **Berlaku:** selalu
- **Sinonim:** tidak
- **Yang dicek:** apakah keyphrase ada di SEO title dan apakah posisinya di awal. Function word yang mendahului keyphrase di judul diabaikan saat menentukan posisi.

| Lampu | Skor | Kriteria | Pesan Yoast |
|---|---|---|---|
| Merah | 2 | Keyphrase atau SEO title belum ada | Please add both a keyphrase and an SEO title beginning with the keyphrase. |
| Merah | 2 | Tidak semua kata konten keyphrase ada di judul | Not all the words from your keyphrase "X" appear in the SEO title. For the best SEO results write the exact match of your keyphrase in the SEO title, and put the keyphrase at the beginning of the title. |
| Merah | 2 | Keyphrase memakai tanda kutip, tetapi judul tidak memuat frasa persisnya | Does not contain the exact match. Try to write the exact match of your keyphrase in the SEO title and put it at the beginning of the title. |
| Oranye | 6 | Semua kata ada, tetapi bukan frasa persis | Does not contain the exact match. Try to write the exact match of your keyphrase in the SEO title and put it at the beginning of the title. |
| Oranye | 6 | Frasa persis ada, tetapi tidak di awal judul | The exact match of the focus keyphrase appears in the SEO title, but not at the beginning. Move it to the beginning for the best results. |
| Hijau | 9 | Frasa persis ada di **awal** SEO title | The exact match of the focus keyphrase appears at the beginning of the SEO title. Good job! |

**Pedoman praktis:** awali SEO title dengan keyphrase persis. Contoh untuk keyphrase "jasa pembuatan website": *Jasa Pembuatan Website untuk UMKM, Siap dalam 7 Hari*.

### 4.8 Lebar SEO title

- **Nama Yoast:** SEO title width
- **Kode rule:** `yoast_seo_title_width`
- **Berlaku:** selalu
- **Yang dicek:** lebar SEO title dalam **piksel** seperti di Google preview, bukan jumlah karakter. Hasil variabel template ikut dihitung, **kecuali** variabel pemisah (`%%sep%%`) dan nama situs (`%%sitename%%`).

| Lampu | Skor | Kriteria | Pesan Yoast |
|---|---|---|---|
| Merah | 1 | SEO title kosong | Please create an SEO title. |
| Merah | 3 | Lebar di atas 600 piksel | The SEO title is wider than the viewable limit. Try to make it shorter. |
| Hijau | 9 | Lebar 1 sampai 600 piksel | Good job! |

Catatan: secara internal ada batas bawah 400 piksel, tetapi pada set standar, taksonomi, dan cornerstone, judul pendek **tidak** diberi penalti (tetap hijau).

**Pedoman praktis:** 600 piksel kira-kira setara 50–60 karakter, tergantung lebar huruf (huruf "m" dan "w" lebih lebar daripada "i" dan "l"). Selalu periksa di Google preview.

### 4.9 Keyphrase di meta description

- **Nama Yoast:** Keyphrase in meta description
- **Kode rule:** `yoast_keyphrase_in_meta_description`
- **Berlaku:** selalu
- **Sinonim:** ya
- **Yang dicek:** berapa kalimat di meta description yang memuat semua kata konten keyphrase (atau sinonim).

| Lampu | Skor | Kriteria | Pesan Yoast |
|---|---|---|---|
| Merah | 3 | Keyphrase atau meta description belum ada | Please add both a keyphrase and a meta description containing the keyphrase. |
| Merah | 3 | Keyphrase tidak ditemukan | The meta description has been specified, but it does not contain the keyphrase. Fix that! |
| Merah | 3 | Ditemukan lebih dari 2 kali | The meta description contains the keyphrase X times, which is over the advised maximum of 2 times. Limit that! |
| Hijau | 9 | Ditemukan 1 atau 2 kali | Keyphrase or synonym appear in the meta description. Well done! |

### 4.10 Panjang meta description

- **Nama Yoast:** Meta description length
- **Kode rule:** `yoast_meta_description_length`
- **Berlaku:** selalu
- **Yang dicek:** jumlah karakter meta description. Bila tanggal ditampilkan di Google preview (biasanya pada post), panjang tanggal ditambah pemisah " - " ikut dihitung. Pada taksonomi, tanggal tidak dihitung.

| Lampu | Skor | Kriteria | Pesan Yoast |
|---|---|---|---|
| Merah | 1 | Meta description kosong | No meta description has been specified. Search engines will display copy from the page instead. Make sure to write one! |
| Oranye (cornerstone: merah) | 6 (cornerstone: 3) | 120 karakter atau kurang | The meta description is too short (under X characters). Up to X characters are available. Use the space! |
| Oranye (cornerstone: merah) | 6 (cornerstone: 3) | 157 karakter atau lebih | The meta description is over X characters. To ensure the entire description will be visible, you should reduce the length! |
| Hijau | 9 | Lebih dari 120 dan kurang dari 157 karakter (121–156) | Well done! |

**Pedoman praktis:** tulis 121–156 karakter, muat keyphrase 1 kali, dan akhiri dengan ajakan bertindak.

### 4.11 Keyphrase di slug

- **Nama Yoast:** Keyphrase in slug
- **Kode rule:** `yoast_keyphrase_in_slug`
- **Berlaku:** selalu
- **Sinonim:** tidak

| Lampu | Skor | Kriteria | Pesan Yoast |
|---|---|---|---|
| Merah | 3 | Keyphrase atau slug belum ada | Please add both a keyphrase and a slug containing the keyphrase. |
| Oranye (cornerstone: merah) | 6 (cornerstone: 3) | Tidak semua kata konten ada di slug | (Part of) your keyphrase does not appear in the slug. Change that! |
| Hijau | 9 | Keyphrase 1–2 kata konten: semua kata ada di slug | Great work! |
| Hijau | 9 | Keyphrase lebih dari 2 kata konten: lebih dari separuh kata ada di slug | More than half of your keyphrase appears in the slug. That's great! |

**Pedoman praktis:** slug pendek, huruf kecil, dipisah tanda hubung, tanpa function word. Contoh: `/jasa-pembuatan-website`.

### 4.12 Keyphrase di subjudul

- **Nama Yoast:** Keyphrase in subheading
- **Kode rule:** `yoast_keyphrase_in_subheadings`
- **Berlaku:** selalu, kecuali di taksonomi
- **Sinonim:** ya
- **Yang dicek:** berapa persen subjudul **H2 dan H3** yang mencerminkan topik. Untuk bahasa dengan dukungan function word seperti Bahasa Indonesia, subjudul dianggap mencerminkan topik bila memuat **lebih dari separuh** kata konten keyphrase (lebih dari 50%). Artinya keyphrase 1–2 kata harus muncul utuh, keyphrase 3 kata minimal 2 kata, dan keyphrase 4 kata minimal 3 kata.

| Lampu | Skor | Kriteria | Pesan Yoast |
|---|---|---|---|
| Merah | 1 | Keyphrase atau teks belum ada | Please add both a keyphrase and some text to receive relevant feedback. |
| Merah | 2 | Teks lebih dari 300 kata (cornerstone: 250) tanpa subjudul | You are not using any higher-level subheadings containing the keyphrase or its synonyms. Fix that! |
| Merah | 3 | Kurang dari 30% subjudul H2/H3 mencerminkan topik | Use more keyphrases or synonyms in your higher-level subheadings! |
| Merah | 3 | Lebih dari 75% subjudul H2/H3 mencerminkan topik | More than 75% of your higher-level subheadings reflect the topic of your copy. That's too much. Don't over-optimize! |
| Hijau | 9 | 30%–75% subjudul H2/H3 mencerminkan topik | X of your higher-level subheadings reflect the topic of your copy. Good job! |
| Hijau | 9 | Hanya ada satu subjudul H2/H3 dan subjudul itu mencerminkan topik | Your higher-level subheading reflects the topic of your copy. Good job! |
| Hijau | 9 | Teks 300 kata atau kurang (cornerstone: 250) tanpa subjudul | You are not using any higher-level subheadings containing the keyphrase or its synonyms, but your text is short enough and probably doesn't need them. |

**Pedoman praktis:** dari 4 subjudul H2/H3, isi 2 atau 3 dengan keyphrase atau sinonimnya. Sisanya pakai variasi topik.

### 4.13 Tautan yang bersaing

- **Nama Yoast:** Competing links
- **Kode rule:** `yoast_competing_links`
- **Berlaku:** selalu, kecuali taksonomi (WordPress) serta halaman koleksi dan blog toko (Shopify)
- **Sinonim:** ya
- **Yang dicek:** apakah ada tautan di teks yang anchor text-nya sama dengan keyphrase atau sinonim.

| Lampu | Skor | Kriteria | Pesan Yoast |
|---|---|---|---|
| Merah | 2 | Ada tautan yang anchor text-nya keyphrase atau sinonim | You have a link which uses your keyphrase or synonym as its anchor text. Fix that! |
| Hijau | 8 | Tidak ada | There are no links which use your keyphrase or synonym as their anchor text. Nice! |

Contoh dengan keyphrase "desain logo":

| Anchor text | Dianggap bersaing? | Alasan |
|---|---|---|
| desain logo | Ya | Cocok penuh |
| logo | Tidak | Hanya sebagian keyphrase |
| desain logo restoran | Tidak | Ada kata konten tambahan |

**Pedoman praktis:** jangan menautkan ke halaman lain memakai keyphrase halaman ini sendiri. Hal itu memberi sinyal bahwa halaman lain lebih relevan untuk keyphrase tersebut.

### 4.14 Tautan internal

- **Nama Yoast:** Internal links
- **Kode rule:** `yoast_internal_links`
- **Berlaku:** selalu (tidak ada di halaman produk)

| Lampu | Skor | Kriteria | Pesan Yoast |
|---|---|---|---|
| Merah | 3 | Tidak ada tautan internal | No internal links appear in this page, make sure to add some! |
| Oranye | 7 | Semua tautan internal *nofollow* | The internal links in this page are all nofollowed. Add some good internal links. |
| Hijau | 8 | Campuran *follow* dan *nofollow* | There are both nofollowed and normal internal links on this page. Good job! |
| Hijau | 9 | Semua tautan internal *follow* | You have enough internal links. Good job! |

**Pedoman praktis:** minimal 1 tautan internal *follow*. Arahkan ke halaman cornerstone atau halaman layanan yang relevan.

### 4.15 Tautan keluar

- **Nama Yoast:** Outbound links
- **Kode rule:** `yoast_outbound_links`
- **Berlaku:** selalu (tidak ada di halaman produk)

| Lampu | Skor | Kriteria | Pesan Yoast |
|---|---|---|---|
| Merah | 3 | Tidak ada tautan keluar | No outbound links appear in this page. Add some! |
| Oranye | 7 | Semua tautan keluar *nofollow* | All outbound links on this page are nofollowed. Add some normal links. |
| Hijau | 8 | Campuran *follow* dan *nofollow* | There are both nofollowed and normal outbound links on this page. Good job! |
| Hijau | 9 | Semua tautan keluar *follow* | Good job! |

**Pedoman praktis:** minimal 1 tautan *follow* ke sumber tepercaya, misalnya data resmi, riset, atau dokumentasi.

### 4.16 Gambar

- **Nama Yoast:** Images
- **Kode rule:** `yoast_images`
- **Berlaku:** selalu

| Lampu | Skor | Kriteria | Pesan Yoast |
|---|---|---|---|
| Merah | 3 | Tidak ada gambar | No images appear on this page. Add some! |
| Hijau | 9 | Minimal 1 gambar | Good job! |

### 4.17 Panjang alt text

- **Nama Yoast:** Alt text length
- **Kode rule:** `yoast_alt_text_length`
- **Berlaku:** hanya muncul bila ada gambar yang alt text-nya terlalu pendek atau terlalu panjang. Tidak ada lampu hijau. Gambar **tanpa** alt text tidak dihitung di cek ini.

| Lampu | Skor | Kriteria | Pesan Yoast |
|---|---|---|---|
| Oranye | 6 | Alt text 10 karakter atau kurang | X of your images have very short alt text. Consider making the alt text longer than 10 characters. |
| Oranye | 6 | Alt text 200 karakter atau lebih | X of your images have very long alt text. Consider making the alt text shorter than 200 characters. |
| Oranye | 6 | Kedua kasus terjadi | X of your images have very short or very long alt text. Consider making the alt text longer than 10 and shorter than 200 characters. |

**Pedoman praktis:** alt text 11–199 karakter.

### 4.18 Alt text duplikat

- **Nama Yoast:** Duplicate alt text
- **Kode rule:** `yoast_alt_text_duplicate`
- **Berlaku:** hanya muncul bila dua gambar berbeda atau lebih memakai alt text yang sama (dibandingkan setelah spasi di ujung dibuang dan huruf dikecilkan). Alt text kosong tidak dihitung, jadi beberapa gambar dekoratif dengan alt kosong tetap benar. Gambar yang sama dalam dua ukuran (`src` berbeda) dihitung sebagai dua gambar.

| Lampu | Skor | Kriteria | Pesan Yoast |
|---|---|---|---|
| Oranye | 6 | Dua gambar berbeda atau lebih berbagi alt text | X of your images don't have unique alt text. Give each image its own alt text. |

### 4.19 Frasa mubazir di alt text

- **Nama Yoast:** Redundant phrase in alt text
- **Kode rule:** `yoast_alt_text_redundant_phrase`
- **Berlaku:** hanya muncul bila ada alt text yang memuat frasa mubazir
- **Pola Bahasa Indonesia yang ditandai:**
  - Alt text diawali "gambar" atau "foto" lalu diikuti kata lain. Contoh: *Gambar kucing tidur di sofa*.
  - Alt text diawali "pada gambar (ini) terlihat", "dalam foto (ini) terlihat", dan pola sejenis.
  - **Pengecualian** (tidak ditandai): *gambar latar, gambar profil, gambar sampul, gambar unggulan, gambar stok, foto profil, foto sampul, foto udara, foto arsip, foto stok*.

| Lampu | Skor | Kriteria | Pesan Yoast |
|---|---|---|---|
| Oranye | 6 | Minimal 1 gambar memuat frasa mubazir | X of your images have alt text with a phrase like "Image of" or similar. Remove it as screen readers already announce that it's an image. You may keep phrases that describe the type of image (e.g. "photograph", "illustration", "screenshot") if the type is worth pointing out. |

**Pedoman praktis:** tulis *Kucing tidur di sofa*, bukan *Gambar kucing tidur di sofa*.

### 4.20 Alt text yang tidak deskriptif

- **Nama Yoast:** Non-descriptive alt text
- **Kode rule:** `yoast_alt_text_non_descriptive`
- **Berlaku:** hanya muncul bila ada alt text yang tidak bermakna. Alt text kosong tidak ditandai di cek ini.
- **Yang ditandai** (seluruh alt text harus cocok):
  - Hanya angka atau tanda baca.
  - Nama file, misalnya `IMG_1234`, `sepatu-merah.jpg`, atau nama file gambar itu sendiri, termasuk versi yang diubah ukurannya (misalnya `sepatu-merah` untuk `sepatu-merah-1024x576.jpg`).
  - Nama file bawaan kamera, ponsel, WhatsApp, dan tangkapan layar berbahasa Inggris (misalnya "Screenshot 2026-03-14 at 10.32.45").
  - Placeholder dan frasa generik berbahasa Inggris, misalnya "Untitled", "N/A", "Alt text", "Product image", "Image 1". Frasa ini juga dicek untuk konten Bahasa Indonesia. Belum ada daftar frasa khusus Bahasa Indonesia.

| Lampu | Skor | Kriteria | Pesan Yoast |
|---|---|---|---|
| Merah | 3 | Minimal 1 gambar memakai alt text yang tidak deskriptif | X of your images have alt text that doesn't describe the image. Describe what the image shows, or leave the alt text empty if the image is decorative. |

Catatan: "Product image" ditandai, sedangkan "Product image of a red bike on a beach" tidak.

### 4.21 Keyphrase berlebihan di alt text

- **Nama Yoast:** Keyphrase stuffing in alt text
- **Kode rule:** `yoast_alt_text_keyphrase_stuffing`
- **Berlaku:** hanya bila keyphrase diisi dan ada gambar yang alt text-nya berlebihan memakai keyphrase. Tidak ada lampu hijau.
- **Sinonim:** ya. Kemunculan keyphrase dan sinonim dalam satu alt text dijumlahkan.

| Lampu | Skor | Kriteria | Pesan Yoast |
|---|---|---|---|
| Merah | 3 | Ada gambar dengan lebih dari 2 kemunculan **dan** ada gambar dengan tepat 2 kemunculan | X of your images have alt text that uses the keyphrase or its synonym twice or more. Do not overuse the keyphrase! |
| Merah | 3 | Ada gambar dengan lebih dari 2 kemunculan | X of your images have alt text that uses the keyphrase or its synonym more than twice. Do not overuse the keyphrase! |
| Merah | 3 | Exact match (keyphrase bertanda kutip) dan lebih dari 1 kemunculan | X of your images have alt text that uses the keyphrase or its synonym multiple times. Do not overuse the keyphrase! |
| Oranye | 6 | Ada gambar dengan tepat 2 kemunculan | X of your images have alt text that uses the keyphrase or its synonym twice. Do not overuse the keyphrase! |

Catatan versi: cek lama *Image keyphrase* (dulu menargetkan 30–75% gambar memuat keyphrase) sudah **deprecated** dan diganti lima cek alt text di Bab 4.17–4.21. Kini Yoast tidak lagi mewajibkan keyphrase di alt text, hanya melarang pemakaian berlebihan.

**Pedoman praktis:** keyphrase boleh muncul maksimal 1 kali per alt text, dan hanya bila relevan dengan isi gambar.

### 4.22 Panjang teks

- **Nama Yoast:** Text length
- **Kode rule:** `yoast_text_length`
- **Berlaku:** selalu

| Lampu | Skor | Kriteria (post atau page biasa) | Pesan Yoast |
|---|---|---|---|
| Merah | −20 | 0–99 kata | The text contains X words. This is far below the recommended minimum of 300 words. Add more content. |
| Merah | −10 | 100–199 kata | The text contains X words. This is far below the recommended minimum of 300 words. Add more content. |
| Merah | 3 | 200–249 kata | The text contains X words. This is below the recommended minimum of 300 words. Add more content. |
| Oranye | 6 | 250–299 kata | The text contains X words. This is slightly below the recommended minimum of 300 words. Add more content. |
| Hijau | 9 | 300 kata atau lebih | The text contains X words. Good job! |

Untuk cornerstone, taksonomi, dan produk, lihat Bab 6.

### 4.23 Satu H1

- **Nama Yoast:** Single title
- **Kode rule:** `yoast_single_h1`
- **Berlaku:** selalu
- **Yang dicek:** jumlah H1 **di dalam isi teks**. Judul utama halaman biasanya sudah dirender sebagai H1 oleh tema.

| Lampu | Skor | Kriteria | Pesan Yoast |
|---|---|---|---|
| Merah | 1 | Ada 2 H1 atau lebih di isi teks | H1s should only be used as your main title. Find all H1s in your text that aren't your main title and change them to a lower heading level! |
| Hijau | 8 | Kurang dari 2 H1 | You don't have multiple H1 headings, well done! |

**Pedoman praktis:** satu halaman cukup satu H1, yaitu judul utama. Isi teks dimulai dari H2.

### 4.24 Judul halaman [Premium]

- **Nama Yoast:** Title
- **Kode rule:** `yoast_title_present`
- **Berlaku:** selalu (Premium), kecuali taksonomi

| Lampu | Skor | Kriteria | Pesan Yoast |
|---|---|---|---|
| Merah | −10000 | Halaman belum punya judul | Your page does not have a title yet. Add one! |
| Hijau | 9 | Halaman punya judul | Your page has a title. Well done! |

---

## 5. Analisis Keterbacaan (Readability)

Ingat aturan penalti Bahasa Indonesia di Bab 2.3: **satu lampu merah saja sudah membuat skor keterbacaan oranye.**

### 5.1 Sebaran subjudul

- **Nama Yoast:** Subheading distribution
- **Kode rule:** `yoast_subheading_distribution`
- **Berlaku:** selalu
- **Yang dicek:** panjang setiap bagian teks di antara subjudul, termasuk bagian sebelum subjudul pertama.

| Lampu | Skor | Kriteria (cornerstone dalam kurung) | Pesan Yoast |
|---|---|---|---|
| Merah | 2 | Teks lebih dari 300 kata (250) tanpa subjudul | You are not using any subheadings, although your text is rather long. Try and add some subheadings. |
| Merah | 3 | Ada bagian setelah subjudul yang lebih dari 350 kata (300) | X sections of your text are longer than the recommended number of words (X) and are not separated by any subheadings. Add subheadings to improve readability. |
| Merah | 3 | Bagian pembuka sebelum subjudul pertama lebih dari 350 kata (300) | The beginning of your text is longer than X words and is not separated by any subheadings. Add subheadings to improve readability. |
| Oranye | 6 | Ada bagian 300–350 kata (250–300) | X sections of your text are longer than the recommended number of words (X) and are not separated by any subheadings. Add subheadings to improve readability. |
| Oranye | 6 | Bagian pembuka 300–350 kata (250–300) | The beginning of your text is longer than X words and is not separated by any subheadings. Add subheadings to improve readability. |
| Hijau | 9 | Teks 300 kata atau kurang tanpa subjudul | You are not using any subheadings, but your text is short enough and probably doesn't need them. |
| Hijau | 9 | Semua bagian di bawah 300 kata (250) | Great job! |

**Pedoman praktis:** beri subjudul setiap 200–300 kata.

### 5.2 Panjang paragraf

- **Nama Yoast:** Paragraph length
- **Kode rule:** `yoast_paragraph_length`
- **Berlaku:** selalu

| Lampu | Skor | Kriteria | Pesan Yoast |
|---|---|---|---|
| Merah | 3 | Ada paragraf lebih dari 200 kata | X of the paragraphs contain more than the recommended maximum number of words (150). Shorten your paragraphs! |
| Oranye | 6 | Ada paragraf 151–200 kata | X of the paragraphs contain more than the recommended maximum number of words (150). Shorten your paragraphs! |
| Hijau | 9 | Semua paragraf 150 kata atau kurang | There are no paragraphs that are too long. Great job! |

### 5.3 Panjang kalimat

- **Nama Yoast:** Sentence length
- **Kode rule:** `yoast_sentence_length`
- **Berlaku:** selalu
- **Batas kalimat panjang untuk Bahasa Indonesia:** lebih dari **20 kata**. Sebagai pembanding: Katalan, Spanyol, Persia, Italia, dan Portugis 25 kata; Ibrani, Rusia, dan Turki 15 kata.
- **Yang dicek:** persentase kalimat yang lebih dari 20 kata.

| Lampu | Skor | Kriteria (cornerstone dalam kurung) | Pesan Yoast |
|---|---|---|---|
| Merah | 3 | Lebih dari 30% kalimat (25%) | X of the sentences contain more than 20 words, which is more than the recommended maximum of X. Try to shorten the sentences. |
| Oranye | 6 | 25%–30% kalimat (20%–25%) | X of the sentences contain more than 20 words, which is more than the recommended maximum of X. Try to shorten the sentences. |
| Hijau | 9 | 25% kalimat atau kurang (20%) | Great! |

### 5.4 Awal kalimat yang berulang

- **Nama Yoast:** Consecutive sentences
- **Kode rule:** `yoast_consecutive_sentences`
- **Berlaku:** bila bahasa didukung (Bahasa Indonesia didukung)
- **Pengecualian Bahasa Indonesia:** bila kalimat diawali *sebuah, seorang, sang, si*, bilangan (*satu, dua, ..., seratus, seribu*), atau *ini, itu, hal, ia*, Yoast membandingkan dua kata pertama, bukan hanya kata pertama.

| Lampu | Skor | Kriteria | Pesan Yoast |
|---|---|---|---|
| Merah | 3 | 3 kalimat berturut-turut atau lebih diawali kata yang sama | The text contains X consecutive sentences starting with the same word. Try to mix things up! |
| Hijau | 9 | Tidak ada | There are no repetitive sentence beginnings. That's great! |

### 5.5 Kalimat pasif

- **Nama Yoast:** Passive voice
- **Kode rule:** `yoast_passive_voice`
- **Berlaku:** bila bahasa didukung (Bahasa Indonesia didukung)
- **Cara Yoast mendeteksi kalimat pasif Bahasa Indonesia:** kalimat dianggap pasif bila memuat kata **lebih dari 4 huruf yang berawalan "di"**, dengan dua pengecualian:
  - Kata tersebut ada di daftar pengecualian, misalnya *digital, diskon, diskusi, direktur, distribusi, dinding, dingin, diri, dimensi, dinas*.
  - Kata tersebut tepat didahului kata *untuk*, misalnya "untuk dijual".
- Kata depan "di" yang ditulis terpisah ("di rumah") tidak terhitung karena hanya 2 huruf.

| Lampu | Skor | Kriteria | Pesan Yoast |
|---|---|---|---|
| Merah | 3 | Lebih dari 15% kalimat | X of the sentences contain passive voice, which is more than the recommended maximum of 10%. Try to use their active counterparts. |
| Oranye | 6 | 10%–15% kalimat | X of the sentences contain passive voice, which is more than the recommended maximum of 10%. Try to use their active counterparts. |
| Hijau | 9 | 10% kalimat atau kurang | You are not using too much passive voice. That's great! |

**Pedoman praktis:** tulis "Kami memproses pesanan dalam 1 hari" alih-alih "Pesanan diproses dalam 1 hari". Waspadai kata seperti *dijual, dibuat, digunakan, diproses, dikirim*, karena semuanya terhitung pasif.

### 5.6 Kata transisi

- **Nama Yoast:** Transition words
- **Kode rule:** `yoast_transition_words`
- **Berlaku:** bila bahasa didukung (Bahasa Indonesia didukung). Penilaian ketat hanya untuk teks lebih dari 200 kata.

| Lampu | Skor | Kriteria | Pesan Yoast |
|---|---|---|---|
| Merah | 3 | Teks panjang dan tidak ada kalimat dengan kata transisi | None of the sentences contain transition words. Use some. |
| Merah | 3 | Teks panjang, kurang dari 20% kalimat memakai kata transisi | Only X of the sentences contain them. This is not enough. Use more transition words. |
| Oranye | 6 | Teks panjang, 20%–30% kalimat | Only X of the sentences contain them. This is not enough. Use more transition words. |
| Hijau | 9 | Teks panjang, 30% kalimat atau lebih | Well done! |
| Hijau | 9 | Teks pendek (200 kata atau kurang), ada kata transisi | Well done! |
| Hijau | 9 | Teks pendek tanpa kata transisi | You are not using any transition words, but your text is short enough and probably doesn't need them. |

**Contoh kata transisi Bahasa Indonesia yang dikenali Yoast:**

- **Satu kata:** akhirnya, bahkan, contohnya, jadi, juga, karena, kemudian, khususnya, maka, meskipun, misalnya, namun, pertama, kedua, ketiga, sehingga, selain, selanjutnya, sementara, terakhir, tetapi, umumnya, walaupun, yaitu, yakni, akibatnya, hasilnya, intinya, faktanya.
- **Frasa:** akan tetapi, oleh karena itu, oleh sebab itu, selain itu, di sisi lain, dengan kata lain, sebagai contoh, pada dasarnya, pada akhirnya, secara umum, secara keseluruhan, setelah itu, sementara itu, maka dari itu, meskipun demikian, sejauh ini, tentu saja, lebih lanjut, di samping itu.
- **Pasangan:** baik ... maupun, bukan ... melainkan, tidak hanya ... tetapi juga, bukan hanya ... melainkan juga, meskipun ... namun, begitu ... sehingga, apabila ... maka, kalau ... maka.

### 5.7 Keberadaan teks

- **Nama Yoast:** Text presence (tampil sebagai "Not enough content")
- **Kode rule:** `yoast_text_presence`

| Lampu | Skor | Kriteria | Pesan Yoast |
|---|---|---|---|
| Merah | 3 | Teks kurang dari 50 karakter | Not enough content: please add some content to enable a good analysis. |

### 5.8 Kerumitan kata [Premium]

- **Nama Yoast:** Word complexity
- **Status untuk Bahasa Indonesia:** **tidak tersedia**.
- Untuk bahasa yang didukung: lebih dari 10% kata dianggap rumit menghasilkan oranye (6), atau merah (3) untuk cornerstone. 10% atau kurang menghasilkan hijau (9). Bentuk kata dari keyphrase tidak dihitung.

### 5.9 Perataan teks [Premium]

- **Nama Yoast:** Alignment
- **Kode rule:** `yoast_text_alignment`
- **Berlaku:** bila teks lebih dari 50 karakter dan ada paragraf atau judul rata tengah.

| Lampu | Skor | Kriteria | Pesan Yoast |
|---|---|---|---|
| Merah | 2 | Ada 1 bagian rata tengah lebih dari 50 karakter | There is a long section of center-aligned text. We recommend making it left-aligned. |
| Merah | 2 | Ada beberapa bagian rata tengah lebih dari 50 karakter | There are X long sections of center-aligned text. We recommend making them left-aligned. |

### 5.10 Cek yang tidak berlaku untuk Bahasa Indonesia

- **Flesch reading ease:** tidak termasuk penilaian keterbacaan. Yoast hanya menampilkannya di panel *Insights* untuk bahasa yang punya rumus Flesch. Bahasa Indonesia tidak punya, jadi cek ini tidak muncul.
- **Inclusive language:** hanya tersedia untuk bahasa Inggris.

---

## 6. Perbedaan Menurut Jenis Konten

### 6.1 Konten cornerstone

| Cek | Post atau page biasa | Cornerstone |
|---|---|---|
| Text length | Hijau ≥ 300 kata; 250–299 oranye; di bawah 250 merah | Hijau ≥ 900 kata; 400–899 oranye (6); di bawah 400 merah (−20) |
| Meta description length | Terlalu pendek atau panjang: oranye (6) | Terlalu pendek atau panjang: **merah** (3) |
| Keyphrase in slug | Sebagian kata hilang: oranye (6) | Sebagian kata hilang: **merah** (3) |
| Keyphrase in subheadings | Teks lebih dari 300 kata tanpa subjudul: merah | Ambangnya 250 kata |
| Subheading distribution | Batas 300 (oranye) / 350 (merah) kata | Batas 250 (oranye) / 300 (merah) kata |
| Sentence length | Hijau ≤ 25%; merah lebih dari 30% | Hijau ≤ 20%; merah lebih dari 25% |
| Word complexity [Premium] | Oranye (6) | Merah (3) |

### 6.2 Halaman taksonomi (kategori dan tag)

**Cek yang dipakai:** keyphrase in introduction, keyphrase length, keyphrase density, keyphrase in meta description, meta description length (tanpa tanggal), text length, keyphrase in SEO title, SEO title width, keyphrase in slug, function words in keyphrase, previously used keyphrase, dan single title.

**Cek yang tidak dipakai:** keyphrase in subheadings, competing links, internal links, outbound links, images, dan semua cek alt text.

**Panjang teks taksonomi:**

| Lampu | Skor | Kriteria | Pesan Yoast |
|---|---|---|---|
| Merah | −20 | 0 kata | Please add some content. |
| Merah | 3 | 1–9 kata | The text contains X words. This is below the recommended minimum of 30 words. Add more content. |
| Oranye | 6 | 10–29 kata | The text contains X words. This is slightly below the recommended minimum of 30 words. Add more content. |
| Hijau | 9 | 30 kata atau lebih | The text contains X words. Good job! |

### 6.3 Halaman produk (WooCommerce dan Shopify)

**Perbedaan pada cek SEO:**

| Cek | Aturan halaman produk |
|---|---|
| Keyphrase length | Hijau 4–6 kata konten; oranye 3 atau 7–8 kata; merah 1–2 atau lebih dari 8 kata |
| Text length | Hijau ≥ 200 kata; 150–199 oranye (6); 100–149 merah (3); 50–99 merah (−10); 0–49 merah (−20). Cornerstone: hijau ≥ 400 kata |
| Images | Hijau ≥ 4 gambar; 1–3 gambar oranye (6, "We recommend at least 4"); tanpa gambar merah (3) |
| Cek alt text | Sama dengan Bab 4.17–4.21, pesannya menyebut "product images" |
| Internal links dan outbound links | **Tidak dipakai**, karena tujuan halaman produk adalah pembelian |

**Cek khusus produk** (ditambahkan oleh integrasi Yoast WooCommerce SEO dan Yoast untuk Shopify):

| Cek | Merah | Oranye | Hijau |
|---|---|---|---|
| Image alt attributes | Tidak ada gambar ber-alt, atau ada gambar tanpa alt (3) | Tidak ada | Semua gambar punya alt (9) |
| Product identifier (GTIN, EAN, ISBN, dan sejenisnya) | Tidak ada | Belum diisi, atau ada varian yang belum diisi (6) | Terisi di produk atau semua varian (9) |
| SKU | Tidak ada | Belum diisi, atau ada varian yang belum diisi (6) | Terisi di produk atau semua varian (9) |

**Perbedaan pada cek keterbacaan:**

| Cek | Aturan halaman produk |
|---|---|
| Paragraph length | Hijau ≤ 70 kata; 71–100 oranye; lebih dari 100 merah |
| Sentence length | Hijau ≤ 20% kalimat panjang; 20%–25% oranye; lebih dari 25% merah |
| Lists (khusus produk) | Merah (3) bila tidak ada daftar; hijau (9) bila ada minimal 1 daftar bernomor atau berpoin |
| Consecutive sentences | Tidak dipakai |

### 6.4 Related keyphrase [Premium]

Yoast Premium memungkinkan sampai 4 keyphrase tambahan selain focus keyphrase. Setiap related keyphrase hanya dinilai dengan cek berikut: keyphrase in introduction, keyphrase length, keyphrase density, keyphrase in meta description, keyphrase stuffing in alt text, competing links, dan previously used keyphrase. Untuk taksonomi, related keyphrase hanya dinilai dengan keyphrase in introduction, keyphrase length, keyphrase density, keyphrase in meta description, dan previously used keyphrase.

---

## 7. Pengaturan Teknis yang Dikelola Yoast

Bagian ini bukan cek berlampu, tetapi pengaturan bawaan Yoast yang menjadi praktik standar.

### 7.1 SEO title dan meta description

- Yoast memakai **template** dengan variabel, misalnya `%%title%% %%page%% %%sep%% %%sitename%%`, dan setiap halaman bisa diganti manual.
- **Google preview** (tampilan desktop dan mobile) dipakai untuk memeriksa apakah judul dan deskripsi terpotong.
- Bila meta description dikosongkan, mesin pencari mengambil potongan teks dari halaman sendiri.

### 7.2 Indeks dan crawling

- **Meta robots per halaman:** opsi tampil di hasil pencarian (`index` atau `noindex`), mengikuti tautan (`follow` atau `nofollow`), serta opsi lanjutan `noimageindex`, `noarchive`, dan `nosnippet`.
- **Canonical URL:** Yoast otomatis menulis canonical yang menunjuk ke halaman itu sendiri. Bisa diganti manual di tab *Advanced* untuk konten duplikat.
- **XML sitemap:** dibuat otomatis di `/sitemap_index.xml`. Halaman ber-`noindex` otomatis dikeluarkan dari sitemap.
- **Halaman lampiran media:** Yoast menganjurkan halaman lampiran dialihkan ke file aslinya agar tidak menjadi halaman tipis yang terindeks.
- **Crawl optimization:** opsi untuk membuang URL, feed, dan metadata yang tidak perlu dari `<head>`.
- **llms.txt:** Yoast dapat membuat file `llms.txt` untuk membantu sistem AI memahami isi situs.
- **IndexNow** [Premium]: memberi tahu mesin pencari yang mendukung saat konten berubah.

### 7.3 Structured data (Schema)

- Yoast membuat satu **graph Schema** yang saling terhubung: `WebSite`, `WebPage`, `Organization` atau `Person`, `Article` (untuk post), `BreadcrumbList`, dan `ImageObject`.
- Jenis halaman (*page type*) dan jenis artikel (*article type*) bisa diatur per konten.
- Data organisasi atau orang (nama, logo, profil sosial) diatur sekali di pengaturan situs.

### 7.4 Breadcrumbs

Yoast menyediakan breadcrumbs dengan Schema `BreadcrumbList`. Judul breadcrumb bisa diatur per halaman.

### 7.5 Media sosial

- Yoast menulis tag **Open Graph** (`og:title`, `og:description`, `og:image`, dan lainnya) serta **X/Twitter Card**.
- Judul, deskripsi, dan gambar sosial bisa dibedakan dari SEO title dan meta description.
- Ukuran gambar Facebook yang dianjurkan: **1200 × 630 piksel**.

### 7.6 Fitur struktur situs

- **Cornerstone content:** penanda konten pilar. Konten bertanda ini dinilai dengan ambang lebih ketat (Bab 6.1).
- **Saran tautan internal** [Premium]: rekomendasi halaman terkait saat menulis.
- **Orphaned content** [Premium]: deteksi halaman yang tidak menerima tautan internal.
- **Redirect manager** [Premium]: membuat redirect 301 otomatis saat slug diubah atau halaman dihapus.
- **Yoast AI** [Premium]: membuat usulan SEO title dan meta description.

---

## 8. Checklist Cepat Sebelum Publikasi

Target semua hijau untuk post atau page biasa berbahasa Indonesia:

| No | Item | Target hijau |
|---|---|---|
| 1 | Focus keyphrase | Terisi, 1–4 kata konten, belum pernah dipakai di halaman lain |
| 2 | Paragraf pembuka | Keyphrase utuh dalam satu kalimat |
| 3 | Kepadatan keyphrase | Sesuai tabel Bab 4.5 (contoh: 1.000 kata, keyphrase 2 kata = 4–21 kali) |
| 4 | SEO title | Diawali keyphrase persis, maksimal 600 piksel (sekitar 50–60 karakter) |
| 5 | Meta description | 121–156 karakter, memuat keyphrase 1–2 kali |
| 6 | Slug | Memuat semua kata konten keyphrase (atau lebih dari separuh untuk keyphrase lebih dari 2 kata) |
| 7 | Subjudul H2/H3 | 30%–75% memuat keyphrase atau sinonim; subjudul setiap kurang dari 300 kata |
| 8 | H1 | Hanya satu (judul utama) |
| 9 | Tautan internal | Minimal 1 *follow* |
| 10 | Tautan keluar | Minimal 1 *follow* |
| 11 | Anchor text | Tidak ada tautan keluar halaman yang memakai keyphrase halaman ini |
| 12 | Gambar | Minimal 1 |
| 13 | Alt text | 11–199 karakter, unik per gambar, tanpa awalan "gambar" atau "foto", bukan nama file, keyphrase maksimal 1 kali |
| 14 | Panjang teks | ≥ 300 kata (cornerstone ≥ 900) |
| 15 | Paragraf | Maksimal 150 kata per paragraf |
| 16 | Kalimat | Maksimal 25% kalimat lebih dari 20 kata |
| 17 | Kalimat pasif | Maksimal 10% kalimat |
| 18 | Kata transisi | Minimal 30% kalimat |
| 19 | Awal kalimat | Tidak ada 3 kalimat berturut-turut dengan kata awal yang sama |

---

## 9. Usulan Pemetaan ke Rules Engine Krafio

### 9.1 Pemetaan lampu ke severity

Tabel `seo_rules` Krafio memakai kolom `code`, `name`, `severity`, `weight`, `is_active`, dan `ruleset_version`. Usulan pemetaan:

| Lampu Yoast | Severity Krafio |
|---|---|
| Merah dengan skor negatif (−10 sampai −999) | `critical` |
| Merah (skor 1–4) | `critical` |
| Oranye (skor 5–7) | `warning` |
| Abu-abu (skor 0) | `info` |

Kode rule untuk setiap cek tercantum di Bab 4 dan Bab 5 (awalan `yoast_`).

**Status:** sudah terpasang di Krafio sebagai rule engine `content` (33 rule, menu Admin › SEO rules). Pengguna memakainya lewat menu SEO › Analisis konten. Setup umum ada di Admin › SEO › Analisis konten.

### 9.2 Batasan penerapan

Sebagian cek Yoast butuh data yang tidak bisa diambil hanya dengan crawling URL:

| Butuh input pengguna | Bisa dari crawl HTML |
|---|---|
| Focus keyphrase, sinonim, related keyphrase | SEO title (`<title>`), meta description, slug |
| Status cornerstone | Heading H1–H3, paragraf, kalimat, panjang teks |
| Daftar keyphrase yang pernah dipakai (perlu data seluruh situs) | Tautan internal dan keluar (beserta `nofollow`) |
| | Gambar dan alt text |

Sesuai PRD Bab 29, skor yang memakai aturan ini tetap ditampilkan sebagai **skor evaluasi internal Krafio**, bukan skor resmi Yoast atau mesin pencari.

### 9.3 Perbedaan dengan rule Krafio yang sudah ada

Rule awal di `SeoAuditor` (ruleset 1.0) berbeda dari Yoast pada beberapa titik:

| Rule Krafio | Aturan Krafio saat ini | Aturan Yoast |
|---|---|---|
| `title_length` | 30–60 karakter | Maksimal 600 piksel; judul pendek tidak dipenalti |
| `meta_description` | Hanya cek keberadaan; rekomendasi 70–160 karakter | Hijau 121–156 karakter; ≤ 120 atau ≥ 157 oranye |
| `img_alt` | Semua gambar wajib punya alt | Set standar tidak memenalti alt kosong (boleh untuk gambar dekoratif). Hanya halaman produk yang mewajibkan semua gambar ber-alt |
| `single_h1` | Tepat satu H1 di seluruh HTML | Penalti bila ada 2 H1 atau lebih di isi teks |

Keputusan mana yang dipakai dapat dicatat di Bab 10.

---

## 10. Pedoman Tambahan (Diisi Tim Krafio)

Bab ini sengaja dikosongkan untuk pedoman internal. Gunakan format berikut agar konsisten dengan aturan Yoast di atas. Bila pedoman tambahan bertentangan dengan aturan Yoast, tuliskan mana yang berlaku.

### 10.1 Format aturan

- **Nama aturan:**
- **Kode rule:**
- **Kategori:** (misalnya Technical SEO, On-page SEO, Content quality, Keyword placement)
- **Berlaku untuk:** (jenis halaman atau jenis website)
- **Kriteria hijau:**
- **Kriteria oranye:**
- **Kriteria merah:**
- **Severity dan bobot:**
- **Rekomendasi perbaikan:**
- **Sumber atau alasan:**
- **Hubungan dengan aturan Yoast:** (menambah, mengganti, atau mengecualikan cek nomor ...)

### 10.2 Daftar pedoman tambahan

| No | Nama aturan | Kode rule | Kategori | Ringkasan kriteria | Status |
|---|---|---|---|---|---|
| 1 | | | | | |
| 2 | | | | | |
| 3 | | | | | |

---

## 11. Sumber dan Catatan Versi

### 11.1 Sumber

- Repo resmi Yoast SEO: `github.com/Yoast/wordpress-seo`, folder `packages/yoastseo/src/scoring`, commit `dde860ac5583805cdb5d5d93f82661753a5b0a04` (11 November 2026).
- Dokumen skoring resmi di repo tersebut: *SCORING SEO.md*, *SCORING READABILITY.md*, *SCORING TAXONOMY.md*, *SCORING SEO PRODUCT.md*, *SCORING READABILITY PRODUCT.md*, *KEYPHRASE MATCHING.md*, dan *ASSESSORS OVERVIEW.md*.
- Konfigurasi Bahasa Indonesia: `packages/yoastseo/src/languageProcessing/languages/id` (function word, kata transisi, deteksi pasif, pengecualian awal kalimat, dan pola alt text).
- Angka ambang diverifikasi langsung dari kode, termasuk rumus kepadatan keyphrase (`recommendedKeywordCount.js`, `keyphraseLengthFactor.js`) dan penilaian keterbacaan (`ReadabilityScoreAggregator.js`).
- Bab 7 (pengaturan teknis) dirangkum dari fitur plugin Yoast SEO dan tidak berasal dari mesin skoring.

### 11.2 Catatan versi

- Yoast merilis pembaruan sekitar dua minggu sekali. Ambang di dokumen ini berlaku per commit di atas dan sebaiknya diverifikasi ulang sebelum perubahan besar pada ruleset.
- Perubahan penting yang sudah tercermin di dokumen ini: cek *Image keyphrase* sudah **deprecated** dan diganti lima cek alt text (Bab 4.17–4.21).

| Versi | Tanggal | Perubahan | Oleh |
|---|---|---|---|
| 1.0 | 14 November 2026 | Draf awal berdasarkan Yoast SEO | Claude |
| 1.1 | 14 November 2026 | Koreksi Bab 4.12 (subjudul: lebih dari separuh kata konten); catatan implementasi di Bab 9.1 | Claude |
| | | | |
