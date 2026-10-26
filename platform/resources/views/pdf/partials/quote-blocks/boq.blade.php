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
                    <td>@if (($item['image'] ?? null) !== null)<img src="{{ $item['image'] }}" alt="" style="float:left;width:52px;height:52px;object-fit:cover;margin:0 8px 2px 0;border-radius:4px;">@endif{!! nl2br(e($item['description'])) !!}@if ($item['is_optional']) (optional, not in total)@endif
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
