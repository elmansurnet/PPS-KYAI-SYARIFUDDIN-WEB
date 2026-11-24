{{--
    Ikon Lucide (lucide-static v1.79.0, ISC, (c) Lucide Icons and Contributors; lisensi di
    resources/icons/LICENSE-lucide.txt). Hanya ikon yang dipakai disalin ke sini sebagai SVG inline.
    Pemakaian: <x-site-icon name="menu" class="size-5" />. Ikon dekoratif (aria-hidden) secara bawaan;
    beri teks yang terbaca di elemen induknya.
--}}
@props(['name'])

@php
    $paths = [
        'menu' => '<path d="M4 5h16"/><path d="M4 12h16"/><path d="M4 19h16"/>',
        'x' => '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
        'map-pin' => '<path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/>',
        'mail' => '<path d="m22 7-8.991 5.727a2 2 0 0 1-2.009 0L2 7"/><rect x="2" y="4" width="20" height="16" rx="2"/>',
        'message-circle' => '<path d="M2.992 16.342a2 2 0 0 1 .094 1.167l-1.065 3.29a1 1 0 0 0 1.236 1.168l3.413-.998a2 2 0 0 1 1.099.092 10 10 0 1 0-4.777-4.719"/>',
        'arrow-up-right' => '<path d="M7 7h10v10"/><path d="M7 17 17 7"/>',
    ];
@endphp

<svg {{ $attributes->merge(['class' => 'shrink-0']) }} xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">{!! $paths[$name] ?? throw new InvalidArgumentException("Ikon tidak dikenal: {$name}") !!}</svg>
