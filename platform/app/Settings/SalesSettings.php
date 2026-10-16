<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Sales rules the owner can change without a developer (QUO-*, INV-*, CRM-010).
 *
 * Note: never use @var on properties: spatie/laravel-settings parses @var to infer casts and rejects
 * generic types such as array<string, int>. Use @phpstan-var for those (spatie ignores it).
 */
class SalesSettings extends Settings
{
    public int $quote_validity_days;

    public bool $round_totals_to_rupee;

    public bool $lakh_grouping;

    public string $execution_terms;

    public string $design_terms;

    /** @phpstan-var array<string, int> role => max discount in basis points without approval */
    public array $discount_limits_bp;

    public ?int $min_margin_bp;

    /** @phpstan-var list<string> */
    public array $lost_reasons;

    /** @phpstan-var array<string, string> key => label */
    public array $budget_bands;

    public int $sla_first_response_minutes;

    /** @phpstan-var array<string, int> lead source => first-response minutes (CRM-010) */
    public array $sla_minutes_by_source;

    /** Minutes after a breach before the second escalation (owner) */
    public int $sla_escalation_minutes;

    /**
     * Lead scoring rules (CRM-011), see App\Domain\Crm\Actions\ScoreLead.
     *
     * @phpstan-var array{base: int, budget: array<string, int>, size: list<array{min_sqft: int, points: int}>, services: array<string, int>, lines: array<string, int>, sources: array<string, int>, lead_types: array<string, int>, timelines: array<string, int>, cities: array<string, int>}
     */
    public array $scoring_rules;

    public int $hot_score;

    /** @phpstan-var list<array{name: string, title: string}> */
    public array $signatories;

    public string $invoice_number_format;

    public int $invoice_due_days;

    /** What happens to the advance invoice when a quotation is accepted: draft, issue, or issue and send. */
    public string $advance_invoice_mode;

    public string $invoice_terms;

    public function slaMinutesFor(string $source): int
    {
        return (int) ($this->sla_minutes_by_source[$source] ?? $this->sla_first_response_minutes);
    }

    public static function group(): string
    {
        return 'sales';
    }
}
