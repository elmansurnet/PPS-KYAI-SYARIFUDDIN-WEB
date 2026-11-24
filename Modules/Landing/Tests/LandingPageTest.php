<?php

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Modules\Core\Contracts\LatestNewsProvider;
use Modules\Core\Database\Seeders\RoleSeeder;
use Modules\Core\Support\NewsItem;
use Modules\Landing\Database\Seeders\LandingSeeder;
use Modules\Landing\Filament\Resources\LandingSectionResource;
use Modules\Landing\Filament\Resources\LandingSectionResource\Pages\EditLandingSection;
use Modules\Landing\Models\LandingSection;
use Modules\Landing\Support\VideoEmbed;

uses(RefreshDatabase::class);

function landingSection(string $key, array $attributes = []): LandingSection
{
    return LandingSection::create($attributes + [
        'key' => $key,
        'title' => 'Judul '.$key,
        'body' => 'Isi '.$key,
        'sort_order' => 0,
        'is_visible' => true,
    ]);
}

function landingUserWithRole(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

test('test_home_renders_visible_sections_in_sort_order', function () {
    landingSection('sejarah', ['title' => 'Bagian Ketiga', 'sort_order' => 30]);
    landingSection('hero', ['title' => 'Bagian Pertama', 'sort_order' => 10]);
    landingSection('proses_belajar', ['title' => 'Bagian Kedua', 'sort_order' => 20, 'body' => null, 'items' => [
        ['title' => 'Mengaji', 'body' => 'Setiap pagi', 'image_path' => null],
    ]]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSeeInOrder(['Bagian Pertama', 'Bagian Kedua', 'Bagian Ketiga']);
});

test('test_hidden_section_is_not_rendered', function () {
    landingSection('hero', ['title' => 'Tampil', 'sort_order' => 1]);
    landingSection('sejarah', ['title' => 'Sejarah Tersembunyi', 'sort_order' => 2, 'is_visible' => false]);

    $this->get('/')->assertOk()->assertSee('Tampil')->assertDontSee('Sejarah Tersembunyi');
});

test('a visible section without body, media, items or video is not rendered', function () {
    landingSection('hero', ['title' => 'Tampil', 'sort_order' => 1]);
    landingSection('sejarah', ['title' => 'Sejarah Kosong', 'body' => null, 'sort_order' => 2]);

    $this->get('/')->assertOk()->assertDontSee('Sejarah Kosong');
});

test('hero and ajakan daftar fall back to neutral copy and the page has exactly one h1', function () {
    landingSection('hero', ['title' => null, 'body' => null]);
    landingSection('ajakan_daftar', ['title' => null, 'body' => null, 'sort_order' => 1]);

    $response = $this->get('/')->assertOk()->assertSee('/daftar', false);

    expect(substr_count($response->getContent(), '<h1'))->toBe(1);
    $response->assertSee(app(Modules\Core\Settings\SiteSettings::class)->site_name);
});

test('test_home_works_when_news_module_disabled', function () {
    landingSection('hero');

    expect(app(LatestNewsProvider::class)->latest(3))->toBeEmpty();
    $this->get('/')->assertOk()->assertDontSee('Berita terbaru');
});

test('news strip renders items from the Core contract', function () {
    landingSection('hero');
    $this->app->bind(LatestNewsProvider::class, fn () => new class implements LatestNewsProvider
    {
        public function latest(int $limit): Collection
        {
            return collect([new NewsItem(
                title: 'Kajian Kitab Pekan Ini',
                url: 'https://example.test/berita/kajian',
                publishedAt: now()->setDate(2026, 11, 20),
                excerpt: 'Ringkasan kajian.',
                coverUrl: null,
            )]);
        }
    });

    $this->get('/')->assertOk()
        ->assertSee('Berita terbaru')
        ->assertSee('Kajian Kitab Pekan Ini')
        ->assertSee('https://example.test/berita/kajian', false);
});

test('video section embeds only a server-built youtube or vimeo player', function () {
    landingSection('video_profil', ['video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ&x=<script>', 'body' => null]);

    $this->get('/')->assertOk()
        ->assertSee('src="https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ"', false)
        ->assertSee('loading="lazy"', false)
        ->assertDontSee('<script', false);
});

test('video section with a non video url renders nothing', function () {
    landingSection('video_profil', ['video_url' => 'https://evil.example/embed/abc', 'body' => null, 'title' => 'Video Jahat']);

    $this->get('/')->assertOk()->assertDontSee('Video Jahat')->assertDontSee('evil.example');
});

test('test_video_url_only_accepts_youtube_or_vimeo', function () {
    foreach ([
        'https://www.youtube.com/watch?v=dQw4w9WgXcQ' => 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ',
        'https://youtu.be/dQw4w9WgXcQ?t=5' => 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ',
        'https://www.youtube.com/embed/dQw4w9WgXcQ' => 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ',
        'https://vimeo.com/76979871' => 'https://player.vimeo.com/video/76979871',
        'https://player.vimeo.com/video/76979871' => 'https://player.vimeo.com/video/76979871',
    ] as $input => $embed) {
        expect(VideoEmbed::embedUrl($input))->toBe($embed);
    }

    foreach ([
        'http://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'https://evil.example/watch?v=dQw4w9WgXcQ',
        'https://youtube.com.evil.example/watch?v=dQw4w9WgXcQ',
        'https://www.youtube.com/watch?v=short',
        'javascript:alert(1)',
        'https://vimeo.com/abc',
        '',
        null,
    ] as $input) {
        expect(VideoEmbed::embedUrl($input))->toBeNull();
    }

    // Aturan form Filament memakai parser yang sama.
    $this->seed(RoleSeeder::class);
    $this->seed(LandingSeeder::class);
    Filament::setCurrentPanel('admin');
    $this->actingAs(landingUserWithRole('admin'));
    $record = LandingSection::where('key', 'video_profil')->firstOrFail();

    Livewire::test(EditLandingSection::class, ['record' => $record->getRouteKey()])
        ->fillForm(['video_url' => 'https://evil.example/video'])
        ->call('save')
        ->assertHasFormErrors(['video_url']);

    Livewire::test(EditLandingSection::class, ['record' => $record->getRouteKey()])
        ->fillForm(['video_url' => 'https://youtu.be/dQw4w9WgXcQ'])
        ->call('save')
        ->assertHasNoFormErrors();
});

test('seeder creates six sections, hides empty ones and never overwrites admin edits', function () {
    $this->seed(RoleSeeder::class);
    $this->seed(LandingSeeder::class);

    expect(LandingSection::count())->toBe(6);
    expect(LandingSection::where('is_visible', true)->pluck('key')->sort()->values()->all())->toBe(['ajakan_daftar', 'hero']);

    LandingSection::where('key', 'sejarah')->update(['body' => 'Diisi admin', 'is_visible' => true]);
    $this->seed(LandingSeeder::class);

    expect(LandingSection::count())->toBe(6);
    expect(LandingSection::where('key', 'sejarah')->first()->body)->toBe('Diisi admin');
    $this->get('/')->assertOk()->assertSee('Diisi admin');
});

test('replacing the media file deletes the old file', function () {
    Storage::fake('public');
    Storage::disk('public')->put('landing/lama.jpg', 'x');
    $row = landingSection('hero', ['media_path' => 'landing/lama.jpg']);

    $row->update(['media_path' => 'landing/baru.jpg']);

    Storage::disk('public')->assertMissing('landing/lama.jpg');
});

test('test_penulis_berita_cannot_open_landing_resource', function () {
    $this->seed(RoleSeeder::class);
    $this->seed(LandingSeeder::class);
    Filament::setCurrentPanel('admin');

    $this->actingAs(landingUserWithRole('penulis_berita'));
    expect(LandingSectionResource::canViewAny())->toBeFalse();
    $this->get('/admin/landing-sections')->assertForbidden();
});

test('admin can open landing resource but cannot create or delete sections', function () {
    $this->seed(RoleSeeder::class);
    $this->seed(LandingSeeder::class);
    Filament::setCurrentPanel('admin');

    $this->actingAs(landingUserWithRole('admin'));
    expect(LandingSectionResource::canViewAny())->toBeTrue();
    expect(LandingSectionResource::canCreate())->toBeFalse();
    $this->get('/admin/landing-sections')->assertOk();

    $this->actingAs(landingUserWithRole('panitia_pendaftaran'));
    expect(LandingSectionResource::canViewAny())->toBeFalse();
});

test('db:seed on a fresh database gives admin the landing permissions', function () {
    $this->artisan('db:seed')->assertSuccessful();

    $admin = landingUserWithRole('admin');
    expect($admin->can('Update:LandingSection'))->toBeTrue();
    expect(landingUserWithRole('penulis_berita')->can('ViewAny:LandingSection'))->toBeFalse();
});
