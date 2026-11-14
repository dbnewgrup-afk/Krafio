<?php

namespace App\Services\Seo;

use App\Services\Settings;

/**
 * Data bahasa Indonesia untuk analisis konten SEO (docs/seo.md Bab 3 & 5).
 * Kategori mengikuti cara kerja Yoast SEO; daftar kata disusun Krafio dan dapat ditambah
 * Admin lewat pengaturan `seo.lexicon.*` tanpa deploy.
 */
class IndonesianLexicon
{
    /** Kata fungsi: dibuang dari keyphrase saat pencocokan (docs/seo.md 3.2). */
    private const FUNCTION_WORDS = [
        // kata sandang & penggolong
        'si', 'sang', 'para', 'kaum', 'sri', 'hang', 'dang', 'sebuah', 'seorang', 'seekor', 'sebiji', 'selembar', 'sehelai',
        'sebutir', 'sebatang', 'sepotong', 'sepasang', 'secarik',
        // bilangan
        'nol', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas', 'belas',
        'puluh', 'puluhan', 'ratus', 'ratusan', 'ribu', 'ribuan', 'juta', 'jutaan', 'miliar', 'triliun', 'seratus', 'seribu',
        'sejuta', 'setengah', 'separuh', 'sepertiga', 'seperempat', 'kesatu', 'pertama', 'kedua', 'ketiga', 'keempat', 'kelima',
        'keenam', 'ketujuh', 'kedelapan', 'kesembilan', 'kesepuluh',
        // kata ganti
        'aku', 'saya', 'engkau', 'kau', 'kamu', 'anda', 'kita', 'kami', 'kalian', 'ia', 'dia', 'beliau', 'mereka', 'milikku',
        'milikmu', 'miliknya', 'punyaku', 'punyamu', 'punyanya', 'diriku', 'dirimu', 'dirinya', 'diri', 'sendiri',
        // penunjuk & relatif
        'ini', 'itu', 'tersebut', 'tadi', 'inilah', 'itulah', 'sini', 'situ', 'sana', 'begini', 'begitu', 'demikian', 'yang',
        // kata tanya
        'apa', 'apakah', 'siapa', 'siapakah', 'mana', 'manakah', 'bagaimana', 'bagaimanakah', 'mengapa', 'kenapa', 'kapan',
        'kapankah', 'berapa', 'berapakah',
        // kata depan
        'di', 'ke', 'dari', 'pada', 'kepada', 'daripada', 'untuk', 'bagi', 'buat', 'dengan', 'oleh', 'tentang', 'mengenai',
        'terhadap', 'demi', 'sejak', 'hingga', 'sampai', 'menuju', 'melalui', 'lewat', 'tanpa', 'antara', 'seperti', 'bagai',
        'bagaikan', 'laksana', 'sebagai', 'menurut', 'atas', 'bawah', 'dalam', 'luar', 'depan', 'belakang', 'samping',
        'sebelah', 'sekitar', 'sepanjang', 'selama', 'per',
        // kata sambung
        'dan', 'atau', 'serta', 'lalu', 'kemudian', 'tetapi', 'tapi', 'namun', 'melainkan', 'sedangkan', 'sementara', 'padahal',
        'karena', 'sebab', 'sehingga', 'maka', 'agar', 'supaya', 'jika', 'kalau', 'apabila', 'bila', 'jikalau', 'asalkan',
        'meskipun', 'walaupun', 'biarpun', 'kendati', 'sekalipun', 'seandainya', 'andaikan', 'ketika', 'saat', 'waktu',
        'sewaktu', 'sebelum', 'sesudah', 'setelah', 'selagi', 'bahwa', 'maupun', 'entah', 'juga', 'pun',
        // kata bantu, kopula, modalitas, negasi
        'ada', 'adalah', 'ialah', 'merupakan', 'akan', 'sedang', 'sudah', 'telah', 'belum', 'masih', 'pernah', 'sempat', 'harus',
        'wajib', 'perlu', 'dapat', 'bisa', 'boleh', 'mampu', 'mau', 'ingin', 'hendak', 'mesti', 'tidak', 'tak', 'bukan', 'jangan',
        // keterangan umum
        'sangat', 'amat', 'sekali', 'terlalu', 'paling', 'lebih', 'kurang', 'cukup', 'agak', 'hampir', 'hanya', 'cuma', 'saja',
        'pula', 'lagi', 'selalu', 'sering', 'jarang', 'kadang', 'kadang-kadang', 'segera', 'langsung', 'pasti', 'mungkin',
        'tentu', 'memang', 'sungguh', 'semua', 'semuanya', 'seluruh', 'setiap', 'tiap', 'beberapa', 'banyak', 'sedikit',
        'berbagai', 'segala', 'masing-masing', 'sebagian', 'kebanyakan', 'lain', 'lainnya', 'sesuatu', 'seseorang',
        // partikel
        'lah', 'kah', 'tah', 'dong', 'kok', 'sih', 'nah', 'deh', 'kan', 'toh', 'ya', 'yah', 'lho',
        // kata kerja umum (makna lemah)
        'punya', 'memiliki', 'mempunyai', 'membuat', 'dibuat', 'melakukan', 'dilakukan', 'menjadi', 'jadi', 'mencoba', 'dicoba',
        'memastikan', 'dipastikan', 'menunjukkan', 'ditunjukkan', 'termasuk', 'berarti', 'terdiri', 'mengandung', 'mari',
        'marilah', 'terlihat', 'kelihatan',
        // kata benda samar
        'cara', 'hal', 'barang', 'masalah', 'bagian', 'aspek', 'ide', 'item', 'tema', 'perkara', 'faktor', 'detail', 'perbedaan',
        'adanya',
        // kata sifat umum
        'baik', 'terbaik', 'buruk', 'bagus', 'terbagus', 'besar', 'terbesar', 'kecil', 'terkecil', 'baru', 'terbaru', 'lama',
        'tua', 'muda', 'mudah', 'termudah', 'cepat', 'tercepat', 'lambat', 'susah', 'sulit', 'penting', 'terpenting', 'utama',
        'umum', 'biasa', 'khusus', 'spesifik', 'spesial', 'tertentu', 'berbeda', 'beda', 'sama', 'keren', 'tersedia',
        // waktu
        'detik', 'menit', 'jam', 'hari', 'minggu', 'bulan', 'tahun', 'kemarin', 'besok', 'sekarang', 'kini', 'nanti', 'dulu',
        'pagi', 'siang', 'sore', 'malam',
        // seruan & lain-lain
        'ah', 'oh', 'wah', 'aduh', 'waduh', 'hai', 'halo', 'ayo', 'yuk', 'dll', 'dsb', 'dst', 'ok', 'oke', 'iya', 'tolong', 'mohon',
        'maaf',
    ];

    /** Kata/frasa transisi (docs/seo.md 5.6). */
    private const TRANSITION_WORDS = [
        'akhirnya', 'akibatnya', 'alhasil', 'apalagi', 'bahkan', 'contohnya', 'misalnya', 'umpamanya', 'jadi', 'juga', 'karena',
        'karenanya', 'kemudian', 'khususnya', 'terutama', 'utamanya', 'lalu', 'maka', 'makanya', 'meskipun', 'walaupun',
        'biarpun', 'kendatipun', 'namun', 'tetapi', 'sedangkan', 'sementara', 'sehingga', 'selain', 'selanjutnya', 'seterusnya',
        'pertama', 'kedua', 'ketiga', 'terakhir', 'umumnya', 'biasanya', 'yaitu', 'yakni', 'hasilnya', 'intinya', 'faktanya',
        'nyatanya', 'ternyata', 'sebaliknya', 'sebelumnya', 'setelahnya', 'kesimpulannya', 'ringkasnya', 'singkatnya',
        'pendeknya', 'lagipula', 'padahal', 'agar', 'supaya', 'sebab', 'hingga', 'mulanya', 'awalnya', 'nantinya', 'tentunya',
        'pastinya', 'sesungguhnya', 'sebetulnya', 'sebenarnya', 'setidaknya', 'diantaranya',
        'akan tetapi', 'oleh karena itu', 'oleh sebab itu', 'karena itu', 'maka dari itu', 'dengan demikian', 'dengan begitu',
        'dengan kata lain', 'sebagai contoh', 'sebagai gambaran', 'di sisi lain', 'di pihak lain', 'di samping itu', 'selain itu',
        'lebih lanjut', 'lebih jauh', 'setelah itu', 'sesudah itu', 'sebelum itu', 'sementara itu', 'pada dasarnya',
        'pada akhirnya', 'pada awalnya', 'pada umumnya', 'pada intinya', 'pada kenyataannya', 'secara umum',
        'secara keseluruhan', 'secara khusus', 'secara singkat', 'sejauh ini', 'tentu saja', 'meskipun demikian',
        'walaupun demikian', 'meskipun begitu', 'walaupun begitu', 'kendati demikian', 'sama halnya', 'begitu juga',
        'begitu pula', 'demikian juga', 'demikian pula', 'sebagai hasilnya', 'sebagai akibatnya', 'untuk itu', 'dalam hal ini',
        'pertama-tama', 'yang terpenting', 'paling tidak', 'singkat kata', 'pendek kata', 'sebagai gantinya',
        'sebagai tambahan', 'lagi pula', 'di sisi lainnya', 'tidak hanya itu', 'bukan hanya itu',
    ];

    /** Pasangan transisi dua bagian, mis. "tidak hanya ... tetapi juga". */
    private const TWO_PART_TRANSITIONS = [
        ['baik', 'maupun'], ['bukan', 'melainkan'], ['bukan', 'tetapi'], ['tidak hanya', 'tetapi juga'],
        ['bukan hanya', 'tetapi juga'], ['bukan hanya', 'melainkan juga'], ['meskipun', 'namun'], ['walaupun', 'namun'],
        ['jika', 'maka'], ['apabila', 'maka'], ['kalau', 'maka'], ['begitu', 'sehingga'], ['sedemikian', 'sehingga'],
        ['entah', 'entah'],
    ];

    /** Awal kalimat yang dibandingkan dua kata pertamanya (docs/seo.md 5.4). */
    private const FIRST_WORD_EXCEPTIONS = [
        'sebuah', 'seorang', 'seekor', 'sang', 'si', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan',
        'sembilan', 'sepuluh', 'sebelas', 'seratus', 'seribu', 'sejuta', 'ini', 'itu', 'hal', 'ia',
    ];

    /** Kata berawalan "di" yang bukan pasif — dicocokkan utuh. */
    private const NON_PASSIVE_EXACT = [
        'diri', 'dirinya', 'dirimu', 'diriku', 'diet', 'dimana', 'disini', 'disana', 'disitu', 'dikau', 'dinas', 'dingin',
        'dinding', 'digit', 'disk', 'dian', 'dini', 'diam', 'diamlah', 'diare',
    ];

    /** Awalan kata berawalan "di" yang bukan pasif (cukup panjang agar tidak menelan kata kerja pasif). */
    private const NON_PASSIVE_PREFIXES = [
        'digital', 'diskon', 'diskusi', 'distribu', 'direktur', 'direksi', 'direktori', 'dinamis', 'dinamik', 'dimensi',
        'diagnos', 'diagram', 'diameter', 'diplom', 'disiplin', 'dividen', 'divisi', 'dinasti', 'dinosaur', 'dialog', 'dialek',
        'diktat', 'diksi', 'dikte', 'diesel', 'diferensial', 'dinamo', 'diorama', 'dioksida', 'disertasi', 'diskotek', 'diskrit',
        'dispenser', 'disposisi', 'distorsi', 'distrik', 'dingin', 'dinding', 'dinas',
    ];

    /** @return array<string, true> peta kata fungsi (ambil sekali per analisis) */
    public static function functionWords(): array
    {
        return array_fill_keys(array_merge(self::FUNCTION_WORDS, self::extra('seo.lexicon.function_words_extra')), true);
    }

    /** @return string[] frasa transisi (huruf kecil), termasuk tambahan dari Admin */
    public static function transitionWords(): array
    {
        return array_values(array_unique(array_merge(self::TRANSITION_WORDS, self::extra('seo.lexicon.transition_words_extra'))));
    }

    /** @return array<int, array{0:string,1:string}> */
    public static function twoPartTransitions(): array
    {
        return self::TWO_PART_TRANSITIONS;
    }

    public static function firstWordExceptions(): array
    {
        return array_fill_keys(self::FIRST_WORD_EXCEPTIONS, true);
    }

    /**
     * Kata pasif bahasa Indonesia ala Yoast: kata > 4 huruf berawalan "di", bukan pengecualian,
     * dan tidak tepat didahului "untuk".
     *
     * @param  string[]  $words  kata-kata kalimat (huruf kecil)
     */
    public static function sentenceIsPassive(array $words): bool
    {
        $extra = self::extra('seo.lexicon.passive_exceptions_extra');
        foreach ($words as $i => $w) {
            if (mb_strlen($w) <= 4 || ! str_starts_with($w, 'di')) {
                continue;
            }
            if (in_array($w, self::NON_PASSIVE_EXACT, true) || in_array($w, $extra, true)) {
                continue;
            }
            foreach (self::NON_PASSIVE_PREFIXES as $p) {
                if (str_starts_with($w, $p)) {
                    continue 2;
                }
            }
            if ($i > 0 && $words[$i - 1] === 'untuk') {
                continue;
            }

            return true;
        }

        return false;
    }

    /** Alt text diawali frasa mubazir (docs/seo.md 4.19). */
    public static function hasRedundantAltPhrase(string $alt): bool
    {
        $a = mb_strtolower(trim($alt));
        $id = '/^(?:(?:gambar(?!\s+(?:latar|profil|sampul|unggulan|stok)\b)|foto(?!\s+(?:profil|sampul|udara|arsip|stok)\b))\s+\p{L}|(?:pada|dalam)\s+(?:gambar|foto)\s+(?:ini\s+)?terlihat\b)/u';
        $en = '/^(?:an?\s+)?(?:image|picture|photo|graphic)\s+(?:of|showing|that shows|depicting)\b|^this\s+(?:image|picture|photo)\s+(?:shows|depicts|is)\b/u';

        return (bool) (preg_match($id, $a) || preg_match($en, $a));
    }

    /** Alt text tanpa makna: placeholder, nama file, angka saja (docs/seo.md 4.20). */
    public static function isNonDescriptiveAlt(string $alt, string $src = ''): bool
    {
        $a = mb_strtolower(trim($alt));
        if ($a === '') {
            return false;
        }
        if (preg_match('/^[\p{N}\p{P}\p{S}\s]+$/u', $a)) {
            return true;
        }
        if (preg_match('/\.(?:jpe?g|png|gif|webp|svg|avif|bmp|tiff?|heic)$/u', $a)) {
            return true;
        }
        if (preg_match('/^(?:img|dsc|dscn|dcim|pxl|mvimg|photo|image|imag|wp|whatsapp image|screenshot|screen shot|tangkapan layar)[\s_-]*[\d\s_.:-]*(?:at[\s\d.:-]*)?(?:\.\w+)?$/u', $a)
            && preg_match('/\d/', $a)) {
            return true;
        }
        $placeholders = ['untitled', 'n/a', 'na', 'alt', 'alt text', 'alt-text', 'image', 'picture', 'photo', 'pic', 'img', 'placeholder',
            'thumbnail', 'product image', 'product photo', 'featured image', 'tanpa judul', 'gambar', 'foto'];
        if (in_array($a, $placeholders, true) || preg_match('/^(?:image|picture|photo|gambar|foto)\s*\d+$/u', $a)) {
            return true;
        }
        if ($src !== '') {
            $file = mb_strtolower(pathinfo(parse_url($src, PHP_URL_PATH) ?: $src, PATHINFO_FILENAME));
            $file = preg_replace('/-(?:\d+x\d+|scaled|rotated|e\d+)$/', '', $file) ?? $file;
            $norm = fn (string $s) => trim(preg_replace('/[\s_-]+/u', ' ', $s) ?? $s);
            if ($file !== '' && $norm($file) === $norm($a)) {
                return true;
            }
        }

        return false;
    }

    /** @return string[] */
    private static function extra(string $key): array
    {
        $v = Settings::get($key, []);
        if (! is_array($v)) {
            return [];
        }

        return array_values(array_filter(array_map(fn ($w) => mb_strtolower(trim((string) $w)), $v), fn ($w) => $w !== ''));
    }
}
