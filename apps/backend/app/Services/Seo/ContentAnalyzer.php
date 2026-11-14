<?php

namespace App\Services\Seo;

/**
 * Analisis konten SEO & keterbacaan bergaya Yoast SEO (docs/seo.md). Rule, ambang, bobot, dan severity
 * dibaca dari ContentRules::effective() sehingga dapat diatur Admin. Hasil adalah skor evaluasi internal
 * Krafio, bukan skor resmi Yoast atau mesin pencari (PRD 29.3).
 */
class ContentAnalyzer
{
    private TextDocument $doc;

    private Topic $keyphrase;

    /** @var Topic[] keyphrase + sinonim */
    private array $topics;

    private array $p = [];

    private string $type;

    /** @var array<string, true> */
    private array $fw;

    /**
     * @param  array{content_type: string, keyphrase: string, synonyms?: ?string, seo_title?: ?string, meta_description?: ?string, slug?: ?string, url?: ?string, content: string}  $input
     * @param  int  $previousUses  berapa halaman lain yang sudah memakai keyphrase yang sama
     * @param  bool  $advanced  pengguna punya entitlement seo.advanced (rule tier "advanced")
     * @return array{seo_score: int, readability_score: int, results: array<int, array>, stats: array}
     */
    public static function run(array $input, int $previousUses = 0, bool $advanced = false, bool $fullReadabilitySupport = false): array
    {
        $a = new self;
        $a->type = in_array($input['content_type'] ?? 'post', ContentRules::TYPES, true) ? $input['content_type'] : 'post';
        $a->fw = IndonesianLexicon::functionWords();
        $a->doc = TextDocument::fromInput((string) ($input['content'] ?? ''));
        $a->topics = Topic::list((string) ($input['keyphrase'] ?? ''), $input['synonyms'] ?? null, $a->fw);
        $a->keyphrase = $a->topics[0] ?? new Topic('', $a->fw);

        $results = [];
        foreach (ContentRules::effective() as $code => $rule) {
            if (! in_array($a->type, $rule['types'], true) || ($rule['tier'] === 'advanced' && ! $advanced)) {
                continue;
            }
            $a->p = $rule['params'];
            $r = match ($code) {
                'yoast_keyphrase_length' => $a->keyphraseLength(),
                'yoast_keyphrase_function_words' => $a->functionWordsOnly(),
                'yoast_keyphrase_previously_used' => $a->previouslyUsed($previousUses),
                'yoast_keyphrase_in_introduction' => $a->introduction(),
                'yoast_keyphrase_density' => $a->density(),
                'yoast_keyphrase_distribution' => $a->distribution(),
                'yoast_keyphrase_in_seo_title' => $a->keyphraseInTitle((string) ($input['seo_title'] ?? '')),
                'yoast_seo_title_width' => $a->titleWidth((string) ($input['seo_title'] ?? '')),
                'yoast_keyphrase_in_meta_description' => $a->keyphraseInMeta((string) ($input['meta_description'] ?? '')),
                'yoast_meta_description_length' => $a->metaLength((string) ($input['meta_description'] ?? '')),
                'yoast_keyphrase_in_slug' => $a->slug((string) ($input['slug'] ?? '')),
                'yoast_keyphrase_in_subheadings' => $a->subheadingsKeyphrase(),
                'yoast_competing_links' => $a->competingLinks((string) ($input['url'] ?? '')),
                'yoast_internal_links' => $a->links((string) ($input['url'] ?? ''), true),
                'yoast_outbound_links' => $a->links((string) ($input['url'] ?? ''), false),
                'yoast_images' => $a->images(),
                'yoast_product_image_alt' => $a->productImageAlt(),
                'yoast_alt_text_length' => $a->altLength(),
                'yoast_alt_text_duplicate' => $a->altDuplicate(),
                'yoast_alt_text_redundant_phrase' => $a->altRedundant(),
                'yoast_alt_text_non_descriptive' => $a->altNonDescriptive(),
                'yoast_alt_text_keyphrase_stuffing' => $a->altStuffing(),
                'yoast_text_length' => $a->textLength(),
                'yoast_single_h1' => $a->singleH1(),
                'yoast_subheading_distribution' => $a->subheadingDistribution(),
                'yoast_paragraph_length' => $a->paragraphLength(),
                'yoast_sentence_length' => $a->sentenceLength(),
                'yoast_consecutive_sentences' => $a->consecutiveSentences(),
                'yoast_passive_voice' => $a->passiveVoice(),
                'yoast_transition_words' => $a->transitionWords(),
                'yoast_text_presence' => $a->textPresence(),
                'yoast_lists' => $a->lists(),
                'yoast_text_alignment' => $a->alignment(),
                default => null,
            };
            if ($r === null || $r[0] === null || $r[1] === '') {
                continue; // tidak berlaku / tidak ada masalah (cek tanpa lampu hijau)
            }
            [$score, $message, $location] = $r;
            $rating = self::rating($score);
            $results[] = [
                'code' => $code, 'category' => $rule['category'], 'title' => $rule['name'], 'score' => $score, 'rating' => $rating,
                'severity' => $rating === 'bad' ? ($rule['rule']?->severity ?: 'critical') : ($rating === 'ok' ? 'warning' : 'info'),
                'weight' => max(1, (int) ($rule['rule']?->weight ?: 5)),
                'location' => $location, 'message' => $message,
                'recommendation' => $rating === 'good' ? '' : $rule['recommendation'],
            ];
        }

        $seo = array_values(array_filter($results, fn ($r) => $r['category'] === 'seo'));
        $read = array_values(array_filter($results, fn ($r) => $r['category'] === 'readability'));

        return [
            'seo_score' => self::seoScore($seo),
            'readability_score' => self::readabilityScore($read, $fullReadabilitySupport),
            'results' => $results,
            'stats' => [
                'words' => $a->doc->wordCount, 'sentences' => count($a->doc->sentences), 'paragraphs' => count($a->doc->paragraphs()),
                'subheadings' => count($a->doc->headings(2, 3)), 'images' => count($a->doc->images), 'links' => count($a->doc->links),
                'keyphrase_count' => $a->keyphraseCount(), 'content_words' => $a->keyphrase->contentWords,
            ],
        ];
    }

    /** Lampu per cek (docs/seo.md 2.1). */
    public static function rating(int $score): string
    {
        return match (true) {
            $score === 0 => 'feedback',
            $score <= 4 => 'bad',
            $score <= 7 => 'ok',
            default => 'good',
        };
    }

    /** Skor SEO total: rata-rata berbobot skor cek pada skala 0–100 (bobot sama = rumus Yoast). */
    public static function seoScore(array $results): int
    {
        $sum = 0;
        $max = 0;
        foreach ($results as $r) {
            $sum += $r['weight'] * $r['score'];
            $max += $r['weight'] * 9;
        }

        return $max > 0 ? max(0, min(100, (int) round($sum * 100 / $max))) : 0;
    }

    /** Skor keterbacaan: poin penalti (docs/seo.md 2.3). Bahasa Indonesia = dukungan parsial. */
    public static function readabilityScore(array $results, bool $fullSupport = false): int
    {
        $valid = array_values(array_filter($results, fn ($r) => $r['score'] !== null && $r['message'] !== ''));
        if (count($valid) <= 1) {
            return 0;
        }
        $penalty = 0;
        foreach ($valid as $r) {
            $penalty += match ($r['rating']) {
                'bad' => $fullSupport ? 3 : 4,
                'ok' => 2,
                default => 0,
            };
        }
        if ($fullSupport) {
            return $penalty > 6 ? 30 : ($penalty > 4 ? 60 : 90);
        }

        return $penalty > 4 ? 30 : ($penalty > 2 ? 60 : 90);
    }

    /** Label skor total untuk tampilan (SEO: ≤40 / 41–70 / ≥71; keterbacaan: 30 / 60 / 90). */
    public static function overallRating(int $score, string $category): string
    {
        if ($category === 'readability') {
            return $score === 0 ? 'feedback' : ($score >= 90 ? 'good' : ($score >= 60 ? 'ok' : 'bad'));
        }

        return $score <= 40 ? 'bad' : ($score <= 70 ? 'ok' : 'good');
    }

    /* ================= Cek SEO ================= */

    private function keyphraseLength(): array
    {
        if ($this->keyphrase->isEmpty()) {
            return [-999, 'Belum ada focus keyphrase. Isi focus keyphrase agar skor SEO dapat dihitung.', 'Keyphrase'];
        }
        $n = count($this->keyphrase->contentWords);
        $p = $this->p;
        if ($this->type === 'product') {
            return match (true) {
                $n <= $p['product_bad_max'] => [3, "Keyphrase berisi {$n} kata konten. Itu jauh kurang dari minimum yang disarankan untuk halaman produk ({$this->n($p['product_ok_low_max'] + 1)} kata). Buat lebih spesifik!", 'Keyphrase'],
                $n <= $p['product_ok_low_max'] => [6, "Keyphrase berisi {$n} kata konten. Itu kurang dari minimum yang disarankan untuk halaman produk ({$this->n($p['product_ok_low_max'] + 1)} kata). Buat lebih spesifik!", 'Keyphrase'],
                $n <= $p['product_good_max'] => [9, 'Panjang keyphrase sudah bagus.', 'Keyphrase'],
                $n <= $p['product_ok_max'] => [6, "Keyphrase berisi {$n} kata konten. Itu lebih dari batas yang disarankan ({$this->n($p['product_good_max'])} kata). Persingkat!", 'Keyphrase'],
                default => [3, "Keyphrase berisi {$n} kata konten. Itu jauh lebih dari batas yang disarankan ({$this->n($p['product_good_max'])} kata). Persingkat!", 'Keyphrase'],
            };
        }

        return match (true) {
            $n <= $p['good_max'] => [9, 'Panjang keyphrase sudah bagus.', 'Keyphrase'],
            $n <= $p['ok_max'] => [6, "Keyphrase berisi {$n} kata konten. Itu lebih dari batas yang disarankan ({$this->n($p['good_max'])} kata). Persingkat!", 'Keyphrase'],
            default => [3, "Keyphrase berisi {$n} kata konten. Itu jauh lebih dari batas yang disarankan ({$this->n($p['good_max'])} kata). Persingkat!", 'Keyphrase'],
        };
    }

    private function functionWordsOnly(): ?array
    {
        if (! $this->keyphrase->onlyFunctionWords || $this->keyphrase->exact) {
            return null;
        }

        return [0, 'Keyphrase "'.$this->keyphrase->raw.'" hanya berisi kata fungsi (mis. "cara", "untuk", "terbaik"). Tambahkan kata yang bermakna.', 'Keyphrase'];
    }

    private function previouslyUsed(int $uses): array
    {
        if ($this->keyphrase->isEmpty()) {
            return [1, 'Belum ada focus keyphrase. Isi keyphrase yang belum pernah Anda pakai di konten lain.', 'Keyphrase'];
        }

        return match (true) {
            $uses === 0 => [9, 'Keyphrase ini belum pernah Anda pakai di konten lain. Bagus.', 'Keyphrase'],
            $uses === 1 => [6, 'Anda sudah memakai keyphrase ini sekali di konten lain. Jangan pakai keyphrase yang sama lebih dari sekali.', 'Keyphrase'],
            default => [1, "Anda sudah memakai keyphrase ini {$uses} kali di konten lain. Jangan pakai keyphrase yang sama lebih dari sekali.", 'Keyphrase'],
        };
    }

    private function introduction(): array
    {
        $first = $this->firstParagraphIndex();
        if ($this->keyphrase->isEmpty() || $first === null) {
            return [3, 'Tambahkan keyphrase dan paragraf pembuka yang memuat keyphrase.', 'Paragraf pembuka'];
        }
        $sentences = $this->doc->sentencesOfBlock($first);
        $all = array_merge(...array_map(fn ($s) => $s['words'], $sentences ?: [['words' => []]]));
        foreach ($this->topics as $t) {
            foreach ($sentences as $s) {
                if ($t->matches($s['words'])) {
                    return [9, 'Keyphrase atau sinonimnya ada di kalimat paragraf pembuka. Bagus!', 'Paragraf pembuka'];
                }
            }
        }
        foreach ($this->topics as $t) {
            if (! $t->exact && $t->percentIn($all) == 100) {
                return [6, 'Keyphrase atau sinonimnya ada di paragraf pembuka, tetapi tidak dalam satu kalimat. Perbaiki!', 'Paragraf pembuka'];
            }
        }

        return [3, 'Keyphrase atau sinonimnya tidak ada di paragraf pembuka. Pastikan topiknya langsung jelas.', 'Paragraf pembuka'];
    }

    private function keyphraseCount(): int
    {
        $c = 0;
        foreach ($this->doc->sentences as $s) {
            $c += $this->keyphrase->countIn($s['words']);
        }

        return $c;
    }

    /** Rumus Yoast: kd = kemunculan ÷ kata × 100 × (0,7 + n/3); batas jumlah minimal 2 (docs/seo.md 4.5). */
    private function density(): array
    {
        $words = $this->doc->wordCount;
        if ($this->keyphrase->isEmpty() || $words === 0) {
            return [-50, 'Tambahkan keyphrase dan teks yang memuat keyphrase.', 'Konten'];
        }
        $count = $this->keyphraseCount();
        $times = fn (int $n) => $n.' kali';
        if ($words < 100) {
            $min = 1;
            $max = $words > 50 ? 2 : 1;

            return match (true) {
                $count === 0 => [4, "Keyphrase ditemukan 0 kali. Itu kurang dari minimum yang disarankan ({$times($min)}) untuk teks sepanjang ini. Fokuslah pada keyphrase Anda!", 'Konten'],
                $count <= $max => [9, "Keyphrase ditemukan {$times($count)}. Bagus!", 'Konten'],
                $count === $max + 1 => [-10, "Keyphrase ditemukan {$times($count)}. Itu lebih dari batas yang disarankan ({$times($max)}) untuk teks sepanjang ini. Jangan berlebihan!", 'Konten'],
                default => [-50, "Keyphrase ditemukan {$times($count)}. Itu jauh lebih dari batas yang disarankan ({$times($max)}) untuk teks sepanjang ini. Jangan berlebihan!", 'Konten'],
            };
        }
        $n = max(1, count($this->keyphrase->contentWords));
        $factor = 0.7 + $n / 3;
        $rec = function (float $density, bool $isMin) use ($words, $factor): int {
            $c = $density * $words / (100 * $factor);
            if ($c < 2) {
                return 2;
            }

            return $isMin ? (int) ceil($c) : (int) floor($c);
        };
        $min = $rec((float) $this->p['min'], true);
        $max = $rec((float) $this->p['max'], false);
        $kd = $count / $words * 100 * $factor;

        return match (true) {
            $count === 0 => [4, "Keyphrase ditemukan 0 kali. Itu kurang dari minimum yang disarankan ({$times($min)}) untuk teks sepanjang ini. Fokuslah pada keyphrase Anda!", 'Konten'],
            $kd < $this->p['min'] || $count === 1 => [4, "Keyphrase ditemukan {$times($count)}. Itu kurang dari minimum yang disarankan ({$times($min)}) untuk teks sepanjang ini. Fokuslah pada keyphrase Anda!", 'Konten'],
            ($kd >= $this->p['min'] && $kd <= $this->p['max']) || ($count === 2 && $min <= 2) => [9, "Keyphrase ditemukan {$times($count)}. Bagus! (rentang yang disarankan {$min}–{$max} kali)", 'Konten'],
            $kd <= $this->p['over_max'] => [-10, "Keyphrase ditemukan {$times($count)}. Itu lebih dari batas yang disarankan ({$times($max)}) untuk teks sepanjang ini. Jangan berlebihan!", 'Konten'],
            default => [-50, "Keyphrase ditemukan {$times($count)}. Itu jauh lebih dari batas yang disarankan ({$times($max)}) untuk teks sepanjang ini. Jangan berlebihan!", 'Konten'],
        };
    }

    private function distribution(): array
    {
        $sentences = $this->doc->sentences;
        $hits = array_map(fn ($s) => (bool) array_filter($this->topics, fn (Topic $t) => $t->matches($s['words'])), $sentences);
        if ($this->keyphrase->isEmpty() || ! in_array(true, $hits, true)) {
            return [1, 'Tambahkan keyphrase dan teks yang memuat keyphrase atau sinonimnya.', 'Konten'];
        }
        $total = count($sentences);
        if ($total < $this->p['min_sentences']) {
            return [9, 'Sebaran keyphrase sudah bagus.', 'Konten'];
        }
        $run = 0;
        $longest = 0;
        foreach ($hits as $h) {
            $run = $h ? 0 : $run + 1;
            $longest = max($longest, $run);
        }
        $ratio = $longest / $total;

        return match (true) {
            $ratio < $this->p['good_max'] => [9, 'Sebaran keyphrase sudah bagus.', 'Konten'],
            $ratio <= $this->p['ok_max'] => [6, 'Kurang merata. Sebagian teks tidak memuat keyphrase atau sinonimnya. Sebarkan lebih merata.', 'Konten'],
            default => [1, 'Sangat tidak merata. Sebagian besar teks tidak memuat keyphrase atau sinonimnya. Sebarkan lebih merata.', 'Konten'],
        };
    }

    private function keyphraseInTitle(string $title): array
    {
        $loc = 'Judul SEO';
        $tw = TextDocument::words($title);
        if ($this->keyphrase->isEmpty() || ! $tw) {
            return [2, 'Tambahkan keyphrase dan judul SEO yang diawali keyphrase.', $loc];
        }
        $k = $this->keyphrase;
        $pos = self::indexOf($tw, $k->words);
        if ($pos !== null) {
            $before = array_slice($tw, 0, $pos);
            $atStart = ! array_filter($before, fn ($w) => ! isset($this->fw[$w]));

            return $atStart
                ? [9, 'Frasa persis focus keyphrase ada di awal judul SEO. Bagus!', $loc]
                : [6, 'Frasa persis focus keyphrase ada di judul SEO, tetapi tidak di awal. Pindahkan ke awal untuk hasil terbaik.', $loc];
        }
        if ($k->exact) {
            return [2, 'Judul SEO tidak memuat frasa persis keyphrase. Tulis frasa persis keyphrase di awal judul SEO.', $loc];
        }
        if ($k->percentIn($tw) == 100) {
            return [6, 'Judul SEO tidak memuat frasa persis keyphrase. Tulis frasa persis keyphrase di awal judul SEO.', $loc];
        }

        return [2, 'Tidak semua kata keyphrase "'.$k->raw.'" ada di judul SEO. Tulis frasa persis keyphrase di awal judul SEO.', $loc];
    }

    private function titleWidth(string $title): array
    {
        if (trim($title) === '') {
            return [1, 'Buat judul SEO terlebih dahulu.', 'Judul SEO'];
        }
        $w = self::pixelWidth($title);
        $max = (int) $this->p['max_px'];

        return $w > $max
            ? [3, "Judul SEO lebih lebar dari batas yang terlihat di Google (sekitar {$w} dari {$max} piksel). Persingkat.", 'Judul SEO']
            : [9, "Lebar judul SEO sudah pas (sekitar {$w} dari {$max} piksel).", 'Judul SEO'];
    }

    private function keyphraseInMeta(string $meta): array
    {
        $loc = 'Meta description';
        if ($this->keyphrase->isEmpty() || trim($meta) === '') {
            return [3, 'Tambahkan keyphrase dan meta description yang memuat keyphrase.', $loc];
        }
        $sentences = array_map(fn ($s) => TextDocument::words($s), TextDocument::splitSentences($meta));
        $best = 0;
        foreach ($this->topics as $t) {
            $best = max($best, array_sum(array_map(fn ($w) => $t->countIn($w), $sentences)));
        }
        $max = (int) $this->p['max'];

        return match (true) {
            $best === 0 => [3, 'Meta description sudah diisi, tetapi belum memuat keyphrase. Perbaiki!', $loc],
            $best > $max => [3, "Meta description memuat keyphrase {$best} kali, lebih dari batas {$max} kali. Kurangi!", $loc],
            default => [9, 'Keyphrase atau sinonimnya ada di meta description. Bagus!', $loc],
        };
    }

    private function metaLength(string $meta): array
    {
        $loc = 'Meta description';
        $len = mb_strlen(trim($meta));
        $short = (int) $this->p['short_max'];
        $max = (int) $this->p['max'];
        $warn = $this->type === 'cornerstone' ? 3 : 6;

        return match (true) {
            $len === 0 => [1, 'Meta description belum diisi. Mesin pencari akan mengambil potongan teks dari halaman. Tulis meta description!', $loc],
            $len <= $short => [$warn, "Meta description terlalu pendek ({$len} karakter, di bawah {$short}). Tersedia hingga {$max} karakter. Manfaatkan ruangnya!", $loc],
            $len > $max => [$warn, "Meta description lebih dari {$max} karakter ({$len}). Persingkat agar tidak terpotong.", $loc],
            default => [9, "Panjang meta description sudah pas ({$len} karakter).", $loc],
        };
    }

    private function slug(string $slug): array
    {
        $loc = 'Slug';
        // Tanda hubung di slug = pemisah kata, tetapi kata keyphrase bertanda hubung ("e-commerce") tetap harus cocok:
        // bandingkan dengan potongan kata ditambah gabungan 2–4 kata berurutan ("e-commerce", "toko-e").
        $sw = TextDocument::words(str_replace(['-', '_', '/', '.'], ' ', $slug));
        if ($this->keyphrase->isEmpty() || ! $sw) {
            return [3, 'Tambahkan keyphrase dan slug yang memuat keyphrase.', $loc];
        }
        $grams = $sw;
        for ($n = 2; $n <= 4; $n++) {
            for ($i = 0; $i + $n <= count($sw); $i++) {
                $grams[] = implode('-', array_slice($sw, $i, $n));
            }
        }
        $k = $this->keyphrase;
        $partial = $this->type === 'cornerstone' ? 3 : 6;
        if ($k->exact) {
            $phrase = TextDocument::words(str_replace('-', ' ', $k->phrase()));

            return self::indexOf($sw, $phrase) !== null ? [9, 'Keyphrase ada di slug. Bagus!', $loc] : [$partial, '(Sebagian) keyphrase tidak ada di slug. Ubah slug!', $loc];
        }
        $pct = $k->percentIn($grams);
        if ($pct == 100) {
            return [9, 'Keyphrase ada di slug. Bagus!', $loc];
        }
        if (count($k->contentWords) > 2 && $pct > 50) {
            return [9, 'Lebih dari separuh keyphrase ada di slug. Bagus!', $loc];
        }

        return [$partial, '(Sebagian) keyphrase tidak ada di slug. Ubah slug!', $loc];
    }

    private function subheadingsKeyphrase(): array
    {
        $loc = 'Subjudul';
        if ($this->keyphrase->isEmpty() || $this->doc->wordCount === 0) {
            return [1, 'Tambahkan keyphrase dan teks agar subjudul dapat dinilai.', $loc];
        }
        $subs = $this->doc->headings(2, 3);
        $long = (int) ($this->type === 'cornerstone' ? $this->p['cornerstone_long_text_words'] : $this->p['long_text_words']);
        if (! $subs) {
            return $this->doc->wordCount > $long
                ? [2, 'Anda belum memakai subjudul H2/H3 yang memuat keyphrase atau sinonimnya. Perbaiki!', $loc]
                : [9, 'Belum ada subjudul H2/H3, tetapi teks cukup pendek sehingga mungkin tidak memerlukannya.', $loc];
        }
        $match = 0;
        foreach ($subs as $h) {
            $w = TextDocument::words($h['text']);
            foreach ($this->topics as $t) {
                if ($t->exact ? $t->matches($w) : $t->percentIn($w) > 50) {
                    $match++;
                    break;
                }
            }
        }
        $total = count($subs);
        if ($total === 1 && $match === 1) {
            return [9, 'Subjudul H2/H3 Anda mencerminkan topik tulisan. Bagus!', $loc];
        }
        $pct = $match / $total * 100;

        return match (true) {
            $pct < $this->p['min_percent'] => [3, "Baru {$match} dari {$total} subjudul H2/H3 yang mencerminkan topik. Pakai keyphrase atau sinonimnya di lebih banyak subjudul!", $loc],
            $pct > $this->p['max_percent'] => [3, "Lebih dari {$this->n($this->p['max_percent'])}% subjudul H2/H3 ({$match} dari {$total}) memuat topik. Itu terlalu banyak. Jangan berlebihan!", $loc],
            default => [9, "{$match} dari {$total} subjudul H2/H3 mencerminkan topik tulisan. Bagus!", $loc],
        };
    }

    private function competingLinks(string $pageUrl): ?array
    {
        if ($this->keyphrase->isEmpty() || $this->doc->wordCount === 0) {
            return null;
        }
        $competing = 0;
        foreach ($this->doc->links as $l) {
            if ($l['href'] === '' || str_starts_with($l['href'], '#') || ($pageUrl !== '' && self::sameUrl($l['href'], $pageUrl))) {
                continue;
            }
            $aw = TextDocument::words($l['text']);
            $anchorContent = array_values(array_unique(array_filter($aw, fn ($w) => ! isset($this->fw[$w]))));
            foreach ($this->topics as $t) {
                $same = $t->exact
                    ? implode(' ', $aw) === $t->phrase()
                    : ($t->percentIn($aw) == 100 && ! array_diff($anchorContent, $t->contentWords));
                if ($same) {
                    $competing++;
                    break;
                }
            }
        }

        return $competing > 0
            ? [2, "Ada {$competing} tautan yang memakai keyphrase atau sinonimnya sebagai anchor text. Perbaiki!", 'Tautan']
            : [8, 'Tidak ada tautan yang memakai keyphrase atau sinonimnya sebagai anchor text. Bagus!', 'Tautan'];
    }

    private function links(string $pageUrl, bool $internal): array
    {
        $host = self::host($pageUrl);
        $follow = 0;
        $nofollow = 0;
        foreach ($this->doc->links as $l) {
            $href = $l['href'];
            if ($href === '' || str_starts_with($href, '#') || preg_match('/^(mailto|tel|sms|javascript|data):/i', $href)) {
                continue;
            }
            $linkHost = self::host($href);
            $isInternal = $linkHost === null || ($host !== null && $linkHost === $host);
            if ($isInternal !== $internal) {
                continue;
            }
            str_contains($l['rel'], 'nofollow') ? $nofollow++ : $follow++;
        }
        $label = $internal ? 'internal' : 'keluar';

        return match (true) {
            $follow + $nofollow === 0 => [3, "Belum ada tautan {$label} di halaman ini. Tambahkan!", 'Tautan'],
            $follow === 0 => [7, "Semua tautan {$label} di halaman ini nofollow. Tambahkan tautan {$label} biasa (follow).", 'Tautan'],
            $nofollow > 0 => [8, "Ada tautan {$label} nofollow dan biasa di halaman ini. Bagus!", 'Tautan'],
            default => [9, "Tautan {$label} sudah ada ({$follow}). Bagus!", 'Tautan'],
        };
    }

    private function images(): array
    {
        $n = count($this->doc->images);
        $min = (int) ($this->type === 'product' ? $this->p['product_min'] : $this->p['min']);

        return match (true) {
            $n === 0 => [3, 'Belum ada gambar di halaman ini. Tambahkan!', 'Gambar'],
            $n < $min => [6, "Baru {$n} gambar di halaman ini. Disarankan minimal {$min}. Tambahkan gambar yang relevan!", 'Gambar'],
            default => [9, "Ada {$n} gambar di halaman ini. Bagus!", 'Gambar'],
        };
    }

    private function productImageAlt(): array
    {
        $imgs = $this->doc->images;
        $missing = count(array_filter($imgs, fn ($i) => $i['alt'] === null || $i['alt'] === ''));

        return match (true) {
            $imgs === [] => [3, 'Halaman ini belum punya gambar dengan atribut alt. Tambahkan!', 'Gambar'],
            $missing === count($imgs) => [3, 'Tidak ada gambar yang punya atribut alt. Tambahkan atribut alt!', 'Gambar'],
            $missing === 1 => [3, 'Satu gambar belum punya atribut alt. Tambahkan atribut alt!', 'Gambar'],
            $missing > 1 => [3, "{$missing} gambar belum punya atribut alt. Tambahkan atribut alt!", 'Gambar'],
            default => [9, 'Semua gambar punya atribut alt. Bagus!', 'Gambar'],
        };
    }

    /** @return array<int, array{src: string, alt: string}> gambar dengan alt terisi */
    private function altImages(): array
    {
        return array_values(array_filter($this->doc->images, fn ($i) => $i['alt'] !== null && $i['alt'] !== ''));
    }

    private function altLength(): ?array
    {
        $short = 0;
        $long = 0;
        foreach ($this->altImages() as $i) {
            $len = mb_strlen($i['alt']);
            if ($len <= $this->p['short_max']) {
                $short++;
            } elseif ($len >= $this->p['long_min']) {
                $long++;
            }
        }
        $s = $this->n($this->p['short_max']);
        $l = $this->n($this->p['long_min']);

        return match (true) {
            $short > 0 && $long > 0 => [6, ($short + $long)." gambar punya alt text yang sangat pendek atau sangat panjang. Buat alt text lebih dari {$s} dan kurang dari {$l} karakter.", 'Gambar'],
            $short > 0 => [6, "{$short} gambar punya alt text yang sangat pendek. Buat alt text lebih dari {$s} karakter.", 'Gambar'],
            $long > 0 => [6, "{$long} gambar punya alt text yang sangat panjang. Buat alt text kurang dari {$l} karakter.", 'Gambar'],
            default => null,
        };
    }

    private function altDuplicate(): ?array
    {
        $groups = [];
        foreach ($this->altImages() as $idx => $i) {
            $key = mb_strtolower(trim($i['alt']));
            $groups[$key][$i['src'] !== '' ? $i['src'] : 'img-'.$idx] = true; // gambar yang sama (src sama) tidak dihitung ganda
        }
        $dup = array_sum(array_map(fn ($g) => count($g) > 1 ? count($g) : 0, $groups));

        return $dup > 0 ? [6, "{$dup} gambar memakai alt text yang sama. Beri setiap gambar alt text sendiri.", 'Gambar'] : null;
    }

    private function altRedundant(): ?array
    {
        $n = count(array_filter($this->altImages(), fn ($i) => IndonesianLexicon::hasRedundantAltPhrase($i['alt'])));

        return $n > 0 ? [6, "{$n} gambar punya alt text dengan awalan seperti \"Gambar ...\" atau \"Foto ...\". Hapus awalan itu karena pembaca layar sudah menyebut bahwa itu gambar. Jenis gambar (mis. \"ilustrasi\", \"tangkapan layar\") boleh tetap ditulis bila penting.", 'Gambar'] : null;
    }

    private function altNonDescriptive(): ?array
    {
        $n = count(array_filter($this->altImages(), fn ($i) => IndonesianLexicon::isNonDescriptiveAlt($i['alt'], $i['src'])));

        return $n > 0 ? [3, "{$n} gambar punya alt text yang tidak menjelaskan isi gambar. Jelaskan isi gambar, atau kosongkan alt bila gambar hanya dekorasi.", 'Gambar'] : null;
    }

    private function altStuffing(): ?array
    {
        if ($this->keyphrase->isEmpty()) {
            return null;
        }
        $ok = (int) $this->p['ok_count'];
        $over = 0;
        $twice = 0;
        $multiple = 0;
        foreach ($this->altImages() as $i) {
            $c = 0;
            foreach (TextDocument::splitSentences($i['alt']) ?: [$i['alt']] as $s) {
                $c += self::topicOccurrences(TextDocument::words($s), $this->topics);
            }
            if ($c > $ok) {
                $over++;
            } elseif ($c === $ok) {
                $twice++;
            }
            if ($c > 1) {
                $multiple++;
            }
        }
        if ($this->keyphrase->exact && $multiple > 0) {
            return [3, "{$multiple} gambar punya alt text yang memakai keyphrase atau sinonimnya lebih dari sekali. Jangan berlebihan!", 'Gambar'];
        }

        return match (true) {
            $over > 0 && $twice > 0 => [3, ($over + $twice)." gambar punya alt text yang memakai keyphrase atau sinonimnya {$ok} kali atau lebih. Jangan berlebihan!", 'Gambar'],
            $over > 0 => [3, "{$over} gambar punya alt text yang memakai keyphrase atau sinonimnya lebih dari {$ok} kali. Jangan berlebihan!", 'Gambar'],
            $twice > 0 => [6, "{$twice} gambar punya alt text yang memakai keyphrase atau sinonimnya {$ok} kali. Jangan berlebihan!", 'Gambar'],
            default => null,
        };
    }

    private function textLength(): array
    {
        $w = $this->doc->wordCount;
        $p = $this->p;
        $loc = 'Konten';
        $msg = fn (int $min, string $level) => "Teks berisi {$w} kata. Itu {$level} minimum yang disarankan ({$min} kata). Tambahkan konten.";
        if ($this->type === 'taxonomy') {
            $good = (int) $p['taxonomy_good'];

            return match (true) {
                $w === 0 => [-20, 'Tambahkan konten deskripsi.', $loc],
                $w < $p['taxonomy_ok'] => [3, $msg($good, 'di bawah'), $loc],
                $w < $good => [6, $msg($good, 'sedikit di bawah'), $loc],
                default => [9, "Teks berisi {$w} kata. Bagus!", $loc],
            };
        }
        if ($this->type === 'cornerstone') {
            $good = (int) $p['cornerstone_good'];

            return match (true) {
                $w < $p['cornerstone_bad'] => [-20, $msg($good, 'jauh di bawah'), $loc],
                $w < $p['cornerstone_ok'] => [-20, $msg($good, 'di bawah'), $loc],
                $w < $good => [6, $msg($good, 'di bawah'), $loc],
                default => [9, "Teks berisi {$w} kata. Bagus!", $loc],
            };
        }
        $pre = $this->type === 'product' ? 'product_' : '';
        $good = (int) $p[$pre.'good'];

        return match (true) {
            $w < $p[$pre.'far'] => [-20, $msg($good, 'jauh di bawah'), $loc],
            $w < $p[$pre.'bad'] => [-10, $msg($good, 'jauh di bawah'), $loc],
            $w < $p[$pre.'ok'] => [3, $msg($good, 'di bawah'), $loc],
            $w < $good => [6, $msg($good, 'sedikit di bawah'), $loc],
            default => [9, "Teks berisi {$w} kata. Bagus!", $loc],
        };
    }

    private function singleH1(): array
    {
        $h1 = count($this->doc->headings(1));

        return $h1 >= 2
            ? [1, "Ada {$h1} H1 di konten. H1 sebaiknya hanya untuk judul utama. Ubah H1 lainnya menjadi tingkat yang lebih rendah (H2/H3)!", 'Konten']
            : [8, 'Tidak ada H1 ganda. Bagus!', 'Konten'];
    }

    /* ================= Cek keterbacaan ================= */

    private function subheadingDistribution(): array
    {
        $loc = 'Konten';
        $corner = $this->type === 'cornerstone';
        $ok = (int) ($corner ? $this->p['cornerstone_ok_max'] : $this->p['ok_max']);
        $bad = (int) ($corner ? $this->p['cornerstone_bad_max'] : $this->p['bad_max']);
        $sections = [];
        $beginning = 0;
        $current = null;
        foreach ($this->doc->blocks as $b) {
            if ($b['type'] === 'heading') {
                if ($current !== null) {
                    $sections[] = $current;
                }
                $current = 0;

                continue;
            }
            $w = count(TextDocument::words($b['text']));
            if ($current === null) {
                $beginning += $w;
            } else {
                $current += $w;
            }
        }
        if ($current !== null) {
            $sections[] = $current;
        }
        if (! $this->doc->headings()) {
            return $this->doc->wordCount > $ok
                ? [2, 'Anda belum memakai subjudul, padahal teks cukup panjang. Tambahkan beberapa subjudul.', $loc]
                : [9, 'Belum ada subjudul, tetapi teks cukup pendek sehingga mungkin tidak memerlukannya.', $loc];
        }
        $tooLong = count(array_filter($sections, fn ($s) => $s > $ok));
        $veryLong = count(array_filter($sections, fn ($s) => $s > $bad));
        if ($tooLong > 0) {
            return [$veryLong > 0 ? 3 : 6, "{$tooLong} bagian teks lebih panjang dari yang disarankan ({$ok} kata) tanpa dipisah subjudul. Tambahkan subjudul agar mudah dibaca.", $loc];
        }
        if ($beginning > $ok) {
            return [$beginning > $bad ? 3 : 6, "Bagian awal teks lebih dari {$ok} kata tanpa dipisah subjudul. Tambahkan subjudul agar mudah dibaca.", $loc];
        }

        return [9, 'Sebaran subjudul sudah bagus!', $loc];
    }

    private function paragraphLength(): ?array
    {
        $paras = $this->doc->paragraphs();
        if (! $paras) {
            return null;
        }
        $pre = $this->type === 'product' ? 'product_' : '';
        $good = (int) $this->p[$pre.'good_max'];
        $ok = (int) $this->p[$pre.'ok_max'];
        $lens = array_map(fn ($b) => count(TextDocument::words($b['text'])), $paras);
        $over = count(array_filter($lens, fn ($l) => $l > $good));
        if ($over === 0) {
            return [9, 'Tidak ada paragraf yang terlalu panjang. Bagus!', 'Konten'];
        }

        return [max($lens) > $ok ? 3 : 6, "{$over} paragraf berisi lebih dari batas yang disarankan ({$good} kata). Persingkat paragraf Anda!", 'Konten'];
    }

    private function sentenceLength(): ?array
    {
        $sentences = $this->doc->sentences;
        if (! $sentences) {
            return null;
        }
        $strict = in_array($this->type, ['cornerstone', 'product'], true);
        $good = (float) ($strict ? $this->p['strict_good_max_percent'] : $this->p['good_max_percent']);
        $ok = (float) ($strict ? $this->p['strict_ok_max_percent'] : $this->p['ok_max_percent']);
        $max = (int) $this->p['max_words'];
        $long = count(array_filter($sentences, fn ($s) => count($s['words']) > $max));
        $pct = round($long / count($sentences) * 100, 1);
        $txt = "{$this->n($pct)}% kalimat berisi lebih dari {$max} kata, lebih dari batas yang disarankan ({$this->n($good)}%). Persingkat kalimat.";

        return match (true) {
            $pct <= $good => [9, 'Panjang kalimat sudah bagus!', 'Konten'],
            $pct <= $ok => [6, $txt, 'Konten'],
            default => [3, $txt, 'Konten'],
        };
    }

    private function consecutiveSentences(): ?array
    {
        $exceptions = IndonesianLexicon::firstWordExceptions();
        $longest = 0;
        $run = 0;
        $prev = null;
        foreach ($this->doc->sentences as $s) {
            if ($this->doc->blocks[$s['block']]['type'] === 'heading') {
                $prev = null;
                $run = 0;

                continue;
            }
            $w = $s['words'];
            $key = isset($exceptions[$w[0]]) && isset($w[1]) ? $w[0].' '.$w[1] : $w[0];
            $run = $key === $prev ? $run + 1 : 1;
            $prev = $key;
            $longest = max($longest, $run);
        }
        if ($longest === 0) {
            return null;
        }

        return $longest > $this->p['max_run']
            ? [3, "Teks berisi {$longest} kalimat berurutan yang diawali kata yang sama. Coba variasikan!", 'Konten']
            : [9, 'Tidak ada awal kalimat yang berulang. Bagus!', 'Konten'];
    }

    private function passiveVoice(): ?array
    {
        $sentences = $this->doc->sentences;
        if (! $sentences) {
            return null;
        }
        $passive = count(array_filter($sentences, fn ($s) => IndonesianLexicon::sentenceIsPassive($s['words'])));
        $pct = round($passive / count($sentences) * 100, 1);
        $txt = "{$this->n($pct)}% kalimat memakai bentuk pasif, lebih dari batas yang disarankan ({$this->n($this->p['good_max'])}%). Ubah ke bentuk aktif.";

        return match (true) {
            $pct <= $this->p['good_max'] => [9, 'Penggunaan kalimat pasif tidak berlebihan. Bagus!', 'Konten'],
            $pct <= $this->p['ok_max'] => [6, $txt, 'Konten'],
            default => [3, $txt, 'Konten'],
        };
    }

    private function transitionWords(): ?array
    {
        $sentences = array_values(array_filter($this->doc->sentences, fn ($s) => $this->doc->blocks[$s['block']]['type'] !== 'heading'));
        if (! $sentences) {
            return null;
        }
        $singles = [];
        $phrases = [];
        foreach (IndonesianLexicon::transitionWords() as $t) {
            if (str_contains($t, ' ')) {
                $phrases[] = $t;
            } else {
                $singles[$t] = true;
            }
        }
        $with = 0;
        foreach ($sentences as $s) {
            $line = ' '.implode(' ', $s['words']).' ';
            $hit = (bool) array_filter($s['words'], fn ($w) => isset($singles[$w]));
            foreach ($hit ? [] : $phrases as $ph) {
                if (str_contains($line, ' '.$ph.' ')) {
                    $hit = true;
                    break;
                }
            }
            foreach ($hit ? [] : IndonesianLexicon::twoPartTransitions() as [$a, $b]) {
                $i = strpos($line, ' '.$a.' ');
                if ($i !== false && strpos($line, ' '.$b.' ', $i + strlen($a) + 1) !== false) {
                    $hit = true;
                    break;
                }
            }
            $with += $hit ? 1 : 0;
        }
        if ($this->doc->wordCount < $this->p['min_words']) {
            return $with > 0
                ? [9, 'Penggunaan kata transisi sudah bagus!', 'Konten']
                : [9, 'Belum ada kata transisi, tetapi teks cukup pendek sehingga mungkin tidak memerlukannya.', 'Konten'];
        }
        $pct = round($with / count($sentences) * 100, 1);

        return match (true) {
            $with === 0 => [3, 'Belum ada kalimat yang memakai kata transisi. Gunakan kata transisi.', 'Konten'],
            $pct >= $this->p['good_min'] => [9, 'Penggunaan kata transisi sudah bagus!', 'Konten'],
            $pct >= $this->p['ok_min'] => [6, "Hanya {$this->n($pct)}% kalimat yang memakai kata transisi. Itu belum cukup. Tambah kata transisi.", 'Konten'],
            default => [3, "Hanya {$this->n($pct)}% kalimat yang memakai kata transisi. Itu belum cukup. Tambah kata transisi.", 'Konten'],
        };
    }

    private function textPresence(): ?array
    {
        return $this->doc->charCount < $this->p['min_chars']
            ? [3, 'Konten belum cukup. Tambahkan konten agar analisis dapat berjalan dengan baik.', 'Konten']
            : null;
    }

    private function lists(): array
    {
        return $this->doc->listCount > 0
            ? [9, 'Sudah ada minimal satu daftar di halaman ini. Bagus!', 'Konten']
            : [3, 'Belum ada daftar di halaman ini. Tambahkan minimal satu daftar berpoin atau bernomor.', 'Konten'];
    }

    private function alignment(): ?array
    {
        if ($this->doc->charCount <= $this->p['min_chars']) {
            return null;
        }
        $n = count(array_filter($this->doc->blocks, fn ($b) => $b['center'] && mb_strlen($b['text']) > $this->p['min_chars']));

        return match (true) {
            $n === 0 => null,
            $n === 1 => [2, 'Ada satu bagian teks panjang yang rata tengah. Sebaiknya rata kiri.', 'Konten'],
            default => [2, "Ada {$n} bagian teks panjang yang rata tengah. Sebaiknya rata kiri.", 'Konten'],
        };
    }

    /* ================= Utilitas ================= */

    private function firstParagraphIndex(): ?int
    {
        $first = $this->doc->firstParagraph();
        if ($first === null) {
            return null;
        }
        foreach ($this->doc->blocks as $i => $b) {
            if ($b === $first) {
                return $i;
            }
        }

        return null;
    }

    /**
     * Kemunculan keyphrase + sinonim dalam satu kalimat. Kata yang sudah cocok dengan satu frasa tidak dicocokkan lagi
     * oleh frasa berikutnya (keyphrase "desain logo" + sinonim "logo" pada "desain logo modern" = 1, bukan 2).
     *
     * @param  Topic[]  $topics
     */
    private static function topicOccurrences(array $words, array $topics): int
    {
        $total = 0;
        foreach ($topics as $t) {
            $c = $t->countIn($words);
            if ($c <= 0) {
                continue;
            }
            $total += $c;
            $remove = $t->exact ? $t->words : $t->contentWords;
            foreach ($remove as $w) {
                for ($k = 0; $k < $c; $k++) {
                    $pos = array_search($w, $words, true);
                    if ($pos !== false) {
                        unset($words[$pos]);
                    }
                }
            }
            $words = array_values($words);
        }

        return $total;
    }

    /** Posisi awal urutan kata $needle di $haystack, atau null. */
    private static function indexOf(array $haystack, array $needle): ?int
    {
        $n = count($needle);
        if ($n === 0) {
            return null;
        }
        for ($i = 0; $i + $n <= count($haystack); $i++) {
            if (array_slice($haystack, $i, $n) === $needle) {
                return $i;
            }
        }

        return null;
    }

    private static function host(string $url): ?string
    {
        $h = parse_url(trim($url), PHP_URL_HOST);
        if (! is_string($h) || $h === '') {
            return null;
        }

        return preg_replace('/^www\./', '', strtolower($h));
    }

    private static function sameUrl(string $a, string $b): bool
    {
        $norm = fn (string $u) => rtrim(preg_replace('/^https?:\/\/(www\.)?/i', '', strtolower(strtok($u, '#') ?: $u)) ?? $u, '/');

        return $norm($a) === $norm($b);
    }

    /** Perkiraan lebar teks di hasil Google (Arial 20px), dalam piksel. */
    public static function pixelWidth(string $text): int
    {
        static $w = null;
        $w ??= self::arialWidths();
        $total = 0;
        foreach (mb_str_split($text) as $ch) {
            $total += $w[$ch] ?? (preg_match('/\p{Lu}/u', $ch) ? 667 : 556);
        }

        return (int) round($total / 1000 * 20);
    }

    /** Lebar glyph Arial dalam satuan 1/1000 em. */
    private static function arialWidths(): array
    {
        $w = [' ' => 278, '!' => 278, '"' => 355, '#' => 556, '$' => 556, '%' => 889, '&' => 667, "'" => 191, '(' => 333, ')' => 333,
            '*' => 389, '+' => 584, ',' => 278, '-' => 333, '.' => 278, '/' => 278, ':' => 278, ';' => 278, '<' => 584, '=' => 584,
            '>' => 584, '?' => 556, '@' => 1015, '[' => 278, '\\' => 278, ']' => 278, '^' => 469, '_' => 556, '`' => 333, '{' => 334,
            '|' => 260, '}' => 334, '~' => 584, '–' => 556, '—' => 1000, '’' => 222, '‘' => 222, '“' => 333, '”' => 333, '…' => 1000, '·' => 278];
        foreach (str_split('0123456789') as $d) {
            $w[$d] = 556;
        }
        $lower = ['a' => 556, 'b' => 556, 'c' => 500, 'd' => 556, 'e' => 556, 'f' => 278, 'g' => 556, 'h' => 556, 'i' => 222, 'j' => 222,
            'k' => 500, 'l' => 222, 'm' => 833, 'n' => 556, 'o' => 556, 'p' => 556, 'q' => 556, 'r' => 333, 's' => 500, 't' => 278,
            'u' => 556, 'v' => 500, 'w' => 722, 'x' => 500, 'y' => 500, 'z' => 500];
        $upper = ['A' => 667, 'B' => 667, 'C' => 722, 'D' => 722, 'E' => 667, 'F' => 611, 'G' => 778, 'H' => 722, 'I' => 278, 'J' => 500,
            'K' => 667, 'L' => 556, 'M' => 833, 'N' => 722, 'O' => 778, 'P' => 667, 'Q' => 778, 'R' => 722, 'S' => 667, 'T' => 611,
            'U' => 722, 'V' => 667, 'W' => 944, 'X' => 667, 'Y' => 667, 'Z' => 611];

        return $w + $lower + $upper;
    }

    /** Angka rapi: 0.5 → "0,5", 4.0 → "4". */
    private function n(float|int $v): string
    {
        return rtrim(rtrim(number_format((float) $v, 1, ',', '.'), '0'), ',');
    }
}
