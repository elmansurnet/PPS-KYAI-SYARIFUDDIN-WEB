<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Berita\Models\Post;
use Modules\Berita\Support\LatestNews;
use Modules\Core\Contracts\LatestNewsProvider;
use Modules\Core\Support\NewsItem;
use Modules\Core\Support\NullLatestNewsProvider;

uses(RefreshDatabase::class);

test('berita provider replaces the null provider from core', function () {
    expect(app(LatestNewsProvider::class))->toBeInstanceOf(LatestNews::class);
});

test('latest returns published posts newest first as news items honouring the limit', function () {
    Post::factory()->create(['title' => 'Lama', 'published_at' => now()->subDays(5)]);
    Post::factory()->create(['title' => 'Baru', 'published_at' => now()->subDay()]);
    Post::factory()->create(['title' => 'Tengah', 'published_at' => now()->subDays(2)]);
    Post::factory()->draft()->create(['title' => 'Konsep']);
    Post::factory()->scheduled()->create(['title' => 'Terjadwal']);

    $items = app(LatestNewsProvider::class)->latest(2);

    expect($items)->toHaveCount(2)
        ->and($items->every(fn ($i) => $i instanceof NewsItem))->toBeTrue()
        ->and($items->pluck('title')->all())->toBe(['Baru', 'Tengah']);
    expect(app(LatestNewsProvider::class)->latest(10)->pluck('title')->all())->toBe(['Baru', 'Tengah', 'Lama']);
});

test('news item has absolute url, cover url and a plain text excerpt without mid-word cut', function () {
    $words = implode(' ', array_fill(0, 60, 'kalimatpanjang'));
    $post = Post::factory()->create([
        'body' => '<h2>Judul</h2><p>Paragraf <strong>pertama</strong> &amp; ringkas.</p><p>'.$words.'</p><ul><li>satu</li><li>dua</li></ul>',
        'cover_path' => 'berita/c.jpg',
    ]);

    $item = app(LatestNewsProvider::class)->latest(1)->first();

    expect($item->url)->toBe(route('berita.show', $post->slug))
        ->and($item->coverUrl)->toContain('berita/c.jpg')
        ->and($item->excerpt)->not->toMatch('/<[^>]+>/')
        ->and($item->excerpt)->toStartWith('Judul Paragraf pertama & ringkas. kalimatpanjang')
        ->and(mb_strlen($item->excerpt))->toBeLessThanOrEqual(165)
        ->and(rtrim($item->excerpt, '…'))->toMatch('/(kalimatpanjang ?)+$/');
    expect(app(LatestNewsProvider::class)->latest(0))->toBeEmpty();
});

test('home news strip shows published posts when berita is enabled', function () {
    Post::factory()->create(['title' => 'Kabar Pondok Terbaru']);
    Post::factory()->draft()->create(['title' => 'Kabar Konsep']);

    $this->get('/')->assertOk()->assertSee('Berita terbaru')->assertSee('Kabar Pondok Terbaru')->assertDontSee('Kabar Konsep');
});

test('home still works when the news provider is the null one', function () {
    Post::factory()->create(['title' => 'Kabar Pondok Terbaru']);
    $this->app->bind(LatestNewsProvider::class, NullLatestNewsProvider::class);

    $this->get('/')->assertOk()->assertDontSee('Kabar Pondok Terbaru');
});
