<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Modules\Core\Settings\SiteSettings;
use Modules\Core\Support\Navigation;
use Modules\Core\Support\SiteIdentity;
use Modules\Core\Support\WhatsAppLink;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Halaman uji yang memakai layout publik; middleware web memasang header keamanan.
    Route::middleware('web')->get('/__layout-probe', fn () => Blade::render(
        '<x-layouts.public title="Halaman uji" description="Deskripsi halaman uji">'
        .'<x-section-heading title="Judul bagian" lead="Pengantar singkat." />'
        .'<x-button href="/daftar">Daftar</x-button><x-button variant="gold" type="submit">Kirim</x-button>'
        .'<x-button variant="ghost" href="/berita">Berita</x-button>'
        .'</x-layouts.public>'
    ));
});

function layoutSettings(array $values): void
{
    $settings = app(SiteSettings::class);

    foreach ($values as $key => $value) {
        $settings->{$key} = $value;
    }

    $settings->save();
}

test('test_layout_has_lang_id_and_viewport', function () {
    $this->withoutVite();

    $html = $this->get('/__layout-probe')->assertOk()->getContent();

    expect($html)
        ->toContain('<html lang="id"')
        ->toContain('<meta name="viewport" content="width=device-width, initial-scale=1">')
        ->toContain('<title>Halaman uji – PPs Kyai Syarifuddin</title>')
        ->toContain('<meta name="description" content="Deskripsi halaman uji">');
});

test('test_layout_has_skip_link_and_landmarks', function () {
    $this->withoutVite();

    $html = $this->get('/__layout-probe')->getContent();

    expect($html)
        ->toMatch('/<body[^>]*>\s*<a href="#konten"[^>]*>Langsung ke konten<\/a>/')
        ->toContain('<header')
        ->toContain('<nav')
        ->toContain('<main id="konten"')
        ->toContain('<footer');
});

test('test_layout_loads_no_external_font_or_icon_hosts', function () {
    $manifest = public_path('build/manifest.json');
    expect(is_file($manifest))->toBeTrue('Jalankan `npm run build` dulu: public/build/manifest.json tidak ada.');

    $html = $this->get('/__layout-probe')->assertOk()->getContent()
        .$this->get('/')->assertOk()->getContent();

    $css = collect(json_decode(file_get_contents($manifest), true))
        ->flatMap(fn (array $chunk) => array_merge([$chunk['file']], $chunk['css'] ?? []))
        ->filter(fn (string $file) => str_ends_with($file, '.css'))
        ->unique()
        ->map(fn (string $file) => file_get_contents(public_path('build/'.$file)))
        ->implode("\n");
    $fontCss = file_get_contents(public_path('fonts/fonts.css'));

    expect($html)->toContain('href="'.asset('fonts/fonts.css').'?v=');
    expect($fontCss)->toContain('@font-face')->toContain('font-display: swap');

    // Semua URL absolut di HTML, CSS build, dan CSS font hanya boleh ke host sendiri
    // (kecuali tautan keluar <a href> yang tidak memuat sumber daya).
    $hostPattern = '~fonts\.googleapis\.com|fonts\.gstatic\.com|fonts\.bunny\.net|use\.typekit|//cdn\.|cdnjs|unpkg\.com|jsdelivr|unicons|fontawesome~i';
    foreach ([$html, $css, $fontCss] as $source) {
        expect($source)->not->toMatch($hostPattern);
    }
    expect($css.$fontCss)->not->toMatch('~url\(["\']?(https?:)?//~i');
    preg_match_all('~<(?:link|script|img)\b[^>]*\s(?:href|src)="(https?://[^"]+)"~i', $html, $resources);
    foreach ($resources[1] as $resource) {
        expect(parse_url($resource, PHP_URL_HOST))->toBe(parse_url(config('app.url'), PHP_URL_HOST));
    }

    // Semua font yang dirujuk CSS font ada di server sendiri.
    preg_match_all('~url\([\'"]?\./([^"\')]+)~', $fontCss, $matches);
    expect($matches[1])->toHaveCount(4);
    foreach ($matches[1] as $path) {
        expect(is_file(public_path('fonts/'.$path)))->toBeTrue("Font {$path} tidak ditemukan di public/fonts");
    }
});

test('test_navbar_links_to_home_news_and_registration', function () {
    $this->withoutVite();

    $html = $this->get('/__layout-probe')->getContent();

    expect($html)
        ->toContain('href="'.url('/').'"')
        ->toContain('href="'.url('/berita').'"')
        ->toContain('href="'.url('/daftar').'"')
        ->toContain('>Beranda<')
        ->toContain('>Berita<')
        ->toContain('>Pendaftaran<');
});

test('test_modules_can_add_or_replace_navigation_items_by_key', function () {
    $this->withoutVite();

    app(Navigation::class)->add('berita', 'Kabar pondok', '/kabar', 20);
    app(Navigation::class)->add('galeri', 'Galeri', '/galeri', 25);

    $labels = array_column(app(Navigation::class)->items(), 'label');
    expect($labels)->toBe(['Beranda', 'Kabar pondok', 'Galeri', 'Pendaftaran']);

    $html = $this->get('/__layout-probe')->getContent();
    expect($html)->toContain('href="'.url('/kabar').'"')->not->toContain('href="'.url('/berita').'"');
});

test('test_public_layout_has_no_inline_script_or_style', function () {
    $html = $this->get('/__layout-probe')->getContent()."\n".$this->get('/')->getContent();

    // Skrip hanya boleh dari file (src=...); tidak ada blok skrip inline.
    preg_match_all('~<script\b([^>]*)>(.*?)</script>~is', $html, $scripts, PREG_SET_ORDER);
    foreach ($scripts as [$tag, $attributes, $body]) {
        expect(trim($body))->toBe('', "Skrip inline ditemukan: {$tag}");
        expect($attributes)->toContain('src=');
    }

    expect($html)
        ->not->toMatch('~<style\b~i')
        ->not->toMatch('~\sstyle\s*=~i')
        ->not->toMatch('~\son[a-z]+\s*=~i');
});

test('test_logo_fallback_shows_monogram_when_logo_path_empty', function () {
    $this->withoutVite();

    $html = $this->get('/__layout-probe')->getContent();

    expect($html)
        ->toContain('data-logo="monogram"')
        ->toMatch('~<span[^>]*aria-hidden="true"[^>]*>KS</span>~')
        ->not->toContain('data-logo="image"');
});

test('test_logo_renders_decorative_img_beside_visible_site_name_when_logo_path_set', function () {
    $this->withoutVite();
    layoutSettings(['logo_path' => 'site/logo.png']);

    $html = $this->get('/__layout-probe')->getContent();

    expect($html)
        ->toContain('data-logo="image"')
        ->toMatch('~<img[^>]+src="[^"]*/storage/site/logo\.png"[^>]+alt=""~')
        ->toMatch('~data-logo="image"[^>]*>\s*<span[^>]*>PPs Kyai Syarifuddin</span>~')
        ->not->toContain('alt="Logo')
        ->not->toMatch('~<img[^>]+(width|height)=~')
        ->not->toContain('data-logo="monogram"');
});

test('test_footer_shows_only_safe_social_links', function () {
    $this->withoutVite();
    layoutSettings(['social_links' => [
        ['platform' => 'Instagram', 'url' => 'https://instagram.com/ppskyai'],
        ['platform' => 'Situs lama', 'url' => 'http://contoh.test/lama'],
        ['platform' => 'Jahat', 'url' => 'javascript:alert(1)'],
        ['platform' => 'Rusak', 'url' => 'https://'],
    ]]);

    $html = $this->get('/__layout-probe')->getContent();

    expect($html)
        ->toMatch('~<a href="https://instagram\.com/ppskyai"[^>]*rel="noopener noreferrer"[^>]*>~')
        ->toContain('Instagram')
        ->not->toContain('contoh.test')
        ->not->toContain('javascript:')
        ->not->toContain('Rusak');
});

test('test_footer_shows_contact_details_and_whatsapp_only_when_set', function () {
    $this->withoutVite();

    $html = $this->get('/__layout-probe')->getContent();
    expect($html)->not->toContain('wa.me')->not->toContain('mailto:');

    layoutSettings([
        'address' => 'Jl. Pesantren No. 1, Desa Contoh',
        'contact_email' => 'info@pps.test',
        'whatsapp_number' => '0812-3456-7890',
    ]);

    $html = $this->get('/__layout-probe')->getContent();
    expect($html)
        ->toContain('Jl. Pesantren No. 1, Desa Contoh')
        ->toContain('href="mailto:info@pps.test"')
        ->toContain('href="https://wa.me/6281234567890?text=')
        ->toContain('+62 812-3456-7890');
});

test('test_button_renders_link_or_button_with_variants', function () {
    $this->withoutVite();

    $html = $this->get('/__layout-probe')->getContent();

    expect($html)
        ->toMatch('~<a href="/daftar"[^>]*class="[^"]*btn-primary~')
        ->toMatch('~<button type="submit"[^>]*class="[^"]*btn-gold~')
        ->toMatch('~<a href="/berita"[^>]*class="[^"]*btn-ghost~');
});

test('test_home_is_served_with_strict_csp_through_security_headers', function () {
    $response = $this->get('/');

    $response->assertOk();
    expect($response->headers->get('Content-Security-Policy'))
        ->toContain("style-src 'self'")
        ->toContain("font-src 'self'")
        ->not->toContain("'unsafe-inline'");
    expect($response->getContent())->toContain('<html lang="id"')->toContain('<main id="konten"');
});

test('test_monogram_initials_skip_generic_institution_words', function (string $name, string $expected) {
    expect(SiteIdentity::initials($name))->toBe($expected);
})->with([
    ['PPs Kyai Syarifuddin', 'KS'],
    ['Pondok Pesantren Al-Hikmah', 'H'],
    ['Pesantren', 'P'],
    ['Darul Ulum Jombang', 'DU'],
]);

test('test_whatsapp_number_is_displayed_readably', function () {
    expect(WhatsAppLink::display('0812-3456-7890'))->toBe('+62 812-3456-7890');
    expect(WhatsAppLink::display('+62 21 555'))->toBe('+62 215-55');
    expect(WhatsAppLink::display(''))->toBe('');
});

test('test_button_href_only_allows_safe_schemes', function (string $href, string $expected) {
    $html = Blade::render('<x-button :href="$href">Tautan</x-button>', ['href' => $href]);

    expect($html)->toContain('href="'.e($expected).'"');
})->with([
    'javascript' => ['javascript:alert(1)', '#'],
    'javascript berkapital dan spasi' => ['  JaVaScRiPt:alert(1)', '#'],
    'data' => ['data:text/html;base64,PHNjcmlwdD4=', '#'],
    'protokol-relatif' => ['//evil.test/x', '#'],
    'backslash' => ['/\evil.test', '#'],
    'relatif polos' => ['berita', '#'],
    'path situs' => ['/daftar', '/daftar'],
    'anchor' => ['#konten', '#konten'],
    'https' => ['https://contoh.test/a?b=1', 'https://contoh.test/a?b=1'],
    'http' => ['http://localhost/berita', 'http://localhost/berita'],
    'mailto' => ['mailto:info@pps.test', 'mailto:info@pps.test'],
    'tel' => ['tel:+6281234567890', 'tel:+6281234567890'],
]);

test('test_mobile_menu_has_no_popover_fallback', function () {
    $this->withoutVite();

    $html = $this->get('/__layout-probe')->getContent();

    // Fallback: tautan navigasi sebaris (tanpa popover) ikut dirender untuk peramban lama.
    preg_match('~<nav[^>]*class="nav-fallback[^"]*"[^>]*>(.*?)</nav>~s', $html, $fallback);
    expect($fallback)->not->toBeEmpty();
    expect($fallback[1])
        ->toContain('href="'.url('/').'"')
        ->toContain('href="'.url('/berita').'"')
        ->toContain('href="'.url('/daftar').'"');
    expect($html)->toMatch('~<button[^>]*popovertarget="menu-utama"[^>]*class="menu-toggle ~');

    // CSS sumber dan hasil build: bawaan = fallback (toggle/panel tersembunyi, nav sebaris),
    // popover hanya opt-in di dalam @supports selector(:popover-open).
    $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true);
    $built = file_get_contents(public_path('build/'.$manifest['resources/css/app.css']['file']));
    $source = file_get_contents(resource_path('css/app.css'));

    foreach ([$source, $built] as $css) {
        $css = preg_replace('~/\*.*?\*/~s', '', $css);
        $supports = strpos($css, '@supports selector(:popover-open)');
        expect($supports)->not->toBeFalse();

        $before = substr($css, 0, $supports);
        expect($before)
            ->toMatch('~\.menu-toggle\s*,\s*\.drawer\s*\{\s*display:\s*none~')
            ->toMatch('~\.nav-fallback\s*\{\s*display:\s*block~');

        $inside = substr($css, $supports, 400);
        expect($inside)
            ->toMatch('~\.menu-toggle\s*\{\s*display:\s*inline-flex~')
            ->toMatch('~\.nav-fallback\s*\{\s*display:\s*none~')
            ->toMatch('~\.drawer:popover-open\s*\{\s*display:\s*block~');
    }
});
