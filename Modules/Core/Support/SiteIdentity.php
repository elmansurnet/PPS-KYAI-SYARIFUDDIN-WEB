<?php

namespace Modules\Core\Support;

/**
 * Bantuan tampilan identitas situs untuk layout publik.
 */
class SiteIdentity
{
    /** Kata umum nama lembaga yang dilewati saat membuat monogram. */
    private const GENERIC_WORDS = ['pp', 'pps', 'ponpes', 'pondok', 'pesantren', 'yayasan', 'al', 'el'];

    /**
     * Inisial untuk monogram saat logo belum diunggah, mis. "PPs Kyai Syarifuddin" menjadi "KS".
     */
    public static function initials(string $siteName): string
    {
        $words = preg_split('/[\s\-]+/u', trim($siteName), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $significant = array_values(array_filter(
            $words,
            fn (string $word) => ! in_array(mb_strtolower(trim($word, '.')), self::GENERIC_WORDS, true),
        ));

        $source = $significant ?: $words;

        $initials = implode('', array_map(
            fn (string $word) => mb_substr($word, 0, 1),
            array_slice($source, 0, 2),
        ));

        return mb_strtoupper($initials);
    }

    /**
     * Hanya tautan media sosial https dengan host yang valid; sisanya dibuang.
     *
     * @param  array<int, mixed>  $links
     * @return list<array{platform: string, url: string}>
     */
    public static function safeSocialLinks(array $links): array
    {
        $safe = [];

        foreach ($links as $link) {
            $platform = trim((string) ($link['platform'] ?? ''));
            $url = trim((string) ($link['url'] ?? ''));
            $parts = parse_url($url);

            if ($platform === '' || $parts === false
                || strtolower($parts['scheme'] ?? '') !== 'https'
                || empty($parts['host'])
                || filter_var($url, FILTER_VALIDATE_URL) === false) {
                continue;
            }

            $safe[] = ['platform' => $platform, 'url' => $url];
        }

        return $safe;
    }
}
