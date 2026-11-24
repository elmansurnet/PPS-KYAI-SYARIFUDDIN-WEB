{{-- Teks polos admin menjadi paragraf (baris kosong memisahkan paragraf). Selalu di-escape. --}}
@foreach (preg_split('/\R{2,}/', trim((string) $text)) ?: [] as $paragraph)
    <p class="{{ $class }}">{!! nl2br(e(trim($paragraph))) !!}</p>
@endforeach
