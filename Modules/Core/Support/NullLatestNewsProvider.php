<?php

namespace Modules\Core\Support;

use Illuminate\Support\Collection;
use Modules\Core\Contracts\LatestNewsProvider;

class NullLatestNewsProvider implements LatestNewsProvider
{
    public function latest(int $limit): Collection
    {
        return collect();
    }
}
