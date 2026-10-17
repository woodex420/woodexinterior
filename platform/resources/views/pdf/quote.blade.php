@php
    $design = $s['document_design'] ?? null;
    use App\Support\Money;
    $lakh = app(\App\Settings\SalesSettings::class)->lakh_grouping;
    $money = fn (int $minor) => Money::ofMinor($minor)->format($lakh);
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
    footer { position: fixed; bottom: -46px; left: 0; right: 0; height: 24px; border-top: 1px solid #c5d1e2; font-size: 8px; color: #6e87ac; }
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
    .box { background: #f2f5f9; padding: 9px 11px; }
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
    .sign td { width: 50%; padding-top: 34px; vertical-align: bottom; }
    .sign .line { border-top: 1px solid #1a2a45; width: 78%; padding-top: 4px; }
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
            <td class="right muted" style="width: 48%; line-height: 1.55;">
                {{ $company['address'] }}<br>
                {{ $company['phone'] }}@if ($company['whatsapp'] && $company['whatsapp'] !== $company['phone']) · WhatsApp {{ $company['whatsapp'] }}@endif<br>
                {{ $company['email'] }} · {{ $company['website'] }}
                @if ($company['ntn'])<br>NTN {{ $company['ntn'] }}@endif
            </td>
        </tr>
    </table>
</header>

<footer>
    @if (! empty($design['footer_text']))
        <div class="document-footer">{{ $design['footer_text'] }}</div>
    @else
    <table><tr>
        <td style="padding-top: 6px;">{{ $company['legal_name'] }} · Quotation {{ $s['label'] }} · Valid until {{ $date($s['valid_until']) }}</td>
    </tr></table>
    @endif
</footer>

@if ($draft)
    <div class="watermark">DRAFT</div>
@endif

<table class="meta">
    <tr>
        <td style="width: 52%;">
            <h1>QUOTATION</h1>
            <div style="font-size: 12px; font-weight: bold;">{{ $s['label'] }}</div>
            <div style="margin-top: 4px;">{{ $s['title'] }}</div>
        </td>
        <td style="width: 48%;">
            <table>
                <tr><td class="muted">Date</td><td class="right">{{ $date($s['issued_on']) }}</td></tr>
                <tr><td class="muted">Valid until</td><td class="right">{{ $date($s['valid_until']) }}</td></tr>
                @if ($s['prepared_by'])<tr><td class="muted">Prepared by</td><td class="right">{{ $s['prepared_by'] }}</td></tr>@endif
            </table>
        </td>
    </tr>
</table>

<div class="box" style="margin-top: 12px;">
    <div class="muted" style="font-size: 8px; text-transform: uppercase; letter-spacing: 1px;">Prepared for</div>
    <div style="font-size: 11px; font-weight: bold;">{{ $client['name'] }}</div>
    @if ($client['company'])<div>{{ $client['company'] }}</div>@endif
    <div class="muted">{{ implode(' · ', array_filter([$client['phone'], $client['email'], $client['address']])) }}</div>
</div>

@if ($s['scope_html'])
    <h2>Scope of work</h2>
    <div class="scope">{!! $s['scope_html'] !!}</div>
@endif

<h2>Price</h2>
<table class="lines">
    <thead>
        <tr>
            <th style="width: 4%;">#</th>
            <th style="width: 46%;">Description</th>
            <th class="right" style="width: 10%;">Qty</th>
            <th style="width: 8%;">Unit</th>
            <th class="right" style="width: 14%;">Rate</th>
            <th class="right" style="width: 18%;">Amount</th>
        </tr>
    </thead>
    <tbody>
        @php $n = 0; @endphp
        @foreach ($s['sections'] as $section)
            <tr class="section"><td colspan="6">{{ $section['name'] }}@if ($section['note']) <span style="font-weight: normal; color: #6e87ac;">· {{ $section['note'] }}</span>@endif</td></tr>
            @foreach ($section['items'] as $item)
                @php $n++; @endphp
                <tr @class(['optional' => $item['is_optional']])>
                    <td>{{ $n }}</td>
                    <td>{!! nl2br(e($item['description'])) !!}@if ($item['is_optional']) (optional, not in total)@endif
                        @if ($item['discount_minor'] > 0)<br><span class="muted">Discount {{ $money($item['discount_minor']) }}</span>@endif</td>
                    <td class="right">{{ $qty($item['qty']) }}</td>
                    <td>{{ $item['unit_label'] }}</td>
                    <td class="right">{{ $money($item['rate_minor']) }}</td>
                    <td class="right">{{ $money($item['line_total_minor']) }}</td>
                </tr>
            @endforeach
            @if (count($s['sections']) > 1)
                <tr class="subtotal"><td></td><td colspan="4">Subtotal · {{ $section['name'] }}</td><td class="right">{{ $money($section['subtotal_minor']) }}</td></tr>
            @endif
        @endforeach
    </tbody>
</table>

<table class="avoid" style="margin-top: 8px;">
    <tr>
        <td style="width: 50%;"></td>
        <td style="width: 50%;">
            <table class="totals">
                <tr><td>Subtotal</td><td class="right">{{ $money($t['subtotal_minor']) }}</td></tr>
                @if ($t['discount_minor'] > 0)<tr><td>{{ $t['discount_label'] }}</td><td class="right">− {{ $money($t['discount_minor']) }}</td></tr>@endif
                @if ($t['other_charges_minor'] > 0)<tr><td>{{ $t['other_charges_label'] }}</td><td class="right">{{ $money($t['other_charges_minor']) }}</td></tr>@endif
                @if ($t['tax_minor'] > 0)<tr><td>Tax ({{ rtrim(rtrim(number_format($t['tax_bp'] / 100, 2), '0'), '.') }}%)</td><td class="right">{{ $money($t['tax_minor']) }}</td></tr>@endif
                @if ($t['rounding_minor'] !== 0)<tr><td>Rounding</td><td class="right">{{ $money($t['rounding_minor']) }}</td></tr>@endif
                <tr class="grand"><td>Total</td><td class="right">{{ $money($t['total_minor']) }}</td></tr>
            </table>
        </td>
    </tr>
</table>
<div class="words">{{ $t['total_words'] }}</div>

@if ($s['milestones'] !== [])
    <div class="avoid">
        <h2>Payment schedule</h2>
        <table class="lines">
            <thead><tr><th style="width: 60%;">Milestone</th><th class="right" style="width: 15%;">Share</th><th class="right" style="width: 25%;">Amount</th></tr></thead>
            <tbody>
                @foreach ($s['milestones'] as $milestone)
                    <tr><td>{{ $milestone['name'] }}</td><td class="right">{{ rtrim(rtrim(number_format($milestone['percent_bp'] / 100, 2), '0'), '.') }}%</td><td class="right">{{ $money($milestone['amount_minor']) }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

@if ($s['terms'] !== [])
    <div class="avoid">
        <h2>Terms and conditions</h2>
        <ol class="terms" style="margin: 0 0 0 14px; padding: 0;">
            @foreach ($s['terms'] as $term)<li>{{ $term }}</li>@endforeach
        </ol>
    </div>
@endif

@if ($s['notes'] !== '')
    <h2>Notes</h2>
    <div>{!! nl2br(e($s['notes'])) !!}</div>
@endif

@if ($s['bank_accounts'] !== [])
    <div class="avoid">
        <h2>Payment details</h2>
        <table>
            @foreach ($s['bank_accounts'] as $account)
                <tr><td class="box" style="border-bottom: 3px solid #fff;">
                    <strong>{{ $account['title'] }}</strong> · {{ $account['bank_name'] }}@if ($account['branch']) · {{ $account['branch'] }}@endif<br>
                    @if ($account['account_number'])Account {{ $account['account_number'] }}@endif @if ($account['iban']) · IBAN {{ $account['iban'] }}@endif
                </td></tr>
            @endforeach
        </table>
    </div>
@endif

<table class="sign avoid">
    <tr>
        @foreach (array_slice($s['signatories'], 0, 2) as $signatory)
            <td><div class="line"><strong>{{ $signatory['name'] }}</strong><br><span class="muted">{{ $signatory['title'] }}, {{ $company['brand'] }}</span></div></td>
        @endforeach
    </tr>
</table>
</body>
</html>
