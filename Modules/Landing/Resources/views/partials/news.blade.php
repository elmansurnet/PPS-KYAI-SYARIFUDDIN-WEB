{{-- Strip berita terbaru dari kontrak Core (Modules\Core\Support\NewsItem). Hanya dirender bila ada isi. --}}
<section id="berita-terbaru" aria-labelledby="judul-berita" class="border-t border-ink-200 bg-ink-50 py-16 lg:py-24">
    <div class="container-site">
        <x-section-heading title="Berita terbaru" id="judul-berita">
            <x-button href="/berita" variant="ghost">Lihat semua berita</x-button>
        </x-section-heading>

        <ul class="mt-10 grid gap-8 md:grid-cols-3">
            @foreach ($news as $item)
                <li class="flex flex-col">
                    @if ($item->coverUrl)
                        <img src="{{ $item->coverUrl }}" alt="" width="640" height="400" loading="lazy" decoding="async"
                             class="mb-4 aspect-[16/10] w-full rounded-xl object-cover ring-1 ring-ink-200">
                    @endif
                    <time datetime="{{ $item->publishedAt->toDateString() }}" class="text-sm font-medium text-ink-600">{{ $item->publishedAt->copy()->locale('id')->translatedFormat('j F Y') }}</time>
                    <h3 class="mt-1 text-subtitle">
                        <a href="{{ \Modules\Core\Support\SafeHref::sanitize($item->url) }}" class="text-ink-900 no-underline hover:text-primary-700 hover:underline">{{ $item->title }}</a>
                    </h3>
                    @if ($item->excerpt)
                        <p class="mt-2 text-ink-600">{{ \Illuminate\Support\Str::limit($item->excerpt, 140) }}</p>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
</section>
