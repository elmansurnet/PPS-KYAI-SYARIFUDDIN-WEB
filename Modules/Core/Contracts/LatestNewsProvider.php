<?php

namespace Modules\Core\Contracts;

use Illuminate\Support\Collection;
use Modules\Core\Support\NewsItem;

interface LatestNewsProvider
{
    /**
     * Berita terbaru untuk ditampilkan modul lain (mis. Landing), terbaru lebih dulu.
     *
     * Setiap elemen adalah {@see NewsItem} (title, url, publishedAt, excerpt, coverUrl).
     * Hanya berita yang sudah terbit yang boleh dikembalikan. Koleksi kosong berarti
     * tidak ada berita (atau modul Berita nonaktif); pemanggil menyembunyikan bagiannya.
     *
     * @return Collection<int, NewsItem>
     */
    public function latest(int $limit): Collection;
}
