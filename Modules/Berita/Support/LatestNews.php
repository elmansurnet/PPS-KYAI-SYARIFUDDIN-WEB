<?php

namespace Modules\Berita\Support;

use Illuminate\Support\Collection;
use Modules\Berita\Models\Post;
use Modules\Core\Contracts\LatestNewsProvider;
use Modules\Core\Support\NewsItem;

/** Implementasi nyata kontrak Core: berita terbit terbaru untuk modul lain (mis. beranda). */
class LatestNews implements LatestNewsProvider
{
    public function latest(int $limit): Collection
    {
        if ($limit < 1) {
            return collect();
        }

        return Post::published()->latestFirst()->limit($limit)->get()
            ->map(fn (Post $post) => new NewsItem(
                title: $post->title,
                url: $post->url(),
                publishedAt: $post->published_at,
                excerpt: $post->excerpt(),
                coverUrl: $post->coverUrl(),
            ))
            ->values();
    }
}
