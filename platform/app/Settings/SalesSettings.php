<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Sales rules the owner can change without a developer (QUO-*, INV-*, CRM-010).
 *
 * Note: no @var docblocks on properties. spatie/laravel-settings parses them to infer casts
 * and rejects generic types such as array<string, int>.
 */
class SalesSettings extends Settings
{
    public int $quote_validity_days;

    public bool $round_totals_to_rupee;

    public bool $lakh_grouping;

    public string $execution_terms;

    public string $design_terms;

    // array<string, int> role => max discount in basis points without approval
    public array $discount_limits_bp;

    public ?int $min_margin_bp;

    // list<string>
    public array $lost_reasons;

    // array<string, string> key => label
    public array $budget_bands;

    public int $sla_first_response_minutes;

    // list<array{name: string, title: string}>
    public array $signatories;

    public string $invoice_number_format;

    public int $invoice_due_days;

    public static function group(): string
    {
        return 'sales';
    }
}
