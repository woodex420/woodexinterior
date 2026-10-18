{{-- Services grid: six disciplines (or any list the editor fills in). --}}
@php $items = array_values(array_filter((array) $block->field('items', []))); @endphp
<section class="border-y border-line bg-soft">
    <div class="mx-auto max-w-6xl px-5 py-16 md:px-8 md:py-24">
        @if ($block->field('heading'))<h2 class="max-w-xl text-[clamp(28px,3.6vw,44px)]">{{ $block->field('heading') }}</h2>@endif
        <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($items as $item)
                <article class="rounded-2xl bg-white p-6 ring-1 ring-line transition hover:-translate-y-0.5 hover:ring-wood-300">
                    @if (! empty($item['number']))<p class="font-heading text-2xl text-wood-600">{{ $item['number'] }}</p>@endif
                    <h3 class="mt-3 text-xl">
                        @if (! empty($item['url']))<a class="underline-offset-4 hover:underline" href="{{ $item['url'] }}">{{ $item['name'] ?? '' }}</a>@else{{ $item['name'] ?? '' }}@endif
                    </h3>
                    @if (! empty($item['copy']))<p class="mt-2 text-sm text-body">{{ $item['copy'] }}</p>@endif
                </article>
            @endforeach
        </div>
    </div>
</section>
