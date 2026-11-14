<?php

namespace App\Support;

use App\Http\Controllers\Api\StudioController;
use App\Models\AiJob;
use App\Models\SeoRule;
use App\Models\User;
use App\Notifications\Notice;
use App\Services\Credits;
use App\Services\Messenger;
use App\Services\Seo\ContentRules;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Efek samping CRUD generik Admin yang tidak boleh dilakukan dengan sekadar insert/update baris:
 * kredit bonus harus lewat Credits::grant (ledger kredit), job dibatalkan → kuota dikembalikan,
 * hapus unggahan → berkas di storage ikut dihapus.
 */
class AdminResourceHooks
{
    /** @return Model|null model yang dibuat, atau null bila memakai create standar */
    public static function create(string $resource, array $data, Request $r): ?Model
    {
        if ($resource === 'credit-packs') {
            $u = User::findOrFail($data['user_id']);
            if ($u->isAdmin()) {
                throw new ApiException('admin_no_features', 'Akun admin adalah pengelola sehingga tidak menerima kredit.', 422);
            }
            $p = Credits::grant($u, 'bonus', (int) $data['credits_total'], isset($data['expires_at']) ? new \DateTime($data['expires_at']) : now()->addDays(90), $data['label'], 'admin', $r->user()->id);
            $u->notify(new Notice('Kredit bonus', number_format((int) $data['credits_total'], 0, ',', '.').' kredit ditambahkan ke akun Anda.', 'subscription', '/app/usage'));

            return $p;
        }
        // Forum: pengumuman/balasan dibuat atas nama Admin; bisu dicatat pembuatnya
        if ($resource === 'forum-threads') {
            return \App\Models\ForumThread::create($data + ['user_id' => $r->user()->id, 'status' => $data['status'] ?? 'published']);
        }
        if ($resource === 'forum-posts') {
            $t = \App\Models\ForumThread::findOrFail($data['thread_id']);
            $p = \App\Models\ForumPost::create($data + ['user_id' => $r->user()->id, 'status' => $data['status'] ?? 'published']);
            \App\Services\Forum::refreshThreadStats($t);
            $t->user && ! $t->user->isAdmin() && $t->user->notify(new Notice('Admin membalas topik Anda', mb_substr($t->title, 0, 80), 'forum', '/app/forum/t/'.$t->id));

            return $p;
        }
        if ($resource === 'forum-mutes') {
            $target = User::findOrFail($data['user_id']);
            if ($target->isAdmin()) {
                throw new ApiException('cannot_mute_admin', 'Akun admin tidak dapat dibisukan.', 422);
            }
            $m = \App\Models\ForumMute::create($data + ['created_by' => $r->user()->id]);
            $until = $m->until ? ' sampai '.$m->until->timezone('Asia/Jakarta')->format('d M Y H:i').' WIB' : '';
            $target->notify(new Notice('Akses menulis forum dibatasi', 'Anda tidak dapat menulis di forum'.$until.'. Alasan: '.$m->reason, 'forum', '/app/forum'));

            return $m;
        }
        if ($resource === 'seo-rules') {
            $data['engine'] = ($data['engine'] ?? null) ?: 'audit';
            $data['tier'] = ($data['tier'] ?? null) ?: 'basic';
            ContentRules::assertValid($data); // rule analisis konten: kode & parameter harus dikenal

            return SeoRule::create($data);
        }

        return null;
    }

    public static function beforeUpdate(string $resource, Model $m, array $data): void
    {
        if ($resource === 'seo-rules' && $m instanceof SeoRule) {
            if (isset($data['code']) && $data['code'] !== $m->code && $m->engine === 'content') {
                throw new ApiException('rule_code_locked', 'Kode rule analisis konten tidak dapat diubah karena terhubung ke logika analisis.', 422, [], ['code' => ['Kode tidak dapat diubah.']]);
            }
            if (! empty($data['engine']) && $data['engine'] !== $m->engine) {
                throw new ApiException('rule_engine_locked', 'Engine rule tidak dapat diubah. Buat rule baru bila perlu.', 422, [], ['engine' => ['Engine tidak dapat diubah.']]);
            }
            ContentRules::assertValid($data, $m);
        }
        if ($resource === 'ai-jobs' && ! in_array($m->status, ['queued', 'processing'], true)) {
            throw new ApiException('not_cancellable', 'Hanya job yang masih antre/diproses yang bisa dibatalkan.', 422);
        }
        // Komisi yang sudah masuk saldo / dibayar tidak dibatalkan lewat sini (koreksi = penyesuaian saldo beralasan)
        if ($resource === 'commissions' && ! in_array($m->status, ['pending', 'approved'], true)) {
            throw new ApiException('not_reversible', 'Hanya komisi yang masih dalam masa tahan yang bisa dibatalkan.', 422);
        }
    }

    public static function afterUpdate(string $resource, Model $m, array $before): void
    {
        if (in_array($resource, ['forum-threads', 'forum-posts'], true) && ($before['status'] ?? null) !== $m->status) {
            if ($resource === 'forum-posts') {
                \App\Services\Forum::refreshThreadStats($m->thread);
            }
            $author = $m->user;
            if (in_array($m->status, ['hidden', 'deleted'], true) && $author && ! $author->isAdmin()) {
                $what = $resource === 'forum-threads' ? 'Topik "'.mb_substr($m->title, 0, 60).'"' : 'Balasan Anda';
                $author->notify(new Notice('Tulisan forum dimoderasi', $what.' disembunyikan Admin.'.($m->moderation_note ? ' Catatan: '.$m->moderation_note : ''), 'forum', '/app/forum'));
            }
        }
        if ($resource === 'forum-reports' && ($before['status'] ?? null) !== $m->status) {
            $m->forceFill(['handled_by' => auth()->id(), 'handled_at' => now()])->saveQuietly();
            if (in_array($m->status, ['resolved', 'rejected'], true) && $m->user) {
                $m->user->notify(new Notice('Laporan forum Anda '.($m->status === 'resolved' ? 'selesai ditangani' : 'ditutup'), $m->admin_note ?: 'Terima kasih atas laporan Anda.', 'forum', '/app/forum/reports'));
            }
        }
        // Domain kustom diubah admin → wajib verifikasi DNS ulang (token baru)
        if ($resource === 'websites' && array_key_exists('custom_domain', $before) && $before['custom_domain'] !== $m->custom_domain) {
            $m->custom_domain
                ? $m->forceFill(['custom_domain' => strtolower($m->custom_domain), 'domain_status' => 'pending', 'domain_token' => \Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(32)), 'domain_verified_at' => null, 'domain_error' => null])->save()
                : \App\Services\CustomDomains::remove($m);
        }
        if ($resource === 'commissions' && $m->status === 'reversed' && ($before['status'] ?? null) !== 'reversed') {
            $m->forceFill(['reversed_at' => now()])->save();
            if ($ref = User::find($m->referrer_id)) {
                $amount = 'Rp'.number_format((int) $m->amount, 0, ',', '.');
                Messenger::notify($ref, 'commission_reversed', ['amount' => $amount, 'reason' => (string) $m->reversal_reason, 'link' => rtrim((string) config('krafio.frontend_url'), '/').'/app/affiliate'],
                    'Komisi dibatalkan', 'Komisi '.$amount.' dibatalkan: '.$m->reversal_reason, '/app/affiliate');
            }
        }
        if ($resource === 'ai-jobs' && $m instanceof AiJob && in_array($before['status'] ?? null, ['queued', 'processing'], true)) {
            $m->update(['completed_at' => now(), 'error' => $m->error ?: 'Dihentikan oleh admin']);
            StudioController::refundJob($m, 'Job #'.$m->id.' dihentikan admin');
        }
    }

    public static function beforeDelete(string $resource, Model $m): void
    {
        if ($resource === 'forum-posts') {
            $m->forceFill(['status' => 'deleted'])->saveQuietly(); // agar ringkasan balasan topik ikut terkoreksi
            \App\Services\Forum::refreshThreadStats($m->thread);
        }
        // Rule analisis konten terhubung ke logika analisis: cukup dinonaktifkan, tidak dihapus
        if ($resource === 'seo-rules' && $m instanceof SeoRule && $m->engine === 'content') {
            throw new ApiException('rule_locked', 'Rule analisis konten tidak dapat dihapus. Nonaktifkan saja bila tidak dipakai.', 422);
        }
        if ($resource === 'uploads' && $m->path) {
            Storage::disk($m->disk ?: 'local')->delete($m->path);
        }
    }
}
