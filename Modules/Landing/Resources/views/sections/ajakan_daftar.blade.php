@php
    $title = filled($section->title) ? $section->title : 'Daftarkan santri baru';
    $body = filled($section->body) ? $section->body : 'Isi formulir pendaftaran secara online, lalu panitia akan menghubungi Anda.';
    // Tombol daftar hanya bila modul Pendaftaran aktif (mendaftarkan item navigasi 'pendaftaran').
    $daftarPath = app(\Modules\Core\Support\Navigation::class)->path('pendaftaran');
    $feePdf = app(\Modules\Core\Settings\SiteSettings::class)->fee_pdf_path;
    $feePdfUrl = filled($feePdf) ? \Illuminate\Support\Facades\Storage::disk('public')->url($feePdf) : null;
@endphp

<section id="daftar" aria-labelledby="judul-daftar" class="py-16 lg:py-24">
    <div class="container-site">
        <div class="rounded-card bg-primary-700 px-6 py-10 sm:px-10 lg:grid lg:grid-cols-12 lg:items-center lg:gap-10 lg:py-14">
            <div class="lg:col-span-8">
                <h2 id="judul-daftar" class="text-title max-w-[24ch] text-white">{{ $title }}</h2>
                <p class="mt-4 max-w-prose text-lg/relaxed text-primary-100">{{ $body }}</p>
            </div>
            @if ($daftarPath || $feePdfUrl)
                <div class="mt-8 flex flex-col gap-3 sm:flex-row lg:col-span-4 lg:mt-0 lg:flex-col lg:items-end">
                    @if ($daftarPath)
                        <x-button :href="$daftarPath" variant="gold" size="lg">Daftar sekarang</x-button>
                    @endif
                    @if ($feePdfUrl)
                        <x-button :href="$feePdfUrl" variant="ghost" size="lg" download>
                            <x-site-icon name="file-text" class="size-5" />
                            Unduh rincian biaya (PDF)
                        </x-button>
                    @endif
                </div>
            @endif
        </div>
    </div>
</section>
