<x-layouts.public title="Terlalu banyak percobaan">
    <section class="pt-10 pb-16 sm:pt-14 lg:pb-24">
        <div class="container-site">
            <div class="max-w-2xl">
                <h1 class="text-title">Terlalu banyak percobaan</h1>
                <p class="mt-4 text-lg/relaxed text-ink-600">
                    Formulir dikirim terlalu sering dari perangkat atau jaringan ini. Silakan tunggu beberapa menit, lalu coba lagi.
                    Isian yang belum dikirim tetap tersimpan sementara.
                </p>
                <x-button :href="route('daftar')" variant="ghost" class="mt-8">Kembali ke formulir</x-button>
            </div>
        </div>
    </section>
</x-layouts.public>
