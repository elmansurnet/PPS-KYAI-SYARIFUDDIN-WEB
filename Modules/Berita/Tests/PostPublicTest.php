<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Modules\Berita\Models\Category;
use Modules\Berita\Models\Post;
use Modules\Core\Settings\SiteSettings;

uses(RefreshDatabase::class);

test('test_index_lists_only_published_posts', function () {
    Post::factory()->create(['title' => 'Berita Terbit Satu']);
    Post::factory()->draft()->create(['title' => 'Berita Konsep Rahasia']);
    Post::factory()->scheduled()->create(['title' => 'Berita Terjadwal Rahasia']);

    $this->get('/berita')->assertOk()
        ->assertSee('Berita Terbit Satu')
        ->assertDontSee('Berita Konsep Rahasia')
        ->assertDontSee('Berita Terjadwal Rahasia');
});

test('test_draft_post_url_returns_404', function () {
    $post = Post::factory()->draft()->create(['title' => 'Konsep']);

    $this->get('/berita/'.$post->slug)->assertNotFound();
    $this->get('/berita/tidak-ada')->assertNotFound();
});

test('test_future_dated_post_is_not_public', function () {
    $post = Post::factory()->scheduled()->create(['title' => 'Nanti']);

    expect($post->status)->toBe('published');
    $this->get('/berita/'.$post->slug)->assertNotFound();
    expect(Post::published()->count())->toBe(0);

    $this->travelTo(now()->addDays(10));
    $this->get('/berita/'.$post->slug)->assertOk();
});

test('test_slug_is_generated_and_unique', function () {
    $a = Post::factory()->create(['title' => 'Kajian Kitab Kuning: Fiqih & Akhlak!', 'slug' => null]);
    $b = Post::factory()->create(['title' => 'Kajian Kitab Kuning: Fiqih & Akhlak!', 'slug' => null]);
    $c = Post::factory()->create(['title' => 'Kajian Kitab Kuning: Fiqih & Akhlak!', 'slug' => null]);

    expect($a->slug)->toBe('kajian-kitab-kuning-fiqih-akhlak')
        ->and($b->slug)->toBe('kajian-kitab-kuning-fiqih-akhlak-2')
        ->and($c->slug)->toBe('kajian-kitab-kuning-fiqih-akhlak-3');

    // Stabil saat judul diubah.
    $a->update(['title' => 'Judul Baru Sama Sekali']);
    expect($a->fresh()->slug)->toBe('kajian-kitab-kuning-fiqih-akhlak');

    // Diubah eksplisit: dibersihkan dan tetap unik.
    $a->update(['slug' => 'Kajian Kitab Kuning Fiqih Akhlak 2']);
    expect($a->fresh()->slug)->toBe('kajian-kitab-kuning-fiqih-akhlak-2-2');

    // Judul tanpa huruf tetap menghasilkan slug yang valid.
    $d = Post::factory()->create(['title' => '!!!', 'slug' => null]);
    expect($d->slug)->not->toBe('')->and($d->slug)->toMatch('/^[a-z0-9-]+$/');
});

test('test_published_post_has_meta_title_and_cover', function () {
    $post = Post::factory()->create([
        'title' => 'Wisuda Santri Angkatan Ke-5',
        'body' => '<p>Acara wisuda berlangsung khidmat di aula pondok bersama para wali santri.</p>',
        'cover_path' => 'berita/cover-wisuda.jpg',
        'published_at' => '2026-08-17 08:00:00',
    ]);

    $url = url('/berita/'.$post->slug);
    $site = app(SiteSettings::class)->site_name;

    $response = $this->get('/berita/'.$post->slug)->assertOk()
        ->assertSee('<title>Wisuda Santri Angkatan Ke-5 – '.e($site).'</title>', false)
        ->assertSee('<meta name="description" content="Acara wisuda berlangsung khidmat di aula pondok bersama para wali santri.">', false)
        ->assertSee('<link rel="canonical" href="'.$url.'">', false)
        ->assertSee('<meta property="og:title" content="Wisuda Santri Angkatan Ke-5">', false)
        ->assertSee('<meta property="og:type" content="article">', false)
        ->assertSee('<meta property="og:url" content="'.$url.'">', false)
        ->assertSee('/storage/berita/cover-wisuda.jpg', false)
        ->assertSee('17 Agustus 2026');

    expect($response->getContent())->toMatch('~<meta property="og:image" content="https?://[^"]+/storage/berita/cover-wisuda\.jpg">~');
    expect(substr_count($response->getContent(), '<h1'))->toBe(1);
    expect($response->getContent())->toContain('loading="eager"');
});

test('article without cover has no og:image and still renders', function () {
    $post = Post::factory()->create(['cover_path' => null]);

    $this->get('/berita/'.$post->slug)->assertOk()->assertDontSee('og:image');
});

test('index paginates nine per page and keeps category filter in links', function () {
    $cat = Category::factory()->create(['name' => 'Kegiatan', 'slug' => 'kegiatan']);
    Post::factory()->count(12)->sequence(fn ($s) => ['published_at' => now()->subHours($s->index + 1)])->create(['category_id' => $cat->id]);
    Post::factory()->count(3)->create();

    $page1 = $this->get('/berita')->assertOk();
    expect(substr_count($page1->getContent(), 'data-berita-card'))->toBe(9);
    $this->get('/berita?page=2')->assertOk();

    $filtered = $this->get('/berita?kategori=kegiatan')->assertOk();
    expect(substr_count($filtered->getContent(), 'data-berita-card'))->toBe(9);
    $filtered->assertSee('kategori=kegiatan', false)->assertSee('page=2', false);
    expect(substr_count($this->get('/berita?kategori=kegiatan&page=2')->getContent(), 'data-berita-card'))->toBe(3);

    $this->get('/berita?kategori=tidak-ada')->assertOk()->assertSee('Belum ada berita');
});

test('empty index shows an Indonesian empty state', function () {
    $this->get('/berita')->assertOk()->assertSee('Belum ada berita');
});

test('month names are Indonesian', function () {
    $post = Post::factory()->create(['published_at' => '2026-02-05 08:00:00']);

    $this->get('/berita/'.$post->slug)->assertSee('5 Februari 2026');
    $this->get('/berita')->assertSee('5 Februari 2026');
});

test('article shows author name only and other posts of the category', function () {
    $author = User::factory()->create(['name' => 'Ustadz Penulis', 'email' => 'rahasia@example.test']);
    $cat = Category::factory()->create();
    $post = Post::factory()->create(['author_id' => $author->id, 'category_id' => $cat->id]);
    Post::factory()->create(['category_id' => $cat->id, 'title' => 'Berita Sekategori']);
    Post::factory()->draft()->create(['category_id' => $cat->id, 'title' => 'Konsep Sekategori']);

    $this->get('/berita/'.$post->slug)->assertOk()
        ->assertSee('Ustadz Penulis')
        ->assertDontSee('rahasia@example.test')
        ->assertSee('Berita Sekategori')
        ->assertDontSee('Konsep Sekategori');
});

test('article body is sanitised at render even if the database holds hostile html', function () {
    $post = Post::factory()->create();
    // Lewati hook model: simulasi data kotor yang masuk lewat jalur lain.
    Post::whereKey($post->id)->update(['body' => '<p>Aman</p><script>alert(1)</script><img src=x onerror=alert(2)>']);

    $html = $this->get('/berita/'.$post->slug)->assertOk()->getContent();

    expect($html)->toContain('Aman')->not->toContain('alert(1)')->not->toContain('onerror')->not->toContain('<img src="x"');
});

test('deleting a post or replacing the cover deletes the old file', function () {
    Storage::fake('public');
    Storage::disk('public')->put('berita/lama.jpg', 'x');
    Storage::disk('public')->put('berita/baru.jpg', 'x');
    $post = Post::factory()->create(['cover_path' => 'berita/lama.jpg']);

    $post->update(['title' => 'Ganti judul saja']);
    Storage::disk('public')->assertExists('berita/lama.jpg');

    $post->update(['cover_path' => 'berita/baru.jpg']);
    Storage::disk('public')->assertMissing('berita/lama.jpg');

    $post->update(['cover_path' => null]);
    Storage::disk('public')->assertMissing('berita/baru.jpg');

    Storage::disk('public')->put('berita/c.jpg', 'x');
    $post->update(['cover_path' => 'berita/c.jpg']);
    $post->delete();
    Storage::disk('public')->assertMissing('berita/c.jpg');
});
