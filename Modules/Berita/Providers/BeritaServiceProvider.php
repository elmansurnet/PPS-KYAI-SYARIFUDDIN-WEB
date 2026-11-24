<?php

namespace Modules\Berita\Providers;

use Filament\Contracts\Plugin;
use Illuminate\Support\Facades\Gate;
use Modules\Berita\Filament\BeritaPlugin;
use Modules\Berita\Models\Category;
use Modules\Berita\Models\Post;
use Modules\Berita\Policies\CategoryPolicy;
use Modules\Berita\Policies\PostPolicy;
use Modules\Berita\Support\LatestNews;
use Modules\Core\Contracts\LatestNewsProvider;
use Modules\Core\Providers\ModuleServiceProvider;

class BeritaServiceProvider extends ModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Berita';
    }

    protected function filamentPlugin(): ?Plugin
    {
        return BeritaPlugin::make();
    }

    public function register(): void
    {
        // Core memakai bindIf (implementasi kosong); ikatan eksplisit ini selalu menang,
        // berapa pun urutan registrasi provider.
        $this->app->bind(LatestNewsProvider::class, LatestNews::class);
    }

    public function boot(): void
    {
        parent::boot();

        Gate::policy(Post::class, PostPolicy::class);
        Gate::policy(Category::class, CategoryPolicy::class);
    }
}
