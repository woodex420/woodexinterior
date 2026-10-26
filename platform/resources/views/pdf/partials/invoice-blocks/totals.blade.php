<div class="avoid" style="margin-top: 10px;">
    <table>
        <tr>
            <td style="width: 56%;"></td>
            <td style="width: 44%;">
                <table class="totals">
                    <tr><td>Subtotal</td><td class="right">{{ $s['subtotal'] }}</td></tr>
                    @if ((int) filter_var($s['discount'], FILTER_SANITIZE_NUMBER_INT) !== 0)
                        <tr><td>Discount</td><td class="right">{{ $s['discount'] }}</td></tr>
                    @endif
                    <tr><td>Tax @if (! empty($s['tax_bp']))({{ $s['tax_bp'] / 100 }}%)@endif</td><td class="right">{{ $s['tax'] }}</td></tr>
                    <tr><td>Paid</td><td class="right">{{ $s['paid'] }}</td></tr>
                </table>
            </td>
        </tr>
    </table>
    <table style="margin-top: 6px;">
        <tr>
            <td style="width: 56%; padding-right: 10px; vertical-align: top;">
                <div class="wordsbox">
                    <div class="boxlabel">Amount in words</div>
                    {{ $s['total_in_words'] }}
                </div>
            </td>
            <td style="width: 44%; vertical-align: top;">
                <table class="pill">
                    <tr>
                        <td class="pill-label">Balance due</td>
                        <td class="pill-amount">{{ $s['balance'] }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</div>
