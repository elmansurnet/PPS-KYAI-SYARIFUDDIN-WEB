<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Modules\Core\Http\Middleware\SecurityHeaders;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Lalu lintas masuk hanya lewat Cloudflare Tunnel (cloudflared), jadi proxy dipercaya
        // HANYA untuk X-Forwarded-For dan -Proto. Host/Port/Prefix tidak dipercaya karena
        // Cloudflare meneruskan header buatan klien. WAJIB: web server origin hanya
        // boleh dijangkau lewat cloudflared (bind ke 127.0.0.1), bukan dari jaringan luar.
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO,
        );
        // Di luar local/testing hanya host dari APP_URL yang diterima (tanpa subdomain).
        $middleware->trustHosts(
            at: fn () => array_filter([
                ($host = parse_url((string) config('app.url'), PHP_URL_HOST)) ? '^'.preg_quote($host).'$' : null,
            ]),
            subdomains: false,
        );
        $middleware->prependToGroup('web', SecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
