<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Skema domain Krafio — PRD Bab 11.3 dan Bab 32 (Identity, Catalog & Entitlement, Finance,
 * Affiliate, AI & Media, Website Builder & SEO, Social, Platform & Operasi).
 * Prinsip keuangan (Bab 11.4): nominal integer Rupiah, ledger append-only, saldo top-up dan komisi terpisah.
 */
return new class extends Migration
{
    public function up(): void
    {
        /* ---------------- Identity ---------------- */
        Schema::table('users', function (Blueprint $t) {
            $t->string('phone', 20)->nullable()->after('email');
            $t->string('role', 10)->default('user')->index()->after('phone');
            $t->string('status', 30)->default('pending_verification')->index()->after('role');
            $t->text('status_note')->nullable()->after('status');
            $t->string('locale', 5)->default('id');
            $t->string('theme', 10)->default('system');
            $t->string('loader', 15)->default('auto');
            $t->string('referral_code', 16)->nullable()->unique();
            $t->foreignId('referred_by')->nullable()->constrained('users')->nullOnDelete();
            $t->text('mfa_secret')->nullable();
            $t->boolean('mfa_enabled')->default(false);
            $t->timestamp('ai_consent_at')->nullable();
            $t->timestamp('last_login_at')->nullable();
            $t->string('last_login_ip', 45)->nullable();
            $t->json('notification_prefs')->nullable();
            $t->unsignedTinyInteger('failed_logins')->default(0);
            $t->softDeletes();
        });

        Schema::create('otp_codes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('purpose', 30)->default('verify');
            $t->string('channel', 15)->default('email');
            $t->string('destination');
            $t->string('code_hash');
            $t->unsignedTinyInteger('attempts')->default(0);
            $t->timestamp('expires_at');
            $t->timestamp('consumed_at')->nullable();
            $t->timestamps();
            $t->index(['user_id', 'purpose']);
        });

        Schema::create('user_consents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('document', 30);
            $t->string('version', 20);
            $t->string('ip_hash', 64)->nullable();
            $t->string('device')->nullable();
            $t->string('locale', 5)->nullable();
            $t->json('payload')->nullable();
            $t->timestamp('agreed_at');
            $t->index(['user_id', 'document']);
        });

        /* ---------------- Catalog & Entitlement ---------------- */
        Schema::create('plans', function (Blueprint $t) {
            $t->id();
            $t->string('code', 40)->unique();
            $t->string('name_id');
            $t->string('name_en')->nullable();
            $t->text('description_id')->nullable();
            $t->text('description_en')->nullable();
            $t->unsignedBigInteger('price_monthly')->default(0);
            $t->unsignedBigInteger('price_yearly')->default(0);
            $t->json('features');      // daftar kunci entitlement
            $t->json('quotas');        // kunci → kuota per periode
            $t->json('limits')->nullable(); // proyek, storage_mb, social_accounts, priority
            $t->boolean('is_recommended')->default(false);
            $t->boolean('is_active')->default(true);
            $t->unsignedInteger('sort')->default(0);
            $t->timestamps();
        });

        Schema::create('addons', function (Blueprint $t) {
            $t->id();
            $t->string('code', 40)->unique();
            $t->string('name_id');
            $t->string('name_en')->nullable();
            $t->text('description_id')->nullable();
            $t->text('description_en')->nullable();
            $t->unsignedBigInteger('price')->default(0);
            $t->string('billing', 30)->default('one_time');
            $t->unsignedInteger('duration_days')->nullable();
            $t->json('grants');          // entitlement → kuota tambahan / true
            $t->json('plan_codes')->nullable(); // null = semua paket
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('custom_packages', function (Blueprint $t) {
            $t->id();
            $t->string('code', 40)->unique();
            $t->string('name');
            $t->text('description')->nullable();
            $t->unsignedBigInteger('price');
            $t->unsignedInteger('duration_days')->default(30);
            $t->json('features');
            $t->json('quotas');
            $t->foreignId('offered_to')->nullable()->constrained('users')->nullOnDelete();
            $t->string('status', 20)->default('draft'); // draft, published, archived
            $t->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('source', 20)->default('plan'); // plan / custom
            $t->string('plan_code', 40);
            $t->string('plan_name');
            $t->string('cycle', 10)->default('monthly');
            $t->unsignedBigInteger('price');
            $t->json('snapshot'); // snapshot harga & entitlement saat pembelian (PRD 4.3 butir 7)
            $t->string('status', 20)->default('pending_payment')->index();
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('ends_at')->nullable();
            $t->timestamps();
        });

        Schema::create('user_addons', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('addon_code', 40);
            $t->string('name');
            $t->json('snapshot');
            $t->string('status', 20)->default('active');
            $t->timestamp('ends_at')->nullable();
            $t->timestamps();
        });

        Schema::create('usage_counters', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('entitlement', 40);
            $t->string('period', 10); // YYYY-MM
            $t->unsignedInteger('used')->default(0);
            $t->timestamps();
            $t->unique(['user_id', 'entitlement', 'period']);
        });

        // Kupon & promo dibuat pada migration Bab 55 (2026_11_10_000200).

        /* ---------------- Finance ---------------- */
        Schema::create('wallet_ledger', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('wallet', 15); // topup | commission
            $t->string('direction', 6); // credit | debit
            $t->unsignedBigInteger('amount');
            $t->bigInteger('balance_after');
            $t->string('ref_type', 40)->nullable();
            $t->unsignedBigInteger('ref_id')->nullable();
            $t->string('description');
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('reason')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['user_id', 'wallet']);
            $t->index(['ref_type', 'ref_id']);
        });

        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('reference', 40)->unique();
            $t->string('purpose', 20); // topup | subscription | addon | custom
            $t->string('purpose_code', 40)->nullable();
            $t->string('cycle', 10)->nullable();
            $t->unsignedBigInteger('amount');
            $t->string('method', 20)->nullable();
            $t->string('gateway', 30)->default('manual');
            $t->string('status', 20)->default('pending')->index(); // pending, paid, failed, expired, refunded
            $t->string('idempotency_key', 80)->nullable()->unique();
            $t->json('gateway_payload')->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->timestamps();
        });

        Schema::create('withdrawal_destinations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('type', 10);
            $t->string('provider', 60);
            $t->string('holder', 120);
            $t->text('account_number'); // terenkripsi
            $t->string('account_last4', 4);
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('withdrawals', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('destination_id')->nullable()->constrained('withdrawal_destinations')->nullOnDelete();
            $t->json('destination_snapshot');
            $t->unsignedBigInteger('amount');
            $t->string('status', 20)->default('requested')->index();
            $t->text('admin_note')->nullable();
            $t->string('reference', 120)->nullable();
            $t->string('idempotency_key', 80)->nullable()->unique();
            $t->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('processed_at')->nullable();
            $t->timestamps();
        });

        /* ---------------- Affiliate ---------------- */
        Schema::create('referral_clicks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('referrer_id')->constrained('users')->cascadeOnDelete();
            $t->string('ip_hash', 64)->nullable();
            $t->timestamp('created_at')->useCurrent();
        });

        Schema::create('commissions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('referrer_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('referred_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $t->unsignedBigInteger('base_amount');
            $t->unsignedBigInteger('amount');
            $t->string('status', 20)->default('pending')->index(); // pending, approved, available, reversed, paid
            $t->timestamp('available_at')->nullable();
            $t->timestamps();
        });

        /* ---------------- Approval ---------------- */
        Schema::create('approvals', function (Blueprint $t) {
            $t->id();
            $t->string('type', 30)->index(); // account, custom_package, withdrawal, refund, integration, template
            $t->string('subject_type', 60);
            $t->unsignedBigInteger('subject_id');
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('title');
            $t->json('payload')->nullable();
            $t->string('status', 20)->default('pending')->index(); // pending, approved, rejected, revision
            $t->text('reason')->nullable();
            $t->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('decided_at')->nullable();
            $t->timestamps();
        });

        /* ---------------- AI & Media ---------------- */
        Schema::create('ai_providers', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('driver', 30)->default('openai_compatible');
            $t->string('base_url')->nullable();
            $t->text('api_key')->nullable(); // terenkripsi, tidak pernah dikembalikan ke klien
            $t->json('capabilities');
            $t->string('status', 20)->default('draft'); // draft, active, disabled
            $t->string('health', 20)->default('unknown');
            $t->unsignedInteger('priority')->default(10);
            $t->unsignedBigInteger('monthly_token_limit')->nullable();
            $t->unsignedBigInteger('tokens_used')->default(0);
            $t->unsignedBigInteger('cost_month')->default(0);
            $t->timestamp('key_rotated_at')->nullable();
            $t->timestamp('last_checked_at')->nullable();
            $t->timestamps();
        });

        Schema::create('ai_models', function (Blueprint $t) {
            $t->id();
            $t->foreignId('provider_id')->constrained('ai_providers')->cascadeOnDelete();
            $t->string('name');
            $t->string('model_id');
            $t->string('capability', 30);
            $t->unsignedInteger('credit_cost')->default(1);
            $t->boolean('is_active')->default(true);
            $t->boolean('is_fallback')->default(false);
            $t->timestamps();
        });

        Schema::create('ai_prompts', function (Blueprint $t) {
            $t->id();
            $t->string('key', 60)->unique();
            $t->string('name');
            $t->text('template');
            $t->string('version', 10)->default('1.0');
            $t->string('status', 20)->default('active');
            $t->timestamps();
        });

        Schema::create('ai_agents', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('purpose');
            $t->foreignId('model_id')->nullable()->constrained('ai_models')->nullOnDelete();
            $t->text('instructions')->nullable();
            $t->string('status', 20)->default('draft');
            $t->timestamps();
        });

        Schema::create('uploads', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('disk', 20)->default('local');
            $t->string('path');
            $t->string('original_name');
            $t->string('mime', 80);
            $t->unsignedBigInteger('size');
            $t->timestamps();
        });

        Schema::create('ai_jobs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('project_id')->nullable();
            $t->foreignId('upload_id')->nullable()->constrained('uploads')->nullOnDelete();
            $t->string('type', 30)->index();
            $t->string('entitlement', 40);
            $t->text('prompt');
            $t->json('params')->nullable();
            $t->string('status', 20)->default('queued')->index();
            $t->unsignedTinyInteger('progress')->default(0);
            $t->unsignedInteger('cost')->default(0);
            $t->boolean('quota_refunded')->default(false);
            $t->json('result')->nullable();
            $t->string('error')->nullable();
            $t->foreignId('provider_id')->nullable()->constrained('ai_providers')->nullOnDelete();
            $t->string('idempotency_key', 80)->nullable()->unique();
            $t->timestamp('started_at')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
        });

        Schema::create('assistant_messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('role', 12);
            $t->text('content');
            $t->timestamp('created_at')->useCurrent();
        });

        /* ---------------- Templates & proyek ---------------- */
        Schema::create('template_categories', function (Blueprint $t) {
            $t->id();
            $t->string('slug', 60)->unique();
            $t->string('name_id');
            $t->string('name_en')->nullable();
            $t->string('type', 20);
            $t->unsignedInteger('sort')->default(0);
            $t->timestamps();
        });

        Schema::create('templates', function (Blueprint $t) {
            $t->id();
            $t->string('title_id');
            $t->string('title_en')->nullable();
            $t->text('description_id')->nullable();
            $t->text('description_en')->nullable();
            $t->string('type', 20)->index();
            $t->string('category', 60)->nullable();
            $t->string('ratio', 8)->default('1:1');
            $t->json('palette');
            $t->json('structure')->nullable();
            $t->boolean('is_premium')->default(false);
            $t->boolean('is_featured')->default(false);
            $t->string('status', 20)->default('draft')->index(); // draft, pending, active, archived
            $t->unsignedInteger('usage_count')->default(0);
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('projects', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('template_id')->nullable()->constrained()->nullOnDelete();
            $t->string('name');
            $t->string('type', 20);
            $t->string('status', 20)->default('active');
            $t->json('data')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });

        /* ---------------- Website builder & SEO ---------------- */
        Schema::create('websites', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('subdomain', 63)->unique();
            $t->string('custom_domain')->nullable();
            $t->string('status', 20)->default('draft'); // draft, published, archived
            $t->json('content');
            $t->json('published_content')->nullable();
            $t->unsignedInteger('version')->default(1);
            $t->timestamp('published_at')->nullable();
            $t->timestamps();
        });

        Schema::create('seo_rules', function (Blueprint $t) {
            $t->id();
            $t->string('code', 60)->unique();
            $t->string('name');
            $t->string('severity', 10);
            $t->unsignedTinyInteger('weight')->default(5);
            $t->boolean('is_active')->default(true);
            $t->string('ruleset_version', 10)->default('1.0');
            $t->timestamps();
        });

        Schema::create('seo_audits', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('url', 500);
            $t->unsignedTinyInteger('score')->default(0);
            $t->string('ruleset_version', 10);
            $t->string('status', 20)->default('completed');
            $t->string('error')->nullable();
            $t->timestamps();
        });

        Schema::create('seo_findings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('audit_id')->constrained('seo_audits')->cascadeOnDelete();
            $t->string('rule', 60);
            $t->string('severity', 10);
            $t->string('location');
            $t->string('message');
            $t->text('recommendation');
            $t->string('state', 10)->default('open');
            $t->timestamps();
        });

        /* ---------------- Social ---------------- */
        Schema::create('social_accounts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('platform', 20);
            $t->string('account_name')->nullable();
            $t->text('access_token')->nullable(); // terenkripsi
            $t->string('status', 20)->default('connected');
            $t->timestamp('expires_at')->nullable();
            $t->timestamps();
            $t->unique(['user_id', 'platform']);
        });

        Schema::create('webhook_logs', function (Blueprint $t) {
            $t->id();
            $t->string('source', 30)->index();
            $t->string('event', 80);
            $t->boolean('signature_valid')->default(false);
            $t->string('status', 20)->default('received');
            $t->json('payload')->nullable();
            $t->timestamp('created_at')->useCurrent();
        });

        /* ---------------- Support & konten ---------------- */
        Schema::create('tickets', function (Blueprint $t) {
            $t->id();
            $t->string('number', 20)->unique();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('guest_name')->nullable();
            $t->string('guest_email')->nullable();
            $t->string('subject');
            $t->string('page')->nullable();
            $t->string('status', 20)->default('open')->index();
            $t->timestamps();
        });

        Schema::create('ticket_messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('from', 10)->default('user');
            $t->text('body');
            $t->timestamps();
        });

        Schema::create('faqs', function (Blueprint $t) {
            $t->id();
            $t->string('category', 30)->default('general');
            $t->string('question_id');
            $t->text('answer_id');
            $t->string('question_en')->nullable();
            $t->text('answer_en')->nullable();
            $t->unsignedInteger('sort')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('banners', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->string('level', 10)->default('info');
            $t->text('message');
            $t->string('link')->nullable();
            $t->string('placement', 20)->default('global');
            $t->boolean('is_active')->default(false);
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('ends_at')->nullable();
            $t->timestamps();
        });

        Schema::create('blog_posts', function (Blueprint $t) {
            $t->id();
            $t->string('slug')->unique();
            $t->string('title_id');
            $t->string('title_en')->nullable();
            $t->longText('body_id')->nullable();
            $t->longText('body_en')->nullable();
            $t->string('status', 20)->default('draft');
            $t->timestamp('published_at')->nullable();
            $t->timestamps();
        });

        Schema::create('legal_documents', function (Blueprint $t) {
            $t->id();
            $t->string('type', 30)->index();
            $t->string('version', 20);
            $t->date('effective_at');
            $t->string('title_id');
            $t->string('title_en')->nullable();
            $t->json('sections_id');
            $t->json('sections_en')->nullable();
            $t->text('summary_id')->nullable();
            $t->text('summary_en')->nullable();
            $t->boolean('requires_reconsent')->default(false);
            $t->boolean('is_active')->default(false);
            $t->timestamps();
        });

        Schema::create('email_templates', function (Blueprint $t) {
            $t->id();
            $t->string('key', 60)->unique();
            $t->string('subject_id');
            $t->text('body_id');
            $t->string('subject_en')->nullable();
            $t->text('body_en')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        /* ---------------- Platform ---------------- */
        Schema::create('settings', function (Blueprint $t) {
            $t->string('key', 80)->primary();
            $t->json('value')->nullable();
            $t->boolean('is_secret')->default(false);
            $t->timestamps();
        });

        Schema::create('feature_flags', function (Blueprint $t) {
            $t->id();
            $t->string('key', 60)->unique();
            $t->string('description');
            $t->boolean('enabled')->default(true);
            $t->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('action', 80)->index();
            $t->string('subject_type', 60)->nullable();
            $t->unsignedBigInteger('subject_id')->nullable();
            $t->string('ip', 45)->nullable();
            $t->json('meta')->nullable();
            $t->timestamp('created_at')->useCurrent();
        });

        Schema::create('security_events', function (Blueprint $t) {
            $t->id();
            $t->string('type', 40)->index();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('ip', 45)->nullable();
            $t->string('severity', 10)->default('info');
            $t->json('meta')->nullable();
            $t->timestamp('created_at')->useCurrent();
        });

        Schema::create('notifications', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('type');
            $t->morphs('notifiable');
            $t->text('data');
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'notifications', 'security_events', 'audit_logs', 'feature_flags', 'settings', 'email_templates', 'legal_documents', 'blog_posts', 'banners', 'faqs',
            'ticket_messages', 'tickets', 'webhook_logs', 'social_accounts', 'seo_findings', 'seo_audits', 'seo_rules', 'websites', 'projects', 'templates',
            'template_categories', 'assistant_messages', 'ai_jobs', 'uploads', 'ai_agents', 'ai_prompts', 'ai_models', 'ai_providers', 'approvals', 'commissions',
            'referral_clicks', 'withdrawals', 'withdrawal_destinations', 'payments', 'wallet_ledger', 'coupons', 'usage_counters', 'user_addons', 'subscriptions',
            'custom_packages', 'addons', 'plans', 'user_consents', 'otp_codes',
        ] as $tbl) {
            Schema::dropIfExists($tbl);
        }
    }
};
