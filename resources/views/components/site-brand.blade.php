{{--
    Logo situs dari SiteSettings. Bila logo belum diunggah, tampil monogram lengkung
    berisi inisial nama situs plus nama situs sebagai wordmark (bukan logo buatan).
    tone: light (di latar putih) | dark (di latar hijau tua).
--}}
@props(['tone' => 'light'])

@php
    $site = app(\Modules\Core\Settings\SiteSettings::class);
    $dark = $tone === 'dark';
@endphp

<a href="{{ url('/') }}" {{ $attributes->class([
    'group inline-flex min-h-11 items-center gap-3 rounded-lg no-underline',
    'focus-visible:outline-gold-400' => $dark,
]) }}>
    @if ($site->logo_path)
        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($site->logo_path) }}"
             alt="Logo {{ $site->site_name }}" data-logo="image"
             class="h-10 w-auto max-w-[9rem] object-contain sm:h-11" width="160" height="44" decoding="async">
    @else
        <span data-logo="monogram" class="contents">
            <span class="monogram h-11 w-9 text-[0.95rem]" aria-hidden="true">{{ \Modules\Core\Support\SiteIdentity::initials($site->site_name) }}</span>
        </span>
    @endif
    <span @class([
        'font-serif text-[1.0625rem] leading-tight font-semibold tracking-[-0.01em] sm:text-lg',
        'text-ink-900' => ! $dark,
        'text-white' => $dark,
    ])>{{ $site->site_name }}</span>
</a>
