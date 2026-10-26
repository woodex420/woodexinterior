@php
    $design = $s['document_design'] ?? null;
    $accent = is_array($design) && preg_match('/^#[0-9a-fA-F]{6}$/', $design['accent_color'] ?? '') ? $design['accent_color'] : null;
    $theme = \App\Domain\Sales\Services\DocumentStyles::theme(is_array($design) ? ($design['pdf_theme'] ?? null) : null);
    $themeAccent = $accent ?: '#0f1e36';
@endphp
@if ($accent)
<style>
    .brand { font-size: 14px; letter-spacing: .4px; line-height: 1.05; word-wrap: break-word; }
    .tagline { font-size: 7px; line-height: 1.2; word-wrap: break-word; }
    header { border-bottom-color: {{ $accent }}; }
    .brand, h1, h2 { color: {{ $accent }}; }
    .lines th, .totals .grand td, .pill td { background: {{ $accent }}; }
    .document-logo { max-width: 150px; max-height: 22px; margin-bottom: 3px; }
    .document-footer { font-size: 7px; }
</style>
@endif
@if ($theme === 'minimal')
<style>
    /* theme: minimal */
    .brand, h1, h2 { color: #111827; }
    header { border-bottom: 1px solid #111827; }
    .lines th { background: #f3f4f6; color: #111827; }
    .lines .section td { background: transparent; color: #111827; border-bottom: 1px solid #9ca3af; }
    .totals .grand td { background: transparent; color: #111827; border-top: 2px solid #111827; }
    .box { background: transparent; border: 1px solid #d1d5db; }
    .muted { color: #6b7280; }
    .doctitle, .doctitle .gold, .secthead { color: #111827; }
    .boxlabel { color: #6b7280; }
    .goldrule { border-top-color: #111827; }
    .wordsbox { border-color: #9ca3af; }
    .pill td { background: transparent; color: #111827; border-top: 2px solid #111827; }
    .pill .pill-amount { color: #111827; }
</style>
@elseif ($theme === 'premium')
<style>
    /* theme: premium */
    header { border-bottom: 4px solid {{ $themeAccent }}; }
    h2 { background: {{ $themeAccent }}; color: #fff; padding: 4px 7px; }
    .lines .section td { background: {{ $themeAccent }}; color: #fff; border-bottom: 1px solid {{ $themeAccent }}; }
    .box { border-left: 3px solid {{ $themeAccent }}; }
    .totals .grand td { background: {{ $themeAccent }}; }
    .doctitle { color: {{ $themeAccent }}; }
    .secthead { color: {{ $themeAccent }}; }
    .pill td { background: {{ $themeAccent }}; }
</style>
@endif
