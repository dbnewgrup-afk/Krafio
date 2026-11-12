<?php

namespace App\Services;

use App\Models\User;
use App\Models\Website;
use App\Notifications\Notice;
use App\Support\ApiException;
use Illuminate\Support\Str;

/**
 * Domain kustom website (blueprint "Website Builder: Domain kustom paket Pro+").
 * Kepemilikan dibuktikan dengan DNS:
 *   TXT   _krafio.<domain>  =  krafio-verify=<token>
 *   CNAME <domain>          →  target (config krafio.custom_domain_target, default sites.<site_domain>)
 * Penerbitan SSL & routing di edge (Cloudflare for SaaS) butuh credential Cloudflare → di luar kode ini.
 */
class CustomDomains
{
    /** @var (callable(string, int): array)|null resolver DNS (dapat diganti di test) */
    public static $resolver = null;

    public static function target(): string
    {
        return (string) (config('krafio.custom_domain_target') ?: 'sites.'.config('krafio.site_domain'));
    }

    public static function set(User $u, Website $w, string $domain): Website
    {
        $domain = strtolower(trim(preg_replace('#^https?://#i', '', rtrim(trim($domain), '/')) ?? ''));
        if (! preg_match('/^(?=.{4,190}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/', $domain)) {
            throw new ApiException('validation_failed', 'Format domain tidak valid.', 422, [], ['domain' => ['Contoh: www.tokokamu.com']]);
        }
        if (str_ends_with($domain, '.'.config('krafio.site_domain')) || $domain === config('krafio.site_domain')) {
            throw new ApiException('validation_failed', 'Gunakan domain milik Anda sendiri, bukan subdomain Krafio.', 422, [], ['domain' => ['Domain ini milik Krafio.']]);
        }
        if (Website::where('custom_domain', $domain)->where('id', '!=', $w->id)->exists()) {
            throw new ApiException('validation_failed', 'Domain sudah dipakai website lain.', 422, [], ['domain' => ['Domain sudah dipakai.']]);
        }
        if ($w->custom_domain !== $domain) {
            $used = Website::where('user_id', $u->id)->where('id', '!=', $w->id)->whereNotNull('custom_domain')->where('custom_domain', '!=', '')->count();
            Entitlements::ensureCount($u, 'custom_domains', $used, 1, 'website.builder', 'domain kustom');
            $w->update(['custom_domain' => $domain, 'domain_status' => 'pending', 'domain_token' => Str::lower(Str::random(32)), 'domain_verified_at' => null, 'domain_checked_at' => null, 'domain_error' => null]);
            Audit::log('website.domain_set', $w, ['domain' => $domain], $u->id);
        }

        return $w->fresh();
    }

    public static function remove(Website $w): void
    {
        $w->update(['custom_domain' => null, 'domain_status' => 'none', 'domain_token' => null, 'domain_verified_at' => null, 'domain_checked_at' => null, 'domain_error' => null]);
    }

    /** Cek TXT & CNAME. Status: verified / pending (belum ditemukan) — error disimpan agar user tahu yang kurang. */
    public static function verify(Website $w): Website
    {
        if (! $w->custom_domain || ! $w->domain_token) {
            return $w;
        }
        $txt = collect(self::lookup('_krafio.'.$w->custom_domain, DNS_TXT))->pluck('txt')->map(fn ($v) => trim((string) $v))->all();
        $okTxt = in_array('krafio-verify='.$w->domain_token, $txt, true);
        $target = rtrim(self::target(), '.');
        $cname = collect(self::lookup($w->custom_domain, DNS_CNAME))->pluck('target')->map(fn ($v) => rtrim(strtolower((string) $v), '.'))->all();
        $okCname = in_array($target, $cname, true);
        if (! $okCname) { // domain apex: A record harus sama dengan IP target
            $targetIps = collect(self::lookup($target, DNS_A))->pluck('ip')->all();
            $ips = collect(self::lookup($w->custom_domain, DNS_A))->pluck('ip')->all();
            $okCname = $targetIps && $ips && ! array_diff($ips, $targetIps);
        }
        $error = ! $okTxt ? 'TXT _krafio belum ditemukan.' : (! $okCname ? 'CNAME/A record belum mengarah ke '.$target.'.' : null);
        $w->update([
            'domain_status' => $error ? ($w->domain_status === 'verified' ? 'failed' : 'pending') : 'verified',
            'domain_verified_at' => $error ? $w->domain_verified_at : ($w->domain_verified_at ?? now()),
            'domain_checked_at' => now(), 'domain_error' => $error,
        ]);
        if (! $error && $w->wasChanged('domain_status') && ($u = $w->user ?? User::find($w->user_id))) {
            $u->notify(new Notice('Domain terverifikasi', $w->custom_domain.' sudah terhubung ke website '.$w->name.'.', 'system', '/app/websites/'.$w->id));
        }

        return $w->fresh();
    }

    /** Scheduler tiap jam: domain pending (≤ 7 hari) & verified (deteksi DNS dicabut). */
    public static function recheckPending(): void
    {
        Website::whereIn('domain_status', ['pending', 'verified', 'failed'])->whereNotNull('custom_domain')
            ->where(fn ($q) => $q->where('domain_status', '!=', 'pending')->orWhere('updated_at', '>=', now()->subDays(7)))
            ->limit(500)->get()->each(fn ($w) => self::verify($w));
    }

    public static function present(Website $w): array
    {
        return [
            'domain' => $w->custom_domain, 'status' => $w->domain_status ?? 'none', 'checked_at' => $w->domain_checked_at?->toIso8601String(),
            'verified_at' => $w->domain_verified_at?->toIso8601String(), 'error' => $w->domain_error,
            'records' => $w->custom_domain ? [
                ['type' => 'TXT', 'name' => '_krafio.'.$w->custom_domain, 'value' => 'krafio-verify='.$w->domain_token],
                ['type' => 'CNAME', 'name' => $w->custom_domain, 'value' => self::target()],
            ] : [],
        ];
    }

    private static function lookup(string $host, int $type): array
    {
        if (self::$resolver) {
            return (array) (self::$resolver)($host, $type);
        }
        $r = @dns_get_record($host, $type);

        return is_array($r) ? $r : [];
    }
}
