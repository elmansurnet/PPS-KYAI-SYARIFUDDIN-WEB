<?php

namespace Modules\Pendaftaran\Providers;

use Filament\Contracts\Plugin;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Core\Providers\ModuleServiceProvider;
use Modules\Core\Support\Navigation;
use Modules\Pendaftaran\Console\RehashNikCommand;
use Modules\Pendaftaran\Filament\PendaftaranPlugin;
use Modules\Pendaftaran\Models\Applicant;
use Modules\Pendaftaran\Models\Jenjang;
use Modules\Pendaftaran\Models\KelasOption;
use Modules\Pendaftaran\Models\PendidikanTerakhir;
use Modules\Pendaftaran\Policies\ApplicantPolicy;
use Modules\Pendaftaran\Policies\JenjangPolicy;
use Modules\Pendaftaran\Policies\KelasOptionPolicy;
use Modules\Pendaftaran\Policies\PendidikanTerakhirPolicy;
use Modules\Pendaftaran\Support\PrivateDiskAdapter;

class PendaftaranServiceProvider extends ModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Pendaftaran';
    }

    protected function filamentPlugin(): ?Plugin
    {
        return PendaftaranPlugin::make();
    }

    public function register(): void
    {
        $this->mergeConfigFrom($this->modulePath().'/Config/pendaftaran.php', 'pendaftaran');
    }

    public function boot(): void
    {
        parent::boot();

        Gate::policy(Applicant::class, ApplicantPolicy::class);
        Gate::policy(Jenjang::class, JenjangPolicy::class);
        Gate::policy(KelasOption::class, KelasOptionPolicy::class);
        Gate::policy(PendidikanTerakhir::class, PendidikanTerakhirPolicy::class);

        // Disk file KK (config/filesystems.php 'kk'): lokal, privat, tanpa URL.
        Storage::extend('kk_private', fn ($app, array $config) => PrivateDiskAdapter::fromConfig($config));

        Blade::anonymousComponentPath($this->modulePath().'/Resources/views/components', 'pendaftaran');

        app(Navigation::class)->add('pendaftaran', 'Pendaftaran', '/daftar', 90, emphasis: true);

        $this->registerRateLimiters();

        if ($this->app->runningInConsole()) {
            $this->commands([RehashNikCommand::class]);
        }
    }

    private function registerRateLimiters(): void
    {
        $tooMany = fn (Request $request, array $headers) => response()
            ->view('pendaftaran::daftar.terlalu-banyak', [], 429)
            ->withHeaders($headers);

        $limits = fn (): array => (array) config('pendaftaran.rate_limits', []);
        // Penanda acak per sesi (bukan ID sesi, yang bisa berganti), disimpan di sesi pada pemakaian pertama.
        $session = function (Request $request): string {
            if (! $request->hasSession()) {
                return $request->ip().'|-';
            }

            $token = $request->session()->get('pendaftaran.rl');
            if (! is_string($token)) {
                $request->session()->put('pendaftaran.rl', $token = Str::random(32));
            }

            return $request->ip().'|'.$token;
        };

        RateLimiter::for('daftar-langkah', fn (Request $request) => [
            Limit::perMinute((int) ($limits()['langkah_per_menit_sesi'] ?? 30))->by('sesi|'.$session($request))->response($tooMany),
            Limit::perMinute((int) ($limits()['langkah_per_menit_ip'] ?? 120))->by('ip|'.$request->ip())->response($tooMany),
        ]);

        RateLimiter::for('daftar-kirim', fn (Request $request) => [
            Limit::perMinute((int) ($limits()['kirim_per_menit_sesi'] ?? 3))->by('sesi|'.$session($request))->response($tooMany),
            Limit::perMinute((int) ($limits()['kirim_per_menit_ip'] ?? 10))->by('ip|'.$request->ip())->response($tooMany),
            Limit::perHour((int) ($limits()['kirim_per_jam_ip'] ?? 30))->by('jam|'.$request->ip())->response($tooMany),
        ]);
    }
}
