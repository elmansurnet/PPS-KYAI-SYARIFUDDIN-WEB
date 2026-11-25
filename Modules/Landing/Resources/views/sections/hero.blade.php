@php
    $site = app(\Modules\Core\Settings\SiteSettings::class);
    $title = filled($section->title) ? $section->title : $site->site_name;
    $body = filled($section->body) ? $section->body : 'Kenali pondok kami, lalu daftarkan santri baru langsung dari website ini.';
    $image = $section->mediaUrl();
    $number = \Modules\Core\Support\WhatsAppLink::normalizeNumber($site->whatsapp_number ?? '');
    // Tombol daftar hanya bila modul Pendaftaran aktif (mendaftarkan item navigasi 'pendaftaran').
    $daftarPath = app(\Modules\Core\Support\Navigation::class)->path('pendaftaran');
@endphp

<section id="beranda" aria-labelledby="judul-hero" class="border-b border-ink-200 bg-primary-50">
    <div class="container-site grid items-center gap-10 py-14 sm:py-16 lg:grid-cols-12 lg:gap-12 lg:py-24">
        <div @class(['lg:col-span-7' => $image, 'lg:col-span-9' => ! $image])>
            <span class="mb-5 block h-0.5 w-12 rounded-full bg-gold-500" aria-hidden="true"></span>
            <h1 id="judul-hero" class="text-display max-w-[18ch] text-balance sm:max-w-[22ch]">{{ $title }}</h1>
            <p class="mt-5 max-w-prose text-lg/relaxed text-ink-600 sm:text-xl/relaxed">{{ $body }}</p>
            <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:items-center">
                @if ($daftarPath)
                    <x-button :href="$daftarPath" size="lg">Daftar santri baru</x-button>
                @endif
                @if ($number !== '')
                    <x-button variant="ghost" size="lg" :href="\Modules\Core\Support\WhatsAppLink::make($site->whatsapp_number, 'Assalamu\'alaikum, saya ingin bertanya tentang '.$site->site_name.'.')">Tanya lewat WhatsApp</x-button>
                @endif
            </div>
        </div>

        @if ($image)
            <div class="lg:col-span-5">
                <img src="{{ $image }}" alt="" width="800" height="1000" fetchpriority="high" decoding="async"
                     class="aspect-[4/5] w-full max-w-md rounded-card object-cover shadow-sm ring-1 ring-ink-200 lg:ml-auto">
            </div>
        @endif
    </div>
</section>
