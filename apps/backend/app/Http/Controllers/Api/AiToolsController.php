<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\PullLocalModel;
use App\Models\AiJob;
use App\Models\AiModel;
use App\Models\AiPrompt;
use App\Models\AiProvider;
use App\Services\AiGateway;
use App\Services\Audit;
use App\Services\Entitlements;
use App\Services\Settings;
use App\Support\AiTools;
use App\Support\ApiException;
use App\Support\ThirdPartyRepos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * AI Tools: skema form untuk panel user + kontrol penuh dari Admin (aktif/nonaktif, label, biaya, batas part,
 * nilai default, field disembunyikan, prompt) tanpa deploy.
 */
class AiToolsController extends Controller
{
    /** Panel user: daftar tool + skema form + status entitlement. */
    public function index(Request $r)
    {
        $u = $r->user();
        app()->setLocale($u->locale === 'en' ? 'en' : 'id');
        $ents = Entitlements::forUser($u);

        return ['data' => array_values(array_filter(array_map(function ($key) use ($ents) {
            $t = AiTools::get($key);
            if (! $t['enabled']) {
                return null;
            }

            return AiTools::present($t) + ['entitlement_status' => $ents[$t['entitlement']] ?? null];
        }, array_keys(AiTools::definitions()))))];
    }

    public function show(Request $r, string $key)
    {
        $t = AiTools::get($key);
        abort_unless($t && $t['enabled'], 404);
        app()->setLocale($r->user()->locale === 'en' ? 'en' : 'id');

        return ['data' => AiTools::present($t) + ['entitlement_status' => Entitlements::forUser($r->user())[$t['entitlement']] ?? null]];
    }

    /* ---------------- Admin ---------------- */

    public function adminIndex()
    {
        app()->setLocale('id');
        $usage = AiJob::where('created_at', '>=', now()->startOfMonth())->selectRaw('type, count(*) c, sum(status = "completed") ok, sum(status = "failed") failed')->groupBy('type')->get()->keyBy('type');
        $prompts = AiPrompt::where('key', 'like', 'tool.%')->get()->keyBy('key');

        return ['data' => [
            // Model teks yang bisa dipilih per tool (Admin → AI → Model)
            'text_models' => AiModel::with('provider:id,name,status')->where('capability', 'text')->where('is_active', true)->get()
                ->map(fn ($m) => ['model_id' => $m->model_id, 'name' => $m->name, 'provider' => $m->provider?->name, 'provider_active' => $m->provider?->status === 'active'])->unique('model_id')->values(),
            'providers' => ['text' => AiGateway::configured('text'), 'image' => AiGateway::configured('image'), 'video' => AiGateway::configured('video')],
            'tools' => array_map(function ($key) use ($usage, $prompts) {
                $def = AiTools::definitions()[$key];
                $t = AiTools::get($key);
                $u = $usage[$t['type']] ?? null;

                return AiTools::present($t) + [
                    'override' => (array) (AiTools::overrides()[$key] ?? []),
                    'default_label' => AiTools::tr($def['label']), 'default_description' => AiTools::tr($def['description'] ?? ''),
                    'default_cost_per_unit' => $def['cost_per_unit'] ?? null, 'default_max_parts' => $def['max_parts'] ?? null,
                    'all_fields' => array_map(fn ($f) => ['key' => $f['key'], 'label' => AiTools::tr($f['label']), 'required' => ! empty($f['required']), 'type' => $f['type'], 'default' => $f['default'] ?? null, 'options' => array_map(fn ($o) => ['value' => $o['value'], 'label' => AiTools::tr($o['label'])], $f['options'] ?? [])], $def['fields']),
                    'prompts' => $t['capability'] === 'text' ? [
                        'system' => ['key' => "tool.{$key}.system", 'id' => $prompts["tool.{$key}.system"]->id ?? null, 'status' => $prompts["tool.{$key}.system"]->status ?? null],
                        'instruction' => ['key' => "tool.{$key}.instruction", 'id' => $prompts["tool.{$key}.instruction"]->id ?? null, 'status' => $prompts["tool.{$key}.instruction"]->status ?? null],
                    ] : ['media' => ['key' => ($t['capability'] === 'video' ? 'video.' : 'image.').$t['type']]],
                    'repos' => ThirdPartyRepos::forTool($key),
                    'usage_month' => ['jobs' => (int) ($u->c ?? 0), 'completed' => (int) ($u->ok ?? 0), 'failed' => (int) ($u->failed ?? 0)],
                ];
            }, array_keys(AiTools::definitions())),
        ]];
    }

    public function adminUpdate(Request $r, string $key)
    {
        $def = AiTools::definitions()[$key] ?? throw new ApiException('not_found', 'Tool tidak dikenal.', 404);
        $data = $r->validate([
            'enabled' => 'sometimes|boolean',
            'label' => 'sometimes|nullable|string|max:80',
            'description' => 'sometimes|nullable|string|max:300',
            'cost_per_unit' => 'sometimes|nullable|integer|min:0|max:100',
            'model' => 'sometimes|nullable|string|max:120',
            'max_parts' => 'sometimes|nullable|integer|min:1|max:'.max(1, (int) ($def['max_parts'] ?? 1)),
            'hidden_fields' => 'sometimes|array',
            'hidden_fields.*' => 'string|in:'.implode(',', array_column($def['fields'], 'key')),
            'defaults' => 'sometimes|array',
        ]);
        // Default hanya untuk field yang dikenal; nilainya divalidasi skema yang sama dengan input pengguna
        if (isset($data['defaults'])) {
            $tool = AiTools::get($key);
            $clean = AiTools::sanitize(['fields' => array_map(fn ($f) => ['required' => false] + $f, $tool['fields'])] + $tool, $data['defaults'])['params'];
            $data['defaults'] = array_intersect_key($clean, $data['defaults']);
        }
        $all = AiTools::overrides();
        $cur = (array) ($all[$key] ?? []);
        foreach ($data as $k => $v) {
            if ($v === null || $v === '' || $v === []) {
                unset($cur[$k]);
            } else {
                $cur[$k] = $v;
            }
        }
        $all[$key] = $cur;
        Settings::set('ai_tools.overrides', $all);
        Audit::log('admin.ai_tools.updated', null, ['tool' => $key, 'changes' => array_keys($data)]);

        return $this->adminIndex();
    }

    /* ---------------- Model AI lokal (Ollama / Jan) ---------------- */

    /** Provider teks lokal (base URL di mesin sendiri) + daftar model terpasang. */
    public function localModels()
    {
        $out = [];
        foreach (AiProvider::orderBy('priority')->get()->filter(fn ($p) => AiGateway::isLocal($p) && in_array('text', $p->capabilities ?? [], true)) as $p) {
            $host = preg_replace('#/v1/?$#', '', rtrim((string) $p->base_url, '/'));
            $models = [];
            $runtime = null;
            try {
                $tags = Http::timeout(5)->get($host.'/api/tags');
                if ($tags->successful()) {
                    $runtime = 'ollama';
                    $models = array_map(fn ($m) => ['name' => $m['name'], 'size_gb' => round(($m['size'] ?? 0) / 1e9, 2), 'modified' => $m['modified_at'] ?? null, 'family' => $m['details']['family'] ?? null, 'params' => $m['details']['parameter_size'] ?? null], (array) $tags->json('models'));
                } else {
                    $list = Http::timeout(5)->withToken((string) $p->api_key)->get(rtrim((string) $p->base_url, '/').'/models');
                    if ($list->successful()) {
                        $runtime = 'openai_compatible'; // mis. Jan
                        $models = array_map(fn ($m) => ['name' => $m['id'], 'size_gb' => null, 'modified' => null, 'family' => null, 'params' => null], (array) $list->json('data'));
                    }
                }
            } catch (\Throwable) {
            }
            $active = AiModel::where('provider_id', $p->id)->where('capability', 'text')->where('is_active', true)->value('model_id');
            $out[] = ['provider_id' => $p->id, 'name' => $p->name, 'base_url' => $p->base_url, 'status' => $p->status, 'runtime' => $runtime, 'reachable' => $runtime !== null, 'active_model' => $active, 'models' => $models,
                'pulls' => collect(Cache::get('ai-local:pulls', []))->mapWithKeys(fn ($m) => [$m => Cache::get(PullLocalModel::key($m))])->filter()->all()];
        }

        return ['data' => ['providers' => $out, 'suggested' => [
            ['name' => 'qwen2.5:3b', 'note' => 'Cepat di CPU, bahasa Indonesia cukup (±1,9 GB)'],
            ['name' => 'qwen2.5:7b', 'note' => 'Kualitas lebih baik, butuh RAM ±8 GB (±4,7 GB)'],
            ['name' => 'llama3.2:3b', 'note' => 'Alternatif ringan (±2 GB)'],
            ['name' => 'gemma2:2b', 'note' => 'Sangat ringan (±1,6 GB)'],
        ]]];
    }

    public function localPull(Request $r)
    {
        $d = $r->validate(['provider_id' => 'required|integer|exists:ai_providers,id', 'model' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9][a-z0-9._:\/-]*$/i']]);
        $p = AiProvider::findOrFail($d['provider_id']);
        abort_unless(AiGateway::isLocal($p), 422);
        $pulls = array_values(array_unique(array_merge((array) Cache::get('ai-local:pulls', []), [$d['model']])));
        Cache::put('ai-local:pulls', array_slice($pulls, -10), 86400);
        Cache::put(PullLocalModel::key($d['model']), ['status' => 'antre', 'pct' => 0, 'done' => false], 7200);
        PullLocalModel::dispatch(preg_replace('#/v1/?$#', '', rtrim((string) $p->base_url, '/')), $d['model'])->onQueue('default');
        Audit::log('admin.ai_local.pull', $p, ['model' => $d['model']]);

        return response()->json(['data' => ['queued' => true]], 202);
    }

    public function localUse(Request $r)
    {
        $d = $r->validate(['provider_id' => 'required|integer|exists:ai_providers,id', 'model' => 'required|string|max:120']);
        $p = AiProvider::findOrFail($d['provider_id']);
        abort_unless(AiGateway::isLocal($p), 422);
        AiModel::updateOrCreate(['provider_id' => $p->id, 'capability' => 'text'], ['name' => $d['model'], 'model_id' => $d['model'], 'is_active' => true]);
        Audit::log('admin.ai_local.use', $p, ['model' => $d['model']]);

        return $this->localModels();
    }

    /** Uji cepat penyedia teks dari Admin: kirim satu prompt pendek, kembalikan teks & waktu respons. */
    public function adminTestText(Request $r)
    {
        $msg = $r->validate(['prompt' => 'nullable|string|max:300'])['prompt'] ?? 'Tulis satu kalimat promosi kopi susu gula aren.';
        $t0 = microtime(true);
        $res = AiGateway::chat([['role' => 'user', 'content' => $msg]], 120, ['timeout' => 180]);

        return ['data' => [
            'ok' => (bool) $res, 'text' => $res['text'] ?? null, 'provider_id' => $res['provider_id'] ?? null, 'model' => $res['model'] ?? null,
            'seconds' => round(microtime(true) - $t0, 1),
        ]];
    }
}
