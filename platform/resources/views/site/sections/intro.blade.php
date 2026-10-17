{{-- Intro + key facts: the block AI assistants quote (docs/09 §4). --}}
@php
    $image = isset($block->data['image_id']) ? ($media[$block->data['image_id']] ?? null) : null;
    $facts = array_values(array_filter((array) $block->field('facts', [])));
@endphp
<section class="mx-auto max-w-6xl px-5 py-16 md:px-8 md:py-24">
    <div class="grid gap-10 md:grid-cols-2 md:items-center md:gap-16">
        <div>
            @if ($block->field('eyebrow'))<p class="text-[13px] font-medium tracking-[0.12em] text-wood-600 uppercase">{{ $block->field('eyebrow') }}</p>@endif
            @if ($block->field('heading'))<h2 class="mt-4 text-[clamp(28px,3.6vw,44px)]">{{ $block->field('heading') }}</h2>@endif
            @if ($block->field('body'))<div class="mt-6 space-y-4 text-body">{!! nl2br(e($block->field('body'))) !!}</div>@endif

            @if ($facts !== [])
                <dl class="mt-8 grid gap-x-8 gap-y-3 border-t border-line pt-6 text-sm sm:grid-cols-2">
                    @foreach ($facts as $fact)
                        <div class="flex gap-2"><dt class="shrink-0 text-muted">{{ $fact['label'] ?? '' }}:</dt><dd class="text-ink">{{ $fact['value'] ?? '' }}</dd></div>
                    @endforeach
                </dl>
            @endif
        </div>

        @if ($image)
            <img src="{{ $image->url() }}" width="{{ $image->width ?? 900 }}" height="{{ $image->height ?? 1100 }}" loading="lazy" decoding="async"
                 alt="{{ $image->alt }}" class="h-full max-h-[620px] w-full rounded-2xl object-cover">
        @endif
    </div>
</section>
