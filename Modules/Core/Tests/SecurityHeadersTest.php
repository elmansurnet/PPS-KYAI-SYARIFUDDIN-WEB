<?php

use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Middleware\TrustHosts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Modules\Core\Http\Middleware\SecurityHeaders;

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

test('x-forwarded-host from a client does not change host or generated urls', function () {
    Route::middleware('web')->get('/__host-probe', fn () => response()->json([
        'host' => request()->getHost(),
        'url' => url('/x'),
    ]));

    $json = $this->get('/__host-probe', ['X-Forwarded-Host' => 'evil.example', 'X-Forwarded-Port' => '4443', 'X-Forwarded-Prefix' => '/evil'])->json();

    expect($json['host'])->not->toBe('evil.example')
        ->and($json['url'])->not->toContain('evil');
});

test('x-forwarded-for client ip is used', function () {
    Route::middleware('web')->get('/__ip-probe', fn () => request()->ip());

    $this->get('/__ip-probe', ['X-Forwarded-For' => '203.0.113.9'])->assertSee('203.0.113.9');
});

test('trusted hosts are limited to the APP_URL host outside local', function () {
    config(['app.url' => 'https://pps.example.id']);

    $hosts = (new TrustHosts(app()))->hosts();

    expect($hosts)->toBe(['^pps\.example\.id$']);
});

test('production forces the url root to APP_URL', function () {
    config(['app.url' => 'https://pps.example.id']);
    app()->detectEnvironment(fn () => 'production');

    try {
        (new AppServiceProvider(app()))->boot();

        expect(url('/x'))->toBe('https://pps.example.id/x');
    } finally {
        URL::forceRootUrl(null);
        URL::forceScheme(null);
    }
});

test('security headers run first in the web group and the admin panel stack', function () {
    expect(app('router')->getMiddlewareGroups()['web'][0])->toBe(SecurityHeaders::class);

    $stack = array_values(array_filter(
        Filament\Facades\Filament::getPanel('admin')->getMiddleware(),
        fn ($m) => ! str_starts_with($m, 'panel:'),
    ));
    expect($stack[0])->toBe(SecurityHeaders::class);
});

test('error responses such as 419 also carry the security headers', function () {
    Route::middleware('web')->post('/__csrf-probe', fn () => abort(419));

    $response = $this->post('/__csrf-probe');

    $response->assertStatus(419);
    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    expect($response->headers->get('Content-Security-Policy'))->toContain("default-src 'self'");
});

test('a CSP pre-set by a route is overridden unless explicitly marked sandbox', function () {
    Route::middleware('web')->get('/__weak-csp', fn () => response('ok')->header('Content-Security-Policy', 'default-src *'));
    Route::middleware('web')->get('/__marked-weak-csp', function (Request $request) {
        $request->attributes->set(SecurityHeaders::KEEP_SANDBOX_CSP, true);

        return response('ok')->header('Content-Security-Policy', 'default-src *; sandbox');
    });
    Route::middleware('web')->get('/__unmarked-sandbox', fn () => response('ok')->header('Content-Security-Policy', "sandbox; default-src 'none'"));
    Route::middleware('web')->get('/__marked-sandbox', function (Request $request) {
        $request->attributes->set(SecurityHeaders::KEEP_SANDBOX_CSP, true);

        return response('ok')->header('Content-Security-Policy', "sandbox; default-src 'none'");
    });

    foreach (['/__weak-csp', '/__marked-weak-csp', '/__unmarked-sandbox'] as $uri) {
        expect($this->get($uri)->headers->get('Content-Security-Policy'))
            ->toContain("default-src 'self'")
            ->toContain("frame-ancestors 'none'")
            ->not->toContain('default-src *')
            ->not->toStartWith('sandbox');
    }

    expect($this->get('/__marked-sandbox')->headers->get('Content-Security-Policy'))->toBe("sandbox; default-src 'none'");
});
