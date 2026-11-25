<x-pendaftaran::wizard :step="$step" :steps="$steps" :completed="$completed">
    <div class="mt-8 rounded-card border border-primary-100 bg-primary-50 p-5 sm:p-6">
        <h2 class="font-sans text-base font-bold text-ink-900">Siapkan dulu</h2>
        <ul class="mt-2 list-disc space-y-1 pl-5 text-ink-900">
            <li>Kartu Keluarga (KK) calon santri, untuk NIK dan alamat.</li>
            <li>Foto atau scan KK yang jelas: JPG, PNG, atau PDF, maksimal {{ config('pendaftaran.kk_max_kb') % 1024 === 0 ? (config('pendaftaran.kk_max_kb') / 1024).' MB' : config('pendaftaran.kk_max_kb').' KB' }}.</li>
            <li>Nomor HP orang tua atau wali yang aktif.</li>
        </ul>
        <p class="mt-3 text-[0.9375rem] text-ink-600">Formulir terdiri dari 4 langkah. Data baru dikirim di langkah terakhir; isian tersimpan sementara selama {{ (int) config('pendaftaran.draft_ttl_minutes') }} menit.</p>
        @if ($feePdfUrl)
            <a href="{{ $feePdfUrl }}" class="mt-4 inline-flex min-h-11 items-center gap-2 font-semibold text-primary-700 underline underline-offset-4 hover:text-primary-800">
                <x-site-icon name="file-text" class="size-5" />
                Lihat rincian biaya (PDF)
            </a>
        @endif
    </div>

    <form method="post" action="{{ route('daftar.simpan', 1) }}" class="mt-10 space-y-10">
        @csrf

        <fieldset class="space-y-6">
            <legend class="form-legend">Identitas calon santri</legend>
            <x-pendaftaran::text name="nik" label="NIK calon santri" :value="$values['nik'] ?? null" inputmode="numeric" autocomplete="off" maxlength="25"
                help="16 digit angka, lihat Kartu Keluarga. Boleh diketik dengan spasi." />
            <x-pendaftaran::text name="nama_lengkap" label="Nama lengkap" :value="$values['nama_lengkap'] ?? null" autocomplete="name" maxlength="100"
                help="Sesuai Kartu Keluarga." />
            <div class="grid gap-6 sm:grid-cols-2">
                <x-pendaftaran::text name="tempat_lahir" label="Tempat lahir" :value="$values['tempat_lahir'] ?? null" autocomplete="off" maxlength="100" />
                <x-pendaftaran::text name="tanggal_lahir" label="Tanggal lahir" type="date" :value="$values['tanggal_lahir'] ?? null" autocomplete="bday"
                    help="Bila tidak ada pilihan tanggal, ketik mis. 17-08-2014." />
            </div>
        </fieldset>

        <fieldset class="space-y-6">
            <legend class="form-legend">Alamat calon santri</legend>
            <p class="text-ink-600">Sesuai alamat di Kartu Keluarga.</p>
            <x-pendaftaran::address :values="$values" />
        </fieldset>

        <div class="form-actions">
            <x-button type="submit" size="lg">Lanjut ke data akademik <x-site-icon name="arrow-right" class="size-5" /></x-button>
        </div>
    </form>
</x-pendaftaran::wizard>
