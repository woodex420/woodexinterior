<?php

declare(strict_types=1);

namespace App\Domain\Projects\Enums;

use App\Support\Concerns\EnumHelpers;

enum ProjectStage: string
{
    use EnumHelpers;

    case Planning = 'planning';
    case Design = 'design';
    case Procurement = 'procurement';
    case Execution = 'execution';
    case Finishing = 'finishing';
    case Handover = 'handover';
    case Completed = 'completed';
}
