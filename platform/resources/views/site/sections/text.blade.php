{{-- A plain text section: rich text is stored as paragraphs separated by blank lines. --}}
<section class="mx-auto max-w-3xl px-5 py-12 md:px-8">
    @if ($block->field('heading'))<h2 class="text-[clamp(24px,3vw,36px)]">{{ $block->field('heading') }}</h2>@endif
    @if ($block->field('body'))<div class="mt-5 space-y-4 text-body">{!! nl2br(e($block->field('body'))) !!}</div>@endif
</section>
