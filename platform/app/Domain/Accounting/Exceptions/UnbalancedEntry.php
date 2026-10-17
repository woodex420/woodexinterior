<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Exceptions;

use RuntimeException;

/** A journal entry whose debits and credits do not match never reaches the database. */
final class UnbalancedEntry extends RuntimeException
{
    public static function forTotals(int $debit, int $credit): self
    {
        return new self("This entry does not balance: debit {$debit}, credit {$credit} (paisa).");
    }
}
