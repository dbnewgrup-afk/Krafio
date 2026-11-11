<?php

use App\Services\Backups;
use App\Services\Billing;
use App\Services\Payment\JualKirimInvoices;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Perintah operasional (PRD Bab 49, 52.55–52.60)
Artisan::command('krafio:backup {--files : Sertakan arsip unggahan}', function () {
    $db = Backups::database('manual');
    $this->info("Database: {$db->status} {$db->file} ".($db->message ?? ''));
    if ($this->option('files') || Backups::setting('backup.include_files')) {
        $f = Backups::files('manual');
        $this->info("Files: {$f->status} {$f->file} ".($f->message ?? ''));
    }
    $this->info('Rotasi menghapus '.Backups::rotate().' backup lama.');
})->purpose('Backup database (dan berkas) lalu rotasi sesuai retensi');

Artisan::command('krafio:prune', function () {
    foreach (Backups::prune() as $k => $n) {
        $this->line("{$k}: {$n} baris dihapus");
    }
})->purpose('Pangkas log operasional sesuai pengaturan retensi');

// Scheduler tunggal
Schedule::call(fn () => Billing::releaseCommissions())->hourly()->name('release-commissions')->withoutOverlapping();
Schedule::call(fn () => Billing::releaseExpiredReservations())->everyFifteenMinutes()->name('expire-payments')->withoutOverlapping();
Schedule::call(fn () => JualKirimInvoices::syncPending())->everyMinute()->name('jualkirim-invoice-sync')->withoutOverlapping(); // JualKirim tanpa webhook → polling
Schedule::call(fn () => \App\Services\Ops::recoverStuckJobs())->everyFiveMinutes()->name('recover-stuck-jobs')->withoutOverlapping();
Schedule::call(fn () => Billing::renewals())->hourly()->name('subscription-renewals')->withoutOverlapping();
Schedule::call(function () {
    Backups::database();
    if (Backups::setting('backup.include_files')) {
        Backups::files();
    }
    Backups::rotate();
})->dailyAt('02:00')->timezone('Asia/Jakarta')->name('daily-backup')->withoutOverlapping();
Schedule::call(fn () => Backups::prune())->dailyAt('03:00')->timezone('Asia/Jakarta')->name('daily-prune')->withoutOverlapping();