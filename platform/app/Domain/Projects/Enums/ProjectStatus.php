<?php

declare(strict_types=1);

namespace App\Domain\Projects\Enums;

use App\Support\Concerns\EnumHelpers;

enum ProjectStatus: string
{
    use EnumHelpers;

    case Active = 'active';
    case OnHold = 'on_hold';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
