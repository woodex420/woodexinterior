{{-- Gallery: images come from the media library, so alt text and dimensions are always known. --}}
@php $images = array_values(array_filter((array) $block->field('images', []))); @endphp
<section class="mx-auto max-w-6xl px-5 py-16 md:px-8 md:py-24">
    @if ($block->field('heading'))<h2 class="text-[clamp(28px,3.6vw,44px)]">{{ $block->field('heading') }}</h2>@endif
    <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($images as $item)
            @php $image = isset($item['id']) ? ($media[$item['id']] ?? null) : null; @endphp
            @if ($image)
                <figure class="overflow-hidden rounded-2xl bg-soft ring-1 ring-line">
                    <img src="{{ $image->url() }}" width="{{ $image->width ?? 720 }}" height="{{ $image->height ?? 540 }}"
                         loading="lazy" decoding="async" alt="{{ $item['alt'] ?? $image->alt }}" class="aspect-[4/3] w-full object-cover">
                    @if (! empty($item['caption']))<figcaption class="px-5 py-4 text-sm text-muted">{{ $item['caption'] }}</figcaption>@endif
                </figure>
            @endif
        @endforeach
    </div>
</section>
