@php
    $title = filled($section->title) ? $section->title : 'Kegiatan unggulan';
    $items = $section->renderableItems();
@endphp

<section id="kegiatan" aria-labelledby="judul-kegiatan" class="py-16 lg:py-24">
    <div class="container-site">
        <x-section-heading :title="$title" :lead="filled($section->body) ? $section->body : null" id="judul-kegiatan" />

        @if ($items)
            <ul class="mt-10 divide-y divide-ink-200 border-y border-ink-200 lg:mt-14">
                @foreach ($items as $item)
                    <li class="grid gap-5 py-8 md:grid-cols-12 md:items-start md:gap-8 lg:py-10">
                        <h3 class="text-subtitle md:col-span-4">{{ $item['title'] }}</h3>
                        @if ($item['body'])
                            <p @class(['text-lg/relaxed text-ink-600', 'md:col-span-5' => $item['image_url'], 'md:col-span-8' => ! $item['image_url']])>{{ $item['body'] }}</p>
                        @endif
                        @if ($item['image_url'])
                            <img src="{{ $item['image_url'] }}" alt="" width="600" height="450" loading="lazy" decoding="async"
                                 class="aspect-[4/3] w-full rounded-xl object-cover ring-1 ring-ink-200 md:col-span-3">
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</section>
