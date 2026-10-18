{{--    The v26 hero slider: full-height slides that rotate, each with its own photo, kicker, title,
    text and two buttons (V2-3, owner answer of 18 Oct). Autoplay pauses on hover and on focus, and
    switches off entirely for people who asked the system to calm down (prefers-reduced-motion).
    The first slide's photo is the LCP element, so it is a real <img> with fetchpriority — never lazy.
    Alpine drives the rotation; no external library, nothing to load.
--}}
@php
    $slides = array_values(array_filter((array) $block->field('slides', []), fn ($slide): bool => ($slide['image_id'] ?? null) !== null));
    $count = count($slides);
    // The page's only H1: a real hero block wins, otherwise the first slide carries it.
    $hasHeroBlock = collect($blocks ?? [])->contains(fn ($b): bool => $b->type === \App\Domain\Website\Enums\BlockType::Hero);
@endphp
@if ($count > 0)
    <section class="relative isolate overflow-hidden bg-navy-950" aria-roledescription="carousel" aria-label="{{ $slides[0]['heading'] ?? 'Featured work' }}"
             x-data="{
                i: 0,
                timer: null,
                get total() { return {{ $count }} },
                go(n) { this.i = (n + this.total) % this.total },
                next() { this.go(this.i + 1) },
                prev() { this.go(this.i - 1) },
                start() {
                    if (this.total < 2 || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
                    this.stop();
                    this.timer = setInterval(() => this.next(), 6000);
                },
                stop() { if (this.timer) { clearInterval(this.timer); this.timer = null; } }
             }"
             x-init="start()"
             @mouseenter="stop()"
             @mouseleave="start()"
             @focusin="stop()"
             @focusout="start()"
             @keydown.arrow-left.prevent="prev()"
             @keydown.arrow-right.prevent="next()">
        {{-- The slides: stacked, only the current one visible. --}}
        <div class="relative min-h-[86vh]">
            @foreach ($slides as $index => $slide)
                @php
                    $image = $media[$slide['image_id']] ?? null;
                    $active = $index === 0;
                    // The page's only H1 comes from the hero or the fallback title; when neither is there, the first slide carries it.
                    $tag = $index === 0 && ! $hasHeroBlock ? 'h1' : 'h2';
                @endphp
                @if ($image)
                    <article class="absolute inset-0 transition-opacity duration-700 {{ $active ? 'opacity-100' : 'pointer-events-none opacity-0' }}"
                             :class="i === {{ $index }} ? 'opacity-100' : 'pointer-events-none opacity-0'"
                             aria-roledescription="slide" :aria-hidden="(i === {{ $index }}).toString()" data-slide="{{ $index }}">
                        <img src="{{ $image->url() }}" width="{{ $image->width ?? 1920 }}" height="{{ $image->height ?? 1080 }}"
                             @if ($index === 0) fetchpriority="high" @else loading="lazy" @endif decoding="async" alt="{{ $image->alt }}"
                             class="absolute inset-0 size-full object-cover">
                        <div class="absolute inset-0 bg-gradient-to-t from-navy-950/90 via-navy-950/45 to-navy-950/25" aria-hidden="true"></div>

                        <div class="relative flex min-h-[86vh] items-end">
                            <div class="mx-auto w-full max-w-6xl px-5 pb-16 md:px-8 md:pb-24">
                                @if ($slide['kicker'] ?? '')<p class="text-[13px] font-medium tracking-[0.12em] text-sand-200 uppercase">{{ $slide['kicker'] }}</p>@endif
                                <{{ $tag }} class="mt-4 max-w-3xl text-[clamp(34px,5.5vw,64px)] text-white">{{ $slide['heading'] ?? '' }}</{{ $tag }}>
                                @if ($slide['text'] ?? '')<p class="mt-5 max-w-xl text-lg text-navy-100/85">{{ $slide['text'] }}</p>@endif

                                <div class="mt-8 flex flex-wrap gap-3">
                                    @if ($slide['primary_label'] ?? '')
                                        <a href="{{ $slide['primary_url'] ?? '#enquiry' }}" class="inline-flex h-12 items-center rounded-full bg-white px-6 text-sm font-medium text-ink transition hover:bg-beige">{{ $slide['primary_label'] }}</a>
                                    @endif
                                    @if ($slide['secondary_label'] ?? '')
                                        <a href="{{ $slide['secondary_url'] ?? '#enquiry' }}" class="inline-flex h-12 items-center rounded-full border border-white/30 px-6 text-sm font-medium text-white transition hover:bg-white/10">{{ $slide['secondary_label'] }}</a>
                                    @endif
                                </div>

                                @if ($slide['display'] ?? '')
                                    <p class="mt-10 font-heading text-[clamp(56px,14vw,150px)] leading-none font-medium tracking-tight text-white/95" aria-hidden="true">{{ $slide['display'] }}</p>
                                @endif
                            </div>
                        </div>
                    </article>
                @endif
            @endforeach
        </div>

        @if ($count > 1)
            {{-- Controls: arrows on the right, dots in the middle, count for screen readers. --}}
            <div class="absolute inset-x-0 bottom-5 z-10">
                <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-5 md:px-8">
                    <p class="text-xs tracking-[0.1em] text-navy-200" aria-live="polite">
                        <span x-text="String(i + 1).padStart(2, '0')">01</span> / {{ str_pad((string) $count, 2, '0', STR_PAD_LEFT) }}
                    </p>
                    <div class="flex items-center gap-2">
                        @foreach ($slides as $index => $slide)
                            @if (($media[$slide['image_id'] ?? null] ?? null) !== null)
                                <button type="button" @click="go({{ $index }})" :aria-pressed="(i === {{ $index }}).toString()"
                                        :class="i === {{ $index }} ? 'w-6 bg-white' : 'w-2.5 bg-white/40 hover:bg-white/70'"
                                        class="h-2.5 rounded-full transition-all" aria-label="Go to slide {{ $index + 1 }}"></button>
                            @endif
                        @endforeach
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" @click="prev()" class="inline-flex size-11 items-center justify-center rounded-full border border-white/30 text-white transition hover:bg-white/10" aria-label="Previous slide">
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M15 6l-6 6 6 6"/></svg>
                        </button>
                        <button type="button" @click="next()" class="inline-flex size-11 items-center justify-center rounded-full border border-white/30 text-white transition hover:bg-white/10" aria-label="Next slide">
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>
                        </button>
                    </div>
                </div>
            </div>
        @endif
    </section>
@endif
