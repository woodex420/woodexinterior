<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Enums;

use App\Support\Concerns\EnumHelpers;

/** Subtypes the posting rules look up by name (AR, tax, revenue per business line). */
enum AccountSubtype: string
{
    use EnumHelpers;

    case Cash = 'cash';
    case Bank = 'bank';
    case Receivable = 'receivable';
    case Payable = 'payable';
    case Tax = 'tax';
    case Revenue = 'revenue';
    case Cogs = 'cogs';
    case Expense = 'expense';
    case Equity = 'equity';
    case FixedAsset = 'fixed_asset';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::Bank => 'Bank',
            self::Receivable => 'Accounts receivable',
            self::Payable => 'Accounts payable',
            self::Tax => 'Tax',
            self::Revenue => 'Revenue',
            self::Cogs => 'Cost of sales',
            self::Expense => 'Operating expense',
            self::Equity => 'Equity',
            self::FixedAsset => 'Fixed asset',
            self::Other => 'Other',
        };
    }
}
