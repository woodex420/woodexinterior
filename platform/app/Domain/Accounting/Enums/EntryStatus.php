<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Enums;

use App\Support\Concerns\EnumHelpers;

enum EntryStatus: string
{
    use EnumHelpers;

    case Posted = 'posted';
    case Reversed = 'reversed';

    public function label(): string
    {
        return $this === self::Posted ? 'Posted' : 'Reversed';
    }
}
