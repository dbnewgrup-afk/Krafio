<?php

namespace App\Services;

use App\Models\Integration;
use App\Support\ApiException;

/**
 * Integrasi yang dikonfigurasi dari Admin Panel. Skema field per driver dipakai untuk membangun form Admin,
 * validasi, dan penyamaran secret (field `secret` tidak pernah dikembalikan; hanya status "tersimpan").
 */
class Integrations
{
    public const SCHEMAS = [
        'payment' => [
            'manual' => ['label' => 'Manual / simulasi (tanpa gateway)', 'fields' => []],
            'midtrans' => ['label' => 'Midtrans Snap', 'fields' => [
                ['key' => 'server_key', 'type' => 'secret', 'required' => true],
                ['key' => 'client_key', 'type' => 'text', 'required' => true],
                ['key' => 'is_production', 'type' => 'bool'],
            ]],
            'xendit' => ['label' => 'Xendit Invoice', 'fields' => [
                ['key' => 'secret_key', 'type' => 'secret', 'required' => true],
                ['key' => 'callback_token', 'type' => 'secret', 'required' => true],
            ]],
            'tripay' => ['label' => 'Tripay', 'fields' => [
                ['key' => 'merchant_code', 'type' => 'text', 'required' => true],
                ['key' => 'api_key', 'type' => 'secret', 'required' => true],
                ['key' => 'private_key', 'type' => 'secret', 'required' => true],
                ['key' => 'default_method', 'type' => 'text'],
                ['key' => 'is_production', 'type' => 'bool'],
            ]],
            // Kredensial JualKirim dibaca dari .env (JUALKIRIM_*), bukan disimpan di sini.
            'jualkirim' => ['label' => 'JualKirim Invoice', 'fields' => [
                ['key' => 'settlement_type', 'type' => 'select', 'options' => ['wallet', 'bank']],
                ['key' => 'bank_account_id', 'type' => 'text'],
                ['key' => 'admin_fee_paid_by', 'type' => 'select', 'options' => ['merchant', 'customer']],
                // auto = aktif begitu lunas (polling/webhook); manual = admin menyetujui di Approval → Pembayaran
                ['key' => 'confirmation', 'type' => 'select', 'options' => ['auto', 'manual']],
            ]],
        ],
        'smtp' => [
            'smtp' => ['label' => 'SMTP', 'fields' => [
                ['key' => 'host', 'type' => 'text', 'required' => true],
                ['key' => 'port', 'type' => 'number', 'required' => true],
                ['key' => 'encryption', 'type' => 'select', 'options' => ['tls', 'ssl', 'none']],
                ['key' => 'username', 'type' => 'text'],
                ['key' => 'password', 'type' => 'secret'],
                ['key' => 'from_address', 'type' => 'text', 'required' => true],
                ['key' => 'from_name', 'type' => 'text', 'required' => true],
            ]],
        ],
        'whatsapp' => [
            'cloud_api' => ['label' => 'WhatsApp Business Platform (Cloud API)', 'fields' => [
                ['key' => 'phone_number_id', 'type' => 'text', 'required' => true],
                ['key' => 'business_account_id', 'type' => 'text', 'required' => true],
                ['key' => 'access_token', 'type' => 'secret', 'required' => true],
                ['key' => 'api_version', 'type' => 'text'],
                ['key' => 'webhook_verify_token', 'type' => 'secret'],
            ]],
        ],
    ];

    public static function get(string $kind): ?Integration
    {
        return Integration::where('kind', $kind)->first();
    }

    /** Konfigurasi aktif (terdekripsi) atau null bila belum aktif. */
    public static function active(string $kind): ?array
    {
        $i = self::get($kind);
        if (! $i || ! $i->is_active) {
            return null;
        }

        return ['driver' => $i->driver] + ($i->config ?? []);
    }

    /** Bentuk aman untuk klien: secret diganti penanda. */
    public static function present(string $kind): array
    {
        $i = self::get($kind);
        $driver = $i?->driver ?? array_key_first(self::SCHEMAS[$kind]);
        $cfg = $i?->config ?? [];
        $values = [];
        foreach (self::SCHEMAS[$kind][$driver]['fields'] ?? [] as $f) {
            $v = $cfg[$f['key']] ?? null;
            $values[$f['key']] = $f['type'] === 'secret' ? ($v ? '__set__' : null) : $v;
        }

        return [
            'kind' => $kind,
            'driver' => $driver,
            'drivers' => collect(self::SCHEMAS[$kind])->map(fn ($d, $k) => ['key' => $k, 'label' => $d['label'], 'fields' => $d['fields']])->values(),
            'values' => (object) $values,
            'is_active' => (bool) $i?->is_active,
            'last_test_status' => $i?->last_test_status,
            'last_test_message' => $i?->last_test_message,
            'last_tested_at' => $i?->last_tested_at?->toIso8601String(),
        ];
    }

    public static function save(string $kind, string $driver, array $values, bool $active, ?int $by): Integration
    {
        $schema = self::SCHEMAS[$kind][$driver] ?? null;
        if (! $schema) {
            throw new ApiException('invalid_driver', 'Driver tidak dikenal.', 422);
        }
        $current = self::get($kind);
        $old = $current && $current->driver === $driver ? ($current->config ?? []) : [];
        $cfg = [];
        $errors = [];
        foreach ($schema['fields'] as $f) {
            $v = $values[$f['key']] ?? null;
            if ($f['type'] === 'secret' && ($v === '__set__' || $v === null || $v === '')) {
                $v = $old[$f['key']] ?? null; // secret tidak diubah bila dikosongkan
            }
            if ($f['type'] === 'bool') {
                $v = (bool) $v;
            }
            if ($f['type'] === 'number' && $v !== null && $v !== '') {
                $v = (int) $v;
            }
            if (($f['required'] ?? false) && ($v === null || $v === '')) {
                $errors["values.{$f['key']}"] = ['Wajib diisi.'];
            }
            $cfg[$f['key']] = $v;
        }
        if ($errors && $active) {
            throw new ApiException('validation_failed', 'Lengkapi field wajib sebelum mengaktifkan.', 422, [], $errors);
        }
        $i = Integration::updateOrCreate(['kind' => $kind], ['driver' => $driver, 'config' => $cfg, 'is_active' => $active, 'updated_by' => $by]);
        Audit::log("admin.integration.{$kind}.saved", $i, ['driver' => $driver, 'active' => $active]);

        return $i;
    }

    public static function recordTest(string $kind, bool $ok, string $message): void
    {
        Integration::where('kind', $kind)->update(['last_test_status' => $ok ? 'success' : 'failed', 'last_test_message' => mb_substr($message, 0, 250), 'last_tested_at' => now()]);
    }
}
