<?php

namespace App\Console\Commands;

use App\Services\JualKirim\JualKirimClient;
use Illuminate\Console\Command;

class JualKirimClearToken extends Command
{
    protected $signature = 'jualkirim:clear-token';

    protected $description = 'Hapus JWT JualKirim dari cache (login ulang pada request berikutnya)';

    public function handle(JualKirimClient $client): int
    {
        $client->forgetToken();
        $this->info('Token JualKirim dihapus dari cache.');

        return self::SUCCESS;
    }
}
