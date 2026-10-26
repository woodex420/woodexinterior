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
