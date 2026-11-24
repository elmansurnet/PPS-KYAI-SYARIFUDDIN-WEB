<?php

namespace Modules\Core\Support;

use Carbon\CarbonInterface;

/**
 * Satu berita ringkas yang dilewatkan lewat kontrak LatestNewsProvider.
 * Bentuk ini stabil: modul penyedia (Berita) mengisi, modul penampil (Landing) membaca.
 */
final readonly class NewsItem
{
    /**
     * @param  string  $url  URL penuh (atau path "/berita/slug") ke halaman berita
     * @param  string|null  $excerpt  ringkasan teks polos (tanpa HTML)
     * @param  string|null  $coverUrl  URL gambar sampul, null bila tidak ada
     */
    public function __construct(
        public string $title,
        public string $url,
        public CarbonInterface $publishedAt,
        public ?string $excerpt = null,
        public ?string $coverUrl = null,
    ) {}
}
