{{--
    Kartu berita. $post (Post), $variant: featured | wide | compact.
    Tanpa atribut style/skrip (CSP publik ketat). data-berita-card dipakai tes.
--}}
@php
    $cover = $post->coverUrl();
    $date = $post->publishedLabel();
@endphp

@if ($variant === 'featured')
    <article data-berita-card class="grid gap-6 lg:grid-cols-12 lg:items-center lg:gap-10">
        <a href="{{ route('berita.show', $post->slug) }}" tabindex="-1" aria-hidden="true" class="block overflow-hidden rounded-card ring-1 ring-ink-200 lg:col-span-7">
            @if ($cover)
                <img src="{{ $cover }}" alt="" width="1200" height="750" loading="eager" fetchpriority="high" decoding="async"
                     class="aspect-[16/10] w-full object-cover">
            @else
                <span class="flex aspect-[16/10] w-full items-end bg-primary-800 p-6">
                    <span class="h-1 w-16 rounded-full bg-gold-400" aria-hidden="true"></span>
                </span>
            @endif
        </a>
        <div class="lg:col-span-5">
            <p class="flex flex-wrap items-center gap-x-3 text-sm font-medium text-ink-600">
                @if ($post->category)
                    <span class="rounded-full bg-primary-100 px-3 py-1 text-primary-800">{{ $post->category->name }}</span>
                @endif
                <time datetime="{{ $post->published_at->toDateString() }}">{{ $date }}</time>
            </p>
            <h2 class="mt-3 text-title">
                <a href="{{ route('berita.show', $post->slug) }}" class="text-ink-900 no-underline hover:text-primary-700 hover:underline">{{ $post->title }}</a>
            </h2>
            <p class="mt-4 text-lg/relaxed text-ink-600">{{ $post->excerpt(220) }}</p>
            <a href="{{ route('berita.show', $post->slug) }}" class="mt-5 inline-flex min-h-11 items-center font-semibold text-primary-700 underline-offset-4 hover:underline">Baca selengkapnya<span class="sr-only">: {{ $post->title }}</span></a>
        </div>
    </article>
@else
    <article data-berita-card class="flex h-full flex-col">
        <a href="{{ route('berita.show', $post->slug) }}" tabindex="-1" aria-hidden="true" class="mb-4 block overflow-hidden rounded-xl ring-1 ring-ink-200">
            @if ($cover)
                <img src="{{ $cover }}" alt="" width="800" height="{{ $variant === 'wide' ? 450 : 500 }}" loading="lazy" decoding="async"
                     class="{{ $variant === 'wide' ? 'aspect-[16/9]' : 'aspect-[16/10]' }} w-full object-cover">
            @else
                <span class="flex {{ $variant === 'wide' ? 'aspect-[16/9]' : 'aspect-[16/10]' }} w-full items-end bg-primary-50 p-4">
                    <span class="h-0.5 w-10 rounded-full bg-gold-500" aria-hidden="true"></span>
                </span>
            @endif
        </a>
        <p class="flex flex-wrap items-center gap-x-3 text-sm font-medium text-ink-600">
            @if ($post->category)
                <span class="text-primary-700">{{ $post->category->name }}</span>
            @endif
            <time datetime="{{ $post->published_at->toDateString() }}">{{ $date }}</time>
        </p>
        <h3 class="{{ $variant === 'wide' ? 'text-subtitle' : 'text-xl/snug' }} mt-1.5">
            <a href="{{ route('berita.show', $post->slug) }}" class="text-ink-900 no-underline hover:text-primary-700 hover:underline">{{ $post->title }}</a>
        </h3>
        <p class="mt-2 text-ink-600">{{ $post->excerpt($variant === 'wide' ? 160 : 110) }}</p>
    </article>
@endif
