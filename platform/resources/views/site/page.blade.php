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
@endphp

@section('content')
    @if (! $hasHero)
        <section class="mx-auto max-w-6xl px-5 pt-14 md:px-8">
            <p class="text-[13px] font-medium tracking-[0.12em] text-wood-600 uppercase">{{ $page->type->label() }}</p>
            <h1 class="mt-4 max-w-3xl text-[clamp(32px,5vw,58px)]">{{ $title }}</h1>
            @if ($description)<p class="mt-4 max-w-2xl text-lg text-body">{{ $description }}</p>@endif
        </section>
    @endif

    @foreach ($blocks as $block)
        @include($block->type->view(), ['block' => $block, 'page' => $page, 'media' => $media, 'business' => $business, 'phone' => $phone])
    @endforeach
@endsection
