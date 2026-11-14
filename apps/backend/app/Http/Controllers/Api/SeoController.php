<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SeoAudit;
use App\Models\SeoFinding;
use App\Services\Entitlements;
use App\Services\Seo\ContentAnalyzer;
use App\Services\Seo\ContentRules;
use App\Services\Seo\PageImporter;
use App\Services\Seo\TextDocument;
use App\Services\Settings;
use App\Support\ApiException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Analisis konten SEO bergaya Yoast (docs/seo.md): pengguna mengisi form (keyphrase, judul SEO, meta description,
 * slug, konten) lalu menekan satu tombol. Rule & setup diatur Admin (SEO rules + SEO › Analisis konten).
 */
class SeoController extends Controller
{
    /** Konfigurasi form untuk pengguna (jenis konten, impor URL, daftar cek aktif). */
    public function config(Request $r)
    {
        $advanced = $this->hasAdvanced($r);
        $rules = collect(ContentRules::effective())
            ->map(fn ($x, $code) => ['code' => $code, 'name' => $x['name'], 'category' => $x['category'], 'tier' => $x['tier'], 'types' => $x['types'], 'available' => $x['tier'] !== 'advanced' || $advanced])
            ->values();

        return ['data' => [
            'enabled' => (bool) Settings::get('seo.content.enabled', true),
            'content_types' => $this->types(),
            'default_type' => in_array(Settings::get('seo.content.default_type'), $this->types(), true) ? Settings::get('seo.content.default_type') : $this->types()[0],
            'url_import' => (bool) Settings::get('seo.content.url_import', true),
            'max_chars' => $this->maxChars(),
            'entitlement' => $this->entitlement(),
            'cost' => $this->cost(),
            'advanced' => $advanced,
            'ruleset_version' => (string) Settings::get('seo.ruleset_version'),
            'rules' => $rules,
        ]];
    }

    /** "Ambil dari URL": isi otomatis judul, meta description, slug, dan konten dari halaman publik. */
    public function import(Request $r)
    {
        $this->assertEnabled();
        if (! Settings::get('seo.content.url_import', true)) {
            throw new ApiException('url_import_disabled', 'Ambil konten dari URL sedang dinonaktifkan Admin.', 403);
        }
        $data = $r->validate(['url' => 'required|url|max:500']);
        Entitlements::ensure($r->user(), $this->entitlement(), 0);
        $page = PageImporter::import($data['url']);
        $page['content'] = mb_substr($page['content'], 0, $this->maxChars());

        return ['data' => $page + ['url' => $data['url']]];
    }

    public function analyze(Request $r)
    {
        $this->assertEnabled();
        $u = $r->user();
        $data = $r->validate([
            'content_type' => ['required', Rule::in($this->types())],
            'keyphrase' => 'required|string|max:191',
            'synonyms' => 'nullable|string|max:500',
            'seo_title' => 'nullable|string|max:300',
            'meta_description' => 'nullable|string|max:1000',
            'slug' => 'nullable|string|max:300',
            'url' => 'nullable|url|max:500',
            'content' => 'nullable|string|max:'.$this->maxChars(),
        ], [], [
            'content_type' => 'jenis konten', 'keyphrase' => 'focus keyphrase', 'synonyms' => 'sinonim', 'seo_title' => 'judul SEO',
            'meta_description' => 'meta description', 'slug' => 'slug', 'url' => 'URL', 'content' => 'konten',
        ]);
        if (trim(strip_tags((string) ($data['content'] ?? ''))) === '') {
            throw ValidationException::withMessages(['content' => 'Isi konten, atau pakai "Ambil dari URL" terlebih dahulu.']);
        }
        if (TextDocument::words((string) $data['keyphrase']) === []) {
            throw ValidationException::withMessages(['keyphrase' => 'Focus keyphrase harus memuat huruf atau angka.']);
        }

        $ent = $this->entitlement();
        $cost = $this->cost();
        Entitlements::ensure($u, $ent, $cost);

        $input = [
            'content_type' => $data['content_type'], 'keyphrase' => trim($data['keyphrase']), 'synonyms' => $data['synonyms'] ?? null,
            'seo_title' => $data['seo_title'] ?? null, 'meta_description' => $data['meta_description'] ?? null,
            'slug' => $data['slug'] ?? null, 'url' => $data['url'] ?? null, 'content' => $data['content'],
        ];
        $keyphrase = self::normalizeKeyphrase($input['keyphrase']);
        $target = self::target($input);
        // Keyphrase yang sama di halaman lain milik pengguna (docs/seo.md 4.3)
        $previous = SeoAudit::where('user_id', $u->id)->where('kind', 'content')->where('keyphrase', $keyphrase)
            ->where('target', '!=', $target)->distinct()->count('target');

        $res = ContentAnalyzer::run($input, $previous, $this->hasAdvanced($r), (bool) Settings::get('seo.content.readability_full_support', false));

        // Kuota dipotong di transaksi yang sama: bila kuota habis karena permintaan bersamaan, audit tidak tersimpan
        $audit = DB::transaction(function () use ($u, $input, $keyphrase, $target, $res, $ent, $cost) {
            Entitlements::consume($u, $ent, $cost);
            $a = SeoAudit::create([
                'user_id' => $u->id, 'kind' => 'content', 'url' => $input['url'], 'title' => $input['seo_title'] ? mb_substr($input['seo_title'], 0, 300) : null,
                'keyphrase' => $keyphrase, 'target' => $target, 'content_type' => $input['content_type'],
                'score' => $res['seo_score'], 'readability_score' => $res['readability_score'], 'input' => $input, 'stats' => $res['stats'],
                'ruleset_version' => (string) Settings::get('seo.ruleset_version'), 'status' => 'completed',
            ]);
            foreach ($res['results'] as $x) {
                SeoFinding::create([
                    'audit_id' => $a->id, 'rule' => $x['code'], 'category' => $x['category'], 'title' => mb_substr($x['title'], 0, 120),
                    'severity' => $x['severity'], 'rating' => $x['rating'], 'score' => $x['score'], 'location' => $x['location'],
                    'message' => $x['message'], 'recommendation' => $x['recommendation'], 'state' => $x['rating'] === 'good' ? 'resolved' : 'open',
                ]);
            }

            return $a;
        });

        return response()->json(['data' => self::present($audit->load(['findings' => fn ($q) => $q->orderBy('id')]), true)], 201);
    }

    /** Detail satu audit, termasuk konten lengkap untuk "Ubah & analisis ulang". */
    public function show(Request $r, SeoAudit $audit)
    {
        abort_unless($audit->user_id === $r->user()->id, 404);

        return ['data' => self::present($audit->load(['findings' => fn ($q) => $q->orderBy('id')]), true)];
    }

    public static function present(SeoAudit $a, bool $full = false): array
    {
        $kind = $a->kind ?: 'url';
        $input = $a->input;
        if (is_array($input) && ! $full) {
            unset($input['content']);
        }

        return [
            'id' => $a->id, 'kind' => $kind, 'url' => $a->url, 'title' => $a->title, 'keyphrase' => $a->keyphrase, 'content_type' => $a->content_type,
            'score' => (int) $a->score, 'readability_score' => $a->readability_score,
            'score_rating' => $kind === 'content' ? ContentAnalyzer::overallRating((int) $a->score, 'seo') : null,
            'readability_rating' => $kind === 'content' ? ContentAnalyzer::overallRating((int) $a->readability_score, 'readability') : null,
            'ruleset_version' => $a->ruleset_version, 'status' => $a->status, 'created_at' => $a->created_at->toIso8601String(),
            'input' => $kind === 'content' ? $input : null, 'stats' => $kind === 'content' ? $a->stats : null,
            'findings' => $a->findings->map(fn ($f) => [
                'id' => $f->id, 'rule' => $f->rule, 'category' => $f->category, 'title' => $f->title, 'severity' => $f->severity, 'rating' => $f->rating,
                'score' => $f->score, 'location' => $f->location, 'message' => $f->message, 'recommendation' => $f->recommendation, 'state' => $f->state,
            ])->values(),
        ];
    }

    public static function normalizeKeyphrase(string $k): string
    {
        return mb_substr(mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', (string) preg_replace('/["“”]/u', '', $k)))), 0, 191);
    }

    /** Identitas halaman: slug, atau URL, atau judul SEO. */
    private static function target(array $in): string
    {
        $slug = trim((string) ($in['slug'] ?? ''), " /\t");
        $url = trim((string) ($in['url'] ?? ''));
        $t = $slug !== '' ? 'slug:'.mb_strtolower($slug)
            : ($url !== '' ? 'url:'.rtrim(mb_strtolower((string) preg_replace('#^https?://(www\.)?#i', '', $url)), '/')
                : 'title:'.mb_strtolower(trim((string) ($in['seo_title'] ?? ''))));

        return mb_strlen($t) > 191 ? 'hash:'.sha1($t) : $t;
    }

    private function assertEnabled(): void
    {
        if (! Settings::get('seo.content.enabled', true)) {
            throw new ApiException('feature_disabled', 'Analisis konten SEO sedang dinonaktifkan Admin.', 403);
        }
    }

    /** @return string[] */
    private function types(): array
    {
        $t = array_values(array_intersect(ContentRules::TYPES, (array) Settings::get('seo.content.content_types', ContentRules::TYPES)));

        return $t ?: ['post'];
    }

    private function entitlement(): string
    {
        $e = (string) Settings::get('seo.content.entitlement', 'seo.basic');

        return in_array($e, ['seo.basic', 'seo.advanced'], true) ? $e : 'seo.basic';
    }

    private function cost(): int
    {
        return max(0, min(100, (int) Settings::get('seo.content.cost', 1)));
    }

    private function maxChars(): int
    {
        return max(1000, min(500000, (int) Settings::get('seo.content.max_chars', 100000)));
    }

    private function hasAdvanced(Request $r): bool
    {
        return (bool) (Entitlements::forUser($r->user())['seo.advanced']['enabled'] ?? false);
    }
}
