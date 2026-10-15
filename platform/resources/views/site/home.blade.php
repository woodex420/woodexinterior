{{--
    Woodex Interior — home page (website phase P6, first release).

    Built in the Studio look on Tailwind v4 tokens (docs/05). Content comes from the live website's own copy
    (services, process, reviews, FAQs, clients: marketingwoodex repo, read-only) rewritten to read as one studio
    voice. Structured data lives in partials/head.blade.php; every fact in it is also visible on this page.
--}}
@php
    $modified = '2026-10-16'; // content last reviewed: keep in step with the copy below
    $title = 'Interior Design & Fit-Out in Lahore | Woodex Interior';
    $description = 'Interior design and fit-out studio in Lahore since 2016: interiors, renovation, architecture, 3D visualisation and custom furniture. 200+ spaces, one team.';
    $facts = [
        'Founded' => $business->founded_year.', '.$business->city.', Pakistan',
        'Studio' => $business->address.', '.$business->city,
        'Hours' => 'Monday to Saturday, '.$business->opens_at.' – '.$business->closes_at,
        'Services' => 'Interior design, fit-out, renovation, architecture, 3D studio, custom furniture',
        'Completed spaces' => '200+ homes, offices, clinics and retail spaces',
        'Coverage' => 'Lahore, with selected projects in Islamabad, Karachi and other cities',
    ];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    @include('site.partials.head')
</head>
<body class="bg-paper">
    @include('site.partials.header')

    <main id="main">
        {{-- Hero: the photograph is the LCP element, so it is a real <img> with fetchpriority, not a background. --}}
        <section class="relative isolate flex min-h-[86vh] items-end overflow-hidden bg-navy-900">
            <img src="{{ asset('img/home-hero-1600.webp') }}"
                 srcset="{{ asset('img/home-hero-800.webp') }} 800w, {{ asset('img/home-hero-1600.webp') }} 1600w"
                 sizes="100vw" width="1600" height="900" fetchpriority="high" decoding="async"
                 alt="Warm contemporary living room with timber detailing and wide city views"
                 class="absolute inset-0 -z-10 size-full object-cover">
            <div class="absolute inset-0 -z-10 bg-gradient-to-t from-navy-950/85 via-navy-950/45 to-navy-950/25" aria-hidden="true"></div>

            <div class="mx-auto w-full max-w-6xl px-5 py-16 md:px-8 md:py-24">
                <p class="text-[13px] font-medium tracking-[0.12em] text-sand-200 uppercase">Design and build · {{ $business->city }}</p>
                <h1 class="mt-4 max-w-3xl text-[clamp(38px,6vw,72px)] text-white">Spaces shaped by purpose and identity</h1>
                <p class="mt-6 max-w-xl text-lg text-navy-100/85">From the first sketch to final handover, design, build and furniture stay with one accountable team — so the drawings you approve are the drawings we build.</p>
                <div class="mt-9 flex flex-wrap gap-3">
                    <a href="#enquiry" class="inline-flex h-12 items-center rounded-full bg-white px-6 text-sm font-medium text-ink transition hover:bg-beige">Start your project</a>
                    <a href="#services" class="inline-flex h-12 items-center rounded-full border border-white/30 px-6 text-sm font-medium text-white transition hover:bg-white/10">Explore services</a>
                </div>

                <dl class="mt-14 grid max-w-3xl grid-cols-2 gap-x-8 gap-y-6 border-t border-white/15 pt-8 text-white sm:grid-cols-4">
                    <div><dt class="text-xs text-navy-200">Practising from {{ $business->city }}</dt><dd class="mt-1 font-heading text-3xl">{{ $business->founded_year }}</dd></div>
                    <div><dt class="text-xs text-navy-200">Completed spaces</dt><dd class="mt-1 font-heading text-3xl">200+</dd></div>
                    <div><dt class="text-xs text-navy-200">Disciplines, one team</dt><dd class="mt-1 font-heading text-3xl">6</dd></div>
                    <div><dt class="text-xs text-navy-200">Google rating</dt><dd class="mt-1 font-heading text-3xl">5.0 <span class="font-sans text-xs font-normal text-navy-200">/ 267 reviews</span></dd></div>
                </dl>
            </div>
        </section>

        {{-- Who we are: experience and proof, in plain sentences an answer engine can quote. --}}
        <section class="mx-auto max-w-6xl px-5 py-16 md:px-8 md:py-24">
            <div class="grid gap-10 md:grid-cols-2 md:items-center md:gap-16">
                <div>
                    <p class="text-[13px] font-medium tracking-[0.12em] text-wood-600 uppercase">One studio, six disciplines</p>
                    <h2 class="mt-4 text-[clamp(28px,3.6vw,44px)]">We survey before we quote, and we show you the space before we build it.</h2>
                    <div class="mt-6 space-y-4 text-body">
                        <p>Woodex Interior is a Lahore interior design and fit-out studio practising since {{ $business->founded_year }}. Architecture, interior design, fit-out, renovation, 3D visualisation and custom furniture sit under one roof, which means one team, one programme and one set of drawings from the first sketch to handover.</p>
                        <p>That structure is practical, not just tidy. We measure the space, price each item on an itemised quotation, and resolve the design in photoreal 3D before a single trade arrives on site — the three places where budgets usually drift.</p>
                    </div>

                    <dl class="mt-8 grid gap-x-8 gap-y-3 border-t border-line pt-6 text-sm sm:grid-cols-2">
                        @foreach ($facts as $label => $value)
                            <div class="flex gap-2"><dt class="shrink-0 text-muted">{{ $label }}:</dt><dd class="text-ink">{{ $value }}</dd></div>
                        @endforeach
                    </dl>
                </div>

                <img src="{{ asset('img/studio-intro.webp') }}" width="900" height="1100" loading="lazy" decoding="async"
                     alt="Contemporary residence exterior at dusk with layered architectural lighting"
                     class="h-full max-h-[620px] w-full rounded-2xl object-cover">
            </div>
        </section>

        {{-- Services --}}
        <section id="services" class="scroll-mt-24 border-y border-line bg-soft">
            <div class="mx-auto max-w-6xl px-5 py-16 md:px-8 md:py-24">
                <div class="flex flex-col gap-6 md:flex-row md:items-end md:justify-between">
                    <div>
                        <p class="text-[13px] font-medium tracking-[0.12em] text-wood-600 uppercase">Design services</p>
                        <h2 class="mt-4 max-w-xl text-[clamp(28px,3.6vw,44px)]">Six disciplines that cover the whole space</h2>
                    </div>
                    <p class="max-w-sm text-sm text-muted">Appoint us for one service, or keep the same team from first idea to handover.</p>
                </div>

                <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($services as $service)
                        <article class="rounded-2xl bg-white p-6 ring-1 ring-line transition hover:-translate-y-0.5 hover:ring-wood-300">
                            <p class="font-heading text-2xl text-wood-600">{{ $service['number'] }}</p>
                            <h3 class="mt-3 text-xl">{{ $service['name'] }}</h3>
                            <p class="mt-2 text-sm text-body">{{ $service['copy'] }}</p>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- Selected work --}}
        <section id="work" class="scroll-mt-24 mx-auto max-w-6xl px-5 py-16 md:px-8 md:py-24">
            <div class="flex flex-col gap-6 md:flex-row md:items-end md:justify-between">
                <div>
                    <p class="text-[13px] font-medium tracking-[0.12em] text-wood-600 uppercase">Selected studies</p>
                    <h2 class="mt-4 max-w-xl text-[clamp(28px,3.6vw,44px)]">Spaces drawn to be built, not just imagined</h2>
                </div>
                <p class="max-w-sm text-sm text-muted">A cross-section of residential, workplace and retail work, each resolved in drawings and 3D before work began on site.</p>
            </div>

            <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($studies as $study)
                    <figure class="overflow-hidden rounded-2xl bg-soft ring-1 ring-line">
                        <img src="{{ asset($study['image']) }}" width="720" height="540" loading="lazy" decoding="async"
                             alt="{{ $study['alt'] }}" class="aspect-[4/3] w-full object-cover">
                        <figcaption class="flex items-baseline justify-between gap-3 px-5 py-4">
                            <div>
                                <h3 class="text-base">{{ $study['title'] }}</h3>
                                <p class="mt-0.5 text-xs text-muted">{{ $study['note'] }}</p>
                            </div>
                            <span class="text-xs text-muted">{{ $study['category'] }}</span>
                        </figcaption>
                    </figure>
                @endforeach
            </div>
        </section>

        {{-- How we work --}}
        <section id="how-we-work" class="scroll-mt-24 bg-navy-900 text-white">
            <div class="mx-auto max-w-6xl px-5 py-16 md:px-8 md:py-24">
                <p class="text-[13px] font-medium tracking-[0.12em] text-sand-300 uppercase">How we work</p>
                <h2 class="mt-4 max-w-xl text-[clamp(28px,3.6vw,44px)] text-white">Four clear stages from first call to handover</h2>
                <p class="mt-4 max-w-xl text-navy-100/80">Every project follows the same accountable process, so you always know what happens next, who owns it and what it costs.</p>

                <ol class="mt-12 grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($stages as $stage)
                        <li class="border-t border-white/20 pt-5">
                            <p class="font-heading text-2xl text-sand-300">{{ $stage['number'] }}</p>
                            <h3 class="mt-3 text-lg text-white">{{ $stage['name'] }}</h3>
                            <p class="mt-2 text-sm text-navy-100/75">{{ $stage['copy'] }}</p>
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>

        {{-- Reviews and clients --}}
        <section id="reviews" class="scroll-mt-24 mx-auto max-w-6xl px-5 py-16 md:px-8 md:py-24">
            <div class="flex flex-col gap-6 md:flex-row md:items-end md:justify-between">
                <div>
                    <p class="text-[13px] font-medium tracking-[0.12em] text-wood-600 uppercase">Client reviews</p>
                    <h2 class="mt-4 max-w-xl text-[clamp(28px,3.6vw,44px)]">What our clients say</h2>
                </div>
                <p class="max-w-xs text-sm text-muted">
                    <strong class="text-ink">Excellent</strong> ★★★★★ · 5.0 out of 5, based on 267 Google reviews.
                    <a href="https://www.google.com/maps/search/?api=1&query=Woodex+Interior+Lahore" target="_blank" rel="noopener" class="text-ink underline">See the reviews on Google <span aria-hidden="true">↗</span></a>
                </p>
            </div>

            <div class="mt-10 grid gap-6 md:grid-cols-3">
                @foreach ($reviews as $review)
                    <figure class="flex h-full flex-col rounded-2xl bg-soft p-6 ring-1 ring-line">
                        <p class="text-sm text-wood-600" aria-label="Rated 5 out of 5">★★★★★</p>
                        <blockquote class="mt-3 grow text-body">“{{ $review['text'] }}”</blockquote>
                        <figcaption class="mt-5 text-sm"><span class="font-medium text-ink">{{ $review['name'] }}</span> <span class="text-muted">· {{ $review['when'] }} · Google review</span></figcaption>
                    </figure>
                @endforeach
            </div>

            <div class="mt-14">
                <h3 class="text-center text-xs tracking-[0.16em] text-muted uppercase">Trusted by</h3>
                <ul class="mt-6 flex flex-wrap items-center justify-center gap-x-10 gap-y-6">
                    @foreach ($clients as $client)
                        <li><img src="{{ asset($client['logo']) }}" alt="{{ $client['name'] }}" width="320" height="160" loading="lazy" decoding="async" class="h-10 w-auto opacity-70 grayscale"></li>
                    @endforeach
                </ul>
            </div>
        </section>

        {{-- FAQ: native details, open without JS, mirrored in the FAQPage structured data --}}
        <section id="faq" class="scroll-mt-24 border-y border-line bg-soft">
            <div class="mx-auto max-w-3xl px-5 py-16 md:px-8 md:py-24">
                <p class="text-[13px] font-medium tracking-[0.12em] text-wood-600 uppercase">Common questions</p>
                <h2 class="mt-4 text-[clamp(28px,3.6vw,44px)]">Clear answers before the work begins</h2>

                <div class="mt-10 divide-y divide-line rounded-2xl bg-white ring-1 ring-line">
                    @foreach ($faqs as $index => $faq)
                        <details class="group px-6 py-5" @if ($index === 0) open @endif>
                            <summary class="flex cursor-pointer items-center justify-between gap-4 text-base font-medium text-ink marker:hidden">
                                {{ $faq['question'] }}
                                <span class="shrink-0 text-xl text-muted transition group-open:rotate-45" aria-hidden="true">+</span>
                            </summary>
                            <p class="mt-3 text-sm text-body">{{ $faq['answer'] }}</p>
                        </details>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- Enquiry + contact --}}
        <section class="mx-auto max-w-6xl px-5 py-16 md:px-8 md:py-24">
            <div class="grid gap-10 md:grid-cols-2 md:gap-14">
                <div>
                    <p class="text-[13px] font-medium tracking-[0.12em] text-wood-600 uppercase">Begin with the brief</p>
                    <h2 class="mt-4 text-[clamp(28px,3.6vw,44px)]">Tell us about your space</h2>
                    <p class="mt-4 text-body">Share the location, approximate area, intended use, timeline and any drawings you already have. We will confirm the right next step within one working day.</p>

                    <dl class="mt-8 space-y-4 border-t border-line pt-6 text-sm">
                        <div><dt class="text-muted">Studio</dt><dd class="text-ink">{{ $business->address }}, {{ $business->city }}</dd></div>
                        <div><dt class="text-muted">Phone &amp; WhatsApp</dt><dd class="text-ink"><a class="underline" href="tel:{{ $business->phone }}">{{ $phone }}</a></dd></div>
                        <div><dt class="text-muted">Email</dt><dd class="text-ink"><a class="underline" href="mailto:{{ $business->email }}">{{ $business->email }}</a></dd></div>
                        <div><dt class="text-muted">Hours</dt><dd class="text-ink">Monday to Saturday · {{ $business->opens_at }} – {{ $business->closes_at }}</dd></div>
                    </dl>
                </div>

                @include('site.partials.lead-form')
            </div>
        </section>
    </main>

    @include('site.partials.footer')
</body>
</html>
