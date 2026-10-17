<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Models;

use App\Domain\Accounting\Enums\AccountSubtype;
use App\Domain\Accounting\Enums\AccountType;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One line of the chart of accounts (docs/17 §4.2). System accounts are created by ChartOfAccountsSeeder and are
 * referenced by code from the posting rules: an accountant may rename them, never delete them.
 */
class Account extends Model
{
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AccountType::class,
            'subtype' => AccountSubtype::class,
            'is_system' => 'boolean',
            'is_active' => 'boolean',
            'opening_minor' => 'integer',
            'opening_on' => 'date',
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<JournalLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    /**
     * @param  Builder<Account>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function balance(): Money
    {
        $sums = JournalLine::query()
            ->where('account_id', $this->id)
            ->selectRaw('COALESCE(SUM(debit_minor), 0) AS debit, COALESCE(SUM(credit_minor), 0) AS credit')
            ->first();

        return Money::ofMinor($this->type->signed((int) ($sums->debit ?? 0), (int) ($sums->credit ?? 0)));
    }

    public function __toString(): string
    {
        return $this->code.' · '.$this->name;
    }
}
