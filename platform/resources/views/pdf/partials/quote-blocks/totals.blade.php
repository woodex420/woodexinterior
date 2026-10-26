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
