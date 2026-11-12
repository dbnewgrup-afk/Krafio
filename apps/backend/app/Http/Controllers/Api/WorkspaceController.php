<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiJob;
use App\Models\AuditLog;
use App\Models\Project;
use App\Models\SeoAudit;
use App\Models\SeoFinding;
use App\Models\SocialAccount;
use App\Models\Ticket;
use App\Models\Website;
use App\Services\Billing;
use App\Services\Entitlements;
use App\Services\SeoAuditor;
use App\Services\SeoWriter;
use App\Services\Settings;
use App\Support\ApiException;
use App\Support\Present;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WorkspaceController extends Controller
{
    public function dashboard(Request $r)
    {
        $u = $r->user();
        Billing::releaseCommissions($u->id);
        $counts = AiJob::where('user_id', $u->id)->selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status');

        return ['data' => [
            'jobs_summary' => ['queued' => (int) ($counts['queued'] ?? 0), 'processing' => (int) ($counts['processing'] ?? 0), 'completed' => (int) ($counts['completed'] ?? 0), 'failed' => (int) ($counts['failed'] ?? 0)],
            'recent_jobs' => AiJob::where('user_id', $u->id)->latest()->limit(5)->get()->map(fn ($j) => Present::job($j)),
            'recent_projects' => Project::where('user_id', $u->id)->latest('updated_at')->limit(5)->get()->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'type' => $p->type, 'updated_at' => $p->updated_at->toIso8601String()]),
            'notices' => $u->unreadNotifications()->limit(3)->get()->map(fn ($n) => ['id' => $n->id, 'title' => $n->data['title'] ?? '', 'body' => $n->data['body'] ?? '', 'created_at' => $n->created_at->toIso8601String()]),
        ]];
    }

    public function activity(Request $r)
    {
        $q = AuditLog::where('actor_id', $r->user()->id)->latest('id');

        return Present::paginate($q->paginate(min(100, (int) $r->query('per_page', 25))), fn ($a) => ['id' => $a->id, 'action' => $a->action, 'ip' => $a->ip, 'created_at' => $a->created_at->toIso8601String()]);
    }

    /* ---------------- Website builder ---------------- */
    private function site(Website $w, bool $content = false): array
    {
        return array_filter([
            'id' => $w->id, 'name' => $w->name, 'subdomain' => $w->subdomain, 'status' => $w->status, 'updated_at' => $w->updated_at->toIso8601String(),
            'content' => $content ? $w->content : null,
            'domain' => \App\Services\CustomDomains::present($w),
        ], fn ($v) => $v !== null);
    }

    /** Pasang / ganti domain kustom (dibatasi limits.custom_domains paket). */
    public function setDomain(Request $r, Website $website)
    {
        abort_unless($website->user_id === $r->user()->id, 404);
        Entitlements::ensure($r->user(), 'website.builder', 0);
        $w = \App\Services\CustomDomains::set($r->user(), $website, (string) $r->validate(['domain' => 'required|string|max:190'])['domain']);

        return ['data' => $this->site($w)];
    }

    public function verifyDomain(Request $r, Website $website)
    {
        abort_unless($website->user_id === $r->user()->id, 404);

        return ['data' => $this->site(\App\Services\CustomDomains::verify($website))];
    }

    public function removeDomain(Request $r, Website $website)
    {
        abort_unless($website->user_id === $r->user()->id, 404);
        \App\Services\CustomDomains::remove($website);

        return ['data' => $this->site($website->fresh())];
    }

    public function websites(Request $r)
    {
        return ['data' => Website::where('user_id', $r->user()->id)->latest('updated_at')->get()->map(fn ($w) => $this->site($w))];
    }

    public function createWebsite(Request $r)
    {
        $u = $r->user();
        Entitlements::ensure($u, 'website.builder', 1);
        $data = $r->validate([
            'name' => 'required|string|max:120', 'business' => 'required|string|max:1000', 'services' => 'nullable|string|max:1000',
            'subdomain' => ['required', 'regex:/^[a-z0-9](?:[a-z0-9-]{1,61}[a-z0-9])?$/', 'unique:websites,subdomain', 'not_in:www,admin,app,api,mail,status,assets'],
        ], ['subdomain.unique' => 'Subdomain sudah dipakai.', 'subdomain.regex' => 'Gunakan huruf kecil, angka, dan tanda hubung (3–63 karakter).']);
        // Paket + add-on "website-extra" (sebelumnya add-on tidak terhitung)
        Entitlements::ensureCount($u, 'websites', Website::where('user_id', $u->id)->count(), 1, 'website.builder', 'jumlah website');
        $services = array_values(array_filter(array_map('trim', preg_split('/[\n,]/', (string) ($data['services'] ?? '')))));
        $w = Website::create([
            'user_id' => $u->id, 'name' => $data['name'], 'subdomain' => $data['subdomain'], 'status' => 'draft',
            'content' => [
                'blocks' => [
                    ['id' => (string) Str::uuid(), 'type' => 'hero', 'title' => $data['name'], 'body' => Str::limit($data['business'], 160)],
                    ['id' => (string) Str::uuid(), 'type' => 'about', 'title' => 'Tentang kami', 'body' => $data['business']],
                    ['id' => (string) Str::uuid(), 'type' => 'services', 'title' => 'Layanan', 'body' => $services ? implode("\n", $services) : 'Tuliskan layanan utama Anda di sini.'],
                    ['id' => (string) Str::uuid(), 'type' => 'contact', 'title' => 'Hubungi kami', 'body' => 'Tambahkan alamat, jam buka, dan kontak.'],
                ],
                // SEO disusun berbasis aturan agar draft langsung lolos validasi publish (judul 10–60, deskripsi 50–160)
                'seo' => ['title' => SeoWriter::title($data['name'], $services, $data['business']), 'description' => SeoWriter::description($data['name'], $data['business'], $services)],
            ],
        ]);

        return response()->json(['data' => $this->site($w, true)], 201);
    }

    public function showWebsite(Request $r, Website $website)
    {
        abort_unless($website->user_id === $r->user()->id, 404);

        return ['data' => $this->site($website, true)];
    }

    private function validContent(Request $r): array
    {
        $data = $r->validate([
            'content.blocks' => 'required|array|max:30', 'content.blocks.*.id' => 'required|string|max:64', 'content.blocks.*.type' => 'required|in:hero,about,services,contact,gallery,testimonial,cta',
            'content.blocks.*.title' => 'nullable|string|max:200', 'content.blocks.*.body' => 'nullable|string|max:5000',
            'content.seo.title' => 'nullable|string|max:120', 'content.seo.description' => 'nullable|string|max:300',
        ]);

        return $data['content'];
    }

    /** Saran perbaikan SEO otomatis (tanpa AI) dari konten editor; tidak menyimpan. */
    public function suggestSeo(Request $r, Website $website)
    {
        abort_unless($website->user_id === $r->user()->id, 404);
        $content = $this->validContent($r);

        return ['data' => ['seo' => SeoWriter::fix($website->name, $content)['seo']]];
    }

    public function updateWebsite(Request $r, Website $website)
    {
        abort_unless($website->user_id === $r->user()->id, 404);
        $website->update(['content' => $this->validContent($r), 'version' => $website->version + 1]);

        return ['data' => $this->site($website, true)];
    }

    /** Publikasi melewati validasi: judul, metadata SEO, konten minimal (Design Spec §19.5). */
    public function publishWebsite(Request $r, Website $website)
    {
        abort_unless($website->user_id === $r->user()->id, 404);
        Entitlements::ensure($r->user(), 'website.builder', 0);
        // Paket Free: website builder hanya pratinjau (PRD 55.2.1 "Preview")
        if (! empty(Entitlements::grants($r->user())['limits']['website_preview_only'])) {
            throw new ApiException('plan_limit', 'Paket Anda hanya dapat membuat pratinjau website. Upgrade untuk mempublikasikan.', 403, ['feature' => 'website.builder']);
        }
        $content = $this->validContent($r);
        $issues = [];
        $seoT = mb_strlen($content['seo']['title'] ?? '');
        $seoD = mb_strlen($content['seo']['description'] ?? '');
        if ($seoT < 10 || $seoT > 60) {
            $issues[] = 'Judul SEO harus 10–60 karakter (sekarang '.$seoT.').';
        }
        if ($seoD < 50 || $seoD > 160) {
            $issues[] = 'Deskripsi SEO harus 50–160 karakter (sekarang '.$seoD.').';
        }
        foreach ($content['blocks'] as $b) {
            if (trim((string) ($b['title'] ?? '')) === '') {
                $issues[] = 'Setiap blok membutuhkan judul.';
                break;
            }
        }
        if (! collect($content['blocks'])->contains('type', 'contact')) {
            $issues[] = 'Tambahkan blok Kontak.';
        }
        if ($issues) {
            throw new ApiException('publish_blocked', 'Website belum dapat dipublikasikan.', 422, ['issues' => $issues]);
        }
        $website->update(['content' => $content, 'published_content' => $content, 'status' => 'published', 'published_at' => now()]);

        return ['data' => $this->site($website, true)];
    }

    /* ---------------- SEO ---------------- */
    private function audit(SeoAudit $a): array
    {
        return [
            'id' => $a->id, 'url' => $a->url, 'score' => $a->score, 'ruleset_version' => $a->ruleset_version, 'status' => $a->status, 'created_at' => $a->created_at->toIso8601String(),
            'findings' => $a->findings->map(fn ($f) => ['id' => $f->id, 'rule' => $f->rule, 'severity' => $f->severity, 'location' => $f->location, 'message' => $f->message, 'recommendation' => $f->recommendation, 'state' => $f->state])->values(),
        ];
    }

    public function seoAudits(Request $r)
    {
        return ['data' => SeoAudit::with('findings')->where('user_id', $r->user()->id)->latest()->limit(20)->get()->map(fn ($a) => $this->audit($a))];
    }

    public function runSeo(Request $r)
    {
        $u = $r->user();
        $data = $r->validate(['url' => 'required|url|max:500']);
        Entitlements::ensure($u, 'seo.basic', 1);
        $res = SeoAuditor::audit($data['url']);
        Entitlements::consume($u, 'seo.basic', 1);
        $a = SeoAudit::create(['user_id' => $u->id, 'url' => $data['url'], 'score' => $res['score'], 'ruleset_version' => (string) Settings::get('seo.ruleset_version'), 'status' => 'completed']);
        foreach ($res['findings'] as $f) {
            SeoFinding::create($f + ['audit_id' => $a->id]);
        }

        return response()->json(['data' => $this->audit($a->load('findings'))], 201);
    }

    public function updateFinding(Request $r, SeoFinding $finding)
    {
        abort_unless(SeoAudit::where('id', $finding->audit_id)->where('user_id', $r->user()->id)->exists(), 404);
        $finding->update($r->validate(['state' => 'required|in:open,resolved,ignored']));

        return response()->noContent();
    }

    /* ---------------- Social (PRD 30, 46) ---------------- */
    public const PLATFORMS = [
        'whatsapp' => ['setting' => 'whatsapp.enabled', 'perms' => ['send_messages', 'read_status']],
        'facebook' => ['setting' => 'social.facebook.enabled', 'perms' => ['manage_pages', 'publish_posts']],
        'instagram' => ['setting' => 'social.instagram.enabled', 'perms' => ['publish_content', 'read_insights']],
        'tiktok' => ['setting' => 'social.tiktok.enabled', 'perms' => ['upload_video', 'read_profile']],
        'threads' => ['setting' => 'social.threads.enabled', 'perms' => ['publish_posts']],
    ];

    public function social(Request $r)
    {
        $acc = SocialAccount::where('user_id', $r->user()->id)->get()->keyBy('platform');

        return ['data' => collect(self::PLATFORMS)->map(function ($cfg, $key) use ($acc) {
            $available = (bool) Settings::get($cfg['setting'], false);
            $a = $acc[$key] ?? null;
            $status = ! $available ? 'not_available' : (! $a ? 'disconnected' : ($a->expires_at && $a->expires_at->isPast() ? 'expired' : $a->status));

            return ['platform' => $key, 'available' => $available, 'status' => $status, 'account_name' => $a?->account_name, 'expires_at' => $a?->expires_at?->toIso8601String(), 'permissions' => $cfg['perms']];
        })->values()];
    }

    public function connectSocial(Request $r, string $platform)
    {
        abort_unless(isset(self::PLATFORMS[$platform]), 404);
        Entitlements::ensure($r->user(), 'social.integrations', 0);
        $connected = SocialAccount::where('user_id', $r->user()->id);
        if (! (clone $connected)->where('platform', $platform)->exists()) { // menyambung ulang platform yang sama tidak menambah jumlah
            Entitlements::ensureCount($r->user(), 'social_accounts', (clone $connected)->count(), 1, 'social.integrations', 'akun sosial');
        }
        if (! Settings::get(self::PLATFORMS[$platform]['setting'], false)) {
            throw new ApiException('integration_not_available', 'Integrasi ini belum tersedia. Admin perlu mengonfigurasi aplikasi resmi platform terlebih dahulu.', 422);
        }
        $redirect = Settings::get("social.{$platform}.oauth_url");
        if (! $redirect) {
            throw new ApiException('integration_not_configured', 'URL OAuth resmi belum dikonfigurasi Admin.', 422);
        }

        return ['data' => ['redirect_url' => $redirect.(str_contains($redirect, '?') ? '&' : '?').'state='.encrypt($r->user()->id)]];
    }

    public function disconnectSocial(Request $r, string $platform)
    {
        SocialAccount::where('user_id', $r->user()->id)->where('platform', $platform)->delete();

        return response()->noContent();
    }

    /* ---------------- Tiket user ---------------- */
    public function tickets(Request $r)
    {
        $q = Ticket::where('user_id', $r->user()->id);
        if ($s = $r->query('status')) {
            $q->where('status', $s);
        }
        if ($s = $r->query('search')) {
            $q->where('subject', 'like', "%{$s}%");
        }

        return Present::paginate($q->latest('updated_at')->paginate(min(100, (int) $r->query('per_page', 25))), fn ($t) => ['id' => $t->id, 'number' => $t->number, 'subject' => $t->subject, 'status' => $t->status, 'updated_at' => $t->updated_at->toIso8601String()]);
    }

    public static function ticketDetail(Ticket $t): array
    {
        return [
            'id' => $t->id, 'number' => $t->number, 'subject' => $t->subject, 'status' => $t->status, 'updated_at' => $t->updated_at->toIso8601String(),
            'requester' => $t->user ? ['name' => $t->user->name, 'email' => $t->user->email] : ['name' => $t->guest_name, 'email' => $t->guest_email],
            'messages' => $t->messages()->with('user')->oldest()->get()->map(fn ($m) => ['id' => $m->id, 'from' => $m->from, 'body' => $m->body, 'created_at' => $m->created_at->toIso8601String(), 'author' => $m->from === 'admin' ? 'Tim Krafio' : ($m->user?->name ?? $t->guest_name ?? 'Pengguna')]),
        ];
    }

    public function ticket(Request $r, Ticket $ticket)
    {
        abort_unless($ticket->user_id === $r->user()->id, 404);

        return ['data' => self::ticketDetail($ticket)];
    }

    public function replyTicket(Request $r, Ticket $ticket)
    {
        abort_unless($ticket->user_id === $r->user()->id, 404);
        AppServicesInbox::post($ticket, 'user', $r->validate(['body' => 'required|string|max:5000'])['body'], $r->user());

        return response()->noContent();
    }
}
