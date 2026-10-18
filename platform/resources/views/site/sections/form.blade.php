{{-- Enquiry form + NAP. The form itself is plain HTML and works before JavaScript starts (D-49). --}}
<section class="mx-auto max-w-6xl px-5 py-16 md:px-8 md:py-24">
    <div class="grid gap-10 md:grid-cols-2 md:gap-14">
        <div>
            @if ($block->field('heading'))<h2 class="text-[clamp(28px,3.6vw,44px)]">{{ $block->field('heading') }}</h2>@endif
            @if ($block->field('text'))<p class="mt-4 text-body">{{ $block->field('text') }}</p>@endif

            @if ($block->field('show_contact', true))
                <dl class="mt-8 space-y-4 border-t border-line pt-6 text-sm">
                    <div><dt class="text-muted">Studio</dt><dd class="text-ink">{{ $business->address }}, {{ $business->city }}</dd></div>
                    <div><dt class="text-muted">Phone &amp; WhatsApp</dt><dd class="text-ink"><a class="underline" href="tel:{{ $business->phone }}">{{ $phone }}</a></dd></div>
                    <div><dt class="text-muted">Email</dt><dd class="text-ink"><a class="underline" href="mailto:{{ $business->email }}">{{ $business->email }}</a></dd></div>
                    <div><dt class="text-muted">Hours</dt><dd class="text-ink">Monday to Saturday · {{ $business->opens_at }} – {{ $business->closes_at }}</dd></div>
                </dl>
            @endif
        </div>

        @include('site.partials.lead-form')
    </div>
</section>
