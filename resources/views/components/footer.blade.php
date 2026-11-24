{{--
    Footer publik: identitas, kontak, media sosial (hanya https), dan tautan navigasi.
    Bagian yang datanya kosong di pengaturan situs tidak ditampilkan.
--}}
@use('Modules\Core\Support\SiteIdentity')
@use('Modules\Core\Support\WhatsAppLink')
@php
    $site = app(\Modules\Core\Settings\SiteSettings::class);
    $social = SiteIdentity::safeSocialLinks($site->social_links ?? []);
    $whatsapp = WhatsAppLink::normalizeNumber($site->whatsapp_number ?? '') !== ''
        ? WhatsAppLink::make($site->whatsapp_number, "Assalamu'alaikum, saya ingin bertanya tentang {$site->site_name}.")
        : null;
    $links = app(\Modules\Core\Support\Navigation::class)->items();
    $linkClass = 'inline-flex min-h-11 items-center gap-2.5 rounded-md text-primary-100 no-underline hover:text-white focus-visible:outline-gold-400';
@endphp

<footer class="mt-auto border-t-2 border-gold-500 bg-primary-800 text-primary-200">
    <div class="container-site grid gap-10 py-12 sm:grid-cols-2 lg:grid-cols-12 lg:gap-8 lg:py-16">
        <div class="sm:col-span-2 lg:col-span-5">
            <x-site-brand tone="dark" />
            @if ($site->address)
                <p class="mt-5 flex max-w-sm gap-2.5 text-[0.9375rem]/relaxed">
                    <x-site-icon name="map-pin" class="mt-0.5 size-5 text-gold-400" />
                    <span>{{ $site->address }}</span>
                </p>
            @endif
        </div>

        @if ($site->contact_email || $whatsapp)
            <div class="lg:col-span-3">
                <h2 class="font-sans text-sm font-semibold tracking-normal text-white">Hubungi kami</h2>
                <ul class="mt-3 space-y-1 text-[0.9375rem]">
                    @if ($whatsapp)
                        <li>
                            <a href="{{ $whatsapp }}" rel="noopener noreferrer" class="{{ $linkClass }}">
                                <x-site-icon name="message-circle" class="size-5 text-gold-400" />
                                <span>WhatsApp {{ WhatsAppLink::display($site->whatsapp_number) }}</span>
                            </a>
                        </li>
                    @endif
                    @if ($site->contact_email)
                        <li>
                            <a href="mailto:{{ $site->contact_email }}" class="{{ $linkClass }} break-all">
                                <x-site-icon name="mail" class="size-5 text-gold-400" />
                                <span>{{ $site->contact_email }}</span>
                            </a>
                        </li>
                    @endif
                </ul>
            </div>
        @endif

        @if ($social)
            <div class="lg:col-span-2">
                <h2 class="font-sans text-sm font-semibold tracking-normal text-white">Media sosial</h2>
                <ul class="mt-3 space-y-1 text-[0.9375rem]">
                    @foreach ($social as $link)
                        <li>
                            <a href="{{ $link['url'] }}" target="_blank" rel="noopener noreferrer" class="{{ $linkClass }}">
                                <span>{{ $link['platform'] }}</span>
                                <x-site-icon name="arrow-up-right" class="size-4 text-gold-400" />
                                <span class="sr-only">(membuka tab baru)</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <nav aria-label="Tautan footer" @class(['lg:col-span-2', 'lg:col-start-11' => ! $social])>
            <h2 class="font-sans text-sm font-semibold tracking-normal text-white">Halaman</h2>
            <ul class="mt-3 space-y-1 text-[0.9375rem]">
                @foreach ($links as $item)
                    <li><a href="{{ url($item['path']) }}" class="{{ $linkClass }}">{{ $item['label'] }}</a></li>
                @endforeach
            </ul>
        </nav>
    </div>

    <div class="border-t border-primary-700">
        <div class="container-site py-5 text-sm text-primary-200">
            <p>&copy; {{ now()->year }} {{ $site->site_name }}</p>
        </div>
    </div>
</footer>
