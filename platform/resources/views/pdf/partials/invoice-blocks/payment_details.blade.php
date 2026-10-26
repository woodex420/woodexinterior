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
