<?php

declare(strict_types=1);

namespace App\Domain\Sales\Actions;

use App\Domain\Crm\Enums\BusinessLine;
use App\Domain\Crm\Models\Lead;
use App\Domain\Sales\Enums\EstimateStatus;
use App\Domain\Sales\Models\Estimate;
use App\Domain\Sales\Models\EstimatorRate;
use App\Domain\Sales\Models\Quote;
use App\Domain\Sales\Services\EstimatorLeadDetails;
use App\Domain\Sales\Services\QuoteEditor;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

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
 *
 * Since deep QA slice C, the one-click quotation also **writes the estimate it came from** and links the two
 * (quote.estimate_id ↔ estimate.quote_id). Before this, a quotation could exist with no estimate behind it,
 * which is why the Estimations screen could stay empty while quotations were being sent. The estimate is the
 * record of what the rate book said; the quotation is the document the client gets.
 */
final class CreateQuoteFromEstimate
{
    public function __construct(
        private readonly CreateQuote $create,
        private readonly QuoteEditor $editor,
        private readonly EstimatorLeadDetails $details,
        private readonly DraftEstimateFromEstimatorLead $drafts,
    ) {}

    /**
     * @return array{service: string, area: float, finish: string, estimate: string, rate: ?EstimatorRate}
     */
    public function read(Lead $lead): array
    {
        return $this->details->read($lead);
    }

    /** Whether this enquiry carries an estimate worth turning into a quotation. */
    public function available(Lead $lead): bool
    {
        return $this->details->available($lead);
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

        return DB::transaction(function () use ($lead, $by, $rate, $finish, $unit, $unitMinor, $read): Quote {
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

            // The estimate behind this quotation (slice C): the draft written when the estimator enquiry
            // arrived, when it still matches; otherwise one is written now with this person as its author.
            // Either way the quotation points at an estimate, and the Estimations screen shows the work.
            $estimate = $this->drafts->existingFor($lead) ?? $this->drafts->handle($lead, $by);

            if ($estimate === null) {
                throw new \InvalidArgumentException('This enquiry cannot be priced from the rate book, so no quotation was started.');
            }

            $estimate->forceFill([
                'status' => EstimateStatus::Converted,
                'quote_id' => $quote->id,
            ])->save();

            $quote->forceFill([
                'estimate_id' => $estimate->id,
                'internal_notes' => trim($notes.' Estimate '.$estimate->number.' records this pricing.'),
            ])->save();

            return $quote->refresh();
        });
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
