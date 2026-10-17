<?php

declare(strict_types=1);

namespace App\Domain\Sales\Actions;

use App\Domain\Crm\Enums\BusinessLine;
use App\Domain\Sales\Enums\EstimateStatus;
use App\Domain\Sales\Models\Estimate;
use App\Domain\Sales\Models\EstimatorRate;
use App\Domain\Sales\Models\Quote;
use App\Domain\Sales\Services\QuoteEditor;
use App\Models\User;
use App\Support\Money;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * One click from estimate to quotation draft (P8.3, docs/19 §7): the priced headline and the
 * rate book lines become quote sections, the range/assumptions/exclusions ride along in the
 * internal notes, and the link is recorded on both sides. Idempotent — converting twice returns
 * the same quotation — and it stays a DRAFT: nothing built from an estimate goes to a client
 * without a person completing and sending it.
 */
final class ConvertEstimateToQuote
{
    public function __construct(
        private readonly CreateQuote $create,
        private readonly QuoteEditor $editor,
    ) {}

    public function handle(Estimate $estimate, User $by): Quote
    {
        $by = User::query()->findOrFail($by->id);
        if (! $by->is_active || ! $by->can('estimates.manage') || ! $by->can('quotes.manage')) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($estimate, $by): Quote {
            $estimate = Estimate::query()->lockForUpdate()->findOrFail($estimate->id);

            if ($estimate->status === EstimateStatus::Converted && $estimate->quote_id !== null) {
                /** @var Quote */
                return $estimate->quote()->withTrashed()->firstOrFail();
            }

            if ($estimate->contact_id === null) {
                throw ValidationException::withMessages(['contact_id' => 'Give this estimate a client before converting — the quotation needs someone to address.']);
            }

            $rate = EstimatorRate::query()->where('key', $estimate->service_key)->firstOrFail();
            $contact = $estimate->contact()->firstOrFail();
            $line = self::businessLineFor($estimate->service_key);
            $computed = $estimate->computed;
            $quantity = (string) ($computed['quantity'] ?? $estimate->inputs['quantity']);
            $finish = (string) ($computed['finish'] ?? $estimate->inputs['finish']);
            $unit = (string) ($computed['unit'] ?? $estimate->inputs['unit']);

            $quote = $this->create->handle(
                $contact,
                $line,
                $by,
                $estimate->lead_id !== null ? $estimate->lead : null,
                null,
                $estimate->title,
            );

            // CreateQuote leaves a starter 'Work items' section with a blank line — it becomes the headline section.
            $section = $quote->sections()->orderBy('position')->first() ?? $this->editor->addSection($quote, $rate->label.' — estimate '.$estimate->number);
            $this->editor->updateSection($section, ['name' => $rate->label.' — estimate '.$estimate->number]);
            $this->editor->addLine($section, [
                'description' => sprintf('%s, %s finish (%s %s) — from estimate %s', $rate->label, ucfirst($finish), $quantity, $unit, $estimate->number),
                'unit' => $unit,
                'qty' => $quantity,
                'rate_minor' => (int) $computed['rate_minor'],
            ]);

            $lines = $computed['lines'] ?? [];

            if ($lines !== []) {
                $items = $this->editor->addSection($quote, 'Rate book items');

                foreach ($lines as $lineItem) {
                    $this->editor->addLine($items, [
                        'description' => (string) $lineItem['title'],
                        'unit' => (string) $lineItem['unit'],
                        'qty' => (string) $lineItem['qty'],
                        'rate_minor' => (int) $lineItem['rate_minor'],
                    ]);
                }
            }

            // The editor leaves blank starter lines behind; the quotation holds only what was priced.
            $quote->items()->where('rate_minor', 0)->where(fn ($query) => $query->where('description', '')->orWhereNull('description'))->each(fn ($item) => $this->editor->deleteLine($item));

            $notes = collect([
                sprintf('Started from estimate %s (%s, %s %s, %s finish) — range %s to %s.', $estimate->number, $rate->label, $quantity, $unit, $finish, Money::ofMinor((int) $computed['range_low_minor'])->format(), Money::ofMinor((int) $computed['range_high_minor'])->format()),
                (int) ($computed['contingency_minor'] ?? 0) > 0 ? sprintf('Contingency %s (%d bp) is included in the estimate total %s — decide whether the quotation carries it.', Money::ofMinor((int) $computed['contingency_minor'])->format(), $estimate->contingency_bp, Money::ofMinor((int) $computed['total_minor'])->format()) : null,
                trim((string) $estimate->assumptions) !== '' ? 'Assumptions: '.trim((string) $estimate->assumptions) : null,
                trim((string) $estimate->exclusions) !== '' ? 'Exclusions: '.trim((string) $estimate->exclusions) : null,
            ])->filter()->implode(' ');

            $quote->forceFill([
                'internal_notes' => $notes,
                'estimate_id' => $estimate->id,
            ])->save();

            $estimate->forceFill([
                'status' => EstimateStatus::Converted,
                'quote_id' => $quote->id,
            ])->save();

            return $quote->refresh();
        });
    }

    private static function businessLineFor(string $serviceKey): BusinessLine
    {
        return $serviceKey === 'furniture' ? BusinessLine::Furniture : BusinessLine::Interior;
    }
}
