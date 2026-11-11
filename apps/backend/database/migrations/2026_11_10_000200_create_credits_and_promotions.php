<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PRD Bab 55 — Sistem Kredit Hybrid, kupon & promo, trial, perpanjangan, upgrade/downgrade.
 * BR-028: kredit (unit pemakaian) punya ledger sendiri, terpisah dari ledger Rupiah (wallet_ledger).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Paket kredit: jatah bulanan paket (source=plan, per periode, tidak di-rollover — BR-027),
        // add-on (berlaku 90 hari), bonus promo, trial.
        Schema::create('credit_packs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('source', 20); // plan | addon | bonus | trial
            $t->string('period', 10)->nullable(); // YYYY-MM untuk source=plan
            $t->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $t->unsignedInteger('credits_total');
            $t->unsignedInteger('credits_used')->default(0);
            $t->string('label')->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->timestamps();
            $t->index(['user_id', 'source', 'expires_at']);
        });

        Schema::create('credit_ledger', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('pack_id')->nullable()->constrained('credit_packs')->nullOnDelete();
            $t->string('direction', 6); // credit | debit
            $t->unsignedInteger('amount');
            $t->string('ref_type', 40)->nullable();
            $t->unsignedBigInteger('ref_id')->nullable();
            $t->string('description');
            $t->timestamp('created_at')->useCurrent();
            $t->index(['user_id', 'created_at']);
        });

        Schema::table('ai_jobs', function (Blueprint $t) {
            $t->json('credit_alloc')->nullable()->after('cost'); // pack_id → kredit, untuk refund tepat sasaran
        });

        Schema::table('users', function (Blueprint $t) {
            $t->boolean('auto_renew')->default(false);
            $t->timestamp('trial_used_at')->nullable();
        });

        Schema::table('subscriptions', function (Blueprint $t) {
            $t->string('scheduled_plan_code', 40)->nullable(); // downgrade berlaku periode berikutnya (BR-034)
            $t->string('scheduled_cycle', 10)->nullable();
            $t->json('promo')->nullable(); // promo berdurasi (55.4.3 butir 8)
            $t->unsignedTinyInteger('reminders_sent')->default(0);
        });

        Schema::table('payments', function (Blueprint $t) {
            $t->json('pricing')->nullable(); // snapshot harga normal, kupon, diskon, prorata, akhir, PPN (55.4.3 butir 5)
            $t->foreignId('coupon_id')->nullable();
        });

        Schema::create('coupons', function (Blueprint $t) {
            $t->id();
            $t->string('code', 40)->unique();
            $t->string('name')->nullable();
            $t->string('type', 15); // percent | fixed | bonus_credit | bonus_days
            $t->unsignedBigInteger('value');
            $t->unsignedBigInteger('max_discount')->nullable();
            $t->unsignedBigInteger('min_purchase')->default(0);
            $t->json('plan_codes')->nullable();
            $t->json('cycles')->nullable();
            $t->boolean('applies_to_addons')->default(false);
            $t->boolean('applies_to_custom')->default(false); // 55.4.3 butir 3
            $t->boolean('first_purchase_only')->default(false);
            $t->unsignedInteger('max_uses')->nullable();
            $t->unsignedInteger('max_uses_per_user')->default(1);
            $t->unsignedInteger('used')->default(0);
            $t->unsignedInteger('reserved')->default(0);
            $t->string('duration', 20)->default('once'); // once | first_n_months | forever
            $t->unsignedTinyInteger('duration_months')->nullable();
            $t->boolean('auto_apply')->default(false); // promo otomatis tanpa kode
            $t->string('campaign', 30)->nullable(); // early_bird, flash_sale, winback, upgrade, referral
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('ends_at')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('coupon_redemptions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $t->string('status', 15)->default('reserved'); // reserved | used | released
            $t->unsignedBigInteger('discount')->default(0);
            $t->timestamps();
        });

        Schema::create('trials', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('phone_hash', 64)->unique(); // BR-033: sekali per nomor HP
            $t->string('plan_code', 40);
            $t->timestamp('ends_at');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trials');
        Schema::dropIfExists('coupon_redemptions');
        Schema::dropIfExists('coupons');
        Schema::dropIfExists('credit_ledger');
        Schema::dropIfExists('credit_packs');
    }
};
