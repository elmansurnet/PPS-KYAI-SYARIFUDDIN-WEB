<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Halaman publik tanpa Vite, agar tes tidak bergantung pada hasil build aset.
    Route::middleware('web')->get('/__public-probe', fn () => 'ok');
});

test('test_public_pages_send_security_headers', function () {
    $response = $this->get('/__public-probe');

    $response->assertOk();
    $csp = $response->headers->get('Content-Security-Policy');

    expect($csp)
        ->toContain("default-src 'self'")
        ->toContain("script-src 'self' https://challenges.cloudflare.com")
        ->toContain("object-src 'none'")
        ->toContain("frame-ancestors 'none'")
        ->toContain("base-uri 'self'")
        ->toContain('https://www.youtube-nocookie.com')
        ->toContain('https://player.vimeo.com')
        ->toContain('https://wa.me')
        ->not->toContain("'unsafe-inline'")
        ->not->toContain("'unsafe-eval'");

    expect($response->headers->get('X-Content-Type-Options'))->toBe('nosniff');
    expect($response->headers->get('Referrer-Policy'))->toBe('strict-origin-when-cross-origin');
    expect($response->headers->get('Permissions-Policy'))
        ->toContain('camera=()')->toContain('microphone=()')->toContain('geolocation=()')
        ->toContain('payment=()')->toContain('usb=()');
});

test('hsts is sent over https', function () {
    $response = $this->get('https://localhost/__public-probe');

    expect($response->headers->get('Strict-Transport-Security'))->toContain('max-age=')->toContain('includeSubDomains');
});

test('hsts is sent in production even when the proxy hop is plain http', function () {
    app()->detectEnvironment(fn () => 'production');

    $this->get('/__public-probe')->assertHeader('Strict-Transport-Security');
});

test('hsts is not sent over plain http outside production', function () {
    $this->get('/__public-probe')->assertHeaderMissing('Strict-Transport-Security');
});

test('forwarded proto from the tunnel marks the request as secure', function () {
    $this->get('/__public-probe', ['X-Forwarded-Proto' => 'https'])
        ->assertHeader('Strict-Transport-Security');
});

test('admin panel still loads with a compatible policy that does not block inline scripts', function () {
    $response = $this->get('/admin/login');

    $response->assertOk();
    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

    $csp = (string) $response->headers->get('Content-Security-Policy');
    expect($csp)->toContain("frame-ancestors 'self'")->toContain("object-src 'none'");
    // Tanpa script-src/default-src: Filament, Livewire dan Alpine butuh skrip inline dan eval.
    expect($csp)->not->toContain('script-src')->not->toContain('default-src');
});

test('livewire endpoints get the admin-compatible policy, not the strict one', function () {
    $csp = (string) $this->post(route('default-livewire.update', absolute: false))->headers->get('Content-Security-Policy');

    expect($csp)->toContain("frame-ancestors 'self'")
        ->not->toContain('script-src')->not->toContain('default-src');
});

test('vite dev server is allowed only in local when the hot file exists', function () {
    $hot = public_path('hot');
    file_put_contents($hot, 'http://localhost:5173');

    try {
        app()->detectEnvironment(fn () => 'local');
        $local = (string) $this->get('/__public-probe')->headers->get('Content-Security-Policy');
        expect($local)->toContain('http://localhost:5173')->toContain('ws://localhost:5173');

        app()->detectEnvironment(fn () => 'production');
        $prod = (string) $this->get('/__public-probe')->headers->get('Content-Security-Policy');
        expect($prod)->not->toContain('localhost:5173');
    } finally {
        @unlink($hot);
    }
});

test('test_app_debug_is_false_in_production_config', function () {
    $config = file_get_contents(config_path('app.php'));
    expect($config)->toContain("env('APP_DEBUG', false)");

    $env = file_get_contents(base_path('.env.example'));
    expect($env)->toMatch('/^APP_DEBUG=false\s*$/m')->toMatch('/^APP_ENV=production\s*$/m');
});
