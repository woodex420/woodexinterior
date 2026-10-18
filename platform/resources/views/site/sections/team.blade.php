{{-- The studio's people (V2-3 F7): members picked from Website → Team first, in the studio's order, then anyone written on the page. A team section with nobody in it renders nothing — an empty grid helps no one. --}}
@php
    $libraryRows = \App\Domain\Website\Services\ContentLibrary::rows((array) $block->field('library_ids', []), 'team');
    $items = [...$libraryRows, ...array_values(array_filter((array) $block->field('items', []), fn ($item): bool => is_array($item) && ! empty($item['name'])))];
@endphp
@if ($items !== [])
    <section class="border-y border-line bg-soft">
        <div class="mx-auto max-w-6xl px-5 py-16 md:px-8 md:py-24">
            <h2 class="text-[clamp(28px,3.6vw,44px)]">{{ $block->field('heading', 'Our team') }}</h2>
            <div class="mt-10 grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($items as $member)
                    @php $photo = isset($member['id']) ? ($media[$member['id']] ?? null) : null; @endphp
                    <figure class="flex h-full flex-col">
                        @if ($photo)
                            <img src="{{ $photo->url() }}" width="{{ $photo->width ?? 480 }}" height="{{ $photo->height ?? 600 }}" loading="lazy" decoding="async"
                                 alt="{{ $member['name'] }}" class="aspect-[4/5] w-full rounded-2xl object-cover">
                        @endif
                        <figcaption class="mt-4">
                            <p class="text-lg font-medium text-ink">{{ $member['name'] }}</p>
                            <p class="mt-0.5 text-sm text-muted">{{ $member['role'] ?? '' }}</p>
                            @if (! empty($member['bio']))
                                <p class="mt-3 text-sm text-body">{{ $member['bio'] }}</p>
                            @endif
                        </figcaption>
                    </figure>
                @endforeach
            </div>
        </div>
    </section>
@endif
