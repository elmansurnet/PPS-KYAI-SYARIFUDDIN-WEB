{{-- Placeholder beranda sampai modul Landing (Task 8) mengganti route "/". --}}
<x-layouts.public>
    <section class="container-site py-16 sm:py-24">
        <div class="max-w-prose">
            <span class="mb-5 block h-0.5 w-10 rounded-full bg-gold-500" aria-hidden="true"></span>
            <h1 class="text-display">{{ app(\Modules\Core\Settings\SiteSettings::class)->site_name }}</h1>
            <p class="mt-5 text-lg/relaxed text-ink-600">Halaman beranda sedang disiapkan. Informasi pendaftaran santri baru tersedia di halaman Pendaftaran.</p>
            <div class="mt-8 flex flex-wrap gap-3">
                <x-button href="{{ url('/daftar') }}" size="lg">Lihat pendaftaran</x-button>
                <x-button href="{{ url('/berita') }}" variant="ghost" size="lg">Baca berita</x-button>
            </div>
        </div>
    </section>
</x-layouts.public>
