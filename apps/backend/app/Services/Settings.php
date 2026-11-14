<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/** Konfigurasi platform yang dapat diubah Admin tanpa deploy (PRD 4.2, 5.3, 13). */
class Settings
{
    public const DEFAULTS = [
        'general.app_name' => 'Krafio',
        'general.environment_label' => null,
        'registration.mode' => 'auto', // auto | manual
        'wallet.topup_min' => 50000,
        'wallet.topup_max' => 1000000,
        'wallet.withdraw_min' => 100000,
        'affiliate.rate_percent' => 10,
        'affiliate.hold_days' => 14,
        'affiliate.active' => true,
        'cs.hours' => 'Senin–Jumat, 09.00–17.00 WIB',
        'cs.reply_estimate' => '1×24 jam kerja',
        'cs.whatsapp' => null,
        'maintenance.active' => false,
        'maintenance.message' => null,
        'security.turnstile_site_key' => null,
        'security.turnstile_secret' => null,
        'security.login_max_attempts' => 5,
        'security.admin_session_minutes' => 30,
        'security.admin_mfa_required' => true,
        'otp.ttl_minutes' => 5,
        'otp.resend_seconds' => 60,
        'otp.max_attempts' => 5,
        'otp.hourly_limit' => 5,
        'sso.google' => false,
        'social.links' => [],
        'payment.gateway' => 'manual',
        'smtp.from_name' => 'Krafio',
        'smtp.from_address' => 'no-reply@krafio.local',
        'whatsapp.enabled' => false,
        'seo.ruleset_version' => '2.0',
        // Analisis konten SEO bergaya Yoast (docs/seo.md) — setup oleh Admin › SEO › Analisis konten
        'seo.content.enabled' => true,
        'seo.content.entitlement' => 'seo.basic', // kuota yang dipakai: seo.basic | seo.advanced
        'seo.content.cost' => 1, // kuota terpakai per analisis
        'seo.content.url_import' => true, // izinkan "ambil konten dari URL"
        'seo.content.content_types' => ['post', 'cornerstone', 'taxonomy', 'product'],
        'seo.content.default_type' => 'post',
        'seo.content.max_chars' => 100000,
        'seo.content.readability_full_support' => false, // Bahasa Indonesia = dukungan parsial (penalti merah 4)
        'seo.lexicon.function_words_extra' => [],
        'seo.lexicon.transition_words_extra' => [],
        'seo.lexicon.passive_exceptions_extra' => [],
        'subscription.grace_days' => 3,
        // PRD Bab 55 — nilai awal konfigurasi
        'credits.costs' => [],
        'trial.plan_code' => 'pro',
        'trial.days' => 7,
        'trial.credits' => 100,
        'billing.ppn_percent' => 11,
        'billing.price_includes_ppn' => true,
        'billing.margin_alert_percent' => 55,
        // Refund (PRD P-09 masih terbuka → dapat diubah Admin)
        'refund.enabled' => true,
        'refund.window_days' => 7,
        'refund.purposes' => ['subscription', 'addon', 'custom', 'topup'],
        // Nilai rupiah 1 kredit untuk menghitung margin AI (harga paket ÷ kredit ≈ Rp180)
        'billing.credit_value_idr' => 176, // nilai bersih terburuk per kredit (report/BIAYA_AI_DAN_MARGIN_2026-11-14.md)
    ];

    public const SECRET_KEYS = ['security.turnstile_secret'];

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = self::all();

        return array_key_exists($key, $all) ? $all[$key] : ($default ?? (self::DEFAULTS[$key] ?? null));
    }

    public static function all(): array
    {
        return Cache::remember('krafio.settings', 60, function () {
            $stored = Setting::query()->get()->mapWithKeys(fn ($s) => [$s->key => $s->value])->all();

            return array_merge(self::DEFAULTS, $stored);
        });
    }

    public static function set(string $key, mixed $value): void
    {
        Setting::updateOrCreate(['key' => $key], ['value' => $value, 'is_secret' => in_array($key, self::SECRET_KEYS, true)]);
        Cache::forget('krafio.settings');
    }
}
