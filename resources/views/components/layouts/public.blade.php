{{--
    Layout halaman publik: <x-layouts.public title="Berita" description="...">...</x-layouts.public>
    CSP publik ketat (style-src/script-src 'self'): jangan menulis <script> inline, <style>,
    atau atribut style="..." di halaman publik; pakai kelas Tailwind dan file aset.
    Slot opsional: head (tag tambahan di <head>, mis. meta Open Graph).
--}}
@props([
    'title' => null,
    'description' => null,
])

@php
    $site = app(\Modules\Core\Settings\SiteSettings::class);
    $pageTitle = $title ? $title.' – '.$site->site_name : $site->site_name;
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $pageTitle }}</title>
    @if ($description)
        <meta name="description" content="{{ $description }}">
    @endif
    <meta name="theme-color" content="#ffffff">
    <link rel="preload" href="{{ asset('fonts/figtree/figtree-latin-wght-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{{ asset('fonts/fraunces/fraunces-latin-wght-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="{{ asset('fonts/fonts.css') }}?v={{ @filemtime(public_path('fonts/fonts.css')) }}">
    @vite('resources/css/app.css')
    {{ $head ?? '' }}
</head>
<body class="flex min-h-dvh flex-col">
    <a href="#konten" class="fixed top-3 left-3 z-50 inline-flex min-h-11 -translate-y-24 items-center rounded-full bg-primary-700 px-5 font-semibold text-white no-underline focus:translate-y-0">Langsung ke konten</a>

    <x-navbar />

    <main id="konten" tabindex="-1" class="flex-1 focus:outline-none">
        {{ $slot }}
    </main>

    <x-footer />
</body>
</html>
