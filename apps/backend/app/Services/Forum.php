<?php

namespace App\Services;

use App\Models\ForumCategory;
use App\Models\ForumMute;
use App\Models\ForumPost;
use App\Models\ForumThread;
use App\Models\User;
use App\Support\ApiException;
use Illuminate\Support\Collection;

/**
 * Aturan Forum Diskusi: privat untuk pelanggan dengan entitlement paket `forum.access` (tidak memakai kredit),
 * kategori & moderasi diatur Admin, pengguna hanya menulis/membalas/melapor.
 */
class Forum
{
    public const ENTITLEMENT = 'forum.access';

    /** Penulis boleh menyunting tulisannya sendiri dalam jendela ini (menit). */
    public const EDIT_MINUTES = 30;

    public const REPORT_REASONS = ['bug', 'error', 'spam', 'abuse', 'harassment', 'scam', 'off_topic', 'other'];

    /** Wajib dipanggil di setiap endpoint forum pengguna (403 feature_locked bila paket tidak mencakup forum). */
    public static function ensureAccess(User $u): void
    {
        if ($u->isAdmin()) {
            throw new ApiException('admin_use_admin_panel', 'Admin mengelola forum dari Admin Panel.', 403);
        }
        Entitlements::ensure($u, self::ENTITLEMENT, 0);
    }

    public static function planCode(User $u): ?string
    {
        return $u->activeSubscription()?->plan_code;
    }

    /** @return Collection<int, ForumCategory> kategori aktif yang boleh diakses paket pengguna */
    public static function categories(User $u): Collection
    {
        $plan = self::planCode($u);

        return ForumCategory::where('is_active', true)->orderBy('sort')->orderBy('id')->get()
            ->filter(fn (ForumCategory $c) => empty($c->plan_codes) || in_array($plan, $c->plan_codes, true))->values();
    }

    public static function category(User $u, string|int $slugOrId): ForumCategory
    {
        $c = self::categories($u)->first(fn ($c) => $c->slug === (string) $slugOrId || (string) $c->id === (string) $slugOrId);
        if (! $c) {
            throw new ApiException('not_found', 'Kategori forum tidak ditemukan atau tidak termasuk paket Anda.', 404);
        }

        return $c;
    }

    /** Topik yang terlihat oleh pengguna (published, kategori aktif & sesuai paket). */
    public static function thread(User $u, ForumThread $t): ForumThread
    {
        $ok = $t->status === 'published' && self::categories($u)->contains('id', $t->category_id);
        if (! $ok) {
            throw new ApiException('not_found', 'Topik tidak ditemukan.', 404);
        }

        return $t;
    }

    public static function activeMute(User $u): ?ForumMute
    {
        return ForumMute::where('user_id', $u->id)->where(fn ($q) => $q->whereNull('until')->orWhere('until', '>', now()))->latest('id')->first();
    }

    public static function ensureCanWrite(User $u): void
    {
        if ($m = self::activeMute($u)) {
            $until = $m->until ? ' sampai '.$m->until->timezone('Asia/Jakarta')->format('d M Y H:i').' WIB' : '';
            throw new ApiException('forum_muted', 'Anda dibatasi menulis di forum'.$until.'. Alasan: '.$m->reason, 403, ['until' => $m->until?->toIso8601String()]);
        }
    }

    public static function ensureOwnerEditable(User $u, ForumThread|ForumPost $m): void
    {
        if ($m->user_id !== $u->id) {
            throw new ApiException('forbidden', 'Anda hanya dapat mengubah tulisan sendiri.', 403);
        }
        if ($m->status !== 'published') {
            throw new ApiException('not_editable', 'Tulisan ini sudah tidak dapat diubah.', 422);
        }
        if ($m->created_at->lt(now()->subMinutes(self::EDIT_MINUTES))) {
            throw new ApiException('edit_window_closed', 'Batas waktu menyunting ('.self::EDIT_MINUTES.' menit) sudah lewat. Kirim balasan baru atau hubungi Admin.', 422);
        }
    }

    public static function author(?User $u): array
    {
        if (! $u) {
            return ['id' => null, 'name' => 'Pengguna dihapus', 'initials' => '?', 'is_admin' => false];
        }
        $name = $u->isAdmin() ? 'Admin Krafio' : $u->name;
        $parts = preg_split('/\s+/', trim($name)) ?: [];

        return ['id' => $u->id, 'name' => $name, 'initials' => mb_strtoupper(mb_substr($parts[0] ?? '?', 0, 1).mb_substr($parts[1] ?? '', 0, 1)), 'is_admin' => $u->isAdmin()];
    }

    public static function presentCategory(ForumCategory $c, User $u): array
    {
        return [
            'id' => $c->id, 'slug' => $c->slug, 'name' => $c->name, 'description' => $c->description, 'icon' => $c->icon,
            'admin_only_posting' => $c->admin_only_posting,
            'threads_count' => ForumThread::where('category_id', $c->id)->where('status', 'published')->count(),
            'last_activity_at' => ForumThread::where('category_id', $c->id)->where('status', 'published')->max('updated_at'),
        ];
    }

    public static function presentThread(ForumThread $t, User $viewer, bool $full = false): array
    {
        $out = [
            'id' => $t->id, 'category' => ['id' => $t->category?->id, 'slug' => $t->category?->slug, 'name' => $t->category?->name],
            'title' => $t->title, 'excerpt' => mb_substr(preg_replace('/\s+/u', ' ', $t->body) ?? '', 0, 180),
            'author' => self::author($t->user), 'is_pinned' => $t->is_pinned, 'is_locked' => $t->is_locked,
            'replies_count' => $t->replies_count, 'last_reply_at' => $t->last_reply_at?->toIso8601String(),
            'last_reply_by' => $t->last_reply_user_id ? self::author($t->lastReplyUser)['name'] : null,
            'created_at' => $t->created_at->toIso8601String(), 'edited_at' => $t->edited_at?->toIso8601String(),
            'is_mine' => $t->user_id === $viewer->id,
            'can_edit' => $t->user_id === $viewer->id && $t->created_at->gt(now()->subMinutes(self::EDIT_MINUTES)),
        ];
        if ($full) {
            $out['body'] = $t->body;
        }

        return $out;
    }

    public static function presentPost(ForumPost $p, User $viewer): array
    {
        return [
            'id' => $p->id, 'body' => $p->body, 'author' => self::author($p->user),
            'created_at' => $p->created_at->toIso8601String(), 'edited_at' => $p->edited_at?->toIso8601String(),
            'is_mine' => $p->user_id === $viewer->id,
            'can_edit' => $p->user_id === $viewer->id && $p->created_at->gt(now()->subMinutes(self::EDIT_MINUTES)),
        ];
    }

    /** Hitung ulang ringkasan balasan topik (dipakai saat balasan dibuat/dihapus/disembunyikan). */
    public static function refreshThreadStats(ForumThread $t): void
    {
        $last = ForumPost::where('thread_id', $t->id)->where('status', 'published')->latest('id')->first();
        $t->forceFill([
            'replies_count' => ForumPost::where('thread_id', $t->id)->where('status', 'published')->count(),
            'last_reply_at' => $last?->created_at, 'last_reply_user_id' => $last?->user_id,
        ])->saveQuietly();
    }

    /** Teks polos: rapikan spasi berlebih tanpa membuang baris baru. HTML ditampilkan apa adanya sebagai teks di UI. */
    public static function clean(string $s): string
    {
        $s = str_replace(["\r\n", "\r"], "\n", $s);
        $s = preg_replace("/[ \t]+\n/", "\n", $s) ?? $s;
        $s = preg_replace("/\n{3,}/", "\n\n", $s) ?? $s;

        return trim($s);
    }
}
