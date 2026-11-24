{{--
    Tombol publik. variant: primary (hijau, aksi utama), gold (sekunder, hemat), ghost (netral).
    Dengan href dirender sebagai <a>, tanpa href sebagai <button type="button|submit">.
--}}
@props([
    'variant' => 'primary',
    'href' => null,
    'type' => 'button',
    'size' => 'md',
])

@php
    $variants = ['primary' => 'btn-primary', 'gold' => 'btn-gold', 'ghost' => 'btn-ghost'];
    $classes = implode(' ', array_filter([
        'btn',
        $variants[$variant] ?? throw new InvalidArgumentException("Varian tombol tidak dikenal: {$variant}"),
        $size === 'lg' ? 'btn-lg' : null,
    ]));
@endphp

@if ($href)
<a href="{{ $href }}" {{ $attributes->class($classes) }}>{{ $slot }}</a>
@else
<button type="{{ $type }}" {{ $attributes->class($classes) }}>{{ $slot }}</button>
@endif
