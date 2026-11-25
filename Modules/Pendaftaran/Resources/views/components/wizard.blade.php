{{--
    Kerangka halaman wizard: judul (satu h1), indikator langkah, pesan info/galat, ringkasan galat.
    Indikator tidak hanya warna: teks "Langkah n dari 4", aria-current="step", ikon centang + "(selesai)".
--}}
@props([
    'step',
    'steps',
    'completed' => [],
])

@php
    $title = $steps[$step]['title'];
    $total = count($steps);
    $errorKeys = $errors->keys();
    $fieldId = fn (string $key) => $key === 'cf-turnstile-response' ? 'f-turnstile' : 'f-'.$key;
    $reachable = function (int $i) use ($completed) {
        for ($j = 1; $j < $i; $j++) {
            if (! in_array($j, $completed, true)) {
                return false;
            }
        }

        return true;
    };
@endphp

<x-layouts.public :title="($errorKeys ? 'Perbaiki isian: ' : '').$title.' (langkah '.$step.' dari '.$total.') – Pendaftaran'"
    description="Formulir pendaftaran santri baru secara online.">
    @isset($head)
        <x-slot:head>{{ $head }}</x-slot:head>
    @endisset

    <section class="pt-8 pb-16 sm:pt-12 lg:pb-24">
        <div class="container-site">
            <div class="max-w-2xl">
                <p class="text-[0.9375rem] font-semibold text-gold-700">Pendaftaran santri baru</p>
                <p class="mt-1 text-ink-600">Langkah {{ $step }} dari {{ $total }}</p>
                <h1 class="mt-2 text-title">{{ $title }}</h1>

                <nav aria-label="Langkah pendaftaran" class="mt-6">
                    <ol class="grid grid-cols-4 gap-2">
                        @foreach ($steps as $i => $item)
                            @php
                                $isCurrent = $i === $step;
                                $isDone = in_array($i, $completed, true) && ! $isCurrent;
                                $canOpen = ! $isCurrent && $reachable($i);
                            @endphp
                            <li>
                                @if ($canOpen)
                                    <a href="{{ $i === 1 ? route('daftar') : route('daftar.langkah', $i) }}" class="step-item step-link {{ $isDone ? 'step-done' : '' }}">
                                @else
                                    <span class="step-item {{ $isCurrent ? 'step-current' : ($isDone ? 'step-done' : 'step-todo') }}" @if ($isCurrent) aria-current="step" @endif>
                                @endif
                                    <span class="step-number" aria-hidden="true">
                                        @if ($isDone)
                                            <x-site-icon name="check" class="size-4" />
                                        @else
                                            {{ $i }}
                                        @endif
                                    </span>
                                    <span class="step-label"><span class="sr-only">Langkah {{ $i }}: </span>{{ $item['short'] }}@if ($isDone)<span class="sr-only"> (selesai)</span>@endif</span>
                                @if ($canOpen)
                                    </a>
                                @else
                                    </span>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                </nav>

                @if (session('pendaftaran.info'))
                    <p class="notice notice-info mt-8">{{ session('pendaftaran.info') }}</p>
                @endif

                @if (session('pendaftaran.galat'))
                    <div class="notice notice-error mt-8">
                        <x-site-icon name="circle-alert" class="mt-0.5 size-5" />
                        <p>{{ session('pendaftaran.galat') }}</p>
                    </div>
                @endif

                @if ($errorKeys)
                    <div class="error-summary mt-8" aria-labelledby="ringkasan-galat">
                        <h2 id="ringkasan-galat" class="flex items-center gap-2 font-sans text-lg font-bold text-danger-700">
                            <x-site-icon name="circle-alert" class="size-5" />
                            {{ count($errorKeys) === 1 ? 'Ada 1 isian yang perlu diperbaiki' : 'Ada '.count($errorKeys).' isian yang perlu diperbaiki' }}
                        </h2>
                        <ul class="mt-2">
                            @foreach ($errorKeys as $key)
                                <li><a href="#{{ $fieldId($key) }}" class="inline-flex min-h-11 items-center py-1 font-medium text-danger-700 underline underline-offset-4">{{ $errors->first($key) }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{ $slot }}
            </div>
        </div>
    </section>
</x-layouts.public>
