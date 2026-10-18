<?php

declare(strict_types=1);

namespace App\Domain\Sales\Actions;

use App\Domain\Crm\Enums\BusinessLine;
use App\Domain\Crm\Models\Lead;
use App\Domain\Sales\Models\EstimatorRate;
use App\Domain\Sales\Models\Quote;
use App\Domain\Sales\Services\QuoteEditor;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Arr;

/**
 * A website estimate becomes a real quotation (QUO-017).
 *
 * Somebody who used the cost estimator has already told us their service, their size and the finish they want, so
 * the quotation starts with a priced line instead of an empty page — the salesperson checks it, adds what a
 * website form cannot know, and sends it. The price is the studio's rate book, not the range the website showed:
 * the range is marketing, the rate book is what the studio charges, and the two are kept side by side on the
 * quotation's internal notes so whoever opens it can see the difference and explain it.
 *
 * It stays a **draft**. Nothing built from three form fields goes to a client without a person reading it.
 */
final class CreateQuoteFromEstimate
{
    public function __construct(
        private readonly CreateQuote $create,
        private readonly QuoteEditor $editor,
    ) {}

    /**
     * @return array{service: string, area: float, finish: string, estimate: string, rate: ?EstimatorRate}
     */
    public function read(Lead $lead): array
    {
        // The bridge keeps everything the estimator sent (docs/11 §11.0.1); the importer and the manual form put
        // their details elsewhere, so both places are looked at.
        $fields = array_merge(
            (array) ($lead->meta['fields'] ?? []),
            (array) ($lead->meta['estimate'] ?? []),
        );

        $service = trim((string) Arr::get($fields, 'service', ''));
        $finish = trim((string) Arr::get($fields, 'finish', '')) ?: 'standard';
        $area = (float) preg_replace('/[^0-9.]/', '', (string) Arr::get($fields, 'area', ''));
        $estimate = trim((string) Arr::get($fields, 'estimate', ''));

        $rate = $service === ''
            ? null
            : EstimatorRate::query()->where('key', $service)->where('is_active', true)->first()
                ?? EstimatorRate::query()->where('label', $service)->where('is_active', true)->first();

        return ['service' => $service, 'area' => $area, 'finish' => $finish, 'estimate' => $estimate, 'rate' => $rate];
    }

    /** Whether this enquiry carries an estimate worth turning into a quotation. */
    public function available(Lead $lead): bool
    {
        $read = $this->read($lead);

        return $read['rate'] !== null && $read['area'] > 0;
    }

    public function handle(Lead $lead, User $by): Quote
    {
        $read = $this->read($lead);

        if ($read['rate'] === null || $read['area'] <= 0) {
            throw new \InvalidArgumentException('This enquiry has no estimate to start a quotation from.');
        }

        $rate = $read['rate'];
        $finish = in_array($read['finish'], ['essential', 'standard', 'premium'], true) ? $read['finish'] : 'standard';
        $unit = (string) $rate->unit;
        $unitMinor = $rate->rateFor($finish)->minor;

        $quote = $this->create->handle(
            $lead->contact,
            BusinessLine::Interior,
            $by,
            $lead,
            null,
            $rate->label.' — '.$lead->contact->name,
        );

        $section = $this->editor->addSection($quote, $rate->label);
        $this->editor->addLine($section, [
            'description' => $this->description($rate->label, $finish, $read['area'], $unit),
            'unit' => $unit,
            'qty' => (string) $read['area'],
            'rate_minor' => $unitMinor,
        ]);

        // Remove the empty starter line the editor leaves behind, so the quotation holds only what we priced.
        $quote->items()->where('rate_minor', 0)->where('description', '')->each(fn ($item) => $this->editor->deleteLine($item));

        $notes = collect([
            'Started from the website cost estimator: '.$rate->label.', '.$this->quantity($read['area'], $unit).', '.ucfirst($finish).' finish.',
            $read['estimate'] !== '' ? 'The website showed the client: '.$read['estimate'].'.' : null,
            'Rate from the rate book: '.Money::ofMinor($unitMinor)->format().' per '.$unit.'.',
            'Check the scope on a call or a visit before sending — the estimator only knows three things.',
        ])->filter()->implode(' ');

        $quote->forceFill(['internal_notes' => $notes])->save();

        return $quote->refresh();
    }

    private function description(string $label, string $finish, float $area, string $unit): string
    {
        return $label.', '.ucfirst($finish).' finish ('.$this->quantity($area, $unit).') — scope to be confirmed after the visit';
    }

    private function quantity(float $area, string $unit): string
    {
        $number = number_format($area, $area < 100 ? 1 : 0);

        return trim($number.' '.$unit);
    }
}
