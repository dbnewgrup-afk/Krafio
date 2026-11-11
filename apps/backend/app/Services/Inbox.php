<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\TicketMessage;
use App\Models\User;
use App\Notifications\Notice;
use Illuminate\Support\Facades\Cache;

/**
 * Pusat pesan Krafio: satu sumber logika untuk tiket support yang dipakai Admin Inbox,
 * inbox pengguna, widget CS, dan notifikasi balas cepat (toast).
 */
class Inbox
{
    /** Target waktu respons pertama per prioritas (menit). */
    public const SLA_MINUTES = ['urgent' => 30, 'high' => 120, 'normal' => 240, 'low' => 1440];

    public const STATUSES = ['open', 'pending', 'resolved', 'closed'];

    public const PRIORITIES = ['low', 'normal', 'high', 'urgent'];

    /** Tambah pesan + perbarui ringkasan percakapan (unread, SLA, status) secara konsisten. */
    public static function post(Ticket $t, string $from, string $body, ?User $author = null, ?string $clientId = null, array $attachments = []): TicketMessage
    {
        if ($clientId && ($dup = $t->messages()->where('client_id', $clientId)->first())) {
            return $dup; // idempoten: kirim ulang dari antrean offline tidak menggandakan pesan
        }
        $m = $t->messages()->create([
            'user_id' => $author?->id,
            'from' => $from,
            'body' => $body,
            'attachments' => $attachments ?: null,
            'client_id' => $clientId,
        ]);
        $patch = ['last_message_at' => $m->created_at, 'last_from' => $from, 'archived_at' => null];
        if ($from === 'user') {
            $patch['admin_unread'] = $t->admin_unread + 1;
            $patch['status'] = 'open';
            $patch['sla_due_at'] = $t->sla_due_at && $t->last_from === 'user' ? $t->sla_due_at : now()->addMinutes(self::SLA_MINUTES[$t->priority] ?? 240);
        } else {
            $patch['user_unread'] = $t->user_unread + 1;
            $patch['admin_unread'] = 0;
            $patch['sla_due_at'] = null;
            if (in_array($t->status, ['open', 'resolved', 'closed'], true)) {
                $patch['status'] = 'pending';
            }
            // Pesan pengguna sebelumnya otomatis terbaca saat admin membalas
            $t->messages()->where('from', 'user')->whereNull('read_at')->update(['read_at' => now()]);
        }
        $t->update($patch);
        self::typing($t->id, $from, false);

        if ($from === 'admin') {
            $t->user?->notify(new Notice('Balasan tiket '.$t->number, mb_substr($body, 0, 140), 'support', '/app/support?c='.$t->id));
        }

        return $m;
    }

    public static function markRead(Ticket $t, string $reader): void
    {
        $other = $reader === 'admin' ? 'user' : 'admin';
        $t->messages()->where('from', $other)->whereNull('read_at')->update(['read_at' => now()]);
        $t->update([$reader === 'admin' ? 'admin_unread' : 'user_unread' => 0]);
    }

    public static function activity(Ticket $t, string $action, array $meta = [], ?int $actorId = null): void
    {
        TicketActivity::create(['ticket_id' => $t->id, 'actor_id' => $actorId ?? auth()->id(), 'action' => $action, 'meta' => $meta ?: null]);
    }

    /** Status mengetik disimpan singkat di cache (8 detik) — dibaca lewat polling thread. */
    public static function typing(int $ticketId, string $who, bool $on = true): void
    {
        $key = "inbox:typing:{$ticketId}:{$who}";
        $on ? Cache::put($key, now()->timestamp, 8) : Cache::forget($key);
    }

    public static function isTyping(int $ticketId, string $who): bool
    {
        return (bool) Cache::get("inbox:typing:{$ticketId}:{$who}");
    }

    public static function message(TicketMessage $m, Ticket $t): array
    {
        return [
            'id' => $m->id,
            'client_id' => $m->client_id,
            'from' => $m->from,
            'body' => $m->body,
            'attachments' => $m->attachments ?? [],
            'status' => $m->read_at ? 'read' : 'delivered',
            'created_at' => $m->created_at->toIso8601String(),
            'author' => $m->from === 'admin' ? ($m->user?->name ?? 'Tim Krafio') : ($m->from === 'system' ? 'Sistem' : ($t->user?->name ?? $t->guest_name ?? 'Pengguna')),
        ];
    }

    /** Ringkasan percakapan untuk daftar inbox. $viewer menentukan unread yang dipakai. */
    public static function summary(Ticket $t, string $viewer = 'admin'): array
    {
        $last = $t->relationLoaded('lastMessage') ? $t->lastMessage : $t->messages()->latest('id')->first();
        $name = $t->user?->name ?? $t->guest_name ?? 'Tamu';

        return [
            'id' => $t->id,
            'number' => $t->number,
            'subject' => $t->subject,
            'status' => $t->status,
            'priority' => $t->priority,
            'tags' => $t->tags ?? [],
            'unread' => $viewer === 'admin' ? $t->admin_unread : $t->user_unread,
            'last_from' => $t->last_from,
            'last_message_at' => ($t->last_message_at ?? $t->updated_at)->toIso8601String(),
            'sla_due_at' => $t->sla_due_at?->toIso8601String(),
            'sla_breached' => $t->sla_due_at && $t->sla_due_at->isPast() && in_array($t->status, ['open', 'pending'], true),
            'archived' => (bool) $t->archived_at,
            'preview' => $last ? mb_substr($last->body, 0, 140) : '',
            'requester' => ['id' => $t->user_id, 'name' => $name, 'email' => $t->user?->email ?? $t->guest_email, 'online' => $t->user?->last_login_at && $t->user->last_login_at->gt(now()->subMinutes(10))],
            'assignee' => $t->assignee ? ['id' => $t->assignee->id, 'name' => $t->assignee->name] : null,
        ];
    }
}
