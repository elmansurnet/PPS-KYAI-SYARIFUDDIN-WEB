{{--
    Layout halaman galat (404, 419, 429, 500, 503, 4xx, 5xx). Sengaja BERDIRI SENDIRI: tidak memakai
    <x-layouts.public>, navbar, atau footer karena semuanya membaca pengaturan situs dari database, dan
    halaman galat (terutama 500/503) harus tetap tampil saat database bermasalah. Nama situs dan CSS
    dimuat dengan try/catch dan punya cadangan. Patuh CSP publik: tanpa <script>, <style>, atau style="...".
    Pemakaian: <x-error-page code="404" title="..." message="..."><x-slot:action>...</x-slot:action></x-error-page>
--}}
@props(['code', 'title', 'message'])
@php
    try {
        $siteName = (string) app(\Modules\Core\Settings\SiteSettings::class)->site_name;
    } catch (\Throwable) {
        $siteName = '';
    }
    $siteName = $siteName !== '' ? $siteName : (string) config('app.name');

    try {
        $styles = app(\Illuminate\Foundation\Vite::class)('resources/css/app.css')->toHtml();
    } catch (\Throwable) {
        $styles = '';
    }
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $title }} – {{ $siteName }}</title>
    <link rel="stylesheet" href="{{ asset('fonts/fonts.css') }}">
    {!! $styles !!}
</head>
<body class="flex min-h-dvh flex-col bg-white text-ink-900">
    <header class="border-b border-ink-200">
        <div class="container-site flex min-h-16 items-center">
            <a href="{{ url('/') }}" class="font-serif text-lg font-semibold text-primary-800 no-underline">{{ $siteName }}</a>
        </div>
    </header>

    <main id="konten" class="flex-1">
        <section class="pt-12 pb-16 sm:pt-16 lg:pb-24">
            <div class="container-site">
                <div class="max-w-2xl">
                    <p class="text-sm font-semibold text-gold-700">Galat {{ $code }}</p>
                    <h1 class="text-title mt-2">{{ $title }}</h1>
                    <p class="mt-4 text-lg/relaxed text-ink-600">{{ $message }}</p>
                    <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                        @isset($action)
                            {{ $action }}
                        @endisset
                        <x-button href="/" variant="ghost">Ke beranda</x-button>
                    </div>
                </div>
            </div>
        </section>
    </main>
</body>
</html>
