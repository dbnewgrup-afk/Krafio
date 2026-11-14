<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * B12 (audit 14 Nov 2026): dua request bersamaan di awal bulan bisa membuat dua pack kredit paket untuk periode yang sama.
 * Unique index (user, sumber, periode, langganan) — pack non-paket punya period/subscription NULL sehingga tidak terdampak.
 * Pack ganda yang sudah ada digabung dulu (yang terpakai dipertahankan).
 */
return new class extends Migration
{
    public function up(): void
    {
        $dups = DB::table('credit_packs')->select('user_id', 'source', 'period', 'subscription_id', DB::raw('MIN(id) keep_id'), DB::raw('COUNT(*) n'))
            ->whereNotNull('period')->whereNotNull('subscription_id')->groupBy('user_id', 'source', 'period', 'subscription_id')->having('n', '>', 1)->get();
        foreach ($dups as $d) {
            $extra = DB::table('credit_packs')->where(['user_id' => $d->user_id, 'source' => $d->source, 'period' => $d->period, 'subscription_id' => $d->subscription_id])->where('id', '!=', $d->keep_id);
            $used = (int) (clone $extra)->sum('credits_used');
            DB::table('credit_packs')->where('id', $d->keep_id)->increment('credits_used', $used);
            DB::table('credit_ledger')->whereIn('pack_id', (clone $extra)->pluck('id'))->update(['pack_id' => $d->keep_id]);
            $extra->delete();
        }
        Schema::table('credit_packs', function (Blueprint $t) {
            $t->unique(['user_id', 'source', 'period', 'subscription_id'], 'credit_packs_plan_period_unique');
        });
    }

    public function down(): void
    {
        Schema::table('credit_packs', fn (Blueprint $t) => $t->dropUnique('credit_packs_plan_period_unique'));
    }
};
