{{--
    Pilihan (select) wizard. Isi <option> lewat slot; opsi kosong "Pilih ..." ditambahkan otomatis.
--}}
@props([
    'name',
    'label',
    'help' => null,
    'empty' => 'Pilih salah satu',
])

@php
    $id = 'f-'.$name;
    $error = $errors->first($name);
    $describedBy = trim(($help ? $id.'-help ' : '').($error ? $id.'-error' : ''));
@endphp

<div {{ $attributes->class(['field']) }}>
    <label for="{{ $id }}" class="block font-semibold text-ink-900">{{ $label }}</label>
    @if ($help)
        <p id="{{ $id }}-help" class="mt-1 text-[0.9375rem]/snug text-ink-600">{{ $help }}</p>
    @endif
    <select id="{{ $id }}" name="{{ $name }}" aria-required="true"
        @if ($error) aria-invalid="true" @endif
        @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
        class="form-control form-select mt-2">
        <option value="">{{ $empty }}</option>
        {{ $slot }}
    </select>
    @if ($error)
        <p id="{{ $id }}-error" class="field-error mt-2">{{ $error }}</p>
    @endif
</div>
