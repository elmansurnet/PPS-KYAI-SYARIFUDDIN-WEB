@php
    $title = filled($section->title) ? $section->title : 'Sejarah pondok';
    $image = $section->mediaUrl();
@endphp

<section id="sejarah" aria-labelledby="judul-sejarah" class="bg-ink-50 py-16 lg:py-24">
    <div class="container-site grid gap-10 lg:grid-cols-12 lg:gap-12">
        <div class="lg:col-span-4">
            <x-section-heading :title="$title" id="judul-sejarah" />
        </div>
        <div class="lg:col-span-8">
            @if (filled($section->body))
                <div class="max-w-prose space-y-5 text-lg/relaxed text-ink-900">
                    @include('landing::partials.paragraphs', ['text' => $section->body, 'class' => ''])
                </div>
            @endif
            @if ($image)
                <img src="{{ $image }}" alt="" width="1200" height="750" loading="lazy" decoding="async"
                     class="mt-8 aspect-[16/10] w-full rounded-card object-cover ring-1 ring-ink-200">
            @endif
        </div>
    </div>
</section>
