{{-- Columns (Phase 2): 2–4 leaf children — text, a photo or a quote. They stack below md, so the
     phone never gets a squeezed two-column layout. No nesting, no raw HTML. --}}
@php
    $columns = \App\Domain\Website\Support\Columns::clean((array) ($block->data ?? []));
    $children = $columns['blocks'];
    $grid = match (count($children)) {
        3 => 'md:grid-cols-3',
        4 => 'md:grid-cols-4',
        default => 'md:grid-cols-2',
    };
@endphp
<section class="mx-auto max-w-6xl px-5 py-12 md:px-8 md:py-16">
    <div class="grid gap-8 {{ $grid }}">
        @foreach ($children as $child)
            <div class="min-w-0">
                @if (($child['kind'] ?? 'text') === 'image')
                    @php $image = isset($child['image_id']) ? ($media[$child['image_id']] ?? null) : null; @endphp
                    @if ($image)
                        <figure>
                            <img src="{{ $image->url() }}" width="{{ $image->width ?? 800 }}" height="{{ $image->height ?? 600 }}"
                                 loading="lazy" decoding="async" alt="{{ $image->alt }}" class="w-full rounded-xl object-cover">
                            @if ($child['caption'] !== '')
                                <figcaption class="mt-2 text-sm text-muted">{{ $child['caption'] }}</figcaption>
                            @endif
                        </figure>
                    @elseif ($child['caption'] !== '')
                        <p class="text-sm text-muted">{{ $child['caption'] }}</p>
                    @endif
                @elseif (($child['kind'] ?? 'text') === 'quote')
                    <blockquote class="border-l-2 border-line pl-5">
                        <p class="text-lg text-ink">{{ $child['quote'] }}</p>
                        @if ($child['cite'] !== '')
                            <cite class="mt-3 block text-sm text-muted not-italic">{{ $child['cite'] }}</cite>
                        @endif
                    </blockquote>
                @else
                    @if ($child['heading'] !== '')
                        <h2 class="text-[clamp(20px,2.4vw,28px)]">{{ $child['heading'] }}</h2>
                    @endif
                    @if ($child['body'] !== '')
                        <div class="mt-4 space-y-3 text-body">{!! nl2br(e($child['body'])) !!}</div>
                    @endif
                @endif
            </div>
        @endforeach
    </div>
</section>
