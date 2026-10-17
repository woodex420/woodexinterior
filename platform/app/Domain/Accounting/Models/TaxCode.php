<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Output (sales) or input (purchases) tax rate; 1800 bp = 18% Pakistan sales tax. */
class TaxCode extends Model
{
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['rate_bp' => 'integer', 'is_active' => 'boolean'];
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function percent(): float
    {
        return $this->rate_bp / 100;
    }
}
