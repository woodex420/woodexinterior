{--
    Public site header. The menu comes from Website → Menus (docs/09 §2.1) when it is filled in; until then the
    built-in links are used so no page ever shows a dead link.

    V2-3: the menu is a tree. A top link with children opens a dropdown on hover and on keyboard focus; when
    its mega switch is on, the dropdown becomes the wide numbered panel of the v26 design (a column per child,
    the child's own links under it). On the phone the drawer folds each parent into a disclosure.
--}}
@php
    $tree ??= [];
    $links = $tree !== [] ? $tree : [
        ['label' => 'Services', 'url' => '/services/', 'target' => '_self', 'is_mega' => false, 'children' => []],
        ['label' => 'Cost guides', 'url' => '/insights/', 'target' => '_self', 'is_mega' => false, 'children' => []],
        ['label' => 'Projects', 'url' => '/projects/', 'target' => '_self', 'is_mega' => false, 'children' => []],
        ['label' => 'About', 'url' => '/about/', 'target' => '_self', 'is_mega' => false, 'children' => []],
        ['label' => 'Contact', 'url' => '/contact/', 'target' => '_self', 'is_mega' => false, 'children' => []],
    ];
    $hasChildren = fn (array $link): bool => ($link['children'] ?? []) !== [];
@endphp
<a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:top-3 focus:left-3 focus:z-50 focus:rounded-full focus:bg-white focus:px-4 focus:py-2 focus:text-sm">Skip to content</a>

<header x-data="{ open: false }" class="sticky top-0 z-40 border-b border-line/70 bg-paper/90 backdrop-blur">
    <div class="mx-auto flex max-w-6xl items-center justify-between gap-6 px-5 py-4 md:px-8">
        <a href="{{ url('/') }}" aria-label="{{ $business->brand_name }} — home" class="text-ink">
            <x-brand-logo subtitle="INTERIOR STUDIO" />
        </a>

        <nav aria-label="Main navigation" class="hidden items-center gap-7 text-sm text-body lg:flex">
            @foreach ($links as $link)
                @if ($hasChildren($link))
                    <div @class(['group relative', 'mega' => $link['is_mega'] ?? false])>
                        <a href="{{ $link['url'] }}" @if ($link['target'] === '_blank') target="_blank" rel="noopener" @endif aria-haspopup="true" class="inline-flex items-center gap-1 py-2.5 hover:text-ink">
                            {{ $link['label'] }}
                            <svg class="size-3.5 transition group-hover:rotate-180 group-focus-within:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                        </a>

                        @if ($link['is_mega'] ?? false)
                            {{-- The mega panel (v26): a numbered column per child, the child's own links under it. --}}
                            <div class="invisible absolute top-full left-1/2 z-50 -translate-x-1/2 pt-2 opacity-0 transition group-hover:visible group-hover:opacity-100 group-focus-within:visible group-focus-within:opacity-100">
                                <div class="w-[min(72rem,calc(100vw-3rem))] rounded-2xl border border-line bg-paper p-6 shadow-xl shadow-navy-950/10">
                                    <div class="grid grid-cols-2 gap-x-8 gap-y-6 md:grid-cols-4">
                                        @foreach ($link['children'] as $column)
                                            <div>
                                                <span class="text-theme-xs font-semibold tracking-[0.12em] text-wood-600">{{ sprintf('%02d', $loop->iteration) }}</span>
                                                <a href="{{ $column['url'] }}" @if ($column['target'] === '_blank') target="_blank" rel="noopener" @endif class="mt-1 block text-sm font-medium text-ink hover:underline">{{ $column['label'] }}</a>
                                                <ul class="mt-3 space-y-2">
                                                    @foreach (($column['children'] ?? []) as $sub)
                                                        <li><a href="{{ $sub['url'] }}" @if ($sub['target'] === '_blank') target="_blank" rel="noopener" @endif class="text-sm text-body hover:text-ink">{{ $sub['label'] }}</a></li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @endforeach
                                    </div>
                                    <a href="{{ $link['url'] }}" class="mt-6 inline-flex items-center gap-1 border-t border-line pt-4 text-sm font-medium text-ink hover:underline">{{ $link['label'] }} — everything <span aria-hidden="true">→</span></a>
                                </div>
                            </div>
                        @else
                            {{-- A plain dropdown: the child links in one column. --}}
                            <div class="invisible absolute top-full left-0 z-50 pt-2 opacity-0 transition group-hover:visible group-hover:opacity-100 group-focus-within:visible group-focus-within:opacity-100">
                                <ul class="min-w-56 rounded-xl border border-line bg-paper py-2 shadow-xl shadow-navy-950/10">
                                    @foreach ($link['children'] as $child)
                                        <li>
                                            <a href="{{ $child['url'] }}" @if ($child['target'] === '_blank') target="_blank" rel="noopener" @endif class="block px-4 py-2 text-sm text-body hover:bg-beige/60 hover:text-ink">{{ $child['label'] }}</a>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                @else
                    <a href="{{ $link['url'] }}" @if ($link['target'] === '_blank') target="_blank" rel="noopener" @endif class="inline-block py-2.5 hover:text-ink">{{ $link['label'] }}</a>
                @endif
            @endforeach
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
         class="max-h-[80vh] overflow-y-auto border-t border-line bg-paper px-5 py-3 text-sm lg:hidden">
        <ul class="space-y-1">
            @foreach ($links as $link)
                @if ($hasChildren($link))
                    <li>
                        <details class="group/acc">
                            <summary class="flex cursor-pointer list-none items-center justify-between py-2 text-body marker:hidden [&::-webkit-details-marker]:hidden">
                                <span>{{ $link['label'] }}</span>
                                <svg class="size-4 transition group-open/acc:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                            </summary>
                            <ul class="space-y-1 pb-2 pl-4">
                                @if ($link['is_mega'] ?? false)
                                    @foreach ($link['children'] as $column)
                                        <li>
                                            <a href="{{ $column['url'] }}" @if ($column['target'] === '_blank') target="_blank" rel="noopener" @endif class="py-1.5 font-medium text-ink" @click="open = false">{{ $column['label'] }}</a>
                                            @if (($column['children'] ?? []) !== [])
                                                <ul class="space-y-1 pl-4">
                                                    @foreach ($column['children'] as $sub)
                                                        <li><a href="{{ $sub['url'] }}" @if ($sub['target'] === '_blank') target="_blank" rel="noopener" @endif class="py-1 text-body" @click="open = false">{{ $sub['label'] }}</a></li>
                                                    @endforeach
                                                </ul>
                                            @endif
                                        </li>
                                    @endforeach
                                @else
                                    @foreach ($link['children'] as $child)
                                        <li><a href="{{ $child['url'] }}" @if ($child['target'] === '_blank') target="_blank" rel="noopener" @endif class="block py-1.5 text-body" @click="open = false">{{ $child['label'] }}</a></li>
                                    @endforeach
                                @endif
                            </ul>
                        </details>
                    </li>
                @else
                    <li><a href="{{ $link['url'] }}" @if ($link['target'] === '_blank') target="_blank" rel="noopener" @endif class="block py-2" @click="open = false">{{ $link['label'] }}</a></li>
                @endif
            @endforeach
        </ul>
    </nav>
</header>
