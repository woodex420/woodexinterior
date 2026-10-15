<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Crm\Actions\CheckLeadSla;
use Illuminate\Console\Command;

class SlaCheck extends Command
{
    protected $signature = 'woodex:sla-check';

    protected $description = 'Flag new leads that missed the first-response target and alert owners and managers (CRM-010).';

    public function handle(CheckLeadSla $check): int
    {
        $flagged = $check->handle();

        if ($flagged > 0) {
            $this->components->warn("{$flagged} lead(s) missed the response target; alerts sent.");
        }

        return self::SUCCESS;
    }
}
