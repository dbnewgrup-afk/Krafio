<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Addon;
use App\Models\Plan;
use App\Services\Audit;
use App\Services\Credits;
use App\Services\Settings;
use App\Support\AiTools;
use Illuminate\Http\Request;

/**
 * Kalkulator margin (Admin → Keuangan): data asli Krafio (biaya kredit per aksi, biaya tool teks, paket kredit,
 * paket langganan) + asumsi yang bisa diubah (kurs, fee gateway, PPN, harga model AI, estimasi token).
 * Perhitungan dilakukan di browser agar Admin bisa mencoba skenario tanpa menyimpan; asumsi bisa disimpan.
 * Acuan angka awal: report/BIAYA_AI_DAN_MARGIN_2026-11-14.md.
 */
class MarginCalculatorController extends Controller
{
    /** Asumsi awal (USD untuk harga model). */
    public const DEFAULTS = [
        'usd_idr' => 16500,
        'fee_pct' => 2,
        'ppn_pct' => 11,
        'target_margin_pct' => 55,
        'media' => [
            'image_1k' => 0.0336, 'image_2k' => 0.0504, 'remove_bg' => 0.0336, 'inpaint' => 0.0336,
            'video_720_per_s' => 0.07, 'video_1080_per_s' => 0.104,
        ],
        'text_models' => [
            'haiku' => ['label' => 'Claude Haiku 5.5', 'in' => 0.10, 'out' => 0.50],
            'sonnet' => ['label' => 'Claude Sonnet 5.5', 'in' => 2.00, 'out' => 10.00],
            'gemini_flash' => ['label' => 'Gemini 3.8 Flash', 'in' => 0.75, 'out' => 3.75],
            'local' => ['label' => 'Ollama lokal', 'in' => 0.0, 'out' => 0.0],
        ],
        // Estimasi token per UNIT (part/variasi/10 item) + model yang dipakai
        'text_tools' => [
            'content_script' => ['in' => 900, 'out' => 1200, 'model' => 'gemini_flash'],
            'content_caption' => ['in' => 700, 'out' => 400, 'model' => 'haiku'],
            'content_brainstorm' => ['in' => 800, 'out' => 1500, 'model' => 'haiku'],
            'content_plan' => ['in' => 800, 'out' => 1500, 'model' => 'haiku'],
            'copywriting' => ['in' => 700, 'out' => 600, 'model' => 'haiku'],
            'article_writer' => ['in' => 800, 'out' => 2500, 'model' => 'gemini_flash'],
            'story_series' => ['in' => 800, 'out' => 900, 'model' => 'haiku'],
            'prompt_optimizer' => ['in' => 600, 'out' => 500, 'model' => 'haiku'],
        ],
        'chat' => ['in' => 1500, 'out' => 400, 'model' => 'haiku'],
    ];

    public function show()
    {
        $saved = (array) Settings::get('margin_calc.assumptions', []);
        $assumptions = array_replace_recursive(self::DEFAULTS, $saved);
        $assumptions['affiliate_pct'] = (float) Settings::get('affiliate.rate_percent', 10);
        $assumptions['affiliate_active'] = (bool) Settings::get('affiliate.active', true);
        $assumptions['ppn_pct'] = (float) Settings::get('billing.ppn_percent', $assumptions['ppn_pct']);

        $tools = [];
        foreach (AiTools::definitions() as $key => $d) {
            if ($d['capability'] !== 'text') {
                continue;
            }
            $t = AiTools::get($key);
            $tools[] = ['key' => $key, 'label' => AiTools::tr($t['label'], 'id'), 'credits_per_unit' => (int) ($t['cost_per_unit'] ?? 1), 'mode' => $t['mode'] ?? 'parts', 'enabled' => $t['enabled']];
        }

        return ['data' => [
            'assumptions' => $assumptions,
            'credit_costs' => Credits::costs(),
            'chat_messages_per_credit' => Credits::chatMessagesPerCredit(),
            'credit_value_idr' => (int) Settings::get('billing.credit_value_idr', 176),
            'text_tools' => $tools,
            'packs' => Addon::where('is_active', true)->get()->filter(fn ($a) => ! empty($a->grants['credits']))->map(fn ($a) => ['code' => $a->code, 'name' => $a->name_id, 'price' => (int) $a->price, 'credits' => (int) $a->grants['credits']])->sortBy('credits')->values(),
            'plans' => Plan::where('is_active', true)->orderBy('sort')->get()->filter(fn ($p) => $p->price_monthly > 0)->map(fn ($p) => ['code' => $p->code, 'name' => $p->name_id, 'price' => (int) $p->price_monthly, 'credits' => (int) ($p->quotas['credits'] ?? 0), 'chat_per_day' => (int) ($p->quotas['chatbot.basic'] ?? 0)])->values(),
            'saved_at' => Settings::get('margin_calc.saved_at'),
        ]];
    }

    /** Simpan asumsi (kurs, fee, harga model, estimasi token). Tidak mengubah harga jual. */
    public function save(Request $r)
    {
        $d = $r->validate([
            'usd_idr' => 'required|numeric|min:1000|max:100000',
            'fee_pct' => 'required|numeric|min:0|max:20',
            'target_margin_pct' => 'required|numeric|min:0|max:95',
            'media' => 'required|array', 'media.*' => 'numeric|min:0|max:100',
            'text_models' => 'required|array', 'text_models.*.in' => 'numeric|min:0|max:1000', 'text_models.*.out' => 'numeric|min:0|max:1000', 'text_models.*.label' => 'string|max:60',
            'text_tools' => 'required|array', 'text_tools.*.in' => 'integer|min:0|max:200000', 'text_tools.*.out' => 'integer|min:0|max:200000', 'text_tools.*.model' => 'string|max:40',
            'chat' => 'required|array', 'chat.in' => 'integer|min:0|max:200000', 'chat.out' => 'integer|min:0|max:200000', 'chat.model' => 'string|max:40',
        ]);
        Settings::set('margin_calc.assumptions', $d);
        Settings::set('margin_calc.saved_at', now()->toIso8601String());
        Audit::log('admin.margin_calc.saved', null, ['usd_idr' => $d['usd_idr'], 'fee_pct' => $d['fee_pct']]);

        return $this->show();
    }
}
