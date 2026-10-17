<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Models\AccountingPeriod;
use App\Domain\Accounting\Models\TaxCode;
use App\Domain\Accounting\Services\ChartOfAccounts;
use Illuminate\Database\Seeder;

/**
 * The chart of accounts, the two 18% tax codes (output and input) and the current month's period (docs/17 §4.2).
 * Idempotent: existing accounts keep the accountant's edits, existing periods keep their lock.
 */
class ChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        app(ChartOfAccounts::class)->ensure();

        $taxPayable = Account::query()->where('code', ChartOfAccounts::TAX_PAYABLE)->first();
        $inputTax = Account::query()->where('code', ChartOfAccounts::INPUT_TAX)->first();

        $codes = [
            ['code' => 'GST18', 'name' => 'Sales tax — 18%', 'rate_bp' => 1800, 'kind' => 'output', 'account_id' => $taxPayable?->id],
            ['code' => 'ITX18', 'name' => 'Input tax — 18%', 'rate_bp' => 1800, 'kind' => 'input', 'account_id' => $inputTax?->id],
        ];

        foreach ($codes as $code) {
            TaxCode::query()->firstOrCreate(['code' => $code['code']], $code + ['is_active' => true]);
        }

        AccountingPeriod::forDate(now());
    }
}
