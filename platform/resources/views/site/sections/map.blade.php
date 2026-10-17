{{-- Map + studio address. The embed is lazy and only loads when it scrolls into view. --}}
<section class="mx-auto max-w-6xl px-5 py-14 md:px-8">
    <h2 class="text-[clamp(24px,3vw,36px)]">{{ $block->field('heading', 'Studio') }}</h2>
    <div class="mt-6 grid gap-6 md:grid-cols-2">
        <address class="not-italic text-body">
            {{ $business->address }}<br>{{ $business->city }}, Pakistan<br>
            <a class="underline" href="tel:{{ $business->phone }}">{{ $phone }}</a><br>
            <a class="underline" href="mailto:{{ $business->email }}">{{ $business->email }}</a><br>
            Monday to Saturday · {{ $business->opens_at }} – {{ $business->closes_at }}
        </address>
        @if ($block->field('embed_url'))
            <iframe src="{{ $block->field('embed_url') }}" loading="lazy" title="{{ $business->brand_name }} studio location"
                    class="min-h-[260px] w-full rounded-2xl ring-1 ring-line"></iframe>
        @endif
    </div>
</section>
