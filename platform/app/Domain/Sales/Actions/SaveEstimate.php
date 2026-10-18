<?php

declare(strict_types=1);

namespace App\Domain\Sales\Actions;

use App\Domain\Sales\Enums\EstimateStatus;
use App\Domain\Sales\Models\Estimate;
use App\Domain\Sales\Models\EstimatorRate;
use App\Models\User;
use App\Support\Numbering;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Create or update a draft estimate, always recomputing the snapshot (P8.3). The number is
 * allocated once, on creation, and survives every edit; a converted estimate is history and
 * refuses edits — the quotation is the living document now.
 */
final class SaveEstimate
{
    public function __construct(
        private readonly ComputeEstimate $compute,
    ) {}

    /**
     * @param  array{title: string, service_key: string, contact_id?: int|string|null, lead_id?: int|string|null, quantity: string, finish: string, contingency_bp?: int|string|null, assumptions?: string|null, exclusions?: string|null, catalog_lines?: list<array{catalog_item_id: int|string, qty: string}>}  $input
     */
    public function handle(array $input, User $by, ?Estimate $estimate = null): Estimate
    {
        $by = User::query()->findOrFail($by->id);
        if (! $by->is_active || ! $by->can('estimates.manage')) {
            throw new AuthorizationException;
        }

        $validated = validator($input, [
            'title' => ['required', 'string', 'max:120'],
            'service_key' => ['required', 'string', 'max:60'],
            'contact_id' => ['nullable', 'integer', 'exists:contacts,id'],
            'lead_id' => ['nullable', 'integer', 'exists:leads,id'],
            'quantity' => ['required', 'string', 'max:20'],
            'finish' => ['required', 'in:essential,standard,premium'],
            'contingency_bp' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'assumptions' => ['nullable', 'string', 'max:4000'],
            'exclusions' => ['nullable', 'string', 'max:4000'],
        ])->validate();

        $rate = EstimatorRate::query()->where('key', $validated['service_key'])->where('is_active', true)->first();

        if ($rate === null) {
            throw ValidationException::withMessages(['service_key' => 'Choose an active service for the estimate.']);
        }

        $catalogLines = collect($input['catalog_lines'] ?? [])
            ->filter(static fn (array $line): bool => (int) ($line['catalog_item_id'] ?? 0) > 0 && (string) ($line['qty'] ?? '') !== '')
            ->map(static fn (array $line): array => ['catalog_item_id' => (int) $line['catalog_item_id'], 'qty' => (string) $line['qty']])
            ->values()
            ->all();

        $computed = $this->compute->handle($rate, $validated['quantity'], $validated['finish'], $catalogLines, (int) ($validated['contingency_bp'] ?? 0));

        return DB::transaction(function () use ($validated, $by, $estimate, $rate, $catalogLines, $computed): Estimate {
            if ($estimate !== null) {
                $estimate = Estimate::query()->findOrFail($estimate->id);

                if ($estimate->status === EstimateStatus::Converted) {
                    throw ValidationException::withMessages(['estimate' => 'This estimate is already converted to '.$estimate->quote?->number.' — the quotation carries it from here.']);
                }

                $estimate->forceFill([
                    'title' => $validated['title'],
                    'service_key' => $rate->key,
                    'contact_id' => $validated['contact_id'] ?? null,
                    'lead_id' => $validated['lead_id'] ?? null,
                    'inputs' => ['quantity' => $validated['quantity'], 'unit' => (string) $rate->unit, 'finish' => $validated['finish']],
                    'assumptions' => $validated['assumptions'] ?? null,
                    'exclusions' => $validated['exclusions'] ?? null,
                    'contingency_bp' => (int) ($validated['contingency_bp'] ?? 0),
                    'catalog_lines' => $catalogLines,
                    'computed' => $computed,
                ])->save();

                return $estimate->refresh();
            }

            return Estimate::query()->create([
                'number' => app(Numbering::class)->next('estimate'),
                'service_key' => $rate->key,
                'title' => $validated['title'],
                'contact_id' => $validated['contact_id'] ?? null,
                'lead_id' => $validated['lead_id'] ?? null,
                'inputs' => ['quantity' => $validated['quantity'], 'unit' => (string) $rate->unit, 'finish' => $validated['finish']],
                'assumptions' => $validated['assumptions'] ?? null,
                'exclusions' => $validated['exclusions'] ?? null,
                'contingency_bp' => (int) ($validated['contingency_bp'] ?? 0),
                'catalog_lines' => $catalogLines,
                'computed' => $computed,
                'status' => EstimateStatus::Draft,
                'created_by' => $by->id,
            ]);
        });
    }
}
