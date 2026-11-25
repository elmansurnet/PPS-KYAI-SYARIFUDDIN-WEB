<x-layouts.public title="Pendaftaran terkirim" description="Pendaftaran santri baru sudah kami terima.">
    @if ($whatsappUrl)
        <x-slot:head>
            {{-- Cadangan tanpa JavaScript: buka WhatsApp otomatis setelah 5 detik. --}}
            <meta http-equiv="refresh" content="5;url={{ $whatsappUrl }}">
        </x-slot:head>
    @endif

    <section class="pt-10 pb-16 sm:pt-14 lg:pb-24">
        <div class="container-site">
            <div class="max-w-2xl">
                <span class="inline-flex size-12 items-center justify-center rounded-full bg-primary-100 text-primary-700" aria-hidden="true">
                    <x-site-icon name="check" class="size-6" />
                </span>
                <h1 class="mt-5 text-title">Pendaftaran sudah kami terima</h1>
                <p class="mt-4 text-lg/relaxed text-ink-600">
                    Terima kasih. Data calon santri atas nama <strong class="font-semibold text-ink-900">{{ $nama }}</strong> sudah tersimpan.
                </p>

                @if ($whatsappUrl)
                    <div class="mt-8 rounded-card bg-primary-700 p-6 text-white sm:p-8">
                        <h2 class="font-sans text-lg font-bold text-white">Satu langkah lagi: kabari panitia lewat WhatsApp</h2>
                        <p class="mt-2 text-primary-100">Pesan sudah terisi otomatis. Cukup tekan kirim di WhatsApp. Halaman ini akan membuka WhatsApp dalam 5 detik.</p>
                        <x-button :href="$whatsappUrl" variant="gold" size="lg" class="mt-5 w-full sm:w-auto">
                            <x-site-icon name="message-circle" class="size-5" />
                            Lanjut ke WhatsApp
                        </x-button>
                    </div>
                @else
                    <p class="notice notice-info mt-8">Selanjutnya, panitia akan menghubungi Anda melalui nomor HP yang didaftarkan.</p>
                @endif

                <h2 class="mt-12 font-sans text-lg font-bold">Langkah berikutnya</h2>
                <ol class="mt-4 space-y-4">
                    @foreach (array_filter([
                        'Panitia memeriksa data dan Kartu Keluarga yang Anda kirim.',
                        'Panitia menghubungi orang tua/wali untuk informasi biaya, jadwal, dan persyaratan lain.',
                        $whatsappUrl ? 'Simpan nomor WhatsApp panitia agar pesan berikutnya mudah ditemukan.' : null,
                    ]) as $i => $text)
                        <li class="flex gap-4">
                            <span class="inline-flex size-8 shrink-0 items-center justify-center rounded-full border border-gold-500 font-semibold text-gold-700" aria-hidden="true">{{ $i + 1 }}</span>
                            <span class="pt-0.5">{{ $text }}</span>
                        </li>
                    @endforeach
                </ol>

                <x-button href="/" variant="ghost" class="mt-10">Kembali ke beranda</x-button>
            </div>
        </div>
    </section>
</x-layouts.public>
