@php
    $design = $s['document_design'] ?? null;
    use App\Support\Money;
    $lakh = app(\App\Settings\SalesSettings::class)->lakh_grouping;
    $money = fn (int $minor) => Money::ofMinor($minor)->format($lakh);
    $plain = fn (int $minor) => Money::ofMinor($minor)->format($lakh, false, '');
    $qty = fn (string $q) => rtrim(rtrim(number_format((float) $q, 3, '.', ','), '0'), '.');
    $date = fn (?string $d) => $d ? \Carbon\CarbonImmutable::parse($d)->format('j M Y') : '—';
    $t = $s['totals'];
    $client = $s['client'];
    $company = $s['company'];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Quotation {{ $s['label'] }}</title>
<style>
    @page { margin: 132px 42px 70px 42px; }
    * { font-family: "DejaVu Sans", sans-serif; }
    body { font-size: 9.5px; color: #1a2a45; line-height: 1.45; }
    header { position: fixed; top: -104px; left: 0; right: 0; height: 84px; border-bottom: 2px solid #0f1e36; }
    footer { position: fixed; bottom: -46px; left: 0; right: 0; height: 24px; border-top: 1px solid #c5d1e2; font-size: 8px; color: #46586f; }
    /* "Page X of Y" is drawn by QuotePdf after rendering: dompdf has no total page counter in CSS (it printed "of 0"). */
    .brand { font-size: 20px; letter-spacing: 3px; font-weight: bold; color: #0f1e36; }
    .tagline { font-size: 7.5px; letter-spacing: 1.4px; color: #6e87ac; text-transform: uppercase; margin-top: 3px; }
    .muted { color: #6e87ac; }
    .right { text-align: right; }
    .center { text-align: center; }
    h1 { font-size: 17px; margin: 0 0 2px; color: #0f1e36; letter-spacing: 1px; }
    h2 { font-size: 10.5px; margin: 16px 0 6px; color: #0f1e36; text-transform: uppercase; letter-spacing: 1.2px; }
    table { width: 100%; border-collapse: collapse; }
    .meta td { vertical-align: top; padding: 0; }
    /* Classic BOQ furniture (Slice R): title band, cream boxes, gold section heads, words box + total pill. */
    .doctitle { font-size: 21px; letter-spacing: 4px; font-weight: bold; color: #0f1e36; text-align: right; }
    .doctitle .gold { color: #c39a4a; }
    .docmeta td { padding: 1px 0 1px 10px; vertical-align: top; }
    .goldrule { border-top: 1px solid #c39a4a; margin: 10px 0 12px; }
    .box { background: #faf7f1; border: 1px solid #e8e0d0; padding: 9px 11px; }
    .boxlabel { font-size: 7.5px; letter-spacing: 1.6px; color: #a07d3c; text-transform: uppercase; margin-bottom: 3px; }
    .secthead { margin: 14px 0 5px; font-size: 10px; font-weight: bold; letter-spacing: 1.4px; color: #a07d3c; text-transform: uppercase; page-break-after: avoid; }
    .secthead .note { font-weight: normal; letter-spacing: normal; text-transform: none; color: #6e87ac; }
    .wordsbox { border: 1px dashed #c9b18a; padding: 8px 10px; }
    .pill td { background: #0f1e36; color: #fff; padding: 9px 12px; }
    .pill .pill-label { font-size: 10px; letter-spacing: 1px; text-transform: uppercase; }
    .pill .pill-amount { font-size: 13px; font-weight: bold; color: #e9c46a; text-align: right; }
    .paylabel { color: #6e87ac; width: 34%; padding: 1px 6px 1px 0; vertical-align: top; }
    .lines th { background: #0f1e36; color: #fff; font-weight: bold; font-size: 8.5px; text-transform: uppercase; letter-spacing: .6px; padding: 6px 6px; text-align: left; }
    .lines th.right { text-align: right; }
    .lines td { padding: 5px 6px; border-bottom: 1px solid #e3e9f1; vertical-align: top; }
    .lines .section td { background: #fcf2e8; font-weight: bold; color: #0f1e36; border-bottom: 1px solid #e7d6c2; }
    .lines .subtotal td { font-weight: bold; border-bottom: 1px solid #c5d1e2; }
    .optional td { color: #6e87ac; font-style: italic; }
    .totals td { padding: 4px 6px; }
    .totals .grand td { background: #0f1e36; color: #fff; font-size: 12px; font-weight: bold; padding: 8px 6px; }
    .words { margin-top: 6px; font-style: italic; }
    .scope p { margin: 0 0 4px; } .scope ul { margin: 0 0 4px 14px; padding: 0; }
    .terms li { margin-bottom: 2px; }
    .sign td { padding-top: 34px; vertical-align: bottom; }
    .sign .line { border-top: 1px solid #1a2a45; width: 82%; padding-top: 4px; }
    .avoid { page-break-inside: avoid; }
    .watermark { position: fixed; top: 34%; left: 6%; width: 88%; text-align: center; font-size: 110px; color: #e3e9f1; transform: rotate(-30deg); z-index: -1; letter-spacing: 12px; }
</style>
@include('pdf.partials.document-style')
</head>
<body>
<header>
    <table class="meta">
        <tr>
            <td style="width: 52%;">
                @if (! empty($design['logo_data_uri']) && preg_match('/^data:image\/(png|jpeg);base64,[A-Za-z0-9+\/=]+$/', $design['logo_data_uri']))
                    <img src="{{ $design['logo_data_uri'] }}" class="document-logo" alt="Brand logo">
                @endif
                <div class="brand">{{ strtoupper($design['brand_name'] ?? $company['brand']) }}</div>
                <div class="tagline">{{ ($design['header_text'] ?? '') !== '' ? $design['header_text'] : $company['tagline'] }}</div>
            </td>
            <td class="right" style="width: 48%; line-height: 1.55;">
                <strong>Quotation {{ $s['label'] }}</strong><br>
                <span class="muted">{{ $date($s['issued_on']) }} · Valid until {{ $date($s['valid_until']) }}</span>
            </td>
        </tr>
    </table>
</header>

<footer>
    @if (! empty($design['footer_text']))
        <div class="document-footer">{{ $design['footer_text'] }}</div>
    @else
    <table><tr>
        <td style="padding-top: 6px; width: 68%;">{{ $company['phone'] }}&nbsp;&nbsp;|&nbsp;&nbsp;{{ $company['email'] }}&nbsp;&nbsp;|&nbsp;&nbsp;{{ $company['address'] }}</td>
        <td class="right" style="padding-top: 6px; padding-right: 88px; width: 32%;">Thank you for your business</td>
    </tr></table>
    @endif
</footer>

@if ($draft)
    <div class="watermark">DRAFT</div>
@endif

<table class="meta">
    <tr>
        <td style="width: 44%;"></td>
        <td style="width: 56%;">
            <div class="doctitle">QUOTATION<span class="gold">.</span></div>
            <table class="docmeta" style="margin-top: 6px;">
                <tr><td class="muted right">Quotation no.</td><td class="right" style="width: 34%;"><strong>{{ $s['label'] }}</strong></td></tr>
                <tr><td class="muted right">Date</td><td class="right">{{ $date($s['issued_on']) }}</td></tr>
                <tr><td class="muted right">Valid until</td><td class="right">{{ $date($s['valid_until']) }}</td></tr>
            </table>
            <div class="muted right" style="margin-top: 6px; line-height: 1.55;">
                {{ $company['address'] }}<br>
                {{ $company['phone'] }} · {{ $company['email'] }}@if ($company['ntn'])<br>NTN {{ $company['ntn'] }}@endif
            </div>
        </td>
    </tr>
</table>

<div class="goldrule"></div>

<table class="meta">
    <tr>
        <td style="width: 49%;">
            <div class="box">
                <div class="boxlabel">Prepared for</div>
                <div style="font-size: 11px; font-weight: bold;">{{ $client['name'] }}</div>
                @if ($client['company'])<div>{{ $client['company'] }}</div>@endif
                <div class="muted">{{ implode(' · ', array_filter([$client['phone'], $client['email'], $client['address']])) }}</div>
            </div>
        </td>
        <td style="width: 2%;"></td>
        <td style="width: 49%;">
            <div class="box">
                <div class="boxlabel">Project details</div>
                <div><span class="muted">Project</span>&nbsp;&nbsp;{{ ($s['title'] ?? '') !== '' ? $s['title'] : '—' }}</div>
                @if ($s['prepared_by'])<div class="muted" style="margin-top: 2px;">Prepared by {{ $s['prepared_by'] }}</div>@endif
            </div>
        </td>
    </tr>
</table>

@foreach (\App\Domain\Sales\Services\DocumentStyles::visibleBlocks('quote', $design) as $block)
    @include('pdf.partials.quote-blocks.'.$block)
@endforeach
</body>
</html>
