@if (($s['furniture_revision'] ?? 1) >= 2)
    @include('pdf.furniture-document-v2')
@else
    @include('pdf.furniture-document-v1')
@endif
