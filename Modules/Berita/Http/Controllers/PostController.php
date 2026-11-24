<?php

namespace Modules\Berita\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Modules\Berita\Models\Category;
use Modules\Berita\Models\Post;

class PostController
{
    private const PER_PAGE = 9;

    private const RELATED = 3;

    public function index(Request $request): View
    {
        $categories = Category::query()->whereHas('posts', fn ($q) => $q->published())->orderBy('name')->get();
        $slug = $request->query('kategori');
        $active = is_string($slug) ? $categories->firstWhere('slug', $slug) : null;

        $posts = Post::published()
            ->with(['category', 'author:id,name'])
            ->when($active, fn ($q) => $q->where('category_id', $active->id))
            ->when(is_string($slug) && $slug !== '' && ! $active, fn ($q) => $q->whereRaw('1 = 0'))
            ->latestFirst()
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('berita::index', ['posts' => $posts, 'categories' => $categories, 'active' => $active]);
    }

    public function show(string $slug): View
    {
        $post = Post::published()->with(['category', 'author:id,name'])->where('slug', $slug)->firstOrFail();

        $related = Post::published()->with('category')->whereKeyNot($post->id)
            ->when($post->category_id, fn ($q) => $q->orderByRaw('category_id = ? desc', [$post->category_id]))
            ->latestFirst()->limit(self::RELATED)->get();

        return view('berita::show', ['post' => $post, 'related' => $related]);
    }
}
