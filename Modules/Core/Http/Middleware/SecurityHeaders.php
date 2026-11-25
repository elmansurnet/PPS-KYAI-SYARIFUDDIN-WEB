<?php

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header keamanan untuk semua respons web.
 *
 * - Halaman publik: CSP ketat (tanpa 'unsafe-inline'/'unsafe-eval' untuk skrip).
 * - Panel admin (/admin*) dan endpoint Livewire (/livewire*): Filament, Livewire dan Alpine
 *   memakai skrip inline dan evaluasi ekspresi, sehingga script-src ketat akan merusaknya.
 *   Di sana hanya CSP yang kompatibel (anti-framing, tanpa object, base-uri terkunci)
 *   plus header non-CSP yang sama.
 *
 * Halaman publik interaktif harus memakai build Livewire/Alpine yang aman-CSP atau form
 * server-rendered; nonce per-request dapat ditambahkan di handle()/publicPolicy().
 */
class SecurityHeaders
{
    /** Atribut request: controller unduhan menyatakan CSP sandbox miliknya harus dipertahankan. */
    public const KEEP_SANDBOX_CSP = 'security_headers.keep_sandbox_csp';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = $response->headers;
        // Gagal tertutup: CSP bawaan selalu dipasang, KECUALI controller secara eksplisit menandai request
        // (atribut KEEP_SANDBOX_CSP) DAN CSP yang ditetapkannya berupa sandbox (mis. unduhan file KK
        // "sandbox; default-src 'none'", lebih ketat dari kebijakan bawaan).
        if (! $this->keepsSandboxPolicy($request, $response)) {
            $headers->set('Content-Security-Policy', $this->isAdminSurface($request)
                ? $this->adminPolicy()
                : $this->publicPolicy());
        }
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'accelerometer=(), autoplay=(), camera=(), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), payment=(), usb=()');

        if ($request->isSecure() || app()->environment('production')) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    private function keepsSandboxPolicy(Request $request, Response $response): bool
    {
        $preset = (string) $response->headers->get('Content-Security-Policy', '');

        return $request->attributes->get(self::KEEP_SANDBOX_CSP) === true
            && preg_match('/^sandbox(;|$)/', $preset) === 1;
    }

    private function isAdminSurface(Request $request): bool
    {
        return $request->is('admin', 'admin/*', 'livewire', 'livewire/*', 'livewire-*', 'livewire-*/*');
    }

    private function adminPolicy(): string
    {
        return implode('; ', [
            "frame-ancestors 'self'",
            "object-src 'none'",
            "base-uri 'self'",
        ]);
    }

    private function publicPolicy(): string
    {
        $vite = $this->viteDevOrigin();

        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' https://challenges.cloudflare.com".($vite ? " {$vite}" : ''),
            // 'unsafe-inline' hanya untuk style saat dev server Vite (HMR menyuntik tag style).
            "style-src 'self'".($vite ? " 'unsafe-inline' {$vite}" : ''),
            "img-src 'self' data: https:",
            "font-src 'self'".($vite ? " {$vite}" : ''),
            'frame-src https://challenges.cloudflare.com https://www.youtube-nocookie.com https://www.youtube.com https://player.vimeo.com',
            "connect-src 'self'".($vite ? " {$vite} ".preg_replace('/^http/', 'ws', $vite) : ''),
            "base-uri 'self'",
            "form-action 'self' https://wa.me",
            "frame-ancestors 'none'",
            "object-src 'none'",
        ]);
    }

    /** Origin dev server Vite, hanya di lingkungan local saat file public/hot ada. */
    private function viteDevOrigin(): ?string
    {
        if (! app()->environment('local') || ! is_file(public_path('hot'))) {
            return null;
        }

        $parts = parse_url(trim((string) file_get_contents(public_path('hot'))));

        if (! isset($parts['scheme'], $parts['host']) || ! in_array($parts['scheme'], ['http', 'https'], true)) {
            return null;
        }

        return $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
    }
}
