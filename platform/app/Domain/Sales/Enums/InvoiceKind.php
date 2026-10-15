<?php

declare(strict_types=1);

namespace App\Domain\Sales\Enums;

use App\Support\Concerns\EnumHelpers;

enum InvoiceKind: string
{
    use EnumHelpers;

    case Milestone = 'milestone';
    case Manual = 'manual';
    case Variation = 'variation';
    case CreditNote = 'credit_note';
}
