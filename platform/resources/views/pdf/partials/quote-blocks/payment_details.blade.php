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
