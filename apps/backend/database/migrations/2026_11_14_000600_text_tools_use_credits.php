<?php

use App\Models\Plan;
use Illuminate\Database\Migrations\Migration;

/**
 * Tool teks (content.brainstorm) memakai Kredit Krafio, bukan kuota request terpisah.
 * Kompensasi: kuota request lama tiap paket ditambahkan ke kredit bulanan paket (berlaku untuk langganan baru/perpanjangan).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (Plan::all() as $p) {
            $q = (array) $p->quotas;
            if (! array_key_exists('content.brainstorm', $q)) {
                continue;
            }
            $extra = (int) $q['content.brainstorm'];
            unset($q['content.brainstorm']);
            if (isset($q['credits']) || $extra > 0) {
                $q['credits'] = (int) ($q['credits'] ?? 0) + $extra;
            }
            $p->update(['quotas' => $q]);
        }
    }

    public function down(): void {}
};
