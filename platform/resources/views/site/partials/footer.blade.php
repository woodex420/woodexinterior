{{--    Public site footer (V2-3): a fixed professional layout whose every word is editable.

    - Brand column: logo, tagline (SiteSettings override), NTN, the social icons (Website settings).
    - Link columns: the footer menu from Website → Menus — a top link with children is one column
      (the link is the column's title, its children the links in it). Until the menu is filled in,
      the built-in links print as one plain column.
    - "What we do" services column, the studio's NAP column and the "Where we work" cities row each
      have a switch in Website settings; the copyright line can be overridden there too.
--}}
@php
    $navigation ??= [];
    $cities ??= [];
    $site ??= app(\App\Settings\SiteSettings::class);
    // Top links with children are the footer's columns; plain top links stay in the bottom row.
    $menuColumns = collect($navigation)->filter(fn (array $item): bool => ($item['children'] ?? []) !== [])->values();
    $menuLinks = collect($navigation)->filter(fn (array $item): bool => ($item['children'] ?? []) === [])->values();
    $socials = collect($site->social_links ?? [])->filter(fn (string $url): bool => $url !== '');
    $icons = [
        'facebook' => 'M13.5 21v-7h2.4l.4-3h-2.8V9.1c0-.9.3-1.5 1.6-1.5h1.3V4.9c-.3 0-1.2-.1-2.2-.1-2.2 0-3.7 1.3-3.7 3.8V11H8v3h2.5v7h3z',
        'instagram' => 'M12 7.4A4.6 4.6 0 1 0 16.6 12 4.6 4.6 0 0 0 12 7.4zm0 7.6A3 3 0 1 1 15 12a3 3 0 0 1-3 3zm5.9-7.8a1.1 1.1 0 1 1-1.1-1.1 1.1 1.1 0 0 1 1.1 1.1zM21 12a9 9 0 0 1-2.6 6.4A8.9 8.9 0 0 1 12 21a8.9 8.9 0 0 1-6.4-2.6A9 9 0 0 1 3 12a9 9 0 0 1 2.6-6.4A8.9 8.9 0 0 1 12 3a8.9 8.9 0 0 1 6.4 2.6A9 9 0 0 1 21 12zm-1.8 0A7.2 7.2 0 0 0 12 4.8 7.2 7.2 0 0 0 4.8 12 7.2 7.2 0 0 0 12 19.2 7.2 7.2 0 0 0 19.2 12z',
        'linkedin' => 'M6.9 8.6H4V20h2.9zM5.4 4a1.7 1.7 0 1 0 0 3.4 1.7 1.7 0 0 0 0-3.4zM20 13.6c0-3-1.6-4.5-3.8-4.5a3.3 3.3 0 0 0-3 1.6V8.6H10.4V20h2.9v-6a2 2 0 0 1 1.8-2.2c1.1 0 1.9.7 1.9 2.2v6H20z',
        'youtube' => 'M21.2 8.2a2.5 2.5 0 0 0-1.8-1.8C17.9 6 12 6 12 6s-5.9 0-7.4.4A2.5 2.5 0 0 0 2.8 8.2 26 26 0 0 0 2.4 12a26 26 0 0 0 .4 3.8 2.5 2.5 0 0 0 1.8 1.8c1.5.4 7.4.4 7.4.4s5.9 0 7.4-.4a2.5 2.5 0 0 0 1.8-1.8 26 26 0 0 0 .4-3.8 26 26 0 0 0-.4-3.8zM10.1 14.7V9.3l4.9 2.7z',
        'tiktok' => 'M16.6 3h-2.8v11.9a2.3 2.3 0 1 1-1.9-2.3V9.7a5.4 5.4 0 1 0 4.7 5.4V9.1a6.6 6.6 0 0 0 3.4 1V7.4a3.8 3.8 0 0 1-3.4-3.6z',
    ];
@endphp
<footer class="mt-20 bg-navy-900 text-white">
    <div class="mx-auto max-w-6xl px-5 py-14 md:px-8">
        <div class="grid gap-10 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <p class="font-heading text-[21px] leading-none font-medium tracking-[0.055em]">WOODEX
                    <small class="mt-1 block font-sans text-[8px] font-normal tracking-[0.42em] text-navy-300">INTERIOR STUDIO</small>
                </p>
                <p class="mt-4 text-sm text-navy-100/80">{{ $site->footer_tagline ?: $business->tagline }}</p>
                @if ($business->ntn)<p class="mt-3 text-xs text-navy-300">NTN {{ $business->ntn }}</p>@endif

                @if ($socials->isNotEmpty())
                    <nav aria-label="Social profiles" class="mt-5 flex items-center gap-3">
                        @foreach ($socials as $platform => $url)
                            <a href="{{ $url }}" target="_blank" rel="noopener" aria-label="{{ ucfirst((string) $platform) }}" class="inline-flex size-9 items-center justify-center rounded-full border border-white/20 text-white/80 transition hover:border-white/60 hover:text-white">
                                <svg class="size-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="{{ $icons[$platform] ?? '' }}"/></svg>
                            </a>
                        @endforeach
                    </nav>
                @endif
            </div>

            {{-- The footer menu: each top link with children is one column. --}}
            @foreach ($menuColumns as $column)
                <div>
                    <h2 class="text-sm font-medium text-white">{{ $column['label'] }}</h2>
                    <ul class="mt-4 space-y-2 text-sm text-navy-100/80">
                        @foreach ($column['children'] as $link)
                            <li><a href="{{ $link['url'] }}" @if ($link['target'] === '_blank') target="_blank" rel="noopener" @endif class="inline-block py-1.5 hover:text-white">{{ $link['label'] }}</a></li>
                        @endforeach
                    </ul>
                </div>
            @endforeach

            @if ($site->footer_show_services)
                <div>
                    <h2 class="text-sm font-medium text-white">What we do</h2>
                    <ul class="mt-4 space-y-2 text-sm text-navy-100/80">
                        @foreach ($services as $service)
                            <li><a href="{{ $service['url'] ?? '#services' }}" class="inline-block py-1.5 hover:text-white">{{ $service['name'] }}</a></li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($site->footer_show_contact)
                <div>
                    <h2 class="text-sm font-medium text-white">Studio</h2>
                    <address class="mt-4 space-y-2 text-sm not-italic text-navy-100/80">
                        <p>{{ $business->address }}<br>{{ $business->city }}, Pakistan</p>
                        <p><a href="tel:{{ $business->phone }}" class="inline-block py-1 hover:text-white">{{ $phone }}</a></p>
                        <p><a href="mailto:{{ $business->email }}" class="inline-block py-1 hover:text-white">{{ $business->email }}</a></p>
                        <p>Monday to Saturday · {{ $business->opens_at }} – {{ $business->closes_at }}</p>
                    </address>
                </div>
            @endif
        </div>

        <div class="mt-12 border-t border-white/10 pt-6 text-xs text-navy-300">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p>{{ $site->footer_copyright ?: '© '.date('Y').' '.$business->legal_name.'. All rights reserved.' }}</p>
                <p>Interior design, fit-out, renovation, architecture, 3D visualisation and custom furniture · {{ $business->city }}, Pakistan</p>
            </div>

            @if ($menuLinks->isNotEmpty())
                <nav aria-label="Footer" class="mt-5">
                    <ul class="flex flex-wrap items-center gap-x-5 gap-y-1">
                        @foreach ($menuLinks as $item)
                            <li><a href="{{ $item['url'] }}" @if ($item['target'] === '_blank') target="_blank" rel="noopener" @endif class="inline-block py-1 hover:text-white">{{ $item['label'] }}</a></li>
                        @endforeach
                    </ul>
                </nav>
            @endif

            @if ($site->footer_show_cities && $cities !== [])
                <div class="mt-5 flex flex-wrap items-baseline gap-x-3 gap-y-1">
                    <h2 class="text-xs text-navy-300">Where we work</h2>
                    <nav aria-label="Cities" class="flex flex-wrap items-center gap-x-3 gap-y-1">
                        @foreach ($cities as $city)
                            <a href="{{ $city['url'] }}" class="inline-block py-1 hover:text-white">{{ $city['name'] }}</a>
                        @endforeach
                    </nav>
                </div>
            @endif
        </div>
    </div>
</footer>
