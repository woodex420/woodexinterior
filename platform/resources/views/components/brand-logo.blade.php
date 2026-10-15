@props(['subtitle' => 'OS', 'compact' => false])
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2.5']) }}>
    <svg class="size-8 shrink-0" viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
        <path d="m3 6 8 28 9-20 9 20 8-28M11 6l9 28L29 6" />
    </svg>
    @unless ($compact)
        <span class="font-heading text-[21px] leading-none font-medium tracking-[0.055em]">
            WOODEX
            <small class="mt-1 block font-sans text-[8px] font-normal tracking-[0.42em]">{{ $subtitle }}</small>
        </span>
    @endunless
</span>
