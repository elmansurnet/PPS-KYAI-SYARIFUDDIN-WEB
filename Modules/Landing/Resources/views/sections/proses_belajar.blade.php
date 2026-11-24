@php
    $title = filled($section->title) ? $section->title : 'Proses belajar';
    $items = $section->renderableItems();
@endphp

<section id="proses-belajar" aria-labelledby="judul-proses" class="bg-primary-800 py-16 text-primary-100 lg:py-24">
    <div class="container-site grid gap-10 lg:grid-cols-12 lg:gap-12">
        <div class="lg:col-span-4">
            <span class="mb-4 block h-0.5 w-10 rounded-full bg-gold-400" aria-hidden="true"></span>
            <h2 id="judul-proses" class="text-title text-white">{{ $title }}</h2>
            @if (filled($section->body))
                <p class="mt-4 max-w-prose text-lg/relaxed text-primary-200">{{ $section->body }}</p>
            @endif
        </div>

        @if ($items)
            <ol class="space-y-8 lg:col-span-8">
                @foreach ($items as $item)
                    <li class="grid grid-cols-[3rem_1fr] gap-x-4 border-t border-primary-600 pt-6 sm:grid-cols-[4.5rem_1fr]">
                        <span class="font-serif text-4xl/none text-gold-400 sm:text-5xl/none" aria-hidden="true">{{ $loop->iteration }}</span>
                        <div class="grid gap-4 sm:grid-cols-[1fr_12rem] sm:gap-6">
                            <div>
                                <h3 class="text-subtitle text-white">{{ $item['title'] }}</h3>
                                @if ($item['body'])
                                    <p class="mt-2 text-primary-200">{{ $item['body'] }}</p>
                                @endif
                            </div>
                            @if ($item['image_url'])
                                <img src="{{ $item['image_url'] }}" alt="" width="400" height="300" loading="lazy" decoding="async"
                                     class="aspect-[4/3] w-full rounded-xl object-cover">
                            @endif
                        </div>
                    </li>
                @endforeach
            </ol>
        @endif
    </div>
</section>
