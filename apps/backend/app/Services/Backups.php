<?php

namespace App\Services;

use App\Models\Backup;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

/**
 * Skema backup & retensi (PRD 15.3, 34.6, 52.55–52.60):
 * - Backup database (mysqldump → .sql.gz) dan arsip unggahan (.zip) ke storage/app/backups.
 * - Rotasi: simpan N backup harian terakhir + M backup mingguan (Minggu) — sisanya dihapus otomatis.
 * - Retensi log operasional (mail, WhatsApp, webhook, audit, security, notifikasi terbaca, OTP) agar data tidak menumpuk.
 * Data keuangan (ledger, payments, withdrawals, credit_ledger) TIDAK dipangkas — wajib dipertahankan.
 * Salin folder backups ke penyimpanan terpisah/off-site (lihat README) agar aman dari kerusakan server.
 */
class Backups
{
    public const RETENTION_DEFAULTS = [
        'backup.keep_daily' => 7, 'backup.keep_weekly' => 4, 'backup.include_files' => true,
        'retention.mail_logs_days' => 90, 'retention.wa_logs_days' => 90, 'retention.webhook_logs_days' => 90,
        'retention.audit_logs_days' => 365, 'retention.security_events_days' => 180, 'retention.notifications_read_days' => 90,
        'retention.otp_days' => 7,
    ];

    public static function dir(): string
    {
        $d = storage_path('app/backups');
        if (! is_dir($d)) {
            @mkdir($d, 0700, true);
        }

        return $d;
    }

    public static function setting(string $k): mixed
    {
        return Settings::get($k, self::RETENTION_DEFAULTS[$k] ?? null);
    }

    public static function database(string $trigger = 'schedule'): Backup
    {
        $c = config('database.connections.'.config('database.default'));
        $file = 'db-'.now()->format('Ymd-His').'.sql.gz';
        $path = self::dir().DIRECTORY_SEPARATOR.$file;
        $bin = (string) config('krafio.mysqldump');
        $cmd = [$bin, '--host='.$c['host'], '--port='.$c['port'], '--user='.$c['username'], '--single-transaction', '--quick', '--routines', '--default-character-set=utf8mb4', $c['database']];
        $note = null;
        try {
            try {
                // Hosting tanpa proc_open (shared hosting/CloudLinux) → langsung pakai dump PHP
                if (! function_exists('proc_open')) {
                    throw new \RuntimeException('proc_open tidak tersedia');
                }
                $proc = new Process($cmd, null, ['MYSQL_PWD' => (string) $c['password']], null, 600);
                $proc->run();
                if (! $proc->isSuccessful()) {
                    throw new \RuntimeException(trim($proc->getErrorOutput()) ?: 'mysqldump gagal.');
                }
                file_put_contents($path, gzencode($proc->getOutput(), 6));
            } catch (\Throwable $e) {
                // Cadangan: dump lewat PDO agar backup tetap jalan walau mysqldump tidak bisa dijalankan
                self::phpDump($path);
                $note = 'Dump PHP (mysqldump tidak tersedia: '.mb_substr($e->getMessage(), 0, 200).')';
            }

            return Backup::create(['type' => 'database', 'file' => $file, 'size' => filesize($path), 'status' => 'success', 'message' => $note, 'trigger' => $trigger]);
        } catch (\Throwable $e) {
            @unlink($path);

            return Backup::create(['type' => 'database', 'file' => $file, 'size' => 0, 'status' => 'failed', 'message' => mb_substr($e->getMessage(), 0, 480), 'trigger' => $trigger]);
        }
    }

    /**
     * Dump SQL murni PHP (struktur + data, per tabel, batch INSERT) ke .sql.gz — setara `mysqldump` untuk dipulihkan
     * dengan `gunzip < file | mysql nama_db`. Dibaca per potongan berdasarkan urutan agar memori tetap kecil.
     */
    public static function phpDump(string $path): void
    {
        $pdo = DB::connection()->getPdo();
        $gz = gzopen($path, 'wb6');
        if ($gz === false) {
            throw new \RuntimeException('Tidak dapat menulis berkas backup.');
        }
        try {
            gzwrite($gz, '-- Krafio database dump (PHP) '.now()->toIso8601String()."\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n");
            foreach (DB::select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'") as $row) {
                $table = (string) array_values((array) $row)[0];
                $q = '`'.str_replace('`', '``', $table).'`';
                $create = (string) array_values((array) DB::selectOne("SHOW CREATE TABLE {$q}"))[1];
                gzwrite($gz, "DROP TABLE IF EXISTS {$q};\n{$create};\n\n");
                // Urutkan per primary key agar potongan LIMIT/OFFSET konsisten (tidak ada baris terlewat/ganda)
                $pk = array_map(fn ($k) => '`'.str_replace('`', '``', (string) $k->Column_name).'`', DB::select("SHOW KEYS FROM {$q} WHERE Key_name = 'PRIMARY'"));
                $order = $pk ? ' ORDER BY '.implode(',', $pk) : '';
                $offset = 0;
                do {
                    $rows = $pdo->query("SELECT * FROM {$q}{$order} LIMIT 500 OFFSET {$offset}")->fetchAll(\PDO::FETCH_NUM);
                    if ($rows) {
                        $values = array_map(fn ($r) => '('.implode(',', array_map(fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), $r)).')', $rows);
                        gzwrite($gz, "INSERT INTO {$q} VALUES\n".implode(",\n", $values).";\n");
                    }
                    $offset += 500;
                } while (count($rows) === 500);
                gzwrite($gz, "\n");
            }
            gzwrite($gz, "SET FOREIGN_KEY_CHECKS=1;\n");
        } finally {
            gzclose($gz);
        }
    }

    public static function files(string $trigger = 'schedule'): ?Backup
    {
        if (! class_exists(\ZipArchive::class)) {
            return Backup::create(['type' => 'files', 'file' => '-', 'status' => 'failed', 'message' => 'Ekstensi zip tidak tersedia.', 'trigger' => $trigger]);
        }
        $src = storage_path('app/private/uploads');
        $alt = storage_path('app/uploads');
        $root = is_dir($src) ? $src : (is_dir($alt) ? $alt : null);
        $file = 'files-'.now()->format('Ymd-His').'.zip';
        $path = self::dir().DIRECTORY_SEPARATOR.$file;
        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $count = 0;
        if ($root) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) {
                $zip->addFile($f->getPathname(), substr($f->getPathname(), strlen($root) + 1));
                $count++;
            }
        }
        $zip->addFromString('MANIFEST.txt', 'Krafio file backup '.now()->toIso8601String()."\nFiles: {$count}\n");
        $zip->close();

        return Backup::create(['type' => 'files', 'file' => $file, 'size' => filesize($path), 'status' => 'success', 'message' => (string) $count, 'trigger' => $trigger]);
    }

    /** Rotasi: pertahankan N harian terbaru + M mingguan (backup hari Minggu terbaru per minggu). */
    public static function rotate(): int
    {
        $daily = (int) self::setting('backup.keep_daily');
        $weekly = (int) self::setting('backup.keep_weekly');
        $deleted = 0;
        foreach (['database', 'files'] as $type) {
            $all = Backup::where('type', $type)->where('status', 'success')->orderByDesc('created_at')->get();
            $keep = $all->take($daily)->pluck('id')->all();
            $weeks = [];
            foreach ($all as $b) {
                $wk = $b->created_at->format('o-W');
                if ($b->created_at->isSunday() && ! isset($weeks[$wk]) && count($weeks) < $weekly) {
                    $weeks[$wk] = $b->id;
                }
            }
            $keep = array_merge($keep, array_values($weeks));
            foreach ($all as $b) {
                if (! in_array($b->id, $keep, true)) {
                    @unlink(self::dir().DIRECTORY_SEPARATOR.$b->file);
                    $b->delete();
                    $deleted++;
                }
            }
        }
        Backup::where('status', 'failed')->where('created_at', '<', now()->subDays(30))->delete();

        return $deleted;
    }

    /** Pemangkasan log operasional sesuai pengaturan retensi. @return array<string,int> */
    public static function prune(): array
    {
        $d = fn ($k) => now()->subDays(max(1, (int) self::setting($k)));
        $out = [
            'mail_logs' => DB::table('mail_logs')->where('created_at', '<', $d('retention.mail_logs_days'))->delete(),
            'wa_logs' => DB::table('wa_logs')->where('created_at', '<', $d('retention.wa_logs_days'))->delete(),
            'webhook_logs' => DB::table('webhook_logs')->where('created_at', '<', $d('retention.webhook_logs_days'))->delete(),
            'audit_logs' => DB::table('audit_logs')->where('created_at', '<', $d('retention.audit_logs_days'))->delete(),
            'security_events' => DB::table('security_events')->where('created_at', '<', $d('retention.security_events_days'))->delete(),
            'notifications' => DB::table('notifications')->whereNotNull('read_at')->where('read_at', '<', $d('retention.notifications_read_days'))->delete(),
            'otp_codes' => DB::table('otp_codes')->where('created_at', '<', $d('retention.otp_days'))->delete(),
            'sessions' => DB::table('sessions')->where('last_activity', '<', now()->subDays(30)->timestamp)->delete(),
        ];
        Audit::log('system.prune', null, $out, null);

        return $out;
    }
}
