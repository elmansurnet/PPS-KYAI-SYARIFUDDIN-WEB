{{-- Paginasi berbahasa Indonesia, target sentuh 44px, tanpa JavaScript. --}}
@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Halaman berita" class="flex flex-wrap items-center gap-2">
        @php
            $link = 'inline-flex min-h-11 min-w-11 items-center justify-center rounded-full border px-4 text-[0.9375rem] font-semibold no-underline';
        @endphp

        @if ($paginator->onFirstPage())
            <span class="{{ $link }} border-ink-200 text-ink-400" aria-disabled="true">Sebelumnya</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="{{ $link }} border-ink-400 text-primary-800 hover:border-primary-700 hover:bg-primary-50">Sebelumnya</a>
        @endif

        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="px-1 text-ink-600" aria-hidden="true">{{ $element }}</span>
            @endif

            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span class="{{ $link }} border-primary-700 bg-primary-700 text-white" aria-current="page"><span class="sr-only">Halaman </span>{{ $page }}</span>
                    @else
                        <a href="{{ $url }}" class="{{ $link }} border-ink-400 text-primary-800 hover:border-primary-700 hover:bg-primary-50"><span class="sr-only">Ke halaman </span>{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="{{ $link }} border-ink-400 text-primary-800 hover:border-primary-700 hover:bg-primary-50">Berikutnya</a>
        @else
            <span class="{{ $link }} border-ink-200 text-ink-400" aria-disabled="true">Berikutnya</span>
        @endif
    </nav>
@endif
