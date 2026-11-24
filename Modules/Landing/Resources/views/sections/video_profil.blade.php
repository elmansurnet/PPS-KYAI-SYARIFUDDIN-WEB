@php
    $embed = $section->embedUrl();
    $title = filled($section->title) ? $section->title : 'Mengenal pondok kami';
@endphp

@if ($embed)
<section id="video-profil" aria-labelledby="judul-video" class="py-16 lg:py-24">
    <div class="container-site grid items-center gap-8 lg:grid-cols-12 lg:gap-12">
        <div class="lg:col-span-4">
            <x-section-heading :title="$title" id="judul-video" />
        </div>
        <div class="lg:col-span-8">
            <div class="aspect-video w-full overflow-hidden rounded-card bg-ink-100 ring-1 ring-ink-200">
                <iframe src="{{ $embed }}" title="Video profil: {{ $title }}" class="size-full" loading="lazy"
                        referrerpolicy="strict-origin-when-cross-origin"
                        allow="accelerometer; encrypted-media; gyroscope; picture-in-picture"
                        allowfullscreen></iframe>
            </div>
        </div>
    </div>
</section>
@endif
