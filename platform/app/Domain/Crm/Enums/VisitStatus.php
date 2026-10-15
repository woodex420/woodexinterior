<?php

declare(strict_types=1);

namespace App\Domain\Crm\Enums;

use App\Support\Concerns\EnumHelpers;

enum VisitStatus: string
{
    use EnumHelpers;

    case Scheduled = 'scheduled';
    case Confirmed = 'confirmed';
    case Rescheduled = 'rescheduled';
    case Completed = 'completed';
    case NoShow = 'no_show';
    case Cancelled = 'cancelled';
}
