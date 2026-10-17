{{-- Process / stages, on navy like the home page. --}}
@php $stages = array_values(array_filter((array) $block->field('stages', []))); @endphp
<section class="bg-navy-900 text-white">
    <div class="mx-auto max-w-6xl px-5 py-16 md:px-8 md:py-24">
        @if ($block->field('heading'))<h2 class="max-w-xl text-[clamp(28px,3.6vw,44px)] text-white">{{ $block->field('heading') }}</h2>@endif
        <ol class="mt-12 grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($stages as $index => $stage)
                <li class="border-t border-white/20 pt-5">
                    <p class="font-heading text-2xl text-sand-300">{{ $stage['number'] ?? str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</p>
                    <h3 class="mt-3 text-lg text-white">{{ $stage['name'] ?? '' }}</h3>
                    @if (! empty($stage['copy']))<p class="mt-2 text-sm text-navy-100/75">{{ $stage['copy'] }}</p>@endif
                </li>
            @endforeach
        </ol>
    </div>
</section>
