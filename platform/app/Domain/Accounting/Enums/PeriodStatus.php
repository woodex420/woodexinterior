<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Enums;

use App\Support\Concerns\EnumHelpers;

enum PeriodStatus: string
{
    use EnumHelpers;

    case Open = 'open';
    case Closed = 'closed';

    public function label(): string
    {
        return $this === self::Open ? 'Open' : 'Closed';
    }

    public function color(): string
    {
        return $this === self::Open ? 'success' : 'gray';
    }
}
