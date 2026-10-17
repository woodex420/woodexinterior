{{-- Reviews are quoted verbatim: never rewritten, never invented (docs/09 §9). --}}
@php $items = array_values(array_filter((array) $block->field('items', []))); @endphp
<section class="mx-auto max-w-6xl px-5 py-16 md:px-8 md:py-24">
    <h2 class="text-[clamp(28px,3.6vw,44px)]">{{ $block->field('heading', 'What our clients say') }}</h2>
    <div class="mt-10 grid gap-6 md:grid-cols-3">
        @foreach ($items as $review)
            <figure class="flex h-full flex-col rounded-2xl bg-soft p-6 ring-1 ring-line">
                <p class="text-sm text-wood-600" aria-label="Rated 5 out of 5">★★★★★</p>
                <blockquote class="mt-3 grow text-body">“{{ $review['text'] ?? '' }}”</blockquote>
                <figcaption class="mt-5 text-sm">
                    <span class="font-medium text-ink">{{ $review['name'] ?? '' }}</span>
                    @if (! empty($review['when']))<span class="text-muted"> · {{ $review['when'] }}</span>@endif
                    @if (! empty($review['source']))<span class="text-muted"> · {{ $review['source'] }}</span>@endif
                </figcaption>
            </figure>
        @endforeach
    </div>
</section>
