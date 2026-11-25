<?php

namespace App\Providers;

use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use RuntimeException;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Di production, URL yang dibangkitkan selalu mengikuti APP_URL (bukan header request).
        if ($this->app->environment('production') && config('app.url')) {
            URL::forceRootUrl((string) config('app.url'));
            URL::forceScheme(parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'https');
        }

        // Di production, backup:run manual menolak berjalan tanpa BACKUP_ARCHIVE_PASSWORD (arsip akan tidak terenkripsi).
        Event::listen(CommandStarting::class, function (CommandStarting $event): void {
            if ($event->command === 'backup:run' && $this->app->environment('production') && blank(config('backup.backup.password'))) {
                throw new RuntimeException('backup:run ditolak: BACKUP_ARCHIVE_PASSWORD kosong, arsip tidak akan terenkripsi.');
            }
        });
    }
}
