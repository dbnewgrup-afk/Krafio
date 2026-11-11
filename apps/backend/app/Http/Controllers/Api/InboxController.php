<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Services\Audit;
use App\Services\Inbox;
use App\Services\Settings;
use App\Services\Wallet;
use App\Support\ApiException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Pusat pesan dua sisi:
 *  - Admin: inbox semua percakapan, monitoring (statistik, SLA), kontrol (assign, status, prioritas, tag, catatan, bulk).
 *  - Pengguna: inbox tiket miliknya dengan percakapan real-time (polling) dan balas cepat.
 * Real-time memakai polling ringan (feed?since=) — tanpa server WebSocket tambahan.
 */
class InboxController extends Controller
{
    /* ============================ ADMIN ============================ */

    private function adminQuery(Request $r)
    {
        $q = Ticket::query()->with(['user', 'assignee', 'lastMessage']);
        $filter = $r->query('filter', 'all');
        match ($filter) {
            'unread' => $q->where('admin_unread', '>', 0),
            'mine' => $q->where('assignee_id', $r->user()->id),
            'unassigned' => $q->whereNull('assignee_id')->whereIn('status', ['open', 'pending']),
            'resolved' => $q->whereIn('status', ['resolved', 'closed']),
            'archived' => $q->whereNotNull('archived_at'),
            default => null,
        };
        if ($filter !== 'archived') {
            $q->whereNull('archived_at');
        }
        if ($s = trim((string) $r->query('search'))) {
            $q->where(function ($w) use ($s) {
                $w->where('subject', 'like', "%{$s}%")->orWhere('number', 'like', "%{$s}%")->orWhere('guest_name', 'like', "%{$s}%")->orWhere('guest_email', 'like', "%{$s}%")
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%"));
            });
        }
        foreach (['priority', 'status'] as $f) {
            if ($v = $r->query($f)) {
                $q->whereIn($f, explode(',', $v));
            }
        }
        if ($a = $r->query('assignee')) {
            $a === 'none' ? $q->whereNull('assignee_id') : $q->where('assignee_id', (int) $a);
        }
        if ($tag = $r->query('tag')) {
            $q->whereJsonContains('tags', $tag);
        }
        if ($from = $r->query('from')) {
            $q->where('last_message_at', '>=', $from);
        }
        if ($to = $r->query('to')) {
            $q->where('last_message_at', '<=', $to.' 23:59:59');
        }
        // SLA terlewat selalu naik ke atas, lalu sesuai urutan yang dipilih
        $q->orderByRaw("CASE WHEN sla_due_at IS NOT NULL AND sla_due_at < ? AND status IN ('open','pending') THEN 0 ELSE 1 END", [now()]);
        match ($r->query('sort', 'recent')) {
            'oldest' => $q->orderBy('last_message_at'),
            'priority' => $q->orderByRaw("FIELD(priority,'urgent','high','normal','low')")->orderByDesc('last_message_at'),
            default => $q->orderByDesc('last_message_at'),
        };

        return $q;
    }

    public function adminIndex(Request $r)
    {
        $page = $this->adminQuery($r)->paginate(min(100, max(10, (int) $r->query('per_page', 40))));

        return [
            'data' => collect($page->items())->map(fn ($t) => Inbox::summary($t, 'admin')),
            'meta' => ['total' => $page->total(), 'page' => $page->currentPage(), 'last_page' => $page->lastPage()],
            'stats' => $this->stats(),
            'admins' => User::where('role', 'admin')->orderBy('name')->get(['id', 'name'])->map(fn ($u) => ['id' => $u->id, 'name' => $u->name]),
            'tags' => $this->knownTags(),
        ];
    }

    private function stats(): array
    {
        $today = now()->startOfDay();
        // Rata-rata waktu respons: jeda pesan user → balasan admin berikutnya (30 hari terakhir, maks 500 pasangan)
        $rows = DB::table('ticket_messages as u')
            ->join('ticket_messages as a', function ($j) {
                $j->on('a.ticket_id', '=', 'u.ticket_id')->where('a.from', 'admin')->whereColumn('a.id', '>', 'u.id');
            })
            ->where('u.from', 'user')->where('u.created_at', '>=', now()->subDays(30))
            ->groupBy('u.id', 'u.created_at')
            ->selectRaw('u.created_at as asked, MIN(a.created_at) as answered')
            ->limit(500)->get();
        $avg = $rows->count() ? (int) round($rows->avg(fn ($x) => max(0, strtotime($x->answered) - strtotime($x->asked))) / 60) : null;

        return [
            'unread' => (int) Ticket::whereNull('archived_at')->sum('admin_unread'),
            'open' => Ticket::whereNull('archived_at')->whereIn('status', ['open', 'pending'])->count(),
            'avg_response_minutes' => $avg,
            'resolved_today' => DB::table('ticket_activities')->where('action', 'status')->where('created_at', '>=', $today)->where('meta->to', 'resolved')->distinct('ticket_id')->count('ticket_id'),
            'sla_breached' => Ticket::whereNull('archived_at')->whereIn('status', ['open', 'pending'])->where('sla_due_at', '<', now())->count(),
        ];
    }

    private function knownTags(): array
    {
        return Ticket::whereNotNull('tags')->pluck('tags')->flatten()->filter()->unique()->values()->take(40)->all();
    }

    public function adminShow(Request $r, Ticket $ticket)
    {
        if ($r->boolean('mark_read', true)) {
            Inbox::markRead($ticket, 'admin');
        }
        $ticket->load(['user', 'assignee']);
        $u = $ticket->user;
        $after = (int) $r->query('after', 0);

        return ['data' => [
            'conversation' => Inbox::summary($ticket, 'admin'),
            'messages' => $ticket->messages()->with('user')->where('id', '>', $after)->oldest('id')->get()->map(fn ($m) => Inbox::message($m, $ticket)),
            'typing' => Inbox::isTyping($ticket->id, 'user'),
            'read_upto' => (int) $ticket->messages()->where('from', 'admin')->whereNotNull('read_at')->max('id'),
            'notes' => $ticket->notes()->with('author')->latest()->get()->map(fn ($n) => ['id' => $n->id, 'body' => $n->body, 'author' => $n->author?->name ?? 'Admin', 'created_at' => $n->created_at->toIso8601String()]),
            'activity' => $ticket->activities()->with('actor')->latest('id')->limit(50)->get()->map(fn ($a) => ['id' => $a->id, 'action' => $a->action, 'meta' => $a->meta, 'actor' => $a->actor?->name ?? 'Sistem', 'created_at' => $a->created_at->toIso8601String()]),
            'profile' => $u ? [
                'id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'phone' => $u->phone, 'status' => $u->status,
                'plan' => $u->subscriptions()->latest('id')->value('plan_name'),
                'joined_at' => $u->created_at?->toIso8601String(),
                'last_login_at' => $u->last_login_at?->toIso8601String(),
                'orders' => DB::table('payments')->where('user_id', $u->id)->where('status', 'paid')->count(),
                'topup_balance' => Wallet::balance($u->id, 'topup'),
                'tickets' => Ticket::where('user_id', $u->id)->count(),
                'recent' => Ticket::where('user_id', $u->id)->where('id', '!=', $ticket->id)->latest('id')->limit(4)->get(['id', 'number', 'subject', 'status'])->toArray(),
            ] : ['id' => null, 'name' => $ticket->guest_name, 'email' => $ticket->guest_email, 'guest' => true],
            'page' => $ticket->page,
        ]];
    }

    public function adminReply(Request $r, Ticket $ticket)
    {
        $d = $r->validate(['body' => 'required|string|max:5000', 'client_id' => 'nullable|string|max:40', 'attachments' => 'nullable|array|max:5', 'attachments.*.url' => 'required_with:attachments|string|max:255', 'attachments.*.name' => 'nullable|string|max:120']);
        $m = Inbox::post($ticket, 'admin', $d['body'], $r->user(), $d['client_id'] ?? null, $d['attachments'] ?? []);
        if (! $ticket->assignee_id) {
            $ticket->update(['assignee_id' => $r->user()->id]);
            Inbox::activity($ticket, 'assigned', ['to' => $r->user()->name, 'auto' => true]);
        }
        Audit::log('admin.ticket.replied', $ticket);

        return response()->json(['data' => Inbox::message($m->fresh(), $ticket)], 201);
    }

    public function adminUpdate(Request $r, Ticket $ticket)
    {
        $d = $r->validate([
            'status' => 'sometimes|in:'.implode(',', Inbox::STATUSES),
            'priority' => 'sometimes|in:'.implode(',', Inbox::PRIORITIES),
            'assignee_id' => 'sometimes|nullable|integer',
            'tags' => 'sometimes|array|max:8', 'tags.*' => 'string|max:24',
            'archived' => 'sometimes|boolean',
        ]);
        $this->apply($ticket, $d, $r->user());

        return ['data' => Inbox::summary($ticket->fresh(['user', 'assignee']), 'admin')];
    }

    /** Terapkan perubahan + catat activity log per field (dipakai juga oleh bulk). */
    private function apply(Ticket $t, array $d, User $actor): void
    {
        if (array_key_exists('assignee_id', $d)) {
            $to = $d['assignee_id'] ? User::where('role', 'admin')->find($d['assignee_id']) : null;
            if ($d['assignee_id'] && ! $to) {
                throw new ApiException('validation_failed', 'Admin tidak ditemukan.', 422);
            }
            if ($t->assignee_id !== $to?->id) {
                $t->assignee_id = $to?->id;
                Inbox::activity($t, 'assigned', ['to' => $to?->name], $actor->id);
            }
        }
        foreach (['status', 'priority'] as $f) {
            if (isset($d[$f]) && $t->{$f} !== $d[$f]) {
                Inbox::activity($t, $f, ['from' => $t->{$f}, 'to' => $d[$f]], $actor->id);
                $t->{$f} = $d[$f];
                if ($f === 'priority' && $t->last_from === 'user' && in_array($t->status, ['open', 'pending'], true)) {
                    $t->sla_due_at = ($t->last_message_at ?? now())->copy()->addMinutes(Inbox::SLA_MINUTES[$d[$f]]);
                }
                if ($f === 'status' && in_array($d[$f], ['resolved', 'closed'], true)) {
                    $t->sla_due_at = null;
                }
            }
        }
        if (array_key_exists('tags', $d)) {
            $tags = collect($d['tags'])->map(fn ($x) => Str::lower(trim($x)))->filter()->unique()->values()->all();
            Inbox::activity($t, 'tags', ['to' => $tags], $actor->id);
            $t->tags = $tags;
        }
        if (array_key_exists('archived', $d)) {
            $t->archived_at = $d['archived'] ? now() : null;
            Inbox::activity($t, $d['archived'] ? 'archived' : 'unarchived', [], $actor->id);
        }
        $t->save();
        Audit::log('admin.ticket.updated', $t, array_keys($d));
    }

    public function adminBulk(Request $r)
    {
        $d = $r->validate(['ids' => 'required|array|min:1|max:200', 'ids.*' => 'integer', 'action' => 'required|in:read,assign,resolve,archive,priority', 'assignee_id' => 'nullable|integer', 'priority' => 'nullable|in:'.implode(',', Inbox::PRIORITIES)]);
        $n = 0;
        foreach (Ticket::whereIn('id', $d['ids'])->get() as $t) {
            match ($d['action']) {
                'read' => Inbox::markRead($t, 'admin'),
                'assign' => $this->apply($t, ['assignee_id' => $d['assignee_id'] ?? null], $r->user()),
                'resolve' => $this->apply($t, ['status' => 'resolved'], $r->user()),
                'archive' => $this->apply($t, ['archived' => true], $r->user()),
                'priority' => $this->apply($t, ['priority' => $d['priority'] ?? 'normal'], $r->user()),
            };
            $n++;
        }

        return ['data' => ['affected' => $n]];
    }

    public function adminNote(Request $r, Ticket $ticket)
    {
        $n = $ticket->notes()->create(['author_id' => $r->user()->id, 'body' => $r->validate(['body' => 'required|string|max:3000'])['body']]);
        Inbox::activity($ticket, 'note');

        return response()->json(['data' => ['id' => $n->id, 'body' => $n->body, 'author' => $r->user()->name, 'created_at' => $n->created_at->toIso8601String()]], 201);
    }

    public function adminTyping(Ticket $ticket)
    {
        Inbox::typing($ticket->id, 'admin');

        return response()->noContent();
    }

    public function adminExport(Ticket $ticket)
    {
        $ticket->load('user');
        $lines = ["Percakapan {$ticket->number} — {$ticket->subject}", 'Status: '.$ticket->status.' · Prioritas: '.$ticket->priority, str_repeat('-', 60)];
        foreach ($ticket->messages()->with('user')->oldest('id')->get() as $m) {
            $msg = Inbox::message($m, $ticket);
            $lines[] = '['.$m->created_at->timezone('Asia/Jakarta')->format('d M Y H:i').'] '.$msg['author'].': '.$m->body;
        }
        Audit::log('admin.ticket.exported', $ticket);

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=utf-8', 'Content-Disposition' => 'attachment; filename="'.$ticket->number.'.txt"']);
    }

    /** Feed pesan baru dari pengguna sejak id tertentu (untuk toast balas cepat di seluruh dashboard admin). */
    public function adminFeed(Request $r)
    {
        $since = (int) $r->query('since', 0);
        $q = TicketMessage::with(['ticket.user'])->where('from', 'user')->whereNull('read_at');
        $since ? $q->where('id', '>', $since) : $q->where('created_at', '>=', now()->subMinutes(10));
        $items = $q->oldest('id')->limit(20)->get()->filter(fn ($m) => $m->ticket && ! $m->ticket->archived_at);

        return ['data' => [
            'cursor' => (int) TicketMessage::max('id'),
            'unread' => (int) Ticket::whereNull('archived_at')->sum('admin_unread'),
            'items' => $items->map(fn ($m) => $this->feedItem($m))->values(),
            'recent' => Ticket::with(['user', 'lastMessage'])->whereNull('archived_at')->where('admin_unread', '>', 0)->orderByDesc('last_message_at')->limit(8)->get()->map(fn ($t) => Inbox::summary($t, 'admin')),
        ]];
    }

    private function feedItem(TicketMessage $m): array
    {
        $t = $m->ticket;

        return ['id' => $m->id, 'conversation_id' => $t->id, 'number' => $t->number, 'subject' => $t->subject, 'body' => mb_substr($m->body, 0, 220), 'created_at' => $m->created_at->toIso8601String(), 'sender' => $m->from === 'admin' ? ($m->user?->name ?? 'Tim Krafio') : ($t->user?->name ?? $t->guest_name ?? 'Pengguna')];
    }

    public function cannedReplies()
    {
        return ['data' => Settings::get('support.canned_replies', [
            ['key' => 'salam', 'title' => 'Salam pembuka', 'body' => 'Halo, terima kasih sudah menghubungi Krafio. Kami bantu cek sekarang ya.'],
            ['key' => 'topup', 'title' => 'Top-up belum masuk', 'body' => 'Saldo top-up bertambah otomatis setelah payment gateway mengonfirmasi pembayaran. Boleh kirimkan nomor invoice-nya agar kami cek?'],
            ['key' => 'withdraw', 'title' => 'Estimasi withdrawal', 'body' => 'Withdrawal diproses 1–3 hari kerja setelah pengajuan dinyatakan valid. Status terbaru dapat dilihat di menu Penarikan.'],
            ['key' => 'kredit', 'title' => 'Kredit job gagal', 'body' => 'Bila job gagal di sisi sistem atau penyedia AI, kredit dikembalikan otomatis ke paket asalnya. Silakan cek riwayat pemakaian.'],
            ['key' => 'selesai', 'title' => 'Penutup', 'body' => 'Senang bisa membantu. Bila masih ada kendala, balas pesan ini kapan saja.'],
        ])];
    }

    /* ============================ USER ============================ */

    private function own(Request $r, Ticket $t): void
    {
        abort_unless($t->user_id === $r->user()->id, 404);
    }

    public function userIndex(Request $r)
    {
        $q = Ticket::with(['user', 'lastMessage'])->where('user_id', $r->user()->id);
        if (($f = $r->query('filter')) === 'unread') {
            $q->where('user_unread', '>', 0);
        } elseif ($f === 'resolved') {
            $q->whereIn('status', ['resolved', 'closed']);
        } elseif ($f === 'open') {
            $q->whereIn('status', ['open', 'pending']);
        }
        if ($s = trim((string) $r->query('search'))) {
            $q->where(fn ($w) => $w->where('subject', 'like', "%{$s}%")->orWhere('number', 'like', "%{$s}%"));
        }

        return ['data' => $q->orderByDesc('last_message_at')->limit(100)->get()->map(fn ($t) => Inbox::summary($t, 'user'))];
    }

    public function userShow(Request $r, Ticket $ticket)
    {
        $this->own($r, $ticket);
        Inbox::markRead($ticket, 'user');
        $after = (int) $r->query('after', 0);

        return ['data' => [
            'conversation' => Inbox::summary($ticket->fresh(['user', 'assignee']), 'user'),
            'messages' => $ticket->messages()->with('user')->where('id', '>', $after)->oldest('id')->get()->map(fn ($m) => Inbox::message($m, $ticket)),
            'typing' => Inbox::isTyping($ticket->id, 'admin'),
            'read_upto' => (int) $ticket->messages()->where('from', 'user')->whereNotNull('read_at')->max('id'),
        ]];
    }

    public function userCreate(Request $r)
    {
        $d = $r->validate(['subject' => 'required|string|min:4|max:160', 'body' => 'required|string|min:2|max:5000', 'page' => 'nullable|string|max:255', 'client_id' => 'nullable|string|max:40']);
        $t = DB::transaction(function () use ($r, $d) {
            $t = Ticket::create(['number' => 'KRF-'.strtoupper(Str::random(6)), 'user_id' => $r->user()->id, 'subject' => $d['subject'], 'page' => $d['page'] ?? null]);
            Inbox::post($t, 'user', $d['body'], $r->user(), $d['client_id'] ?? null);
            Inbox::activity($t, 'created', [], $r->user()->id);

            return $t;
        });

        return response()->json(['data' => Inbox::summary($t->fresh(['user', 'assignee', 'lastMessage']), 'user')], 201);
    }

    public function userReply(Request $r, Ticket $ticket)
    {
        $this->own($r, $ticket);
        $d = $r->validate(['body' => 'required|string|max:5000', 'client_id' => 'nullable|string|max:40', 'attachments' => 'nullable|array|max:5', 'attachments.*.url' => 'required_with:attachments|string|max:255', 'attachments.*.name' => 'nullable|string|max:120']);
        if ($ticket->status === 'closed') {
            throw new ApiException('conversation_closed', 'Percakapan ini sudah ditutup. Buat percakapan baru.', 422);
        }
        $m = Inbox::post($ticket, 'user', $d['body'], $r->user(), $d['client_id'] ?? null, $d['attachments'] ?? []);

        return response()->json(['data' => Inbox::message($m->fresh(), $ticket)], 201);
    }

    public function userTyping(Request $r, Ticket $ticket)
    {
        $this->own($r, $ticket);
        Inbox::typing($ticket->id, 'user');

        return response()->noContent();
    }

    /** Feed balasan admin untuk pengguna (toast balas cepat di panel user). */
    public function userFeed(Request $r)
    {
        $uid = $r->user()->id;
        $since = (int) $r->query('since', 0);
        $q = TicketMessage::with(['ticket.user', 'user'])->where('from', 'admin')->whereNull('read_at')->whereHas('ticket', fn ($t) => $t->where('user_id', $uid));
        $since ? $q->where('id', '>', $since) : $q->where('created_at', '>=', now()->subMinutes(10));

        return ['data' => [
            'cursor' => (int) TicketMessage::max('id'),
            'unread' => (int) Ticket::where('user_id', $uid)->sum('user_unread'),
            'items' => $q->oldest('id')->limit(20)->get()->map(fn ($m) => $this->feedItem($m))->values(),
            'recent' => Ticket::with(['user', 'lastMessage'])->where('user_id', $uid)->where('user_unread', '>', 0)->orderByDesc('last_message_at')->limit(8)->get()->map(fn ($t) => Inbox::summary($t, 'user')),
        ]];
    }

    public function userRead(Request $r, Ticket $ticket)
    {
        $this->own($r, $ticket);
        Inbox::markRead($ticket, 'user');

        return response()->noContent();
    }

    public function adminRead(Ticket $ticket)
    {
        Inbox::markRead($ticket, 'admin');

        return response()->noContent();
    }
}
