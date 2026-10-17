<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Services;

use App\Domain\Accounting\Enums\AccountSubtype;
use App\Domain\Accounting\Enums\AccountType;
use App\Domain\Accounting\Models\Account;

/**
 * The default chart for a Pakistani interior/furniture studio (docs/17 §4.2). Codes are the contract: the posting
 * rules refer to them (1100 AR, 2100 tax, 4000/4010 revenue). Seeding is idempotent and never renames an account the
 * accountant has already edited.
 */
final class ChartOfAccounts
{
    public const CASH = '1010';

    public const BANK = '1020';

    public const RECEIVABLE = '1100';

    public const INPUT_TAX = '1150';

    public const TAX_PAYABLE = '2100';

    public const CLIENT_ADVANCES = '2200';

    public const EQUITY = '3000';

    public const OPENING_EQUITY = '3100';

    public const REVENUE_INTERIOR = '4000';

    public const REVENUE_FURNITURE = '4010';

    public const REVENUE_DESIGN = '4020';

    public const ROUNDING = '4900';

    public const COGS_MATERIALS = '5000';

    public const SUBCONTRACTORS = '5010';

    public const OPEX = '6000';

    /**
     * @return list<array{code: string, name: string, type: AccountType, subtype: AccountSubtype, system: bool}>
     */
    public static function defaults(): array
    {
        return [
            ['code' => self::CASH, 'name' => 'Cash in hand', 'type' => AccountType::Asset, 'subtype' => AccountSubtype::Cash, 'system' => true],
            ['code' => self::BANK, 'name' => 'Bank — main account', 'type' => AccountType::Asset, 'subtype' => AccountSubtype::Bank, 'system' => true],
            ['code' => self::RECEIVABLE, 'name' => 'Accounts receivable', 'type' => AccountType::Asset, 'subtype' => AccountSubtype::Receivable, 'system' => true],
            ['code' => self::INPUT_TAX, 'name' => 'Input tax receivable', 'type' => AccountType::Asset, 'subtype' => AccountSubtype::Tax, 'system' => true],
            ['code' => '1200', 'name' => 'Inventory — furniture', 'type' => AccountType::Asset, 'subtype' => AccountSubtype::Other, 'system' => false],
            ['code' => '1500', 'name' => 'Furniture & equipment', 'type' => AccountType::Asset, 'subtype' => AccountSubtype::FixedAsset, 'system' => false],
            ['code' => '2000', 'name' => 'Accounts payable — suppliers', 'type' => AccountType::Liability, 'subtype' => AccountSubtype::Payable, 'system' => true],
            ['code' => self::TAX_PAYABLE, 'name' => 'Sales tax payable', 'type' => AccountType::Liability, 'subtype' => AccountSubtype::Tax, 'system' => true],
            ['code' => '2110', 'name' => 'Withholding tax payable', 'type' => AccountType::Liability, 'subtype' => AccountSubtype::Tax, 'system' => false],
            ['code' => self::CLIENT_ADVANCES, 'name' => 'Client advances (unearned)', 'type' => AccountType::Liability, 'subtype' => AccountSubtype::Other, 'system' => true],
            ['code' => self::EQUITY, 'name' => "Owner's equity", 'type' => AccountType::Equity, 'subtype' => AccountSubtype::Equity, 'system' => true],
            ['code' => self::OPENING_EQUITY, 'name' => 'Opening balance equity', 'type' => AccountType::Equity, 'subtype' => AccountSubtype::Equity, 'system' => true],
            ['code' => self::REVENUE_INTERIOR, 'name' => 'Interior revenue', 'type' => AccountType::Income, 'subtype' => AccountSubtype::Revenue, 'system' => true],
            ['code' => self::REVENUE_FURNITURE, 'name' => 'Furniture revenue', 'type' => AccountType::Income, 'subtype' => AccountSubtype::Revenue, 'system' => true],
            ['code' => self::REVENUE_DESIGN, 'name' => 'Design fees', 'type' => AccountType::Income, 'subtype' => AccountSubtype::Revenue, 'system' => true],
            ['code' => self::ROUNDING, 'name' => 'Rounding differences', 'type' => AccountType::Income, 'subtype' => AccountSubtype::Other, 'system' => true],
            ['code' => self::COGS_MATERIALS, 'name' => 'Cost of materials', 'type' => AccountType::Expense, 'subtype' => AccountSubtype::Cogs, 'system' => true],
            ['code' => self::SUBCONTRACTORS, 'name' => 'Subcontractors', 'type' => AccountType::Expense, 'subtype' => AccountSubtype::Cogs, 'system' => false],
            ['code' => self::OPEX, 'name' => 'Operating expenses', 'type' => AccountType::Expense, 'subtype' => AccountSubtype::Expense, 'system' => true],
            ['code' => '6100', 'name' => 'Salaries & wages', 'type' => AccountType::Expense, 'subtype' => AccountSubtype::Expense, 'system' => false],
            ['code' => '6200', 'name' => 'Rent', 'type' => AccountType::Expense, 'subtype' => AccountSubtype::Expense, 'system' => false],
            ['code' => '6300', 'name' => 'Utilities', 'type' => AccountType::Expense, 'subtype' => AccountSubtype::Expense, 'system' => false],
            ['code' => '6400', 'name' => 'Marketing & advertising', 'type' => AccountType::Expense, 'subtype' => AccountSubtype::Expense, 'system' => false],
            ['code' => '6500', 'name' => 'Transport & fuel', 'type' => AccountType::Expense, 'subtype' => AccountSubtype::Expense, 'system' => false],
            ['code' => '6600', 'name' => 'Software & subscriptions', 'type' => AccountType::Expense, 'subtype' => AccountSubtype::Expense, 'system' => false],
        ];
    }

    /** Create any missing default account. Existing rows are left untouched. */
    public function ensure(): void
    {
        $position = 0;

        foreach (self::defaults() as $default) {
            Account::query()->firstOrCreate(
                ['code' => $default['code']],
                [
                    'name' => $default['name'],
                    'type' => $default['type'],
                    'subtype' => $default['subtype'],
                    'is_system' => $default['system'],
                    'is_active' => true,
                    'position' => $position += 10,
                ],
            );
        }
    }

    public function byCode(string $code): Account
    {
        /** @var Account $account */
        $account = Account::query()->where('code', $code)->firstOrFail();

        return $account;
    }
}
