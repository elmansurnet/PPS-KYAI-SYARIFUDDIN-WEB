@php
    [$santri, $akademik, $ortu] = [$review[1], $review[2], $review[3]];
    $meninggal = ($ortu['orang_tua_meninggal'] ?? null) === 'ya';
    $alamat = fn (array $d, string $p = '') => collect([
        $d[$p.'jalan_dusun'] ?? null,
        isset($d[$p.'rt']) ? 'RT '.$d[$p.'rt'].' / RW '.($d[$p.'rw'] ?? '') : null,
        $d[$p.'desa'] ?? null, $d[$p.'kecamatan'] ?? null, $d[$p.'kabupaten'] ?? null, $d[$p.'provinsi'] ?? null,
    ])->filter()->implode(', ');
    $tanggal = filled($santri['tanggal_lahir'] ?? null) ? \Illuminate\Support\Carbon::parse($santri['tanggal_lahir'])->translatedFormat('j F Y') : '';
    $kkLimit = $kkMaxKb % 1024 === 0 ? ($kkMaxKb / 1024).' MB' : $kkMaxKb.' KB';
    $kkError = $errors->first('kk');
    $turnstileError = $errors->first('cf-turnstile-response');
    $sections = [
        1 => ['Data calon santri', [
            'NIK' => $santri['nik'] ?? '',
            'Nama lengkap' => $santri['nama_lengkap'] ?? '',
            'Tempat, tanggal lahir' => trim(($santri['tempat_lahir'] ?? '').', '.$tanggal, ', '),
            'Alamat' => $alamat($santri),
        ]],
        2 => ['Data akademik', array_filter([
            'Pernah mondok' => ($akademik['pernah_mondok'] ?? null) === 'ya' ? 'Ya' : 'Belum pernah',
            'Pondok sebelumnya' => ($akademik['pernah_mondok'] ?? null) === 'ya' ? ($akademik['pondok_sebelumnya'] ?? '').' ('.($akademik['pendidikan_pondok_sebelumnya'] ?? '').')' : null,
            'Pendidikan terakhir' => $akademik['pendidikan_nama'] ?? '',
            'Jenjang dan kelas' => ($akademik['jenjang_nama'] ?? '').', '.($akademik['kelas'] ?? ''),
        ], fn ($v) => $v !== null)],
        3 => ['Data orang tua / wali', array_filter([
            'Ayah' => trim(($ortu['ayah_nama'] ?? '').(filled($ortu['ayah_hp'] ?? null) ? ', '.$ortu['ayah_hp'] : '')),
            'Ibu' => trim(($ortu['ibu_nama'] ?? '').(filled($ortu['ibu_hp'] ?? null) ? ', '.$ortu['ibu_hp'] : '')),
            'Alamat orang tua' => $meninggal ? null : (($ortu['alamat_sama'] ?? null) === '1' ? 'Sama dengan alamat calon santri' : $alamat($ortu, 'ortu_')),
            'Wali' => $meninggal ? ($ortu['wali_nama'] ?? '').', '.($ortu['wali_hp'] ?? '') : null,
            'Alamat wali' => $meninggal ? $alamat($ortu, 'wali_') : null,
        ], fn ($v) => $v !== null)],
    ];
@endphp
<x-pendaftaran::wizard :step="$step" :steps="$steps" :completed="$completed">
    @if ($turnstileSiteKey)
        <x-slot:head>
            <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
        </x-slot:head>
    @endif

    <p class="mt-8 text-lg/relaxed text-ink-600">Periksa kembali data di bawah. Bila ada yang keliru, pilih <span class="font-semibold text-ink-900">Ubah</span> pada bagian tersebut.</p>

    <div class="mt-6 space-y-4">
        @foreach ($sections as $i => [$heading, $rows])
            <section class="rounded-card border border-ink-200 p-5 sm:p-6" aria-labelledby="ringkas-{{ $i }}">
                <div class="flex items-start justify-between gap-4">
                    <h2 id="ringkas-{{ $i }}" class="font-sans text-lg font-bold">{{ $heading }}</h2>
                    <a href="{{ $i === 1 ? route('daftar') : route('daftar.langkah', $i) }}" class="-my-2 inline-flex min-h-11 items-center px-2 font-semibold text-primary-700 underline underline-offset-4">
                        Ubah<span class="sr-only"> {{ strtolower($heading) }}</span>
                    </a>
                </div>
                <dl class="mt-3 space-y-3">
                    @foreach ($rows as $label => $value)
                        <div>
                            <dt class="text-[0.9375rem] text-ink-600">{{ $label }}</dt>
                            <dd class="font-medium break-words text-ink-900">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>
        @endforeach
    </div>

    <form method="post" action="{{ route('daftar.kirim') }}" enctype="multipart/form-data" class="mt-10 space-y-10">
        @csrf

        <fieldset class="space-y-3">
            <legend class="form-legend">Kartu Keluarga</legend>
            <div class="field">
                <label for="f-kk" class="block font-semibold text-ink-900">Foto atau scan Kartu Keluarga</label>
                <p id="f-kk-help" class="mt-1 text-[0.9375rem]/snug text-ink-600">
                    JPG, PNG, atau PDF, maksimal {{ $kkLimit }}. Pastikan seluruh halaman KK terlihat dan tulisan terbaca.
                    @if ($errors->any())
                        <strong class="font-semibold text-ink-900">Pilih ulang file KK sebelum mengirim lagi.</strong>
                    @endif
                </p>
                <input type="file" id="f-kk" name="kk" accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf" aria-required="true"
                    aria-describedby="f-kk-help{{ $kkError ? ' f-kk-error' : '' }}" @if ($kkError) aria-invalid="true" @endif
                    class="form-control form-file mt-2">
                @if ($kkError)
                    <p id="f-kk-error" class="field-error mt-2">{{ $kkError }}</p>
                @endif
            </div>
        </fieldset>

        <fieldset id="f-turnstile" class="space-y-3" tabindex="-1">
            <legend class="form-legend">Verifikasi keamanan</legend>
            @if ($turnstileSiteKey)
                <p class="text-ink-600">Centang kotak verifikasi di bawah bila diminta.</p>
                <div class="cf-turnstile" data-sitekey="{{ $turnstileSiteKey }}" data-language="id" data-size="flexible"></div>
            @else
                <p class="notice notice-error">Verifikasi keamanan tidak tersedia, coba lagi nanti. Pendaftaran online sementara belum bisa dikirim.</p>
            @endif
            @if ($turnstileError)
                <p class="field-error">{{ $turnstileError }}</p>
            @endif
        </fieldset>

        <p class="text-ink-600">Dengan mengirim, Anda menyatakan data di atas benar. Data hanya dipakai panitia untuk penerimaan santri baru.</p>

        <div class="form-actions">
            <x-button type="submit" size="lg" :disabled="! $turnstileSiteKey">Kirim pendaftaran</x-button>
            <x-button variant="ghost" :href="route('daftar.langkah', 3)"><x-site-icon name="arrow-left" class="size-5" /> Kembali</x-button>
        </div>
    </form>
</x-pendaftaran::wizard>
