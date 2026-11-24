{{--
    Judul bagian halaman: garis emas pendek, judul serif, pengantar opsional.
    <x-section-heading title="Berita terbaru" lead="..." :level="2" align="start|center" />
    Slot opsional (mis. tautan "Lihat semua") tampil di sisi kanan pada layar lebar.
--}}
@props([
    'title',
    'lead' => null,
    'level' => 2,
    'align' => 'start',
    'id' => null,
])

@php
    $tag = 'h'.max(1, min(4, (int) $level));
    $centered = $align === 'center';
@endphp

<div {{ $attributes->class([
    'flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between',
    'items-center text-center sm:flex-col sm:items-center' => $centered,
]) }}>
    <div @class(['max-w-prose', 'mx-auto' => $centered])>
        <span @class(['mb-4 block h-0.5 w-10 rounded-full bg-gold-500', 'mx-auto' => $centered]) aria-hidden="true"></span>
        <{{ $tag }} @if ($id) id="{{ $id }}" @endif class="text-title">{{ $title }}</{{ $tag }}>
        @if ($lead)
            <p class="mt-3 text-lg/relaxed text-ink-600">{{ $lead }}</p>
        @endif
    </div>
    @if (trim($slot) !== '')
        <div class="shrink-0">{{ $slot }}</div>
    @endif
</div>
