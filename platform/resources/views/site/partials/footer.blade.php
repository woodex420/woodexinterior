{{-- Public site footer: name, address, phone (NAP), the service list and the links from Website → Menus, all
     matching the structured data. --}}
@php
    $navigation ??= [];
    $cities ??= [];
@endphp
<footer class="mt-20 bg-navy-900 text-white">
    <div class="mx-auto max-w-6xl px-5 py-14 md:px-8">
        <div class="grid gap-10 md:grid-cols-2 lg:grid-cols-4">
            <div>
                <p class="font-heading text-[21px] leading-none font-medium tracking-[0.055em]">WOODEX
                    <small class="mt-1 block font-sans text-[8px] font-normal tracking-[0.42em] text-navy-300">INTERIOR STUDIO</small>
                </p>
                <p class="mt-4 text-sm text-navy-100/80">{{ $business->tagline }}</p>
                @if ($business->ntn)<p class="mt-3 text-xs text-navy-300">NTN {{ $business->ntn }}</p>@endif
            </div>

            <div>
                <h2 class="text-sm font-medium text-white">What we do</h2>
                <ul class="mt-4 space-y-2 text-sm text-navy-100/80">
                    @foreach ($services as $service)
                        <li><a href="{{ $service['url'] ?? '#services' }}" class="inline-block py-1.5 hover:text-white">{{ $service['name'] }}</a></li>
                    @endforeach
                </ul>
            </div>

            <div>
                <h2 class="text-sm font-medium text-white">Studio</h2>
                <address class="mt-4 space-y-2 text-sm not-italic text-navy-100/80">
                    <p>{{ $business->address }}<br>{{ $business->city }}, Pakistan</p>
                    <p><a href="tel:{{ $business->phone }}" class="inline-block py-1 hover:text-white">{{ $phone }}</a></p>
                    <p><a href="mailto:{{ $business->email }}" class="inline-block py-1 hover:text-white">{{ $business->email }}</a></p>
                    <p>Monday to Saturday · {{ $business->opens_at }} – {{ $business->closes_at }}</p>
                </address>
            </div>

            <div>
                <h2 class="text-sm font-medium text-white">Begin a conversation</h2>
                <p class="mt-4 text-sm text-navy-100/80">Have a space in mind? Share the location, size and timeline and we will reply within one working day.</p>
                <a href="#enquiry" class="btn-studio mt-5">Start your project <span aria-hidden="true">↗</span></a>
                @if ($whatsapp)
                    <a href="{{ $whatsapp }}" target="_blank" rel="noopener" class="mt-3 inline-block py-1 text-sm text-navy-100/80 hover:text-white">Message us on WhatsApp <span aria-hidden="true">↗</span></a>
                @endif
            </div>
        </div>

        <div class="mt-12 border-t border-white/10 pt-6 text-xs text-navy-300">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p>© {{ date('Y') }} {{ $business->legal_name }}. All rights reserved.</p>
                <p>Interior design, fit-out, renovation, architecture, 3D visualisation and custom furniture · {{ $business->city }}, Pakistan</p>
            </div>

            @if ($cities !== [])
                <div class="mt-5 flex flex-wrap items-baseline gap-x-3 gap-y-1">
                    <h2 class="text-xs text-navy-300">Where we work</h2>
                    <nav aria-label="Cities" class="flex flex-wrap items-center gap-x-3 gap-y-1">
                        @foreach ($cities as $city)
                            <a href="{{ $city['url'] }}" class="inline-block py-1 hover:text-white">{{ $city['name'] }}</a>
                        @endforeach
                    </nav>
                </div>
            @endif

            @if ($navigation !== [])
                <nav aria-label="Footer" class="mt-5">
                    <ul class="flex flex-wrap items-center gap-x-5 gap-y-1">
                        @foreach ($navigation as $item)
                            <li><a href="{{ $item['url'] }}" @if ($item['target'] === '_blank') target="_blank" rel="noopener" @endif class="inline-block py-1 hover:text-white">{{ $item['label'] }}</a></li>
                        @endforeach
                    </ul>
                </nav>
            @endif
        </div>
    </div>
</footer>
