@php
    $featured = $posts->first();
    $rest = $posts->slice(1)->values();
    $title = $active ? 'Berita '.$active->name : 'Berita';
@endphp
<x-layouts.public :title="$title" description="Kabar dan kegiatan terbaru dari pondok pesantren.">
    <section aria-labelledby="judul-berita" class="pt-10 pb-16 sm:pt-14 lg:pb-24">
        <div class="container-site">
            <div class="max-w-3xl">
                <span class="mb-4 block h-0.5 w-10 rounded-full bg-gold-500" aria-hidden="true"></span>
                <h1 id="judul-berita" class="text-display">{{ $active ? $active->name : 'Berita pondok' }}</h1>
                <p class="mt-4 text-lg/relaxed text-ink-600">Kabar, kegiatan, dan pengumuman terbaru dari pondok.</p>
            </div>

            @if ($categories->isNotEmpty())
                <nav aria-label="Kategori berita" class="mt-8">
                    <ul class="flex flex-wrap gap-2">
                        <li>
                            <a href="{{ route('berita.index') }}" @if (! $active) aria-current="page" @endif
                               class="inline-flex min-h-11 items-center rounded-full border px-5 text-[0.9375rem] font-semibold no-underline {{ $active ? 'border-ink-400 bg-white text-primary-800 hover:border-primary-700 hover:bg-primary-50' : 'border-primary-700 bg-primary-700 text-white' }}">Semua</a>
                        </li>
                        @foreach ($categories as $category)
                            <li>
                                <a href="{{ route('berita.index', ['kategori' => $category->slug]) }}" @if ($active?->id === $category->id) aria-current="page" @endif
                                   class="inline-flex min-h-11 items-center rounded-full border px-5 text-[0.9375rem] font-semibold no-underline {{ $active?->id === $category->id ? 'border-primary-700 bg-primary-700 text-white' : 'border-ink-400 bg-white text-primary-800 hover:border-primary-700 hover:bg-primary-50' }}">{{ $category->name }}</a>
                            </li>
                        @endforeach
                    </ul>
                </nav>
            @endif

            @if ($featured)
                <div class="mt-10 lg:mt-14">
                    @include('berita::partials.card', ['post' => $featured, 'variant' => 'featured'])
                </div>

                @if ($rest->isNotEmpty())
                    <div class="mt-14 grid gap-x-8 gap-y-12 sm:grid-cols-2 lg:grid-cols-6">
                        @foreach ($rest as $i => $post)
                            <div class="{{ $i < 2 ? 'lg:col-span-3' : 'lg:col-span-2' }}">
                                @include('berita::partials.card', ['post' => $post, 'variant' => $i < 2 ? 'wide' : 'compact'])
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="mt-14">{{ $posts->links('berita::pagination') }}</div>
            @else
                <div class="mt-12 max-w-xl rounded-card border border-ink-200 bg-ink-50 p-8">
                    <h2 class="text-subtitle">Belum ada berita</h2>
                    <p class="mt-2 text-ink-600">
                        @if ($active)
                            Belum ada berita di kategori ini. Coba kategori lain atau lihat semua berita.
                        @else
                            Berita akan muncul di sini setelah diterbitkan. Silakan kembali lagi nanti.
                        @endif
                    </p>
                    @if ($active)
                        <x-button :href="route('berita.index')" variant="ghost" class="mt-5">Lihat semua berita</x-button>
                    @endif
                </div>
            @endif
        </div>
    </section>
</x-layouts.public>
