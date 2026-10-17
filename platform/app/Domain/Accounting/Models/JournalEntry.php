<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Models;

use App\Domain\Accounting\Enums\EntryStatus;
use App\Models\User;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A balanced journal entry. `source_type/source_id/source_event` is unique, which is what makes posting a document
 * idempotent: running "verify payment 42" twice cannot double the ledger.
 */
class JournalEntry extends Model
{
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'status' => EntryStatus::class,
            'posted_at' => 'datetime',
            'reversal_of_id' => 'integer',
        ];
    }

    /**
     * @return HasMany<JournalLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class)->orderBy('position');
    }

    /**
     * @return BelongsTo<AccountingPeriod, $this>
     */
    public function period(): BelongsTo
    {
        return $this->belongsTo(AccountingPeriod::class, 'period_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function total(): Money
    {
        return Money::ofMinor((int) $this->lines()->sum('debit_minor'));
    }

    public function debits(): int
    {
        return (int) $this->lines()->sum('debit_minor');
    }

    public function credits(): int
    {
        return (int) $this->lines()->sum('credit_minor');
    }

    public function isBalanced(): bool
    {
        return $this->debits() === $this->credits();
    }
}
