<?php

namespace App\Services\Seo;

/**
 * Keyphrase atau sinonim (docs/seo.md 3.1): pencocokan per kata di dalam satu kalimat dengan kata fungsi
 * dibuang, atau exact match bila diapit tanda kutip ganda.
 */
class Topic
{
    public string $raw;

    public bool $exact;

    /** @var string[] semua kata frasa (huruf kecil) */
    public array $words;

    /** @var string[] kata konten unik; bila semuanya kata fungsi, berisi semua kata */
    public array $contentWords;

    public bool $onlyFunctionWords;

    /** @param array<string, true> $functionWords */
    public function __construct(string $raw, array $functionWords)
    {
        $raw = trim($raw);
        $this->exact = (bool) preg_match('/^["“”].+["“”]$/u', $raw);
        $this->raw = $this->exact ? trim(mb_substr($raw, 1, -1)) : $raw;
        $this->words = TextDocument::words($this->raw);
        $content = array_values(array_unique(array_filter($this->words, fn ($w) => ! isset($functionWords[$w]))));
        $this->onlyFunctionWords = $this->words !== [] && $content === [];
        $this->contentWords = $this->exact ? array_values(array_unique($this->words)) : ($content ?: array_values(array_unique($this->words)));
    }

    /** @param array<string, true> $functionWords @return self[] */
    public static function list(string $keyphrase, ?string $synonyms, array $functionWords): array
    {
        $out = [new self($keyphrase, $functionWords)];
        foreach (preg_split('/[,\n]/u', (string) $synonyms) ?: [] as $s) {
            if (trim($s) !== '') {
                $out[] = new self($s, $functionWords);
            }
        }

        return array_values(array_filter($out, fn (self $t) => $t->words !== []));
    }

    public function isEmpty(): bool
    {
        return $this->words === [];
    }

    public function phrase(): string
    {
        return implode(' ', $this->words);
    }

    /** Jumlah kecocokan di satu kalimat (kemunculan berulang semua kata konten = kecocokan berulang). */
    public function countIn(array $words): int
    {
        if (! $this->words) {
            return 0;
        }
        if ($this->exact) {
            return substr_count(' '.implode(' ', $words).' ', ' '.$this->phrase().' ');
        }
        $counts = array_count_values($words);
        $min = PHP_INT_MAX;
        foreach ($this->contentWords as $w) {
            $min = min($min, $counts[$w] ?? 0);
        }

        return $min === PHP_INT_MAX ? 0 : $min;
    }

    public function matches(array $words): bool
    {
        return $this->countIn($words) > 0;
    }

    /** Persentase kata konten yang muncul (dipakai untuk subjudul dan slug). */
    public function percentIn(array $words): float
    {
        if (! $this->contentWords) {
            return 0;
        }
        if ($this->exact) {
            return $this->matches($words) ? 100 : 0;
        }
        $set = array_flip($words);
        $hit = count(array_filter($this->contentWords, fn ($w) => isset($set[$w])));

        return round($hit / count($this->contentWords) * 100);
    }
}
