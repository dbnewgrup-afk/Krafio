<?php

namespace App\Services\Seo;

use Illuminate\Support\Str;

/**
 * Representasi konten untuk analisis: blok (heading/paragraf/daftar), kalimat, kata, gambar, dan tautan.
 * Menerima HTML, atau teks biasa/Markdown (judul "## ", daftar "- ", gambar ![alt](src), tautan [teks](url)).
 * Tanda hubung tidak memisahkan kata (aturan Yoast untuk bahasa Indonesia).
 */
class TextDocument
{
    /** @var array<int, array{type: string, level: int, text: string, center: bool}> */
    public array $blocks = [];

    /** @var array<int, array{text: string, words: string[], block: int}> */
    public array $sentences = [];

    /** @var array<int, array{src: string, alt: ?string}> */
    public array $images = [];

    /** @var array<int, array{href: string, rel: string, text: string}> */
    public array $links = [];

    public int $wordCount = 0;

    public int $charCount = 0;

    public int $listCount = 0;

    private const BLOCK_TAGS = ['p', 'li', 'blockquote', 'figcaption', 'dd', 'dt', 'pre', 'td', 'th', 'caption'];

    private const SKIP_TAGS = ['script', 'style', 'noscript', 'template', 'svg', 'iframe', 'object', 'canvas', 'form', 'button', 'select', 'textarea', 'head', 'title', 'meta', 'link'];

    private const INLINE_TAGS = ['a', 'strong', 'em', 'b', 'i', 'u', 'span', 'small', 'mark', 'code', 'sup', 'sub', 'abbr', 'cite', 'q', 'time', 'label', 's', 'del', 'ins', 'font', 'big', 'kbd', 'var', 'bdi', 'bdo', 'data'];

    private const ABBREVIATIONS = ['dr', 'drs', 'dra', 'prof', 'ir', 'h', 'hj', 'st', 'no', 'rp', 'jl', 'jln', 'tel', 'telp', 'bpk', 'yth', 'sdr', 'kec', 'kab', 'ket', 'mr', 'mrs', 'ms', 'vs', 'etc', 'e.g', 'i.e'];

    public static function fromInput(string $content): self
    {
        $content = trim(str_replace(["\r\n", "\r"], "\n", $content));
        $isHtml = (bool) preg_match('/<\s*(p|h[1-6]|div|ul|ol|li|img|a|br|article|section|main|strong|em|span|table|blockquote|figure)\b/i', $content);
        if (! $isHtml) {
            // Teks tempelan biasanya memisah paragraf dengan satu baris baru → jadikan setiap baris paragraf sendiri
            $content = preg_replace('/(?<!\n)\n(?!\n)/', "\n\n", $content) ?? $content;
        }
        $html = $isHtml ? $content : (string) Str::markdown($content, ['html_input' => 'escape', 'allow_unsafe_links' => false]);
        if ($isHtml && preg_match('/<\s*(html|body|head)\b/i', $html)) {
            $html = PageImporter::parse($html, '')['content']; // dokumen utuh ditempel → hanya isi utama, tanpa menu/footer
        }

        return self::fromHtml($html);
    }

    public static function fromHtml(string $html): self
    {
        $doc = new self;
        $dom = new \DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?><div id="krafio-root">'.$html.'</div>', LIBXML_NONET);
        libxml_clear_errors();
        $root = (new \DOMXPath($dom))->query('//div[@id="krafio-root"]')->item(0);
        if ($root) {
            $doc->walk($root);
        }
        $doc->finish();

        return $doc;
    }

    /** Teks normal (huruf kecil) → kata. */
    public static function words(string $text): array
    {
        preg_match_all('/[\p{L}\p{N}]+(?:(?:[\'’-]|(?<=\p{N})[.,](?=\p{N}))[\p{L}\p{N}]+)*/u', mb_strtolower($text), $m);

        return $m[0];
    }

    /** @return string[] */
    public static function splitSentences(string $text): array
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
        if ($text === '') {
            return [];
        }
        $parts = preg_split('/(?<=[.!?…])["”’)]*\s+(?=["“(]?\p{Lu})/u', $text) ?: [$text];
        $out = [];
        foreach ($parts as $p) {
            $prev = $out ? $out[count($out) - 1] : null;
            if ($prev !== null && preg_match('/(?:^|\s)([\p{L}.]+)\.$/u', $prev, $m) && in_array(mb_strtolower($m[1]), self::ABBREVIATIONS, true)) {
                $out[count($out) - 1] = $prev.' '.$p; // singkatan seperti "Dr." bukan akhir kalimat
            } else {
                $out[] = $p;
            }
        }

        return array_values(array_filter(array_map('trim', $out), fn ($s) => $s !== ''));
    }

    /** Blok paragraf (untuk cek panjang paragraf & paragraf pembuka). */
    public function paragraphs(): array
    {
        return array_values(array_filter($this->blocks, fn ($b) => $b['type'] === 'paragraph'));
    }

    /** @return array<int, array{type: string, level: int, text: string, center: bool}> */
    public function headings(int ...$levels): array
    {
        return array_values(array_filter($this->blocks, fn ($b) => $b['type'] === 'heading' && (! $levels || in_array($b['level'], $levels, true))));
    }

    public function firstParagraph(): ?array
    {
        foreach ($this->blocks as $b) {
            if ($b['type'] === 'paragraph') {
                return $b;
            }
        }
        foreach ($this->blocks as $b) {
            if ($b['type'] !== 'heading') {
                return $b;
            }
        }

        return null;
    }

    /** @return array<int, array{text: string, words: string[], block: int}> */
    public function sentencesOfBlock(int $index): array
    {
        return array_values(array_filter($this->sentences, fn ($s) => $s['block'] === $index));
    }

    private function walk(\DOMNode $node, bool $inList = false): void
    {
        $buffer = '';
        foreach ($node->childNodes as $child) {
            if ($child instanceof \DOMText) {
                $buffer .= $child->nodeValue;

                continue;
            }
            if (! $child instanceof \DOMElement) {
                continue;
            }
            $tag = strtolower($child->tagName);
            if (in_array($tag, self::SKIP_TAGS, true)) {
                continue;
            }
            $this->collectMedia($child);
            if (in_array($tag, self::INLINE_TAGS, true)) {
                $buffer .= self::text($child); // elemen sebaris tetap bagian paragraf

                continue;
            }
            if ($tag === 'br') {
                $buffer .= "\n";

                continue;
            }
            $this->flush($buffer, $node, $inList);
            $buffer = '';
            if (preg_match('/^h([1-6])$/', $tag, $m)) {
                $this->addBlock('heading', (int) $m[1], self::text($child), $this->isCentered($child));
            } elseif (in_array($tag, ['ul', 'ol'], true)) {
                $this->listCount++;
                $this->walk($child, true);
            } elseif (in_array($tag, self::BLOCK_TAGS, true)) {
                $itemOfList = $inList || $tag === 'li';
                if ($this->hasBlockChildren($child)) {
                    $this->walk($child, $itemOfList);
                } else {
                    $this->addBlock($itemOfList ? 'list_item' : 'paragraph', 0, self::text($child), $this->isCentered($child));
                }
            } elseif ($tag !== 'img') {
                $this->walk($child, $inList);
            }
        }
        $this->flush($buffer, $node, $inList);
    }

    /** Isi teks elemen: <br> dan batas elemen blok menjadi spasi agar kata tidak menempel. */
    private static function text(\DOMNode $node): string
    {
        $out = '';
        foreach ($node->childNodes as $c) {
            if ($c instanceof \DOMText) {
                $out .= $c->nodeValue;
            } elseif ($c instanceof \DOMElement) {
                $tag = strtolower($c->tagName);
                if (in_array($tag, self::SKIP_TAGS, true)) {
                    continue;
                }
                if ($tag === 'br') {
                    $out .= ' ';

                    continue;
                }
                $inner = self::text($c);
                $out .= in_array($tag, self::INLINE_TAGS, true) ? $inner : ' '.$inner.' ';
            }
        }

        return $out;
    }

    /** Teks lepas di antara elemen blok (mis. teks langsung di dalam <div>) → paragraf; baris kosong ganda memisahkan paragraf. */
    private function flush(string $buffer, \DOMNode $parent, bool $inList = false): void
    {
        if (trim($buffer) === '') {
            return;
        }
        $centered = $parent instanceof \DOMElement && $this->isCentered($parent);
        $type = $inList || ($parent instanceof \DOMElement && strtolower($parent->tagName) === 'li') ? 'list_item' : 'paragraph';
        foreach (preg_split('/\n\s*\n/u', $buffer) ?: [] as $chunk) {
            $this->addBlock($type, 0, $chunk, $centered);
        }
    }

    private function addBlock(string $type, int $level, string $text, bool $center): void
    {
        $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
        if ($text === '') {
            return;
        }
        $this->blocks[] = ['type' => $type, 'level' => $level, 'text' => $text, 'center' => $center];
    }

    private function hasBlockChildren(\DOMElement $el): bool
    {
        foreach ($el->childNodes as $c) {
            if ($c instanceof \DOMElement && (in_array(strtolower($c->tagName), array_merge(self::BLOCK_TAGS, ['ul', 'ol', 'div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'section', 'article', 'figure', 'table']), true))) {
                return true;
            }
        }

        return false;
    }

    private function collectMedia(\DOMElement $el): void
    {
        $tag = strtolower($el->tagName);
        $imgs = $tag === 'img' ? [$el] : iterator_to_array($el->getElementsByTagName('img'));
        $anchors = $tag === 'a' ? [$el] : iterator_to_array($el->getElementsByTagName('a'));
        foreach ($imgs as $img) {
            if (! $this->seen($img)) {
                $this->images[] = ['src' => trim($img->getAttribute('src')), 'alt' => $img->hasAttribute('alt') ? trim($img->getAttribute('alt')) : null];
            }
        }
        foreach ($anchors as $a) {
            if ($a->hasAttribute('href') && ! $this->seen($a)) {
                $this->links[] = ['href' => trim($a->getAttribute('href')), 'rel' => strtolower($a->getAttribute('rel')), 'text' => trim(preg_replace('/\s+/u', ' ', self::text($a)) ?? '')];
            }
        }
    }

    /** Hindari menghitung gambar/tautan dua kali karena elemen induk & anak sama-sama dikunjungi. */
    private function seen(\DOMElement $el): bool
    {
        if ($el->hasAttribute('data-krafio-seen')) {
            return true;
        }
        $el->setAttribute('data-krafio-seen', '1');

        return false;
    }

    private function isCentered(\DOMElement $el): bool
    {
        $class = ' '.strtolower($el->getAttribute('class')).' ';
        $style = strtolower(str_replace(' ', '', $el->getAttribute('style')));

        return str_contains($class, ' has-text-align-center ') || str_contains($class, ' text-center ')
            || str_contains($style, 'text-align:center') || strtolower($el->getAttribute('align')) === 'center';
    }

    private function finish(): void
    {
        foreach ($this->blocks as $i => $b) {
            $sentences = $b['type'] === 'heading' ? [$b['text']] : self::splitSentences($b['text']);
            foreach ($sentences as $s) {
                $words = self::words($s);
                if ($words) {
                    $this->sentences[] = ['text' => $s, 'words' => $words, 'block' => $i];
                }
            }
            $this->wordCount += count(self::words($b['text']));
            $this->charCount += mb_strlen($b['text']);
        }
    }
}
