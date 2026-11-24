{{--
    Beranda. Bagian dirender sesuai urutan sort_order; bagian tersembunyi atau tanpa isi sudah
    disaring di controller. Strip berita (kontrak Core LatestNewsProvider) disisipkan sebelum
    ajakan mendaftar, dan disembunyikan bila kosong. CSP ketat: tanpa skrip, style, atau atribut style.
--}}
@php
    $site = app(\Modules\Core\Settings\SiteSettings::class);
    $hero = $sections->firstWhere('key', 'hero');
    $description = filled($hero?->body) ? \Illuminate\Support\Str::limit(strip_tags($hero->body), 160) : null;
    $newsShown = false;
@endphp

<x-layouts.public :description="$description">
    {{-- Tanpa bagian hero (disembunyikan admin) halaman tetap punya satu h1 yang tersembunyi secara visual. --}}
    @unless ($hero)
        <h1 class="sr-only">{{ $site->site_name }}</h1>
    @endunless

    @foreach ($sections as $section)
        @if ($section->key === 'ajakan_daftar' && $news->isNotEmpty() && ! $newsShown)
            @include('landing::partials.news', ['news' => $news])
            @php $newsShown = true; @endphp
        @endif

        @include('landing::sections.'.$section->key, ['section' => $section])
    @endforeach

    @if ($news->isNotEmpty() && ! $newsShown)
        @include('landing::partials.news', ['news' => $news])
    @endif
</x-layouts.public>
