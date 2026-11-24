<?php

namespace Modules\Landing\Support;

/**
 * Ubah tautan video dari admin menjadi URL embed yang dibangun di server.
 * Hanya https dengan host YouTube atau Vimeo dan ID yang berbentuk valid; selain itu null.
 * URL asli dari admin tidak pernah dipakai langsung sebagai src iframe.
 */
class VideoEmbed
{
    private const YOUTUBE_HOSTS = ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtube-nocookie.com', 'www.youtube-nocookie.com'];

    private const YOUTUBE_ID = '/^[A-Za-z0-9_-]{11}$/';

    public static function embedUrl(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '' || strlen($url) > 500) {
            return null;
        }

        $parts = parse_url($url);

        if ($parts === false || strtolower($parts['scheme'] ?? '') !== 'https' || ! empty($parts['user']) || ! empty($parts['port'])) {
            return null;
        }

        $host = strtolower($parts['host'] ?? '');
        $path = $parts['path'] ?? '';
        parse_str($parts['query'] ?? '', $query);

        if (in_array($host, self::YOUTUBE_HOSTS, true)) {
            $id = match (true) {
                $path === '/watch' => is_string($query['v'] ?? null) ? $query['v'] : null,
                (bool) preg_match('#^/(?:embed|shorts|live)/([^/]+)/?$#', $path, $m) => $m[1],
                default => null,
            };

            return self::youtube($id);
        }

        if ($host === 'youtu.be') {
            return self::youtube(ltrim($path, '/'));
        }

        if (in_array($host, ['vimeo.com', 'www.vimeo.com'], true)
            && preg_match('#^/(\d{5,12})/?$#', $path, $m)) {
            return 'https://player.vimeo.com/video/'.$m[1];
        }

        if ($host === 'player.vimeo.com' && preg_match('#^/video/(\d{5,12})/?$#', $path, $m)) {
            return 'https://player.vimeo.com/video/'.$m[1];
        }

        return null;
    }

    public static function isValid(?string $url): bool
    {
        return self::embedUrl($url) !== null;
    }

    private static function youtube(?string $id): ?string
    {
        return $id !== null && preg_match(self::YOUTUBE_ID, $id)
            ? 'https://www.youtube-nocookie.com/embed/'.$id
            : null;
    }
}
