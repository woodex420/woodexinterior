<?php

declare(strict_types=1);

namespace App\Domain\Crm\Enums;

use App\Support\Concerns\EnumHelpers;

enum ActivityType: string
{
    use EnumHelpers;

    case Note = 'note';
    case Call = 'call';
    case WhatsApp = 'whatsapp';
    case Email = 'email';
    case Meeting = 'meeting';
    case Visit = 'visit';
    case StageChange = 'stage_change';
    case Assignment = 'assignment';
    case QuoteEvent = 'quote_event';
    case InvoiceEvent = 'invoice_event';
    case Payment = 'payment';
    case System = 'system';
}
