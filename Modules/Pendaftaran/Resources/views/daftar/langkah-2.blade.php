@php
    $mondok = $values['pernah_mondok'] ?? null;
    $mondokError = $errors->first('pernah_mondok');
    $selectedJenjang = (string) ($values['jenjang_id'] ?? '');
    $selectedKelas = $values['kelas'] ?? null;
    $jenjangs = $jenjangs->filter(fn ($j) => $j->kelasOptions->isNotEmpty());
@endphp
<x-pendaftaran::wizard :step="$step" :steps="$steps" :completed="$completed">
    <form method="post" action="{{ route('daftar.simpan', 2) }}" class="mt-10 space-y-10">
        @csrf

        {{-- Tampil-sembunyi isian pondok sebelumnya murni CSS (.reveal-on:checked ~ .reveal-panel). Server tetap acuan:
             bila jawaban "tidak", isian ini dibuang (exclude_unless). --}}
        <fieldset class="reveal">
            <legend class="form-legend">Pernah mondok sebelumnya?</legend>
            <p id="f-pernah_mondok-help" class="mt-1 text-ink-600">Pernah tinggal dan belajar di pondok pesantren lain.</p>
            @if ($mondokError)
                <p id="f-pernah_mondok-error" class="field-error mt-2">{{ $mondokError }}</p>
            @endif
            <input type="radio" id="f-pernah_mondok" name="pernah_mondok" value="ya" class="choice-input reveal-on" @checked($mondok === 'ya')
                aria-describedby="f-pernah_mondok-help{{ $mondokError ? ' f-pernah_mondok-error' : '' }}" @if ($mondokError) aria-invalid="true" @endif>
            <label for="f-pernah_mondok" class="choice-label">Ya, pernah</label>
            <input type="radio" id="f-pernah_mondok-tidak" name="pernah_mondok" value="tidak" class="choice-input" @checked($mondok === 'tidak')
                aria-describedby="f-pernah_mondok-help{{ $mondokError ? ' f-pernah_mondok-error' : '' }}" @if ($mondokError) aria-invalid="true" @endif>
            <label for="f-pernah_mondok-tidak" class="choice-label">Belum pernah</label>

            <div class="reveal-panel mt-6 space-y-6 border-l-2 border-gold-500 pl-4 sm:pl-6">
                <x-pendaftaran::text name="pondok_sebelumnya" label="Nama pondok sebelumnya" :value="$values['pondok_sebelumnya'] ?? null" maxlength="150" autocomplete="off"
                    help="Diisi bila menjawab Ya." />
                <x-pendaftaran::text name="pendidikan_pondok_sebelumnya" label="Pendidikan di pondok sebelumnya" :value="$values['pendidikan_pondok_sebelumnya'] ?? null" maxlength="150" autocomplete="off"
                    help="Mis. Madrasah Diniyah Ula kelas 3, atau lama mondok 2 tahun." />
            </div>
        </fieldset>

        <fieldset class="space-y-6">
            <legend class="form-legend">Pendidikan</legend>

            <x-pendaftaran::select name="pendidikan_terakhir_id" label="Pendidikan terakhir" help="Sekolah formal terakhir yang sudah atau sedang ditempuh." empty="Pilih pendidikan terakhir">
                @foreach ($pendidikan as $item)
                    <option value="{{ $item->id }}" @selected((string) ($values['pendidikan_terakhir_id'] ?? '') === (string) $item->id)>{{ $item->name }}</option>
                @endforeach
            </x-pendaftaran::select>

            @if ($jenjangs->isEmpty())
                <p class="notice notice-info">Pilihan jenjang dan kelas belum tersedia. Silakan hubungi panitia.</p>
            @else
                <x-pendaftaran::select name="jenjang_id" label="Jenjang sekolah formal yang dituju" empty="Pilih jenjang">
                    @foreach ($jenjangs as $jenjang)
                        <option value="{{ $jenjang->id }}" @selected($selectedJenjang === (string) $jenjang->id)>{{ $jenjang->name }}</option>
                    @endforeach
                </x-pendaftaran::select>

                <x-pendaftaran::select name="kelas" label="Kelas yang dituju" help="Pilih kelas di kelompok jenjang yang sama dengan pilihan di atas." empty="Pilih kelas">
                    @foreach ($jenjangs as $jenjang)
                        <optgroup label="{{ $jenjang->name }}">
                            @foreach ($jenjang->kelasOptions as $kelas)
                                <option value="{{ $kelas->name }}" @selected($selectedKelas === $kelas->name && $selectedJenjang === (string) $jenjang->id)>{{ $kelas->name }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </x-pendaftaran::select>
            @endif
        </fieldset>

        <div class="form-actions">
            <x-button type="submit" size="lg">Lanjut ke data orang tua <x-site-icon name="arrow-right" class="size-5" /></x-button>
            <x-button type="submit" variant="ghost" name="aksi" value="kembali" formnovalidate><x-site-icon name="arrow-left" class="size-5" /> Kembali</x-button>
        </div>
    </form>
</x-pendaftaran::wizard>
