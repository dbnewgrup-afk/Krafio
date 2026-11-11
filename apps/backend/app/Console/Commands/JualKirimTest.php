<?php

namespace App\Console\Commands;

use App\Services\JualKirim\Exceptions\JualKirimAuthException;
use App\Services\JualKirim\Exceptions\JualKirimException;
use App\Services\JualKirim\JualKirimClient;
use Illuminate\Console\Command;

/**
 * Uji koneksi JualKirim: login, GET /api/v1/me, cocokkan JUALKIRIM_API_KEY dengan organisasi akun.
 * Hanya memanggil endpoint baca — tidak ada transaksi.
 */
class JualKirimTest extends Command
{
    protected $signature = 'jualkirim:test {--fresh : Hapus token tersimpan lalu login ulang}';

    protected $description = 'Uji koneksi & kredensial JualKirim API v1 (login + /me + cek API key)';

    public function handle(JualKirimClient $client): int
    {
        if (! config('jualkirim.email') || ! config('jualkirim.password')) {
            $this->error('JUALKIRIM_EMAIL / JUALKIRIM_PASSWORD belum diisi di .env.');

            return self::FAILURE;
        }
        if ($this->option('fresh')) {
            $client->forgetToken();
        }

        $this->line('Server  : '.config('jualkirim.base_url'));
        $this->line('Email   : '.config('jualkirim.email'));
        $this->line('API key : '.JualKirimClient::maskKey($client->configuredApiKey()));

        try {
            $client->getToken();
            $this->info('✓ Login berhasil (token tersimpan di cache).');
            $me = $client->me();
        } catch (JualKirimAuthException $e) {
            $this->error('✗ '.$e->getMessage());

            return self::FAILURE;
        } catch (JualKirimException $e) {
            $this->error('✗ '.$e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->line('User    : '.($me->fullName() ?: '(tanpa nama)').' <'.$me->email.'>');
        $this->table(['Organisasi', 'ID', 'API key'], array_map(fn ($o) => [$o->name, $o->id, JualKirimClient::maskKey($o->apiKey)], $me->organizations));

        $key = $client->configuredApiKey();
        if (! $key) {
            $this->warn('JUALKIRIM_API_KEY belum diisi. Pilih salah satu API key organisasi di atas (lihat di dashboard JualKirim).');

            return self::FAILURE;
        }
        $org = $me->organizationForKey($key);
        if (! $org) {
            $this->error('✗ JUALKIRIM_API_KEY tidak cocok dengan organisasi mana pun milik akun ini. Request selain /me akan ditolak 401.');

            return self::FAILURE;
        }
        $this->info("✓ API key cocok dengan organisasi \"{$org->name}\".");

        return self::SUCCESS;
    }
}
