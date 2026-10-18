@php
    $design = $s['document_design'] ?? null;
    $accent = is_array($design) && preg_match('/^#[0-9a-fA-F]{6}$/', $design['accent_color'] ?? '') ? $design['accent_color'] : null;
@endphp
@if ($accent)
<style>
    .brand { font-size: 14px; letter-spacing: .4px; line-height: 1.05; word-wrap: break-word; }
    .tagline { font-size: 7px; line-height: 1.2; word-wrap: break-word; }
    header { border-bottom-color: {{ $accent }}; }
    .brand, h1, h2 { color: {{ $accent }}; }
    .lines th, .totals .grand td { background: {{ $accent }}; }
    .document-logo { max-width: 150px; max-height: 22px; margin-bottom: 3px; }
    .document-footer { font-size: 7px; }
</style>
@endif
