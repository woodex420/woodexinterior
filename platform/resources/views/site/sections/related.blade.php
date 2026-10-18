{{-- Internal links chosen in the editor (docs/09 §11.1: internal linking is planned, not accidental). --}}
@php
    $ids = array_values(array_filter((array) $block->field('page_ids', [])));
    $links = \App\Domain\Website\Models\Page::query()->published()->whereIn('id', $ids)->orderBy('sort')->get();
@endphp
@if ($links->isNotEmpty())
    <section class="mx-auto max-w-6xl px-5 py-14 md:px-8">
        <h2 class="text-xs tracking-[0.16em] text-muted uppercase">{{ $block->field('heading', 'Related') }}</h2>
        <ul class="mt-6 grid gap-4 sm:grid-cols-3">
            @foreach ($links as $link)
                <li>
                    <a href="{{ $link->path() }}" class="block rounded-2xl bg-soft p-5 ring-1 ring-line transition hover:-translate-y-0.5 hover:ring-wood-300">
                        <span class="text-base font-medium text-ink">{{ $link->title }}</span>
                        @if ($link->excerpt)<span class="mt-1 block text-sm text-body">{{ \Illuminate\Support\Str::limit($link->excerpt, 110) }}</span>@endif
                    </a>
                </li>
            @endforeach
        </ul>
    </section>
@endif
