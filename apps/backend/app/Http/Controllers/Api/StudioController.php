<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessAiJob;
use App\Models\AiJob;
use App\Models\AiModel;
use App\Models\AssistantMessage;
use App\Models\Project;
use App\Models\Template;
use App\Models\Upload;
use App\Services\AiGateway;
use App\Services\Audit;
use App\Services\Credits;
use App\Services\Entitlements;
use App\Support\ApiException;
use App\Support\Present;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Studio AI, unggahan, proyek, aset, dan AI Assistant (PRD 6.1–6.10, 9.4). */
class StudioController extends Controller
{
    public const TYPES = [
        'photo_generate' => ['ent' => 'photo.generate', 'upload' => null],
        'video_generate' => ['ent' => 'video.generate', 'upload' => null],
        'photo_edit' => ['ent' => 'photo.edit', 'upload' => 'image'],
        'video_edit' => ['ent' => 'video.edit', 'upload' => 'video'],
        'content_brainstorm' => ['ent' => 'content.brainstorm', 'upload' => null],
        'video_clipper' => ['ent' => 'video.clipper', 'upload' => 'video'],
    ];

    /**
     * Biaya kredit per aksi (PRD 55.1.1) dihitung di server sesuai batas paket (55.2): resolusi foto,
     * resolusi & durasi maksimum video, durasi sumber clipper. Brainstorming memakai limit request (bukan kredit).
     */
    public static function cost(string $type, array $p, ?\App\Models\User $u = null): int
    {
        $c = Credits::costs();
        $limits = $u ? Entitlements::grants($u)['limits'] : [];

        return match ($type) {
            'photo_generate' => ((($p['quality'] ?? 'standard') === 'hd') ? $c['photo_hd'] : $c['photo_standard']) * max(1, min(4, (int) ($p['count'] ?? 1))),
            'photo_edit' => ["remove_bg" => $c['remove_bg'], "upscale" => $c['upscale']][$p['operation'] ?? ''] ?? $c['inpaint'],
            'video_generate' => (int) ceil(((int) ($p['duration'] ?? 5)) / 5) * ((($limits['video_resolution'] ?? '720p') === '1080p') ? $c['video_1080_per_5s'] : $c['video_720_per_5s']),
            'video_edit' => $c['video_edit_per_min'] * max(1, (int) ($p['minutes'] ?? 1)),
            'video_clipper' => $c['clipper_per_min'] * max(1, (int) ($p['source_minutes'] ?? 1)),
            default => 1, // content_brainstorm: 1 request
        };
    }

    /** Kembalikan kredit/limit job (BR-017: kegagalan sistem/provider, atau dibatalkan sebelum diproses). */
    public static function refundJob(AiJob $job, string $desc): void
    {
        if ($job->quota_refunded) {
            return;
        }
        if ($job->credit_alloc) {
            Credits::refund($job->user, $job->credit_alloc, 'ai_job', $job->id, $desc);
        } else {
            Entitlements::refund($job->user, $job->entitlement, $job->cost);
        }
        $job->update(['quota_refunded' => true]);
    }

    /** Batas per paket (PRD 55.2): job paralel, resolusi foto, durasi video, durasi sumber clipper. */
    private function enforceLimits(\App\Models\User $u, string $type, array $p): void
    {
        $l = Entitlements::grants($u)['limits'];
        $active = AiJob::where('user_id', $u->id)->whereIn('status', ['queued', 'processing'])->count();
        if ($active >= max(1, (int) ($l['parallel_jobs'] ?? 1))) {
            throw new ApiException('parallel_limit', 'Batas job paralel paket Anda tercapai. Tunggu job berjalan selesai.', 429);
        }
        if ($type === 'photo_generate' && ($p['quality'] ?? 'standard') === 'hd' && (int) ($l['photo_max_px'] ?? 1024) < 2048) {
            throw new ApiException('plan_limit', 'Kualitas HD tersedia mulai paket Starter.', 403, ['feature' => 'photo.generate']);
        }
        if ($type === 'video_generate' && (int) ($p['duration'] ?? 5) > (int) ($l['video_max_seconds'] ?? 0)) {
            throw new ApiException('plan_limit', 'Durasi melebihi batas paket (maks '.((int) ($l['video_max_seconds'] ?? 0)).' detik per job).', 403, ['feature' => 'video.generate']);
        }
        if ($type === 'video_clipper' && (int) ($p['source_minutes'] ?? 1) > (int) ($l['clipper_max_minutes'] ?? 0)) {
            throw new ApiException('plan_limit', 'Durasi sumber melebihi batas paket (maks '.((int) ($l['clipper_max_minutes'] ?? 0)).' menit).', 403, ['feature' => 'video.clipper']);
        }
    }

    private function params(string $type, array $in): array
    {
        $allowed = [
            'photo_generate' => ['ratio' => ['1:1', '4:5', '9:16', '16:9'], 'style' => ['product', 'lifestyle', 'flatlay', 'studio', 'illustration'], 'count' => ['1', '2', '3', '4'], 'quality' => ['standard', 'hd']],
            'photo_edit' => ['operation' => ['inpaint', 'remove_bg', 'upscale']],
            'video_edit' => ['minutes' => ['1', '2', '3', '5', '10']],
            'video_generate' => ['ratio' => ['1:1', '4:5', '9:16', '16:9'], 'duration' => ['5', '10', '15', '30']],
            'content_brainstorm' => ['tone' => ['friendly', 'professional', 'playful', 'persuasive'], 'platform' => ['instagram', 'tiktok', 'facebook', 'threads', 'whatsapp', 'website'], 'ideas' => ['3', '5', '8', '10']],
            'video_clipper' => ['clip_len' => ['15', '30', '60'], 'clips' => ['1', '3', '5'], 'source_minutes' => ['5', '10', '15', '30', '60', '120']],
        ][$type] ?? [];
        $out = [];
        foreach ($allowed as $k => $vals) {
            if (isset($in[$k]) && in_array((string) $in[$k], $vals, true)) {
                $out[$k] = (string) $in[$k];
            }
        }
        if (! empty($in['template_id']) && Template::where('id', (int) $in['template_id'])->where('status', 'active')->exists()) {
            $out['template_id'] = (int) $in['template_id'];
        }

        return $out;
    }

    public function estimate(Request $r)
    {
        $type = (string) $r->query('type');
        if (! isset(self::TYPES[$type])) {
            throw new ApiException('invalid_type', 'Jenis job tidak dikenal.', 422);
        }
        $e = Entitlements::forUser($r->user())[self::TYPES[$type]['ent']];

        return ['data' => ['cost' => self::cost($type, $this->params($type, $r->query()), $r->user()), 'unit' => $e['unit'], 'remaining' => $e['remaining'], 'quota' => $e['quota'], 'enabled' => $e['enabled']]];
    }

    public function index(Request $r)
    {
        $q = AiJob::where('user_id', $r->user()->id)->latest();
        if ($t = $r->query('type')) {
            $q->where('type', $t);
        }

        return ['data' => $q->limit(min(50, (int) $r->query('per_page', 20)))->get()->map(fn ($j) => Present::job($j))];
    }

    public function history(Request $r)
    {
        $q = AiJob::where('user_id', $r->user()->id);
        if ($s = $r->query('status')) {
            $q->where('status', $s);
        }
        if ($s = $r->query('search')) {
            $q->where('prompt', 'like', "%{$s}%");
        }
        $sort = (string) $r->query('sort', '-created_at');
        $q->orderBy(ltrim($sort, '-') === 'created_at' ? 'created_at' : 'id', str_starts_with($sort, '-') || $sort === '' ? 'desc' : 'asc');

        return Present::paginate($q->paginate(min(100, (int) $r->query('per_page', 25))), fn ($j) => Present::job($j));
    }

    public function store(Request $r)
    {
        $u = $r->user();
        $data = $r->validate([
            'type' => 'required|in:'.implode(',', array_keys(self::TYPES)),
            'prompt' => 'nullable|string|max:1000',
            'params' => 'nullable|array',
            'upload_id' => 'nullable|integer',
        ]);
        $type = $data['type'];
        $def = self::TYPES[$type];
        if (! $u->ai_consent_at) {
            throw new ApiException('ai_consent_required', 'Persetujuan pemrosesan AI diperlukan.', 403);
        }
        if ($type !== 'video_clipper' && trim((string) ($data['prompt'] ?? '')) === '') {
            throw new ApiException('validation_failed', 'Prompt wajib diisi.', 422, [], ['prompt' => ['Prompt wajib diisi.']]);
        }
        $upload = null;
        if ($def['upload']) {
            $upload = Upload::where('id', $data['upload_id'] ?? 0)->where('user_id', $u->id)->first();
            if (! $upload || ! str_starts_with($upload->mime, $def['upload'].'/')) {
                throw new ApiException('validation_failed', 'Berkas sumber wajib diunggah.', 422, [], ['upload_id' => ['Berkas sumber wajib diunggah.']]);
            }
        }
        $params = $this->params($type, $data['params'] ?? []);
        Entitlements::ensure($u, $def['ent'], 0); // fitur terkunci dilaporkan lebih dulu daripada batas paket
        $this->enforceLimits($u, $type, $params);
        $cost = self::cost($type, $params, $u);
        $idem = $r->header('Idempotency-Key');
        if ($idem && ($exist = AiJob::where('idempotency_key', $idem)->where('user_id', $u->id)->first())) {
            return response()->json(['data' => Present::job($exist)]);
        }
        Entitlements::ensure($u, $def['ent'], $cost);

        $job = DB::transaction(function () use ($u, $def, $type, $data, $params, $cost, $upload, $idem, $r) {
            $projectId = $r->query('project') ?? ($data['params']['project_id'] ?? null);

            $job = AiJob::create([
                'user_id' => $u->id,
                'project_id' => $projectId && Project::where('id', $projectId)->where('user_id', $u->id)->exists() ? $projectId : null,
                'upload_id' => $upload?->id,
                'type' => $type,
                'entitlement' => $def['ent'],
                'prompt' => trim((string) ($data['prompt'] ?? '')) ?: 'Klip otomatis',
                'params' => $params,
                'cost' => $cost,
                'idempotency_key' => $idem,
            ]);
            // Kredit (media) atau limit request (teks) direservasi saat job dibuat; dikembalikan bila gagal/batal.
            if (in_array($def['ent'], Entitlements::CREDIT_KEYS, true)) {
                $alloc = Credits::consume($u, $cost, 'ai_job', $job->id, 'Job '.$type.' #'.$job->id);
                $job->update(['credit_alloc' => $alloc]);
            } else {
                Entitlements::consume($u, $def['ent'], $cost);
            }

            return $job;
        });
        ProcessAiJob::enqueue($job);
        Audit::log('ai.job_created', $job, ['type' => $type, 'cost' => $cost]);

        return response()->json(['data' => Present::job($job->fresh())], 201);
    }

    public function cancel(Request $r, AiJob $job)
    {
        abort_unless($job->user_id === $r->user()->id, 404);
        if ($job->status !== 'queued') {
            throw new ApiException('not_cancellable', 'Job sudah diproses.', 422);
        }
        $job->update(['status' => 'cancelled', 'completed_at' => now()]);
        self::refundJob($job, 'Pembatalan job #'.$job->id);

        return ['data' => Present::job($job)];
    }

    /** Unggahan media: validasi MIME & ukuran di server (PRD 10.4); disimpan privat. */
    public function upload(Request $r)
    {
        $r->validate(['file' => 'required|file|max:204800|mimetypes:image/jpeg,image/png,image/webp,video/mp4,video/quicktime,video/webm']);
        $f = $r->file('file');
        if (str_starts_with($f->getMimeType(), 'image/') && $f->getSize() > 10 * 1024 * 1024) {
            throw new ApiException('validation_failed', 'Gambar maksimal 10 MB.', 422, [], ['file' => ['Gambar maksimal 10 MB.']]);
        }
        // Kuota storage paket + add-on storage (PRD 55.2 "Storage"), dalam MB
        $usedMb = (int) ceil(Upload::where('user_id', $r->user()->id)->sum('size') / 1048576);
        Entitlements::ensureCount($r->user(), 'storage_mb', $usedMb, (int) ceil($f->getSize() / 1048576), 'storage', 'storage (MB)');
        $path = $f->store('uploads/'.$r->user()->id, 'local');
        $up = Upload::create(['user_id' => $r->user()->id, 'disk' => 'local', 'path' => $path, 'original_name' => mb_substr($f->getClientOriginalName(), 0, 200), 'mime' => $f->getMimeType(), 'size' => $f->getSize()]);

        return response()->json(['data' => ['id' => $up->id]], 201);
    }

    public function assets(Request $r)
    {
        $uid = $r->user()->id;
        $kind = $r->query('kind');
        $out = collect();
        if ($kind !== 'upload') {
            $out = $out->merge(AiJob::where('user_id', $uid)->where('status', 'completed')->whereIn('type', ['photo_generate', 'photo_edit', 'video_generate', 'video_edit', 'video_clipper'])->latest()->limit(60)->get()->map(fn ($j) => ['id' => $j->id, 'name' => mb_substr($j->prompt, 0, 60), 'kind' => 'generated', 'size' => 0, 'created_at' => $j->created_at->toIso8601String(), 'job' => Present::job($j)]));
        }
        if ($kind !== 'generated') {
            $out = $out->merge(Upload::where('user_id', $uid)->latest()->limit(60)->get()->map(fn ($u) => ['id' => 1_000_000 + $u->id, 'name' => $u->original_name, 'kind' => 'upload', 'size' => $u->size, 'created_at' => $u->created_at->toIso8601String(), 'job' => null]));
        }

        return ['data' => $out->sortByDesc('created_at')->values()];
    }

    /* ---------------- Proyek ---------------- */
    public function projects(Request $r)
    {
        return ['data' => Project::with('template')->where('user_id', $r->user()->id)->latest('updated_at')->get()->map(fn ($p) => $this->project($p))];
    }

    private function project(Project $p, bool $jobs = false): array
    {
        return [
            'id' => $p->id, 'name' => $p->name, 'type' => $p->type, 'status' => $p->status, 'updated_at' => $p->updated_at->toIso8601String(), 'created_at' => $p->created_at?->toIso8601String(),
            'description' => $p->data['description'] ?? null, 'tags' => $p->data['tags'] ?? [], 'pinned' => (bool) ($p->data['pinned'] ?? false), 'jobs_count' => $p->jobs()->count(),
            'template' => $p->template ? Present::template($p->template) : null,
            'jobs' => $jobs ? $p->jobs()->latest()->get()->map(fn ($j) => Present::job($j)) : null,
        ];
    }

    public function createProject(Request $r)
    {
        $data = $r->validate(['name' => 'required|string|max:120', 'type' => 'required|in:photo,video,website,social,business', 'template_id' => 'nullable|integer']);
        $tpl = ! empty($data['template_id']) ? Template::where('id', $data['template_id'])->where('status', 'active')->first() : null;
        if ($tpl && $tpl->is_premium) {
            Entitlements::ensure($r->user(), 'templates.premium', 0);
        }
        $this->ensureProjectSlot($r->user());
        $p = Project::create(['user_id' => $r->user()->id, 'name' => $data['name'], 'type' => $data['type'], 'template_id' => $tpl?->id]);
        $tpl?->increment('usage_count');

        return response()->json(['data' => $this->project($p)], 201);
    }

    public function showProject(Request $r, Project $project)
    {
        abort_unless($project->user_id === $r->user()->id, 404);

        return ['data' => $this->project($project->load('template'), true)];
    }

    public function updateProject(Request $r, Project $project)
    {
        abort_unless($project->user_id === $r->user()->id, 404);
        $d = $r->validate(['name' => 'sometimes|required|string|max:120', 'type' => 'sometimes|in:photo,video,website,social,business', 'template_id' => 'sometimes|nullable|integer', 'status' => 'sometimes|in:active,archived', 'description' => 'sometimes|nullable|string|max:300', 'tags' => 'sometimes|array|max:6', 'tags.*' => 'string|max:24', 'pinned' => 'sometimes|boolean']);
        if (array_key_exists('template_id', $d)) {
            $tpl = $d['template_id'] ? Template::where('id', $d['template_id'])->where('status', 'active')->first() : null;
            if ($tpl && $tpl->is_premium) {
                Entitlements::ensure($r->user(), 'templates.premium', 0);
            }
            $project->template_id = $tpl?->id;
        }
        foreach (['name', 'type', 'status'] as $f) {
            if (isset($d[$f])) {
                $project->{$f} = $d[$f];
            }
        }
        $data = $project->data ?? [];
        foreach (['description', 'tags', 'pinned'] as $f) {
            if (array_key_exists($f, $d)) {
                $data[$f] = $d[$f];
            }
        }
        $project->data = $data;
        $project->save();

        return ['data' => $this->project($project->load('template'))];
    }

    public function duplicateProject(Request $r, Project $project)
    {
        abort_unless($project->user_id === $r->user()->id, 404);
        $this->ensureProjectSlot($r->user());
        $copy = $project->replicate();
        $copy->name = mb_substr($project->name.' (salinan)', 0, 120);
        $copy->save();

        return response()->json(['data' => $this->project($copy->load('template'))], 201);
    }

    public function restoreProject(Request $r, int $id)
    {
        $p = Project::onlyTrashed()->where('user_id', $r->user()->id)->findOrFail($id);
        $this->ensureProjectSlot($r->user());
        $p->restore();

        return ['data' => $this->project($p->load('template'))];
    }

    /** Batas jumlah proyek aktif per paket (PRD 55.2 "Proyek"); proyek di tempat sampah tidak dihitung. */
    private function ensureProjectSlot(\App\Models\User $u): void
    {
        Entitlements::ensureCount($u, 'projects', Project::where('user_id', $u->id)->count(), 1, 'projects', 'jumlah proyek');
    }

    public function deleteProject(Request $r, Project $project)
    {
        abort_unless($project->user_id === $r->user()->id, 404);
        $project->delete(); // soft delete = tempat sampah 30 hari

        return response()->noContent();
    }

    /* ---------------- AI Assistant ---------------- */
    public function assistant(Request $r)
    {
        return ['data' => [
            'messages' => AssistantMessage::where('user_id', $r->user()->id)->latest('id')->limit(50)->get()->reverse()->values()->map(fn ($m) => ['id' => $m->id, 'role' => $m->role, 'content' => $m->content, 'created_at' => $m->created_at->toIso8601String()]),
            'provider_configured' => AiGateway::configured('text'),
        ]];
    }

    public function ask(Request $r)
    {
        $u = $r->user();
        $data = $r->validate(['message' => 'required|string|max:2000']);
        Entitlements::ensure($u, 'chatbot.basic', 1);
        Entitlements::consume($u, 'chatbot.basic', 1);
        AssistantMessage::create(['user_id' => $u->id, 'role' => 'user', 'content' => $data['message']]);
        $history = AssistantMessage::where('user_id', $u->id)->latest('id')->limit(10)->get()->reverse()->map(fn ($m) => ['role' => $m->role, 'content' => $m->content])->values()->all();
        $res = AiGateway::chat(array_merge([['role' => 'system', 'content' => 'Anda AI Assistant Krafio yang membantu UMKM dan kreator membuat konten pemasaran. Jawab ringkas dalam bahasa pengguna.']], $history));
        $text = $res['text'] ?? "Provider AI teks belum dikonfigurasi oleh Admin, jadi saya belum dapat menjawab dengan model sungguhan.\n\nSementara itu, Anda dapat memakai Brainstorming Konten untuk ide terstruktur, atau menghubungi tim support lewat tombol bantuan.";
        if (! $res) {
            Entitlements::refund($u, 'chatbot.basic', 1);
        }
        $m = AssistantMessage::create(['user_id' => $u->id, 'role' => 'assistant', 'content' => $text]);

        return ['data' => ['id' => $m->id, 'role' => 'assistant', 'content' => $m->content, 'created_at' => $m->created_at->toIso8601String()]];
    }
}
