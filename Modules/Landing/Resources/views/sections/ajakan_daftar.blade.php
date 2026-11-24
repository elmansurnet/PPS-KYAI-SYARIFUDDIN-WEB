@php
    $title = filled($section->title) ? $section->title : 'Daftarkan santri baru';
    $body = filled($section->body) ? $section->body : 'Isi formulir pendaftaran secara online, lalu panitia akan menghubungi Anda.';
@endphp

<section id="daftar" aria-labelledby="judul-daftar" class="py-16 lg:py-24">
    <div class="container-site">
        <div class="rounded-card bg-primary-700 px-6 py-10 sm:px-10 lg:grid lg:grid-cols-12 lg:items-center lg:gap-10 lg:py-14">
            <div class="lg:col-span-8">
                <h2 id="judul-daftar" class="text-title max-w-[24ch] text-white">{{ $title }}</h2>
                <p class="mt-4 max-w-prose text-lg/relaxed text-primary-100">{{ $body }}</p>
            </div>
            <div class="mt-8 lg:col-span-4 lg:mt-0 lg:text-right">
                <x-button href="/daftar" variant="gold" size="lg">Daftar sekarang</x-button>
            </div>
        </div>
    </div>
</section>
