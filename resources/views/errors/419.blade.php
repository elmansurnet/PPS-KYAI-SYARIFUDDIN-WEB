{{-- 419: token CSRF/sesi kedaluwarsa, paling sering saat formulir pendaftaran lama dibiarkan terbuka. --}}
@php($daftar = app(\Modules\Core\Support\Navigation::class)->path('pendaftaran'))
<x-error-page code="419" title="Sesi Anda sudah berakhir" message="Halaman terlalu lama dibiarkan terbuka sehingga sesinya kedaluwarsa demi keamanan. Bila Anda sedang mengisi formulir pendaftaran, buka lagi formulirnya; isian yang belum dikirim mungkin perlu diisi ulang.">
    @if ($daftar)
        <x-slot:action>
            <x-button :href="$daftar">Buka lagi formulir pendaftaran</x-button>
        </x-slot:action>
    @endif
</x-error-page>
