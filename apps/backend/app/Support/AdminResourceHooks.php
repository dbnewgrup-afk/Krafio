<?php

namespace App\Support;

use App\Http\Controllers\Api\StudioController;
use App\Models\AiJob;
use App\Models\User;
use App\Notifications\Notice;
use App\Services\Credits;
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

        return null;
    }

    public static function beforeUpdate(string $resource, Model $m, array $data): void
    {
        if ($resource === 'ai-jobs' && ! in_array($m->status, ['queued', 'processing'], true)) {
            throw new ApiException('not_cancellable', 'Hanya job yang masih antre/diproses yang bisa dibatalkan.', 422);
        }
    }

    public static function afterUpdate(string $resource, Model $m, array $before): void
    {
        if ($resource === 'ai-jobs' && $m instanceof AiJob && in_array($before['status'] ?? null, ['queued', 'processing'], true)) {
            $m->update(['completed_at' => now(), 'error' => $m->error ?: 'Dihentikan oleh admin']);
            StudioController::refundJob($m, 'Job #'.$m->id.' dihentikan admin');
        }
    }

    public static function beforeDelete(string $resource, Model $m): void
    {
        if ($resource === 'uploads' && $m->path) {
            Storage::disk($m->disk ?: 'local')->delete($m->path);
        }
    }
}
