<table class="sign avoid">
    <tr>
        @foreach (array_slice($s['signatories'], 0, 2) as $signatory)
            <td><div class="line"><strong>{{ $signatory['name'] }}</strong><br><span class="muted">{{ $signatory['title'] }}, {{ $company['brand'] }}</span></div></td>
        @endforeach
    </tr>
</table>
