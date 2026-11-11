<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Addon;
use App\Models\Banner;
use App\Models\Faq;
use App\Models\LegalDocument;
use App\Models\Plan;
use App\Models\Template;
use App\Models\Ticket;
use App\Notifications\Notice;
use App\Models\User;
use App\Services\Settings;
use App\Support\ApiException;
use App\Support\Present;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/** Endpoint publik Frontpage (PRD 2.1-A, 33.2). */
class PublicController extends Controller
{
    public function settings()
    {
        $s = Settings::all();
        $banner = Banner::where('is_active', true)->where('placement', 'global')
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->latest()->first();

        return ['data' => [
            'app_name' => $s['general.app_name'],
            'environment' => $s['general.environment_label'] ?? (app()->environment('production') ? 'production' : app()->environment()),
            'registration_mode' => $s['registration.mode'],
            'topup_min' => (int) $s['wallet.topup_min'],
            'topup_max' => (int) $s['wallet.topup_max'],
            'withdraw_min' => (int) $s['wallet.withdraw_min'],
            'cs' => ['hours' => $s['cs.hours'], 'whatsapp' => $s['cs.whatsapp'], 'reply_estimate' => $s['cs.reply_estimate']],
            'maintenance' => ['active' => (bool) $s['maintenance.active'], 'message' => $s['maintenance.message']],
            'announcement' => $banner ? ['level' => $banner->level, 'message' => $banner->message, 'link' => $banner->link] : null,
            'turnstile_site_key' => $s['security.turnstile_site_key'],
            'sso_google' => (bool) $s['sso.google'],
            'social' => $s['social.links'] ?? [],
            'app_version' => config('krafio.version'),
        ]];
    }

    public function plans()
    {
        return ['data' => Plan::where('is_active', true)->orderBy('sort')->get()->map(fn ($p) => Present::plan($p))];
    }

    public function addons()
    {
        return ['data' => Addon::where('is_active', true)->orderBy('price')->get()->map(fn ($a) => [
            'id' => $a->id, 'code' => $a->code, 'name' => Present::tr($a, 'name'), 'description' => Present::tr($a, 'description') ?? '',
            'price' => (int) $a->price, 'billing' => $a->billing, 'duration_days' => $a->duration_days, 'grants' => (object) ($a->grants ?? []), 'is_active' => $a->is_active,
        ])];
    }

    public function unlocks(Request $r)
    {
        $f = (string) $r->query('feature');
        $plans = Plan::where('is_active', true)->orderBy('sort')->get()->filter(fn ($p) => in_array($f, $p->features ?? [], true));
        $addons = Addon::where('is_active', true)->get()->filter(fn ($a) => array_key_exists($f, $a->grants ?? []));

        return ['data' => [
            'plans' => $plans->map(fn ($p) => ['code' => $p->code, 'name' => Present::tr($p, 'name'), 'price_monthly' => (int) $p->price_monthly])->values(),
            'addons' => $addons->map(fn ($a) => ['code' => $a->code, 'name' => Present::tr($a, 'name'), 'price' => (int) $a->price])->values(),
        ]];
    }

    public function templates(Request $r)
    {
        $q = Template::where('status', 'active');
        if ($t = $r->query('type')) {
            $q->where('type', $t);
        }
        if ($r->boolean('featured')) {
            $q->where('is_featured', true);
        }
        if ($s = trim((string) $r->query('search'))) {
            $q->where(fn ($w) => $w->where('title_id', 'like', "%{$s}%")->orWhere('title_en', 'like', "%{$s}%")->orWhere('category', 'like', "%{$s}%"));
        }
        $r->query('sort') === 'newest' ? $q->latest() : $q->orderByDesc('usage_count');

        return ['data' => $q->limit(min(100, (int) $r->query('per_page', 48)))->get()->map(fn ($t) => Present::template($t))];
    }

    /** Etalase promo publik (halaman /promo & section beranda). */
    public function promos()
    {
        return ['data' => \App\Services\Promos::publicList()];
    }

    public function faqs(Request $r)
    {
        $q = Faq::where('is_active', true)->orderBy('sort');
        if ($s = trim((string) $r->query('search'))) {
            $words = array_filter(preg_split('/\s+/', mb_strtolower($s)), fn ($w) => mb_strlen($w) >= 3);
            $q->where(function ($w) use ($words, $s) {
                $w->where('question_id', 'like', "%{$s}%")->orWhere('answer_id', 'like', "%{$s}%");
                foreach ($words as $word) {
                    $w->orWhere('question_id', 'like', "%{$word}%")->orWhere('question_en', 'like', "%{$word}%");
                }
            });
        }

        return ['data' => $q->limit(min(200, (int) $r->query('limit', 50)))->get()->map(fn ($f) => [
            'id' => $f->id, 'question' => Present::tr($f, 'question'), 'answer' => Present::tr($f, 'answer'), 'category' => $f->category,
        ])];
    }

    public function legal(string $type)
    {
        $doc = LegalDocument::where('type', $type)->where('is_active', true)->latest('effective_at')->first();
        if (! $doc) {
            throw new ApiException('not_found', 'Dokumen tidak ditemukan.', 404);
        }

        return ['data' => Present::legal($doc)];
    }

    public function status()
    {
        $db = 'healthy';
        try {
            DB::select('select 1');
        } catch (\Throwable) {
            $db = 'down';
        }
        $queueBacklog = (int) DB::table('jobs')->count();
        $components = [
            ['key' => 'web', 'status' => 'healthy'],
            ['key' => 'api', 'status' => $db === 'healthy' ? 'healthy' : 'down'],
            ['key' => 'jobs', 'status' => $queueBacklog > 500 ? 'degraded' : 'healthy'],
            ['key' => 'ai', 'status' => \App\Models\AiProvider::where('status', 'active')->exists() ? 'healthy' : 'degraded'],
            ['key' => 'payments', 'status' => 'healthy'],
        ];
        $overall = collect($components)->contains('status', 'down') ? 'down' : (collect($components)->contains('status', 'degraded') ? 'degraded' : 'healthy');

        return ['data' => [
            'overall' => $overall,
            'components' => $components,
            'checked_at' => now()->toIso8601String(),
            'maintenance' => ['active' => (bool) Settings::get('maintenance.active'), 'message' => Settings::get('maintenance.message')],
        ]];
    }

    /** Tiket dari panel CS / halaman kontak; tamu wajib nama + email (Design Spec §15.2 butir 6). */
    public function ticket(Request $r)
    {
        $key = 'ticket:'.($r->user()?->id ?? $r->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw new ApiException('too_many_attempts', 'Terlalu banyak permintaan.', 429, ['retry_after' => RateLimiter::availableIn($key)]);
        }
        RateLimiter::hit($key, 3600);
        $u = $r->user();
        $data = $r->validate([
            'name' => [$u ? 'nullable' : 'required', 'string', 'max:120'],
            'email' => [$u ? 'nullable' : 'required', 'email', 'max:190'],
            'subject' => ['required', 'string', 'min:4', 'max:160'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'page' => ['nullable', 'string', 'max:255'],
        ]);
        $t = DB::transaction(function () use ($data, $u) {
            $t = Ticket::create([
                'number' => 'KRF-'.strtoupper(Str::random(6)),
                'user_id' => $u?->id,
                'guest_name' => $u ? null : $data['name'],
                'guest_email' => $u ? null : $data['email'],
                'subject' => $data['subject'],
                'page' => $data['page'] ?? null,
            ]);
            AppServicesInbox::post($t, 'user', $data['message'], $u);

            return $t;
        });
        Notification::send(User::where('role', 'admin')->get(), new Notice('Tiket baru '.$t->number, $t->subject, 'support', '/admin/support/tickets/'.$t->id));

        return response()->json(['data' => ['number' => $t->number, 'id' => $t->id]], 201);
    }
}
