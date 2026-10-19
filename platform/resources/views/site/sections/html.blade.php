{{--    The HTML block (19 Oct, builder parity): the studio's own markup for a section the typed library
    does not cover. It is cleaned when it is saved and once more here — scripts, on-event attributes
    and javascript: URLs are removed both times — so the freedom stops at code that could run.
--}}
@php($html = \App\Domain\Website\Support\CanvasHtml::clean((string) $block->field('code', '')))
@if ($html !== '')
    <div class="wx-html" data-wx-html>
        {!! $html !!}
    </div>
@endif
