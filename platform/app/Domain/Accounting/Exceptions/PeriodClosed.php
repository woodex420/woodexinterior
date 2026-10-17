<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Exceptions;

use App\Domain\Accounting\Models\AccountingPeriod;
use RuntimeException;

/** Books that have been signed off cannot take new postings; corrections are reversals in the next open month. */
final class PeriodClosed extends RuntimeException
{
    public static function make(AccountingPeriod $period): self
    {
        return new self("{$period->name} is closed. Post the correction in the next open month.");
    }
}
