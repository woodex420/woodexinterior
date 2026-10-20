{{-- Client logos, from the media library. --}}
@php
    $libraryRows = \App\Domain\Website\Services\ContentLibrary::rows((array) $block->field('library_ids', []), 'logo');
    $logos = [...$libraryRows, ...array_values(array_filter((array) $block->field('logos', [])))];
@endphp
<section class="mx-auto max-w-6xl px-5 py-14 md:px-8">
    {{-- The big line is optional and only appears when the studio writes one: the v1 row (small caps, logos)
         renders exactly as before when the field is empty. --}}
    @if ($block->field('heading_strong'))
        <h2 class="text-center text-[clamp(24px,3vw,36px)]">{{ $block->field('heading_strong') }}</h2>
    @endif
    <h2 class="text-center text-xs tracking-[0.16em] text-muted uppercase {{ $block->field('heading_strong') ? 'mt-3' : '' }}">{{ $block->field('heading', 'Trusted by') }}</h2>
    <ul class="mt-6 flex flex-wrap items-center justify-center gap-x-10 gap-y-6">
        @foreach ($logos as $item)
            @php $logo = isset($item['id']) ? ($media[$item['id']] ?? null) : null; @endphp
            @if ($logo)
                <li><img src="{{ $logo->url() }}" width="{{ $logo->width ?? 320 }}" height="{{ $logo->height ?? 160 }}" loading="lazy" decoding="async"
                         alt="{{ $item['alt'] ?: $logo->alt }}" class="h-10 w-auto opacity-70 grayscale"></li>
            @endif
        @endforeach
    </ul>
</section>
