@php
    $fresh = $values === [];
    $meninggal = $values['orang_tua_meninggal'] ?? 'tidak';
    $alamatSama = $fresh || ($values['alamat_sama'] ?? null) === '1';
    $statusError = $errors->first('orang_tua_meninggal');
    $hpHelp = 'Isi minimal salah satu: HP ayah atau HP ibu. Mis. 0812 3456 7890.';
@endphp
<x-pendaftaran::wizard :step="$step" :steps="$steps" :completed="$completed">
    {{-- .ortu-scope: dengan dukungan :has(), alamat orang tua disembunyikan bila kedua orang tua meninggal
         (peningkatan progresif; server membuang isian itu). --}}
    <form method="post" action="{{ route('daftar.simpan', 3) }}" class="ortu-scope mt-10 space-y-10">
        @csrf

        <fieldset class="reveal">
            <legend class="form-legend">Apakah kedua orang tua calon santri sudah meninggal?</legend>
            <p id="f-orang_tua_meninggal-help" class="mt-1 text-ink-600">Bila ya, isi data wali yang mengasuh calon santri.</p>
            @if ($statusError)
                <p id="f-orang_tua_meninggal-error" class="field-error mt-2">{{ $statusError }}</p>
            @endif
            <input type="radio" id="f-orang_tua_meninggal" name="orang_tua_meninggal" value="tidak" class="choice-input" @checked($meninggal === 'tidak')
                aria-describedby="f-orang_tua_meninggal-help">
            <label for="f-orang_tua_meninggal" class="choice-label">Tidak</label>
            <input type="radio" id="f-orang_tua_meninggal-ya" name="orang_tua_meninggal" value="ya" class="choice-input reveal-on ortu-meninggal" @checked($meninggal === 'ya')
                aria-describedby="f-orang_tua_meninggal-help">
            <label for="f-orang_tua_meninggal-ya" class="choice-label">Ya, keduanya sudah meninggal</label>

            <div class="reveal-panel mt-6 space-y-6 border-l-2 border-gold-500 pl-4 sm:pl-6">
                <h2 class="font-sans text-lg font-bold">Data wali</h2>
                <x-pendaftaran::text name="wali_nama" label="Nama lengkap wali" :value="$values['wali_nama'] ?? null" maxlength="100" autocomplete="off" />
                <x-pendaftaran::text name="wali_pekerjaan" label="Pekerjaan wali" :value="$values['wali_pekerjaan'] ?? null" maxlength="100" autocomplete="off" />
                <x-pendaftaran::text name="wali_hp" label="Nomor HP wali" type="tel" :value="$values['wali_hp'] ?? null" inputmode="numeric" autocomplete="off" maxlength="20"
                    help="Nomor aktif, sebaiknya terhubung WhatsApp. Mis. 0812 3456 7890." />
                <h3 class="font-sans text-base font-bold">Alamat wali</h3>
                <x-pendaftaran::address prefix="wali_" :values="$values" />
            </div>
        </fieldset>

        <fieldset class="space-y-6">
            <legend class="form-legend">Ayah</legend>
            <x-pendaftaran::text name="ayah_nama" label="Nama lengkap ayah" :value="$values['ayah_nama'] ?? null" maxlength="100" autocomplete="off" help="Sesuai Kartu Keluarga." />
            <x-pendaftaran::text name="ayah_pekerjaan" label="Pekerjaan ayah" :value="$values['ayah_pekerjaan'] ?? null" maxlength="100" autocomplete="off"
                help="Bila sudah meninggal, tulis: Almarhum." />
            <x-pendaftaran::text name="ayah_hp" label="Nomor HP ayah" type="tel" :value="$values['ayah_hp'] ?? null" inputmode="numeric" autocomplete="off" maxlength="20"
                :required="false" :help="$hpHelp" />
        </fieldset>

        <fieldset class="space-y-6">
            <legend class="form-legend">Ibu</legend>
            <x-pendaftaran::text name="ibu_nama" label="Nama lengkap ibu" :value="$values['ibu_nama'] ?? null" maxlength="100" autocomplete="off" help="Sesuai Kartu Keluarga." />
            <x-pendaftaran::text name="ibu_pekerjaan" label="Pekerjaan ibu" :value="$values['ibu_pekerjaan'] ?? null" maxlength="100" autocomplete="off"
                help="Bila sudah meninggal, tulis: Almarhumah." />
            <x-pendaftaran::text name="ibu_hp" label="Nomor HP ibu" type="tel" :value="$values['ibu_hp'] ?? null" inputmode="numeric" autocomplete="off" maxlength="20"
                :required="false" :help="$hpHelp" />
        </fieldset>

        <fieldset class="reveal ortu-alamat">
            <legend class="form-legend">Alamat orang tua</legend>
            <p class="mt-1 text-ink-600">Tidak perlu diisi bila kedua orang tua sudah meninggal.</p>
            <input type="checkbox" id="f-alamat_sama" name="alamat_sama" value="1" class="check-input reveal-off" @checked($alamatSama)>
            <label for="f-alamat_sama" class="check-label">Sama dengan alamat calon santri</label>

            <div class="reveal-panel mt-6 border-l-2 border-gold-500 pl-4 sm:pl-6">
                <p class="mb-6 text-ink-600">Isi bila alamat orang tua berbeda dengan alamat calon santri.</p>
                <x-pendaftaran::address prefix="ortu_" :values="$values" />
            </div>
        </fieldset>

        <div class="form-actions">
            <x-button type="submit" size="lg">Lanjut ke kartu keluarga <x-site-icon name="arrow-right" class="size-5" /></x-button>
            <x-button type="submit" variant="ghost" name="aksi" value="kembali" formnovalidate><x-site-icon name="arrow-left" class="size-5" /> Kembali</x-button>
        </div>
    </form>
</x-pendaftaran::wizard>
