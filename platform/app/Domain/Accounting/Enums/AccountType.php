<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Enums;

use App\Support\Concerns\EnumHelpers;

/**
 * The five account types. `normal()` decides which side increases the balance, and therefore how a trial balance
 * reads: assets and expenses are debit-natured, the rest credit-natured.
 */
enum AccountType: string
{
    use EnumHelpers;

    case Asset = 'asset';
    case Liability = 'liability';
    case Equity = 'equity';
    case Income = 'income';
    case Expense = 'expense';

    public function title(): string
    {
        return match ($this) {
            self::Asset => 'Assets',
            self::Liability => 'Liabilities',
            self::Equity => 'Equity',
            self::Income => 'Income',
            self::Expense => 'Expenses',
        };
    }

    public function normal(): string
    {
        return match ($this) {
            self::Asset, self::Expense => 'debit',
            self::Liability, self::Equity, self::Income => 'credit',
        };
    }

    /** Signed balance in paisa: positive when the account is in its normal position. */
    public function signed(int $debitMinor, int $creditMinor): int
    {
        return $this->normal() === 'debit' ? $debitMinor - $creditMinor : $creditMinor - $debitMinor;
    }
}
