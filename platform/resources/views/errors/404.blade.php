@extends('layouts.site')

@php
    $business = $business ?? app(\App\Settings\BusinessSettings::class);
    $phone = $phone ?? \App\Support\Phone::display($business->phone);
    $title = 'Page not found | '.$business->brand_name;
    $description = 'That page has moved or never existed. The studio services, work and contact details are one click away.';
    $canonical = \App\Domain\Website\Models\Page::urlFor('/'.request()->path());
    $services = \App\Domain\Website\Services\Menus::serviceLinks();
    $ogImage = \App\Domain\Website\Models\Page::urlFor('/img/og-home.jpg');
@endphp

@section('content')
    <section class="mx-auto max-w-3xl px-5 py-24 text-center md:px-8">
        <p class="text-[13px] font-medium tracking-[0.12em] text-wood-600 uppercase">404</p>
        <h1 class="mt-4 text-[clamp(32px,5vw,54px)]">This page has moved</h1>
        <p class="mt-4 text-body">The link you followed is no longer here. Everything on the old website is still available — start from the service you need, or tell us what you are looking for.</p>

        <div class="mt-8 flex flex-wrap justify-center gap-3">
            <a href="{{ url('/') }}" class="inline-flex h-12 items-center rounded-full bg-navy-900 px-6 text-sm font-medium text-white transition hover:bg-navy-800">Back to the home page</a>
            <a href="tel:{{ $business->phone }}" class="inline-flex h-12 items-center rounded-full border border-line px-6 text-sm font-medium text-ink transition hover:bg-soft">Call {{ $phone }}</a>
        </div>

        @if ($services !== [])
            <ul class="mt-12 flex flex-wrap justify-center gap-3 text-sm">
                @foreach ($services as $service)
                    <li><a href="{{ $service['url'] }}" class="rounded-full bg-soft px-4 py-2 text-body ring-1 ring-line transition hover:ring-wood-300">{{ $service['name'] }}</a></li>
                @endforeach
            </ul>
        @endif
    </section>
@endsection
