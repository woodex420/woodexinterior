{{-- Public site header. Until the other pages are built the menu points at sections of this page; no dead links. --}}
<a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:top-3 focus:left-3 focus:z-50 focus:rounded-full focus:bg-white focus:px-4 focus:py-2 focus:text-sm">Skip to content</a>

<header x-data="{ open: false }" class="sticky top-0 z-40 border-b border-line/70 bg-paper/90 backdrop-blur">
    <div class="mx-auto flex max-w-6xl items-center justify-between gap-6 px-5 py-4 md:px-8">
        <a href="{{ url('/') }}" aria-label="{{ $business->brand_name }} — home" class="text-ink">
            <x-brand-logo subtitle="INTERIOR STUDIO" />
        </a>

        <nav aria-label="Main navigation" class="hidden items-center gap-7 text-sm text-body lg:flex">
            <a href="#services" class="inline-block py-2.5 hover:text-ink">Services</a>
            <a href="#work" class="inline-block py-2.5 hover:text-ink">Work</a>
            <a href="#how-we-work" class="inline-block py-2.5 hover:text-ink">How we work</a>
            <a href="#reviews" class="inline-block py-2.5 hover:text-ink">Reviews</a>
            <a href="#faq" class="inline-block py-2.5 hover:text-ink">FAQ</a>
        </nav>

        <div class="flex items-center gap-3">
            <a href="tel:{{ $business->phone }}" class="hidden py-2.5 text-sm text-body hover:text-ink md:inline">{{ $phone }}</a>
            <a href="#enquiry" class="inline-flex h-11 items-center rounded-full bg-navy-900 px-5 text-sm font-medium text-white transition hover:bg-navy-800">
                Start your project
            </a>
            <button type="button" @click="open = !open" :aria-expanded="open.toString()" aria-controls="mobile-nav" class="inline-flex size-11 items-center justify-center rounded-full border border-line lg:hidden" aria-label="Menu">
                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M3 7h18M3 12h18M3 17h18"/></svg>
            </button>
        </div>
    </div>

    <nav id="mobile-nav" x-cloak x-show="open" @click.outside="open = false" aria-label="Main navigation (mobile)"
         class="border-t border-line bg-paper px-5 py-3 text-sm lg:hidden">
        <ul class="space-y-1">
            <li><a href="#services" class="block py-2" @click="open = false">Services</a></li>
            <li><a href="#work" class="block py-2" @click="open = false">Work</a></li>
            <li><a href="#how-we-work" class="block py-2" @click="open = false">How we work</a></li>
            <li><a href="#reviews" class="block py-2" @click="open = false">Reviews</a></li>
            <li><a href="#faq" class="block py-2" @click="open = false">FAQ</a></li>
        </ul>
    </nav>
</header>
