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
