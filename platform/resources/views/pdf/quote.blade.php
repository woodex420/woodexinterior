@if (($s['quotation_renderer'] ?? null) === 'v21' && ($s['quote_layout'] ?? '') === 'furniture')
    @include('pdf.furniture-document')
@elseif (($s['quotation_renderer'] ?? null) === 'v21')
    @include('pdf.quote-v21')
@else
    @include('pdf.quote-legacy')
@endif
