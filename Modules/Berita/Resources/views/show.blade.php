@php
    $cover = $post->coverUrl();
    $canonical = route('berita.show', $post->slug);
    $description = $post->excerpt();
    $site = app(\Modules\Core\Settings\SiteSettings::class)->site_name;
@endphp
<x-layouts.public :title="$post->title" :description="$description">
    <x-slot:head>
        <link rel="canonical" href="{{ $canonical }}">
        <meta property="og:type" content="article">
        <meta property="og:site_name" content="{{ $site }}">
        <meta property="og:title" content="{{ $post->title }}">
        <meta property="og:description" content="{{ $description }}">
        <meta property="og:url" content="{{ $canonical }}">
        <meta property="og:locale" content="id_ID">
        <meta property="article:published_time" content="{{ $post->published_at->toIso8601String() }}">
        @if ($cover)
            <meta property="og:image" content="{{ $cover }}">
            <meta name="twitter:card" content="summary_large_image">
        @endif
    </x-slot:head>

    <article class="pt-8 pb-16 sm:pt-12 lg:pb-24">
        <header class="container-site">
            <nav aria-label="Navigasi berita" class="mb-6">
                <a href="{{ route('berita.index') }}" class="inline-flex min-h-11 items-center gap-2 font-semibold text-primary-700 no-underline hover:underline">
                    <span aria-hidden="true">&larr;</span> Semua berita
                </a>
            </nav>
            <div class="max-w-3xl">
                <p class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm font-medium text-ink-600">
                    @if ($post->category)
                        <a href="{{ route('berita.index', ['kategori' => $post->category->slug]) }}" class="rounded-full bg-primary-100 px-3 py-1 text-primary-800 no-underline hover:bg-primary-200">{{ $post->category->name }}</a>
                    @endif
                    <time datetime="{{ $post->published_at->toDateString() }}">{{ $post->publishedLabel() }}</time>
                    @if ($post->author)
                        <span>Oleh {{ $post->author->name }}</span>
                    @endif
                </p>
                <h1 class="mt-4 text-display">{{ $post->title }}</h1>
            </div>
        </header>

        @if ($cover)
            <div class="container-site mt-8 lg:mt-10">
                <img src="{{ $cover }}" alt="" width="1200" height="675" loading="eager" fetchpriority="high" decoding="async"
                     class="aspect-[16/9] w-full rounded-card object-cover ring-1 ring-ink-200">
            </div>
        @endif

        <div class="container-site mt-10 grid grid-cols-1 gap-12 lg:grid-cols-12">
            <div class="article-body min-w-0 lg:col-span-8">
                {!! $post->safeBody() !!}
            </div>
        </div>
    </article>

    @if ($related->isNotEmpty())
        <section aria-labelledby="judul-lainnya" class="border-t border-ink-200 bg-ink-50 py-14 lg:py-20">
            <div class="container-site">
                <h2 id="judul-lainnya" class="text-subtitle">Berita lainnya</h2>
                <ul class="mt-8 grid gap-8 md:grid-cols-3">
                    @foreach ($related as $item)
                        <li>@include('berita::partials.card', ['post' => $item, 'variant' => 'compact'])</li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif
</x-layouts.public>
