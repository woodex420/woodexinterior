{{-- Indicative investment: bands, never fixed prices. --}}
@php $rows = array_values(array_filter((array) $block->field('rows', []))); @endphp
<section class="mx-auto max-w-4xl px-5 py-14 md:px-8">
    <h2 class="text-[clamp(24px,3vw,36px)]">{{ $block->field('heading', 'Indicative investment') }}</h2>
    <ul class="mt-6 divide-y divide-line rounded-2xl bg-soft ring-1 ring-line">
        @foreach ($rows as $row)
            <li class="flex items-baseline justify-between gap-4 px-5 py-4">
                <span class="text-sm text-ink">{{ $row['label'] ?? '' }}</span>
                <span class="text-sm font-medium text-ink">{{ $row['value'] ?? '' }}</span>
            </li>
        @endforeach
    </ul>
    @if ($block->field('note'))<p class="mt-4 text-sm text-muted">{{ $block->field('note') }}</p>@endif
</section>
