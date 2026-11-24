<?php

namespace Modules\Core\Contracts;

use Illuminate\Support\Collection;

interface LatestNewsProvider
{
    /**
     * Berita terbaru untuk ditampilkan modul lain (mis. Landing).
     */
    public function latest(int $limit): Collection;
}
