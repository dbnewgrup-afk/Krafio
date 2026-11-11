<?php

namespace App\Services;

use App\Models\Faq;

/**
 * Jawaban tanpa AI untuk AI Assistant saat provider teks belum aktif: mencocokkan pertanyaan
 * pengguna dengan FAQ aktif memakai skor kemiripan token (Jaccard berbobot IDF sederhana).
 */
class FaqResponder
{
    private const STOP = ['yang', 'dan', 'di', 'ke', 'dari', 'untuk', 'dengan', 'apa', 'apakah', 'bagaimana', 'gimana', 'cara', 'saya', 'aku', 'gw', 'kak', 'min', 'bisa', 'tidak', 'ga', 'gak', 'nggak', 'ini', 'itu', 'ada', 'kalau', 'kalo', 'mau', 'ya', 'yg', 'dong', 'sih', 'kah', 'the', 'a', 'an', 'to', 'how', 'do', 'i', 'is', 'can', 'my', 'of', 'what', 'krafio'];

    /** Sinonim sederhana agar bahasa santai tetap cocok dengan FAQ. */
    private const SYN = ['topup' => 'top', 'isi' => 'top', 'saldo' => 'saldo', 'duit' => 'saldo', 'bayar' => 'pembayaran', 'langganan' => 'paket', 'plan' => 'paket', 'withdraw' => 'penarikan', 'tarik' => 'penarikan', 'wd' => 'penarikan', 'refund' => 'pengembalian', 'akun' => 'akun', 'password' => 'sandi', 'kredit' => 'kredit', 'token' => 'kredit'];

    /** @return array{answer:string, question:string, score:float}|null */
    public static function answer(string $message, string $lang = 'id'): ?array
    {
        $q = self::tokens($message);
        if (! $q) {
            return null;
        }
        $faqs = Faq::where('is_active', true)->get();
        if ($faqs->isEmpty()) {
            return null;
        }
        $docs = $faqs->map(fn ($f) => self::tokens($f->question_id.' '.$f->question_en.' '.$f->answer_id));
        $n = $docs->count();
        $df = [];
        foreach ($docs as $d) {
            foreach (array_unique($d) as $t) {
                $df[$t] = ($df[$t] ?? 0) + 1;
            }
        }
        $idf = fn ($t) => log(1 + $n / ($df[$t] ?? 1));
        $best = null;
        foreach ($faqs->values() as $i => $f) {
            $doc = array_unique($docs[$i]);
            $qTok = array_unique($q);
            $common = array_intersect($qTok, $doc);
            if (! $common) {
                continue;
            }
            $num = array_sum(array_map($idf, $common));
            $den = array_sum(array_map($idf, array_unique(array_merge($qTok, array_intersect($doc, self::tokens($f->question_id.' '.$f->question_en))))));
            $score = $den > 0 ? $num / $den : 0;
            if (! $best || $score > $best['score']) {
                $useEn = $lang === 'en' && $f->answer_en;
                $best = ['answer' => $useEn ? $f->answer_en : $f->answer_id, 'question' => $useEn && $f->question_en ? $f->question_en : $f->question_id, 'score' => round($score, 3)];
            }
        }

        return $best && $best['score'] >= 0.2 ? $best : null;
    }

    /** @return string[] */
    public static function tokens(string $s): array
    {
        $s = mb_strtolower($s);
        $s = str_replace(['top up', 'top-up'], 'topup', $s);
        $words = preg_split('/[^\p{L}\p{N}]+/u', $s, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $out = [];
        foreach ($words as $w) {
            if (mb_strlen($w) < 3 && ! is_numeric($w)) {
                continue;
            }
            if (in_array($w, self::STOP, true)) {
                continue;
            }
            $w = self::SYN[$w] ?? $w;
            // Stemming ringan bahasa Indonesia: buang akhiran umum
            foreach (['nya', 'kan', 'lah', 'an', 'i'] as $suf) {
                if (mb_strlen($w) > 5 && str_ends_with($w, $suf)) {
                    $w = mb_substr($w, 0, -mb_strlen($suf));
                    break;
                }
            }
            $out[] = $w;
        }

        return $out;
    }
}
