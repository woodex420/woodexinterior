{{-- Questions and answers: native <details>, open without JavaScript, mirrored in the FAQPage schema. --}}
@php $items = array_values(array_filter((array) $block->field('items', []))); @endphp
<section class="border-y border-line bg-soft">
    <div class="mx-auto max-w-3xl px-5 py-16 md:px-8 md:py-24">
        <h2 class="text-[clamp(28px,3.6vw,44px)]">{{ $block->field('heading', 'Common questions') }}</h2>
        <div class="mt-10 divide-y divide-line rounded-2xl bg-white ring-1 ring-line">
            @foreach ($items as $index => $item)
                @if (! empty($item['question']))
                    <details class="group px-6 py-5" @if ($index === 0) open @endif>
                        <summary class="flex cursor-pointer items-center justify-between gap-4 text-base font-medium text-ink marker:hidden">
                            {{ $item['question'] }}
                            <span class="shrink-0 text-xl text-muted transition group-open:rotate-45" aria-hidden="true">+</span>
                        </summary>
                        <p class="mt-3 text-sm text-body">{{ $item['answer'] ?? '' }}</p>
                    </details>
                @endif
            @endforeach
        </div>
    </div>
</section>
