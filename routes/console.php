<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Cadangan harian terenkripsi (config/backup.php). Berjalan hanya bila BACKUP_ARCHIVE_PASSWORD terisi,
 * supaya tidak pernah membuat arsip tanpa enkripsi; bila kosong, kesalahan dicatat di log tiap hari.
 * Butuh cron server: `* * * * * php artisan schedule:run` (lihat docs/DEPLOY-aapanel.md).
 */
$backupReady = fn (): bool => filled(config('backup.backup.password'));

Schedule::command('backup:run')->dailyAt('02:00')->when($backupReady)->withoutOverlapping()->onOneServer();
Schedule::command('backup:clean')->dailyAt('02:30')->when($backupReady)->withoutOverlapping()->onOneServer();
Schedule::command('backup:monitor')->dailyAt('03:00')->when($backupReady)->onOneServer();
Schedule::call(function () use ($backupReady) {
    if (! $backupReady()) {
        Log::error('Cadangan tidak berjalan: BACKUP_ARCHIVE_PASSWORD kosong.');
    }
})->dailyAt('02:00')->name('backup-password-check');
