@php
    $design = $s['document_design'] ?? null;
    $b = $s['business'];
    $client = $s['client'];
    $bank = $s['bank'];
    $isCreditNote = $s['kind'] === 'credit_note';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Invoice {{ $s['number'] }}</title>
<style>
    @page { margin: 132px 42px 70px 42px; }
    * { font-family: "DejaVu Sans", sans-serif; }
    body { font-size: 9.5px; color: #1a2a45; line-height: 1.45; }
    header { position: fixed; top: -104px; left: 0; right: 0; height: 84px; border-bottom: 2px solid #0f1e36; }
    footer { position: fixed; bottom: -46px; left: 0; right: 0; height: 24px; border-top: 1px solid #c5d1e2; font-size: 8px; color: #6e87ac; }
    .brand { font-size: 20px; letter-spacing: 3px; font-weight: bold; color: #0f1e36; }
    .tagline { font-size: 7.5px; letter-spacing: 1.4px; color: #6e87ac; text-transform: uppercase; margin-top: 3px; }
    .muted { color: #6e87ac; }
    .right { text-align: right; }
    h1 { font-size: 17px; margin: 0 0 2px; color: #0f1e36; letter-spacing: 1px; }
    h2 { font-size: 10.5px; margin: 16px 0 6px; color: #0f1e36; text-transform: uppercase; letter-spacing: 1.2px; }
    table { width: 100%; border-collapse: collapse; }
    .meta td { vertical-align: top; padding: 0; }
    .box { background: #f2f5f9; padding: 9px 11px; }
    .lines th { background: #0f1e36; color: #fff; font-weight: bold; font-size: 8.5px; text-transform: uppercase; letter-spacing: .6px; padding: 6px; text-align: left; }
    .lines th.right { text-align: right; }
    .lines td { padding: 6px; border-bottom: 1px solid #e3e9f1; vertical-align: top; }
    .totals td { padding: 4px 6px; }
    .totals .grand td { background: #0f1e36; color: #fff; font-size: 12px; font-weight: bold; padding: 8px 6px; }
    .words { margin-top: 6px; font-style: italic; }
    .terms li { margin-bottom: 2px; }
    .avoid { page-break-inside: avoid; }
    .stamp { color: #b0453a; font-weight: bold; font-size: 11px; letter-spacing: 2px; text-transform: uppercase; }
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
                <div class="brand">{{ strtoupper($design['brand_name'] ?? $b['name']) }}</div>
                <div class="tagline">{{ ($design['header_text'] ?? '') !== '' ? $design['header_text'] : $b['tagline'] }}</div>
            </td>
            <td class="right muted" style="width: 48%; line-height: 1.55;">
                {{ $b['address'] }}<br>
                {{ $b['phone'] }}@if (! empty($b['whatsapp']) && $b['whatsapp'] !== $b['phone']) · WhatsApp {{ $b['whatsapp'] }}@endif<br>
                {{ $b['email'] }}@if (! empty($b['website'])) · {{ $b['website'] }}@endif
                @if (! empty($b['ntn']))<br>NTN {{ $b['ntn'] }}@endif
            </td>
        </tr>
    </table>
</header>

<footer>
    @if (! empty($design['footer_text']))
        <div class="document-footer">{{ $design['footer_text'] }}</div>
    @else
    {{ $b['legal_name'] }} · {{ $b['address'] }}, {{ $b['city'] }} · {{ $b['phone'] }}
    @endif
</footer>

@if ($s['status'] === 'draft')<div class="stamp">DRAFT — not issued</div>@endif

@if ($isCreditNote)
    <div class="stamp">Credit note</div>
@endif

<h1>{{ $isCreditNote ? 'Credit note' : 'Invoice' }} {{ $s['number'] }}</h1>
<div class="muted">
    @if (! empty($s['project']))Project {{ $s['project']['code'] }} — {{ $s['project']['name'] }}<br>@endif
    @if (! empty($s['quote']))Against quotation {{ $s['quote']['number'] }} ({{ $s['quote']['title'] }}, total {{ $s['quote']['total'] }})@endif
</div>

<table class="meta" style="margin-top: 12px;">
    <tr>
        <td style="width: 48%;">
            <div class="box">
                <strong>Billed to</strong><br>
                {{ $client['name'] }}@if (! empty($client['company']))<br>{{ $client['company'] }}@endif<br>
                @if (! empty($client['address'])){{ $client['address'] }}<br>@endif
                @if (! empty($client['city'])){{ $client['city'] }}<br>@endif
                {{ $client['phone'] }}@if (! empty($client['email']))<br>{{ $client['email'] }}@endif
            </div>
        </td>
        <td style="width: 4%;"></td>
        <td style="width: 48%;">
            <table class="meta">
                <tr><td class="muted" style="width: 40%;">Invoice date</td><td>{{ $s['issue_date'] ?? '—' }}</td></tr>
                <tr><td class="muted">Due date</td><td>{{ $s['due_date'] ?? '—' }}</td></tr>
                <tr><td class="muted">Amount due</td><td><strong>{{ $s['balance'] }}</strong></td></tr>
            </table>
        </td>
    </tr>
</table>

<h2>Particulars</h2>
<table class="lines">
    <thead>
        <tr>
            <th style="width: 60%;">Description</th>
            <th class="right" style="width: 12%;">Qty</th>
            <th class="right" style="width: 14%;">Rate</th>
            <th class="right" style="width: 14%;">Amount</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($s['items'] as $item)
            <tr>
                <td>{{ $item['description'] }}</td>
                <td class="right">{{ rtrim(rtrim(number_format((float) $item['qty'], 3, '.', ','), '0'), '.') }}{{ $item['unit'] ? ' '.$item['unit'] : '' }}</td>
                <td class="right">{{ $item['rate'] }}</td>
                <td class="right">{{ $item['line_total'] }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<table class="totals" style="margin-top: 10px; width: 46%; margin-left: 54%;">
    <tr><td class="muted">Subtotal</td><td class="right">{{ $s['subtotal'] }}</td></tr>
    @if ((int) filter_var($s['discount'], FILTER_SANITIZE_NUMBER_INT) !== 0)
        <tr><td class="muted">Discount</td><td class="right">{{ $s['discount'] }}</td></tr>
    @endif
    <tr><td class="muted">Tax @if (! empty($s['tax_bp']))({{ $s['tax_bp'] / 100 }}%)@endif</td><td class="right">{{ $s['tax'] }}</td></tr>
    <tr><td class="muted">Paid</td><td class="right">{{ $s['paid'] }}</td></tr>
    <tr class="grand"><td>Balance due</td><td class="right">{{ $s['balance'] }}</td></tr>
</table>
<div class="words">{{ $s['total_in_words'] }}</div>

@if ($bank)
    <div class="avoid">
        <h2>Pay to</h2>
        <table class="box">
            <tr>
                <td style="width: 34%;"><span class="muted">Title</span><br><strong>{{ $bank['title'] }}</strong></td>
                <td style="width: 33%;"><span class="muted">Bank</span><br>{{ $bank['bank_name'] }}@if (! empty($bank['branch']))<br><span class="muted">{{ $bank['branch'] }}</span>@endif</td>
                <td style="width: 33%;">
                    <span class="muted">{{ ! empty($bank['iban']) ? 'IBAN' : 'Account no.' }}</span><br>
                    <strong>{{ $bank['iban'] ?: $bank['account_number'] }}</strong>
                    @if (! empty($bank['wallet_provider']))<br><span class="muted">{{ $bank['wallet_provider'] }}</span>@endif
                </td>
            </tr>
        </table>
    </div>
@endif

@if (! empty($s['notes']))
    <div class="avoid">
        <h2>Notes</h2>
        {{ $s['notes'] }}
    </div>
@endif

@if (! empty($s['terms']))
    <div class="avoid">
        <h2>Terms</h2>
        {{ $s['terms'] }}
    </div>
@endif
</body>
</html>
