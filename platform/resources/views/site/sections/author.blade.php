{{-- Author byline: E-E-A-T (docs/09 §11.4). No invented people: if the owner names nobody, the studio is the author. --}}
@php $image = isset($block->data['image_id']) ? ($media[$block->data['image_id']] ?? null) : null; @endphp
<div class="mx-auto flex max-w-3xl items-center gap-4 px-5 py-8 md:px-8">
    @if ($image)
        <img src="{{ $image->url() }}" width="96" height="96" loading="lazy" alt="{{ $image->alt }}" class="size-12 shrink-0 rounded-full object-cover">
    @endif
    <div>
        <p class="text-sm font-medium text-ink">{{ $block->field('name') }}</p>
        @if ($block->field('role'))<p class="text-xs text-muted">{{ $block->field('role') }}</p>@endif
        @if ($block->field('bio'))<p class="mt-1 text-sm text-body">{{ $block->field('bio') }}</p>@endif
    </div>
</div>
