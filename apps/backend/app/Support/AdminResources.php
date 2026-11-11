<?php

namespace App\Support;

use App\Models;

/**
 * Registry resource Admin Panel (PRD 13, Spesifikasi Admin Panel). Satu definisi per resource:
 * model, kolom pencarian, filter, sort yang diizinkan (server-side, §7.2 butir 5), aturan validasi,
 * dan apakah read-only (data keuangan/log tidak boleh diubah langsung — PRD 11.4).
 */
class AdminResources
{
    public static function all(): array
    {
        $bool = 'boolean';

        return [
            'plans' => ['model' => Models\Plan::class, 'search' => ['code', 'name_id'], 'filters' => ['is_active'], 'sort' => ['sort', 'price_monthly', 'created_at'], 'rules' => [
                'code' => 'required|alpha_dash|max:40', 'name_id' => 'required|string|max:120', 'name_en' => 'nullable|string|max:120', 'description_id' => 'nullable|string|max:500', 'description_en' => 'nullable|string|max:500',
                'price_monthly' => 'required|integer|min:0', 'price_yearly' => 'required|integer|min:0', 'features' => 'array', 'quotas' => 'array', 'limits' => 'nullable|array',
                'is_recommended' => $bool, 'is_active' => $bool, 'sort' => 'integer|min:0',
            ]],
            'addons' => ['model' => Models\Addon::class, 'search' => ['code', 'name_id'], 'filters' => ['is_active', 'billing'], 'sort' => ['price', 'created_at'], 'rules' => [
                'code' => 'required|alpha_dash|max:40', 'name_id' => 'required|string|max:120', 'name_en' => 'nullable|string|max:120', 'description_id' => 'nullable|string|max:500', 'description_en' => 'nullable|string|max:500',
                'price' => 'required|integer|min:0', 'billing' => 'required|in:one_time,recurring,follow_subscription', 'duration_days' => 'nullable|integer|min:1', 'grants' => 'array', 'plan_codes' => 'nullable|array', 'is_active' => $bool,
            ]],
            'custom-packages' => ['model' => Models\CustomPackage::class, 'search' => ['code', 'name'], 'filters' => ['status'], 'sort' => ['price', 'created_at'], 'rules' => [
                'code' => 'required|alpha_dash|max:40', 'name' => 'required|string|max:120', 'description' => 'nullable|string|max:1000', 'price' => 'required|integer|min:0', 'duration_days' => 'required|integer|min:1',
                'features' => 'array', 'quotas' => 'array', 'offered_to' => 'nullable|integer|exists:users,id', 'status' => 'required|in:draft,published,archived',
            ]],
            // Kupon & promo (PRD 55.4.2) — seluruh perubahan tercatat audit (55.4.3 butir 10)
            'coupons' => ['model' => Models\Coupon::class, 'search' => ['code', 'name'], 'filters' => ['is_active', 'type', 'auto_apply', 'campaign'], 'sort' => ['created_at', 'used', 'ends_at'], 'rules' => [
                'code' => 'required|alpha_dash|max:40', 'name' => 'nullable|string|max:120', 'type' => 'required|in:percent,fixed,bonus_credit,bonus_days', 'value' => 'required|integer|min:1',
                'max_discount' => 'nullable|integer|min:0', 'min_purchase' => 'nullable|integer|min:0', 'plan_codes' => 'nullable|array', 'cycles' => 'nullable|array', 'cycles.*' => 'in:monthly,yearly',
                'applies_to_addons' => $bool, 'applies_to_custom' => $bool, 'first_purchase_only' => $bool, 'max_uses' => 'nullable|integer|min:1', 'max_uses_per_user' => 'nullable|integer|min:1',
                'duration' => 'required|in:once,first_n_months,forever', 'duration_months' => 'nullable|integer|min:1|max:24', 'auto_apply' => $bool,
                'campaign' => 'nullable|in:early_bird,flash_sale,annual,referral,winback,upgrade', 'starts_at' => 'nullable|date', 'ends_at' => 'nullable|date', 'is_active' => $bool,
            ]],
            'coupon-redemptions' => ['model' => Models\CouponRedemption::class, 'readonly' => true, 'search' => [], 'filters' => ['status', 'coupon_id'], 'sort' => ['created_at', 'discount']],
            'credit-packs' => ['model' => Models\CreditPack::class, 'readonly' => true, 'search' => ['label'], 'filters' => ['source', 'period'], 'sort' => ['created_at', 'expires_at']],
            'credit-ledger' => ['model' => Models\CreditLedgerEntry::class, 'readonly' => true, 'search' => ['description'], 'filters' => ['direction', 'ref_type'], 'sort' => ['created_at', 'amount']],
            'subscriptions' => ['model' => Models\Subscription::class, 'readonly' => true, 'with' => ['user'], 'search' => ['plan_code', 'plan_name'], 'filters' => ['status', 'plan_code'], 'sort' => ['created_at', 'ends_at', 'price']],
            'usage' => ['model' => Models\UsageCounter::class, 'readonly' => true, 'search' => ['entitlement', 'period'], 'filters' => ['entitlement', 'period'], 'sort' => ['used', 'updated_at']],
            'payments' => ['model' => Models\Payment::class, 'readonly' => true, 'with' => ['user'], 'search' => ['reference'], 'filters' => ['status', 'purpose', 'gateway'], 'sort' => ['created_at', 'amount']],
            'topups' => ['model' => Models\Payment::class, 'readonly' => true, 'scope' => ['purpose' => 'topup'], 'with' => ['user'], 'search' => ['reference'], 'filters' => ['status'], 'sort' => ['created_at', 'amount']],
            'ledger' => ['model' => Models\LedgerEntry::class, 'readonly' => true, 'with' => ['user'], 'search' => ['description'], 'filters' => ['wallet', 'direction'], 'sort' => ['created_at', 'amount']],
            'commissions' => ['model' => Models\Commission::class, 'readonly' => true, 'with' => ['referrer', 'referred'], 'search' => [], 'filters' => ['status'], 'sort' => ['created_at', 'amount']],
            'withdrawals' => ['model' => Models\Withdrawal::class, 'readonly' => true, 'with' => ['user'], 'search' => ['reference'], 'filters' => ['status'], 'sort' => ['created_at', 'amount']],
            'approvals' => ['model' => Models\Approval::class, 'readonly' => true, 'with' => ['user', 'decider'], 'search' => ['title'], 'filters' => ['type', 'status'], 'sort' => ['created_at', 'decided_at']],
            'ai-providers' => ['model' => Models\AiProvider::class, 'search' => ['name', 'driver'], 'filters' => ['status', 'health'], 'sort' => ['priority', 'tokens_used', 'cost_month'], 'secret' => ['api_key'], 'rules' => [
                'name' => 'required|string|max:80', 'driver' => 'required|in:openai_compatible,comfyui,custom_http', 'base_url' => 'nullable|url|max:255', 'capabilities' => 'required|array|min:1', 'capabilities.*' => 'in:text,image,video',
                'status' => 'required|in:draft,active,disabled', 'priority' => 'integer|min:1|max:999', 'monthly_token_limit' => 'nullable|integer|min:0',
            ]],
            'ai-models' => ['model' => Models\AiModel::class, 'with' => ['provider'], 'search' => ['name', 'model_id'], 'filters' => ['capability', 'is_active'], 'sort' => ['credit_cost', 'created_at'], 'rules' => [
                'provider_id' => 'required|exists:ai_providers,id', 'name' => 'required|string|max:80', 'model_id' => 'required|string|max:120', 'capability' => 'required|in:text,image,video',
                'credit_cost' => 'required|integer|min:1|max:1000', 'is_active' => $bool, 'is_fallback' => $bool,
            ]],
            'ai-prompts' => ['model' => Models\AiPrompt::class, 'search' => ['key', 'name'], 'filters' => ['status'], 'sort' => ['updated_at'], 'rules' => [
                'key' => 'required|alpha_dash|max:60', 'name' => 'required|string|max:120', 'template' => 'required|string|max:8000', 'version' => 'required|string|max:10', 'status' => 'required|in:draft,active,archived',
            ]],
            'ai-agents' => ['model' => Models\AiAgent::class, 'search' => ['name', 'purpose'], 'filters' => ['status'], 'sort' => ['updated_at'], 'rules' => [
                'name' => 'required|string|max:80', 'purpose' => 'required|string|max:255', 'model_id' => 'nullable|exists:ai_models,id', 'instructions' => 'nullable|string|max:8000', 'status' => 'required|in:draft,active,disabled',
            ]],
            'ai-jobs' => ['model' => Models\AiJob::class, 'readonly' => true, 'with' => ['user'], 'search' => ['prompt'], 'filters' => ['status', 'type'], 'sort' => ['created_at', 'cost']],
            'uploads' => ['model' => Models\Upload::class, 'readonly' => true, 'search' => ['original_name', 'mime'], 'filters' => ['mime'], 'sort' => ['created_at', 'size']],
            'templates' => ['model' => Models\Template::class, 'search' => ['title_id', 'category'], 'filters' => ['type', 'status', 'is_premium', 'is_featured'], 'sort' => ['usage_count', 'created_at', 'updated_at'], 'rules' => [
                'title_id' => 'required|string|max:160', 'title_en' => 'nullable|string|max:160', 'description_id' => 'nullable|string|max:1000', 'description_en' => 'nullable|string|max:1000',
                'type' => 'required|in:photo,video,website,social,business', 'category' => 'nullable|string|max:60', 'ratio' => 'required|in:1:1,4:5,9:16,16:9,4:3',
                'palette' => 'required|array|size:3', 'palette.*' => 'regex:/^#[0-9A-Fa-f]{6}$/', 'is_premium' => $bool, 'is_featured' => $bool, 'status' => 'required|in:draft,pending,active,archived',
            ]],
            'template-categories' => ['model' => Models\TemplateCategory::class, 'search' => ['slug', 'name_id'], 'filters' => ['type'], 'sort' => ['sort'], 'rules' => [
                'slug' => 'required|alpha_dash|max:60', 'name_id' => 'required|string|max:80', 'name_en' => 'nullable|string|max:80', 'type' => 'required|in:photo,video,website,social,business', 'sort' => 'integer|min:0',
            ]],
            'projects' => ['model' => Models\Project::class, 'readonly' => true, 'search' => ['name'], 'filters' => ['type', 'status'], 'sort' => ['updated_at']],
            'websites' => ['model' => Models\Website::class, 'readonly' => true, 'with' => ['user'], 'search' => ['name', 'subdomain'], 'filters' => ['status'], 'sort' => ['updated_at', 'published_at']],
            'seo-rules' => ['model' => Models\SeoRule::class, 'search' => ['code', 'name'], 'filters' => ['severity', 'is_active'], 'sort' => ['weight', 'code'], 'rules' => [
                'code' => 'required|alpha_dash|max:60', 'name' => 'required|string|max:120', 'severity' => 'required|in:critical,warning,info', 'weight' => 'required|integer|min:1|max:20', 'is_active' => $bool, 'ruleset_version' => 'required|string|max:10',
            ]],
            'seo-audits' => ['model' => Models\SeoAudit::class, 'readonly' => true, 'search' => ['url'], 'filters' => ['status'], 'sort' => ['created_at', 'score']],
            'social-accounts' => ['model' => Models\SocialAccount::class, 'readonly' => true, 'search' => ['account_name'], 'filters' => ['platform', 'status'], 'sort' => ['updated_at']],
            'webhook-logs' => ['model' => Models\WebhookLog::class, 'readonly' => true, 'search' => ['event', 'source'], 'filters' => ['source', 'signature_valid'], 'sort' => ['created_at']],
            'email-templates' => ['model' => Models\EmailTemplate::class, 'search' => ['key', 'subject_id'], 'filters' => ['is_active'], 'sort' => ['key'], 'rules' => [
                'key' => 'required|alpha_dash|max:60', 'subject_id' => 'required|string|max:160', 'body_id' => 'required|string|max:10000', 'subject_en' => 'nullable|string|max:160', 'body_en' => 'nullable|string|max:10000', 'is_active' => $bool,
            ]],
            'faqs' => ['model' => Models\Faq::class, 'search' => ['question_id', 'answer_id'], 'filters' => ['category', 'is_active'], 'sort' => ['sort'], 'rules' => [
                'category' => 'required|in:general,billing,ai,account,affiliate', 'question_id' => 'required|string|max:255', 'answer_id' => 'required|string|max:4000', 'question_en' => 'nullable|string|max:255', 'answer_en' => 'nullable|string|max:4000', 'sort' => 'integer|min:0', 'is_active' => $bool,
            ]],
            'banners' => ['model' => Models\Banner::class, 'search' => ['title', 'message'], 'filters' => ['level', 'is_active', 'placement'], 'sort' => ['created_at'], 'rules' => [
                'title' => 'required|string|max:120', 'level' => 'required|in:info,warning,danger', 'message' => 'required|string|max:500', 'link' => 'nullable|string|max:255', 'placement' => 'required|in:global,frontpage,user,admin',
                'is_active' => $bool, 'starts_at' => 'nullable|date', 'ends_at' => 'nullable|date|after:starts_at',
            ]],
            'blog-posts' => ['model' => Models\BlogPost::class, 'search' => ['slug', 'title_id'], 'filters' => ['status'], 'sort' => ['published_at', 'updated_at'], 'rules' => [
                'slug' => 'required|alpha_dash|max:160', 'title_id' => 'required|string|max:200', 'title_en' => 'nullable|string|max:200', 'body_id' => 'nullable|string', 'body_en' => 'nullable|string', 'status' => 'required|in:draft,published,archived', 'published_at' => 'nullable|date',
            ]],
            'legal-documents' => ['model' => Models\LegalDocument::class, 'search' => ['title_id', 'version'], 'filters' => ['type', 'is_active'], 'sort' => ['effective_at'], 'rules' => [
                'type' => 'required|in:terms,privacy,refund,affiliate_terms,cookies', 'version' => 'required|string|max:20', 'effective_at' => 'required|date', 'title_id' => 'required|string|max:160', 'title_en' => 'nullable|string|max:160',
                'sections_id' => 'required|array|min:1', 'sections_en' => 'nullable|array', 'summary_id' => 'nullable|string|max:1000', 'summary_en' => 'nullable|string|max:1000', 'requires_reconsent' => $bool, 'is_active' => $bool,
            ]],
            'feature-flags' => ['model' => Models\FeatureFlag::class, 'search' => ['key', 'description'], 'filters' => ['enabled'], 'sort' => ['key'], 'rules' => [
                'key' => 'required|alpha_dash|max:60', 'description' => 'required|string|max:255', 'enabled' => $bool,
            ]],
            'audit-logs' => ['model' => Models\AuditLog::class, 'readonly' => true, 'with' => ['actor'], 'search' => ['action'], 'filters' => ['action', 'subject_type'], 'sort' => ['created_at']],
            'security-events' => ['model' => Models\SecurityEvent::class, 'readonly' => true, 'with' => ['user'], 'search' => ['type'], 'filters' => ['type', 'severity'], 'sort' => ['created_at']],
            'mail-logs' => ['model' => Models\MailLog::class, 'readonly' => true, 'search' => ['to', 'subject', 'template'], 'filters' => ['status', 'template', 'mailer'], 'sort' => ['created_at']],
            'wa-logs' => ['model' => Models\WaLog::class, 'readonly' => true, 'search' => ['to', 'event', 'template'], 'filters' => ['status', 'event'], 'sort' => ['created_at']],
            'wa-templates' => ['model' => Models\WaTemplate::class, 'search' => ['event', 'meta_name'], 'filters' => ['is_active', 'language'], 'sort' => ['event', 'updated_at'], 'rules' => [
                'event' => 'required|in:otp,welcome,password_changed,topup_success,subscription_active,subscription_expiring,withdrawal_requested,withdrawal_paid,withdrawal_rejected,test',
                'meta_name' => 'required|string|max:120|regex:/^[a-z0-9_]+$/', 'language' => 'required|string|max:10', 'body_preview' => 'required|string|max:1024',
                'variables' => 'array', 'is_active' => $bool,
            ]],
            'tickets' => ['model' => Models\Ticket::class, 'readonly' => true, 'with' => ['user'], 'search' => ['number', 'subject', 'guest_email'], 'filters' => ['status'], 'sort' => ['updated_at', 'created_at']],
        ];
    }

    public static function get(string $key): array
    {
        $all = self::all();
        if (! isset($all[$key])) {
            throw new ApiException('not_found', 'Resource tidak dikenal.', 404);
        }

        return $all[$key];
    }
}
