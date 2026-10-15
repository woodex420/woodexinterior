<?php

declare(strict_types=1);

namespace App\Domain\Crm\Actions;

use App\Domain\Crm\Enums\BusinessLine;
use App\Domain\Crm\Enums\LeadSource;
use App\Domain\Crm\Enums\LeadType;
use App\Domain\Crm\Models\Lead;

/**
 * Lead score 0–100 (CRM-011, first version): budget, size, service, line and source.
 * Rules move to Settings in a later iteration; 80+ shows as "Hot" on the pipeline.
 */
final class ScoreLead
{
    public function score(Lead $lead): int
    {
        $score = 20;

        $score += match ($lead->budget_band) {
            '15m-plus' => 30,
            '7m-15m' => 25,
            '3m-7m' => 15,
            '1m-3m' => 8,
            default => 0,
        };

        $sqft = (int) ($lead->area_sqft ?? 0);
        $score += $sqft >= 4500 ? 15 : ($sqft >= 2000 ? 10 : ($sqft > 0 ? 5 : 0));

        if (in_array($lead->service, ['fit-out', 'renovation', 'turnkey', 'interior-design', 'architecture'], true)) {
            $score += 10;
        }

        if ($lead->business_line === BusinessLine::Project) {
            $score += 10;
        }

        if (in_array($lead->source, [LeadSource::Referral, LeadSource::ReturningClient], true)
            || in_array($lead->lead_type, [LeadType::Returning, LeadType::Referral], true)) {
            $score += 15;
        }

        if (in_array($lead->timeline, ['asap', '1-3-months'], true)) {
            $score += 5;
        }

        return max(0, min(100, $score));
    }
}
