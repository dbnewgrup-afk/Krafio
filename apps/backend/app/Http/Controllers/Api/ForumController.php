<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ForumPost;
use App\Models\ForumReport;
use App\Models\ForumThread;
use App\Models\User;
use App\Notifications\Notice;
use App\Services\Audit;
use App\Services\Forum;
use App\Support\ApiException;
use App\Support\Present;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

/**
 * Forum Diskusi (pengguna). Semua endpoint mewajibkan paket dengan `forum.access`; tidak memakai kredit.
 * Pengguna: baca, buat topik, balas, sunting/hapus tulisan sendiri (≤ 30 menit untuk sunting), lapor ke Admin.
 */
class ForumController extends Controller
{
    public function categories(Request $r)
    {
        $u = $r->user();
        Forum::ensureAccess($u);
        $mute = Forum::activeMute($u);

        return ['data' => Forum::categories($u)->map(fn ($c) => Forum::presentCategory($c, $u))->values(), 'meta' => [
            'muted' => (bool) $mute, 'muted_until' => $mute?->until?->toIso8601String(), 'muted_reason' => $mute?->reason,
            'edit_minutes' => Forum::EDIT_MINUTES, 'report_reasons' => Forum::REPORT_REASONS,
        ]];
    }

    public function threads(Request $r)
    {
        $u = $r->user();
        Forum::ensureAccess($u);
        $data = $r->validate(['category' => 'nullable|string|max:60', 'q' => 'nullable|string|max:100', 'mine' => 'nullable|boolean', 'page' => 'nullable|integer|min:1', 'per_page' => 'nullable|integer|min:1|max:50']);
        $ids = Forum::categories($u)->pluck('id');
        $q = ForumThread::with(['user', 'category', 'lastReplyUser'])->where('status', 'published')->whereIn('category_id', $ids);
        if (! empty($data['category'])) {
            $q->where('category_id', Forum::category($u, $data['category'])->id);
        }
        if (! empty($data['q'])) {
            $term = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $data['q']).'%';
            $q->where(fn ($w) => $w->where('title', 'like', $term)->orWhere('body', 'like', $term));
        }
        if (! empty($data['mine'])) {
            $q->where('user_id', $u->id);
        }
        $q->orderByDesc('is_pinned')->orderByRaw('COALESCE(last_reply_at, created_at) DESC')->orderByDesc('id');

        return Present::paginate($q->paginate($data['per_page'] ?? 20), fn ($t) => Forum::presentThread($t, $u));
    }

    public function createThread(Request $r)
    {
        $u = $r->user();
        Forum::ensureAccess($u);
        Forum::ensureCanWrite($u);
        $data = $r->validate(['category' => 'required', 'title' => 'required|string|min:5|max:150', 'body' => 'required|string|min:10|max:10000'],
            [], ['category' => 'kategori', 'title' => 'judul', 'body' => 'isi']);
        $c = Forum::category($u, $data['category']);
        if ($c->admin_only_posting) {
            throw new ApiException('admin_only', 'Hanya Admin Krafio yang dapat membuat topik di kategori ini.', 403);
        }
        $t = ForumThread::create(['category_id' => $c->id, 'user_id' => $u->id, 'title' => trim($data['title']), 'body' => Forum::clean($data['body'])]);

        return response()->json(['data' => Forum::presentThread($t->load(['user', 'category']), $u, true)], 201);
    }

    public function showThread(Request $r, ForumThread $thread)
    {
        $u = $r->user();
        Forum::ensureAccess($u);
        Forum::thread($u, $thread->load(['user', 'category', 'lastReplyUser']));
        $posts = ForumPost::with('user')->where('thread_id', $thread->id)->where('status', 'published')->orderBy('id')->paginate(max(1, min(100, (int) $r->input('per_page', 50))));

        return ['data' => Forum::presentThread($thread, $u, true) + [
            'can_reply' => ! $thread->is_locked && ! Forum::activeMute($u),
            'posts' => Present::paginate($posts, fn ($p) => Forum::presentPost($p, $u)),
        ]];
    }

    public function updateThread(Request $r, ForumThread $thread)
    {
        $u = $r->user();
        Forum::ensureAccess($u);
        Forum::ensureCanWrite($u);
        Forum::ensureOwnerEditable($u, $thread);
        $data = $r->validate(['title' => 'sometimes|required|string|min:5|max:150', 'body' => 'sometimes|required|string|min:10|max:10000']);
        $thread->update(array_filter(['title' => isset($data['title']) ? trim($data['title']) : null, 'body' => isset($data['body']) ? Forum::clean($data['body']) : null]) + ['edited_at' => now()]);

        return ['data' => Forum::presentThread($thread->load(['user', 'category']), $u, true)];
    }

    public function deleteThread(Request $r, ForumThread $thread)
    {
        $u = $r->user();
        Forum::ensureAccess($u);
        abort_unless($thread->user_id === $u->id && $thread->status === 'published', 404);
        $thread->update(['status' => 'deleted']);

        return response()->noContent();
    }

    public function reply(Request $r, ForumThread $thread)
    {
        $u = $r->user();
        Forum::ensureAccess($u);
        Forum::thread($u, $thread);
        Forum::ensureCanWrite($u);
        if ($thread->is_locked) {
            throw new ApiException('thread_locked', 'Topik ini dikunci Admin sehingga tidak dapat dibalas.', 422);
        }
        $data = $r->validate(['body' => 'required|string|min:2|max:5000'], [], ['body' => 'balasan']);
        $p = DB::transaction(function () use ($thread, $u, $data) {
            $p = ForumPost::create(['thread_id' => $thread->id, 'user_id' => $u->id, 'body' => Forum::clean($data['body'])]);
            Forum::refreshThreadStats($thread);

            return $p;
        });
        if ($thread->user_id !== $u->id && $thread->user) {
            $thread->user->notify(new Notice('Balasan baru di topik Anda', $u->name.' membalas "'.mb_substr($thread->title, 0, 80).'".', 'forum', '/app/forum/t/'.$thread->id));
        }

        return response()->json(['data' => Forum::presentPost($p->load('user'), $u)], 201);
    }

    public function updatePost(Request $r, ForumPost $post)
    {
        $u = $r->user();
        Forum::ensureAccess($u);
        Forum::ensureCanWrite($u);
        Forum::ensureOwnerEditable($u, $post);
        $data = $r->validate(['body' => 'required|string|min:2|max:5000']);
        $post->update(['body' => Forum::clean($data['body']), 'edited_at' => now()]);

        return ['data' => Forum::presentPost($post->load('user'), $u)];
    }

    public function deletePost(Request $r, ForumPost $post)
    {
        $u = $r->user();
        Forum::ensureAccess($u);
        abort_unless($post->user_id === $u->id && $post->status === 'published', 404);
        $post->update(['status' => 'deleted']);
        Forum::refreshThreadStats($post->thread);

        return response()->noContent();
    }

    /** Kirim masukan / laporan ke Admin: kendala sistem, atau konten/perilaku pengguna lain. */
    public function report(Request $r)
    {
        $u = $r->user();
        Forum::ensureAccess($u);
        $data = $r->validate([
            'type' => 'required|in:system,thread,post,user',
            'reason' => ['required', Rule::in(Forum::REPORT_REASONS)],
            'message' => 'required|string|min:10|max:3000',
            'thread_id' => 'nullable|required_if:type,thread|integer',
            'post_id' => 'nullable|required_if:type,post|integer',
            'user_id' => 'nullable|required_if:type,user|integer',
            'page_url' => 'nullable|string|max:300',
        ], [], ['type' => 'jenis laporan', 'reason' => 'alasan', 'message' => 'keterangan']);
        $thread = $post = $target = null;
        if ($data['type'] === 'thread') {
            $thread = Forum::thread($u, ForumThread::findOrFail($data['thread_id']));
            $target = $thread->user_id;
        } elseif ($data['type'] === 'post') {
            $post = ForumPost::where('status', 'published')->findOrFail($data['post_id']);
            $thread = Forum::thread($u, $post->thread);
            $target = $post->user_id;
        } elseif ($data['type'] === 'user') {
            $target = User::where('role', 'user')->findOrFail($data['user_id'])->id;
        }
        if ($target === $u->id) {
            throw new ApiException('cannot_report_self', 'Anda tidak dapat melaporkan diri sendiri.', 422);
        }
        $dup = ForumReport::where('user_id', $u->id)->whereIn('status', ['open', 'in_review'])->where('type', $data['type'])
            ->where('thread_id', $thread?->id)->where('post_id', $post?->id)->where('reported_user_id', $data['type'] === 'system' ? null : $target)
            ->when($data['type'] === 'system', fn ($q) => $q->where('created_at', '>', now()->subMinutes(10)))->exists();
        if ($dup) {
            throw new ApiException('report_exists', 'Laporan Anda untuk hal ini masih diproses Admin.', 409);
        }
        $rep = ForumReport::create(['user_id' => $u->id, 'type' => $data['type'], 'reason' => $data['reason'], 'message' => Forum::clean($data['message']),
            'thread_id' => $thread?->id, 'post_id' => $post?->id, 'reported_user_id' => $data['type'] === 'system' ? null : $target, 'page_url' => $data['page_url'] ?? null]);
        Audit::log('forum.report.created', $rep, ['type' => $rep->type, 'reason' => $rep->reason], $u->id);
        Notification::send(User::where('role', 'admin')->get(), new Notice('Laporan forum baru', '['.$rep->type.'/'.$rep->reason.'] '.mb_substr($rep->message, 0, 120), 'forum', '/admin/forum/reports'));

        return response()->json(['data' => self::presentReport($rep)], 201);
    }

    public function myReports(Request $r)
    {
        $u = $r->user();
        Forum::ensureAccess($u);

        return ['data' => ForumReport::where('user_id', $u->id)->latest('id')->limit(50)->get()->map(fn ($x) => self::presentReport($x))->values()];
    }

    private static function presentReport(ForumReport $x): array
    {
        return ['id' => $x->id, 'type' => $x->type, 'reason' => $x->reason, 'message' => $x->message, 'thread_id' => $x->thread_id, 'post_id' => $x->post_id,
            'status' => $x->status, 'admin_note' => $x->admin_note, 'created_at' => $x->created_at->toIso8601String(), 'handled_at' => $x->handled_at?->toIso8601String()];
    }
}
