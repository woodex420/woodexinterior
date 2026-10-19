@extends('layouts.site')

@php
    $business = $business ?? app(\App\Settings\BusinessSettings::class);
    $title = $title ?? $page->title;
    $description = $description ?? (string) ($page->seo['description'] ?? $page->excerpt ?? '');
    $canonical = $canonical ?? $page->canonical();
    $ogImage = $ogImage ?? \App\Domain\Website\Models\Page::urlFor('/img/og-home.jpg');
    $faqs = $faqs ?? [];
    $services = $services ?? [];
    $schemaType = $schemaType ?? ($page->schema_type ?? $page->type->schemaType());
    $modified = $modified ?? ($page->updated_at ?? now())->toDateString();
    $ogImageAlt = $title;
    // Per-section device styles (SectionStyle): generated from integers only, scoped by block key.
    $sectionCss = collect($blocks ?? [])
        ->map(fn ($block): string => \App\Domain\Website\Support\SectionStyle::css($block->settings, (string) $block->block_key))
        ->filter()
        ->implode("\n");
@endphp

@if ($sectionCss !== '')
    @push('head')
        <style>{!! $sectionCss !!}</style>
    @endpush
@endif

@section('content')
    @if ($page->template === 'canvas')
        {{-- The campaign canvas (D-107): hand-placed HTML, sanitised on save, full width — the theme's section chrome deliberately stays out. --}}
        {!! $page->canvas_html !!}
    @elseif (! $hasHero)
        <section class="mx-auto max-w-6xl px-5 pt-14 md:px-8">
            <p class="text-[13px] font-medium tracking-[0.12em] text-wood-600 uppercase">{{ $page->type->label() }}</p>
            <h1 class="mt-4 max-w-3xl text-[clamp(32px,5vw,58px)]">{{ $title }}</h1>
            @if ($description)<p class="mt-4 max-w-2xl text-lg text-body">{{ $description }}</p>@endif
        </section>
    @endif

    @if ($page->template !== 'canvas')
        @foreach ($blocks as $block)
            @php
                $sectionKey = $block->block_key;
                // The section's own look choices (SectionStyle): background, spacing, width, alignment.
                // A section with no settings keeps exactly the design its view was built with.
                $sectionClasses = \App\Domain\Website\Support\SectionStyle::classes($block->settings);
            @endphp
            <div @class($sectionClasses) @if ($sectionKey) data-wx-key="{{ $sectionKey }}" @endif>
                @include($block->type->view(), ['block' => $block, 'page' => $page, 'media' => $media, 'business' => $business, 'phone' => $phone])
            </div>
        @endforeach
    @endif
@endsection
