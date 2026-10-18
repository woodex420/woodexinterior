{{-- Hero: the photograph is the LCP element, so it is a real <img> with fetchpriority, never a background. --}}
@php
    $image = isset($block->data['image_id']) ? ($media[$block->data['image_id']] ?? null) : null;
    $facts = array_values(array_filter((array) $block->field('facts', [])));
@endphp
<section class="relative isolate flex min-h-[86vh] items-end overflow-hidden bg-navy-900">
    @if ($image)
        <img src="{{ $image->url() }}" width="{{ $image->width ?? 1600 }}" height="{{ $image->height ?? 900 }}"
             fetchpriority="high" decoding="async" alt="{{ $image->alt }}"
             class="absolute inset-0 -z-10 size-full object-cover">
    @endif
    <div class="absolute inset-0 -z-10 bg-gradient-to-t from-navy-950/85 via-navy-950/45 to-navy-950/25" aria-hidden="true"></div>

    <div class="mx-auto w-full max-w-6xl px-5 py-16 md:px-8 md:py-24">
        @if ($block->field('eyebrow'))<p class="text-[13px] font-medium tracking-[0.12em] text-sand-200 uppercase">{{ $block->field('eyebrow') }}</p>@endif
        <h1 class="mt-4 max-w-3xl text-[clamp(38px,6vw,72px)] text-white">{{ $block->field('heading', $page->title) }}</h1>
        @if ($block->field('text'))<p class="mt-6 max-w-xl text-lg text-navy-100/85">{{ $block->field('text') }}</p>@endif
        <div class="mt-9 flex flex-wrap gap-3">
            @if ($block->field('primary_label'))
                <a href="{{ $block->field('primary_url', '#enquiry') }}" class="inline-flex h-12 items-center rounded-full bg-white px-6 text-sm font-medium text-ink transition hover:bg-beige">{{ $block->field('primary_label') }}</a>
            @endif
            @if ($block->field('secondary_label'))
                <a href="{{ $block->field('secondary_url') }}" class="inline-flex h-12 items-center rounded-full border border-white/30 px-6 text-sm font-medium text-white transition hover:bg-white/10">{{ $block->field('secondary_label') }}</a>
            @endif
        </div>

        @if ($facts !== [])
            <dl class="mt-14 grid max-w-3xl grid-cols-2 gap-x-8 gap-y-6 border-t border-white/15 pt-8 text-white sm:grid-cols-4">
                @foreach ($facts as $fact)
                    <div>
                        <dt class="text-xs text-navy-200">{{ $fact['label'] ?? '' }}</dt>
                        <dd class="mt-1 font-heading text-3xl">{{ $fact['value'] ?? '' }}</dd>
                    </div>
                @endforeach
            </dl>
        @endif
    </div>
</section>
