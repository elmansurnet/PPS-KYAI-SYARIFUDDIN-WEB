{{--
    Kelompok isian alamat (jalan/dusun, RT, RW, desa, kecamatan, kabupaten, provinsi) dengan awalan kolom.
    <x-pendaftaran::address prefix="ortu_" :values="$values" />
--}}
@props([
    'prefix' => '',
    'values' => [],
])

<div {{ $attributes->class(['grid gap-5 sm:grid-cols-6']) }}>
    <x-pendaftaran::text :name="$prefix.'jalan_dusun'" label="Jalan/dusun" :value="$values[$prefix.'jalan_dusun'] ?? null"
        autocomplete="{{ $prefix === '' ? 'address-line1' : 'off' }}" maxlength="150" help="Nama jalan, gang, atau dusun dan nomor rumah bila ada." class="sm:col-span-6" />
    <x-pendaftaran::text :name="$prefix.'rt'" label="RT" :value="$values[$prefix.'rt'] ?? null" inputmode="numeric" maxlength="3" autocomplete="off" class="sm:col-span-3" />
    <x-pendaftaran::text :name="$prefix.'rw'" label="RW" :value="$values[$prefix.'rw'] ?? null" inputmode="numeric" maxlength="3" autocomplete="off" class="sm:col-span-3" />
    <x-pendaftaran::text :name="$prefix.'desa'" label="Desa/kelurahan" :value="$values[$prefix.'desa'] ?? null" maxlength="100" autocomplete="off" class="sm:col-span-3" />
    <x-pendaftaran::text :name="$prefix.'kecamatan'" label="Kecamatan" :value="$values[$prefix.'kecamatan'] ?? null" maxlength="100" autocomplete="off" class="sm:col-span-3" />
    <x-pendaftaran::text :name="$prefix.'kabupaten'" label="Kabupaten/kota" :value="$values[$prefix.'kabupaten'] ?? null"
        autocomplete="{{ $prefix === '' ? 'address-level2' : 'off' }}" maxlength="100" class="sm:col-span-3" />
    <x-pendaftaran::text :name="$prefix.'provinsi'" label="Provinsi" :value="$values[$prefix.'provinsi'] ?? null"
        autocomplete="{{ $prefix === '' ? 'address-level1' : 'off' }}" maxlength="100" class="sm:col-span-3" />
</div>
