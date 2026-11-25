{{--
    Isian teks wizard: label selalu terlihat (tanpa placeholder), teks bantuan dan galat terhubung lewat
    aria-describedby. <x-pendaftaran::text name="nik" label="NIK" :value="$values['nik'] ?? null" help="..." />
--}}
@props([
    'name',
    'label',
    'value' => null,
    'type' => 'text',
    'help' => null,
    'optional' => false,
    'required' => null,
    'inputmode' => null,
    'autocomplete' => null,
    'maxlength' => null,
])

@php
    $id = 'f-'.$name;
    $error = $errors->first($name);
    $required ??= ! $optional;
    $describedBy = trim(($help ? $id.'-help ' : '').($error ? $id.'-error' : ''));
@endphp

<div {{ $attributes->class(['field']) }}>
    <label for="{{ $id }}" class="block font-semibold text-ink-900">
        {{ $label }}
        @if ($optional)
            <span class="font-normal text-ink-600">(boleh kosong)</span>
        @endif
    </label>
    @if ($help)
        <p id="{{ $id }}-help" class="mt-1 text-[0.9375rem]/snug text-ink-600">{{ $help }}</p>
    @endif
    <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}" value="{{ $value }}"
        @if ($inputmode) inputmode="{{ $inputmode }}" @endif
        @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
        @if ($maxlength) maxlength="{{ $maxlength }}" @endif
        @if ($required) aria-required="true" @endif
        @if ($error) aria-invalid="true" @endif
        @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
        class="form-control mt-2">
    @if ($error)
        <p id="{{ $id }}-error" class="field-error mt-2">{{ $error }}</p>
    @endif
</div>
