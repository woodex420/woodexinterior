{{-- The article an editor wrote in the v2.1 editor (Website → Blog / Portfolio).

     The whole article travels as one `text` block whose `studio` key is the editor's own JSON — the same
     JSON the editor reopens — so what is written and what is shown cannot drift apart. Pages whose text
     block has no `studio` key (every page imported from v1) render through the plain path below, byte for
     byte as before. --}}
@php
    $article = $block->field('studio');
    $heroId = (int) ($block->field('hero_id') ?? 0);
    $hero = $heroId > 0 ? \App\Domain\Website\Models\Media::query()->find($heroId) : null;
    $meta = array_values(array_filter((array) ($article['meta'] ?? []), fn ($row) => is_array($row) && ($row['k'] ?? '') !== ''));
@endphp
<article class="mx-auto max-w-3xl px-5 py-12 md:px-8">
    @if (! empty($article['kicker']))
        <p class="text-xs tracking-[0.16em] text-muted uppercase">{{ $article['kicker'] }}</p>
    @endif
    <h1 class="mt-4 max-w-3xl text-[clamp(32px,5vw,58px)]">{{ $page->title }}</h1>
    @if (! empty($article['dek']))
        <p class="mt-5 text-lg text-body">{{ $article['dek'] }}</p>
    @endif

    @if ($meta !== [])
        <dl class="mt-8 flex flex-wrap gap-x-10 gap-y-4 border-y border-line py-5 text-sm">
            @foreach ($meta as $row)
                <div><dt class="text-muted">{{ $row['k'] }}</dt><dd class="mt-1 font-medium">{{ $row['v'] ?? '' }}</dd></div>
            @endforeach
        </dl>
    @endif

    @if ($hero)
        <img src="{{ $hero->url() }}" @if ($hero->width) width="{{ $hero->width }}" @endif @if ($hero->height) height="{{ $hero->height }}" @endif
             alt="{{ $hero->alt ?: $page->title }}" class="mt-8 w-full rounded-2xl" loading="eager" decoding="async">
    @endif

    <div class="mt-10 space-y-6 text-body">
        @foreach ((array) ($article['blocks'] ?? []) as $row)
            @php $kind = $row['t'] ?? 'p'; @endphp
            @if ($kind === 'h' && trim((string) ($row['text'] ?? '')) !== '')
                <h2 class="pt-4 text-[clamp(22px,2.6vw,30px)]">{{ $row['text'] }}</h2>
            @elseif ($kind === 'p' && trim((string) ($row['text'] ?? '')) !== '')
                <p>{{ $row['text'] }}</p>
            @elseif ($kind === 'list')
                <ul class="list-disc space-y-2 pl-6">
                    @foreach (array_filter((array) ($row['items'] ?? [])) as $item)
                        <li>{{ $item }}</li>
                    @endforeach
                </ul>
            @elseif ($kind === 'quote' && trim((string) ($row['text'] ?? '')) !== '')
                <blockquote class="border-l-2 border-wood-400 pl-5 text-lg italic">{{ $row['text'] }}</blockquote>
            @elseif ($kind === 'img' && ! empty($row['id']))
                @php $image = \App\Domain\Website\Models\Media::query()->find((int) $row['id']); @endphp
                @if ($image)
                    <figure>
                        <img src="{{ $image->url() }}" alt="{{ $row['alt'] ?? $image->alt }}" class="w-full rounded-2xl" loading="lazy" decoding="async">
                        @if (! empty($row['alt']))<figcaption class="mt-2 text-center text-sm text-muted">{{ $row['alt'] }}</figcaption>@endif
                    </figure>
                @endif
            @elseif ($kind === 'table')
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead><tr>@foreach ((array) ($row['head'] ?? []) as $cell)<th class="border-b border-line py-2 pr-4 font-medium">{{ $cell }}</th>@endforeach</tr></thead>
                        <tbody>@foreach ((array) ($row['rows'] ?? []) as $line)<tr>@foreach ((array) $line as $cell)<td class="border-b border-line py-2 pr-4">{{ $cell }}</td>@endforeach</tr>@endforeach</tbody>
                    </table>
                </div>
            @endif
        @endforeach
    </div>

    @if (! empty($article['quote']))
        <p class="mt-10 rounded-2xl bg-soft p-6 text-lg">{{ $article['quote'] }}</p>
    @endif
</article>
