{{--
    Navbar publik. Item berasal dari Modules\Core\Support\Navigation (modul menambah itemnya sendiri).
    Menu HP memakai Popover API: tombol popovertarget membuka panel geser tanpa JavaScript,
    sehingga lolos CSP ketat (tanpa skrip inline). Esc dan klik di luar panel menutupnya.
    Peramban tanpa Popover API mendapat .nav-fallback (tautan sebaris) sebagai tampilan bawaan.
--}}
@php
    $items = array_map(function (array $item) {
        $pattern = trim($item['path'], '/');

        return $item + [
            'url' => url($item['path']),
            'active' => $pattern === '' ? request()->is('/') : request()->is($pattern, $pattern.'/*'),
        ];
    }, app(\Modules\Core\Support\Navigation::class)->items());
@endphp

<header class="sticky top-0 z-30 border-b border-ink-200 bg-white">
    <div class="container-site flex h-16 items-center justify-between gap-4 lg:h-[4.5rem]">
        <x-site-brand />

        <nav aria-label="Navigasi utama" class="hidden lg:block">
            <ul class="flex items-center gap-1">
                @foreach ($items as $item)
                    <li>
                        @if ($item['emphasis'])
                            <a href="{{ $item['url'] }}" @if ($item['active']) aria-current="page" @endif class="btn btn-primary ml-3">{{ $item['label'] }}</a>
                        @else
                            <a href="{{ $item['url'] }}" @if ($item['active']) aria-current="page" @endif @class([
                                'relative inline-flex min-h-11 items-center rounded-md px-3.5 text-[0.9375rem] font-medium no-underline transition-colors',
                                'after:absolute after:inset-x-3.5 after:bottom-1.5 after:h-0.5 after:rounded-full after:bg-gold-500',
                                'text-ink-900 after:opacity-100' => $item['active'],
                                'text-ink-600 hover:text-primary-800 after:opacity-0 hover:after:opacity-40' => ! $item['active'],
                            ])>{{ $item['label'] }}</a>
                        @endif
                    </li>
                @endforeach
            </ul>
        </nav>

        <button type="button" popovertarget="menu-utama"
                class="menu-toggle -mr-2 size-11 items-center justify-center rounded-full text-ink-900 hover:bg-primary-50 lg:hidden">
            <x-site-icon name="menu" class="size-6" />
            <span class="sr-only">Buka menu</span>
        </button>
    </div>

    {{-- Fallback tanpa Popover API (peramban lama): tautan sebaris di bawah header. Lihat .nav-fallback di app.css. --}}
    <nav aria-label="Navigasi utama (tanpa menu geser)" class="nav-fallback border-t border-ink-200 lg:hidden">
        <ul class="container-site flex flex-wrap items-center gap-x-1 gap-y-2 py-2">
            @foreach ($items as $item)
                <li @class(['ml-auto' => $item['emphasis']])>
                    <a href="{{ $item['url'] }}" @if ($item['active']) aria-current="page" @endif @class([
                        'btn btn-primary px-4' => $item['emphasis'],
                        'inline-flex min-h-11 items-center rounded-md px-3 font-medium no-underline' => ! $item['emphasis'],
                        'text-ink-900 underline decoration-gold-500 decoration-2 underline-offset-8' => $item['active'] && ! $item['emphasis'],
                        'text-ink-600 hover:text-primary-800' => ! $item['active'] && ! $item['emphasis'],
                    ])>{{ $item['label'] }}</a>
                </li>
            @endforeach
        </ul>
    </nav>

    <div id="menu-utama" popover class="drawer lg:hidden" aria-label="Menu">
        <div class="flex h-16 items-center justify-between border-b border-ink-200 px-4">
            <span class="font-serif text-lg font-semibold">Menu</span>
            <button type="button" popovertarget="menu-utama" popovertargetaction="hide"
                    class="-mr-2 inline-flex size-11 items-center justify-center rounded-full hover:bg-primary-50">
                <x-site-icon name="x" class="size-6" />
                <span class="sr-only">Tutup menu</span>
            </button>
        </div>

        <nav aria-label="Navigasi utama (seluler)" class="px-2 py-4">
            <ul class="flex flex-col">
                @foreach ($items as $item)
                    @continue($item['emphasis'])
                    <li>
                        <a href="{{ $item['url'] }}" @if ($item['active']) aria-current="page" @endif @class([
                            'flex min-h-12 items-center gap-3 rounded-xl px-3 text-lg font-medium no-underline',
                            'bg-primary-50 text-primary-800' => $item['active'],
                            'text-ink-900 hover:bg-ink-50' => ! $item['active'],
                        ])>
                            <span @class(['h-5 w-0.5 rounded-full', 'bg-gold-500' => $item['active'], 'bg-transparent' => ! $item['active']]) aria-hidden="true"></span>
                            {{ $item['label'] }}
                        </a>
                    </li>
                @endforeach
            </ul>

            @foreach ($items as $item)
                @if ($item['emphasis'])
                    <div class="mt-6 border-t border-ink-200 px-3 pt-6">
                        <a href="{{ $item['url'] }}" @if ($item['active']) aria-current="page" @endif class="btn btn-primary btn-lg w-full">{{ $item['label'] }}</a>
                    </div>
                @endif
            @endforeach
        </nav>
    </div>
</header>
