<?php

namespace Modules\Core\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class TurnstileVerifier
{
    private const ENDPOINT = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    /** Gagal tertutup: setiap kegagalan jaringan/respons mengembalikan false, tidak pernah melempar. */
    public function passes(string $token, ?string $ip = null): bool
    {
        $secret = (string) config('services.turnstile.secret');

        if ($token === '' || $secret === '') {
            return false;
        }

        try {
            $response = Http::asForm()->timeout(5)->post(self::ENDPOINT, array_filter([
                'secret' => $secret,
                'response' => $token,
                'remoteip' => $ip,
            ], fn ($v) => $v !== null && $v !== ''));

            if ($response->failed()) {
                Log::warning('Turnstile siteverify gagal.', ['status' => $response->status()]);

                return false;
            }

            $data = $response->json();

            if (! is_array($data)) {
                Log::warning('Turnstile siteverify mengembalikan respons tidak valid.');

                return false;
            }

            return ($data['success'] ?? null) === true;
        } catch (Throwable $e) {
            Log::warning('Turnstile siteverify tidak dapat dihubungi.', ['error' => $e->getMessage()]);

            return false;
        }
    }
}
