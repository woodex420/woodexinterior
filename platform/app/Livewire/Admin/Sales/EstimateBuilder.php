<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Sales;

use App\Domain\Crm\Models\Contact;
use App\Domain\Sales\Actions\ComputeEstimate;
use App\Domain\Sales\Actions\ConvertEstimateToQuote;
use App\Domain\Sales\Actions\ReviewEstimate;
use App\Domain\Sales\Actions\SaveEstimate;
use App\Domain\Sales\Enums\EstimateStatus;
use App\Domain\Sales\Models\CatalogItem;
use App\Domain\Sales\Models\Estimate;
use App\Domain\Sales\Models\EstimatorRate;
use App\Livewire\Concerns\InteractsWithAdmin;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * P8.3: the estimate builder — pick a service, give the size and finish, set the honest
 * contingency, add known rate book items, and watch the same server-side engine price it that
 * will price the snapshot. One click converts it to a quotation draft; the estimate itself never
 * reaches a client.
 */
#[Layout('layouts::admin')]
#[Title('Estimate builder')]
class EstimateBuilder extends Component
{
    use InteractsWithAdmin;

    public ?int $estimateId = null;

    public string $number = '';

    public string $title = '';

    public string $serviceKey = '';

    public string $contactId = '';

    public string $quantity = '';

    public string $finish = 'standard';

    public string $contingencyBp = '0';

    public string $assumptions = '';

    public string $exclusions = '';

    /** @var list<array{catalog_item_id: string, qty: string}> */
    public array $catalogLines = [];

    public string $status = 'draft';

    public function mount(?int $estimate = null): void
    {
        abort_unless($this->user()->can('estimates.view'), 403);

        if ($estimate === null) {
            return;
        }

        $model = Estimate::query()->findOrFail($estimate);

        $this->estimateId = $model->id;
        $this->number = $model->number;
        $this->title = $model->title;
        $this->serviceKey = $model->service_key;
        $this->contactId = (string) ($model->contact_id ?? '');
        $this->quantity = (string) $model->inputs['quantity'];
        $this->finish = (string) $model->inputs['finish'];
        $this->contingencyBp = (string) $model->contingency_bp;
        $this->assumptions = (string) $model->assumptions;
        $this->exclusions = (string) $model->exclusions;
        $this->catalogLines = collect($model->catalog_lines ?? [])
            ->map(static fn (array $line): array => ['catalog_item_id' => (string) $line['catalog_item_id'], 'qty' => (string) $line['qty']])
            ->values()
            ->all();
        $this->status = $model->status->value;
    }

    public function render(): View
    {
        return view('livewire.admin.sales.estimate-builder', [
            'services' => EstimatorRate::query()->where('is_active', true)->orderBy('position')->get(['id', 'key', 'label', 'unit', 'unit_label', 'essential_minor', 'standard_minor', 'premium_minor']),
            'contacts' => Contact::query()->orderBy('name')->limit(300)->get(['id', 'name']),
            'catalogItems' => CatalogItem::query()->where('is_active', true)->orderBy('title')->get(['id', 'title', 'unit', 'rate_minor', 'business_line']),
            'summary' => $this->summary(),
            'estimate' => $this->estimateId !== null ? Estimate::query()->with('quote:id,number,status')->find($this->estimateId) : null,
        ]);
    }

    /**
     * Live pricing from the same engine that will freeze the snapshot.
     *
     * @return array{rate_minor: string, headline: string, range_low: string, range_high: string, lines_minor: string, contingency: string, total: string, effective_on: string}|null
     */
    public function summary(): ?array
    {
        $rate = $this->currentRate();

        if ($rate === null) {
            return null;
        }

        try {
            $computed = app(ComputeEstimate::class)->handle($rate, $this->quantity, $this->finish, $this->normalizedLines(), (int) $this->contingencyBp);
        } catch (ValidationException) {
            return null;
        }

        return [
            'rate_minor' => Money::ofMinor($computed['rate_minor'])->format(),
            'headline' => Money::ofMinor($computed['headline_minor'])->format(),
            'range_low' => Money::ofMinor($computed['range_low_minor'])->format(),
            'range_high' => Money::ofMinor($computed['range_high_minor'])->format(),
            'lines_minor' => Money::ofMinor($computed['lines_minor'])->format(),
            'contingency' => Money::ofMinor($computed['contingency_minor'])->format(),
            'total' => Money::ofMinor($computed['total_minor'])->format(),
            'effective_on' => $computed['effective_on'],
        ];
    }

    public function addLine(): void
    {
        $this->catalogLines[] = ['catalog_item_id' => '', 'qty' => '1'];
    }

    public function removeLine(int $index): void
    {
        unset($this->catalogLines[$index]);
        $this->catalogLines = array_values($this->catalogLines);
    }

    public function save(): void
    {
        abort_unless($this->user()->can('estimates.manage'), 403);

        $estimate = app(SaveEstimate::class)->handle($this->payload(), $this->user(), $this->estimateId !== null ? Estimate::query()->find($this->estimateId) : null);

        $this->estimateId = $estimate->id;
        $this->number = $estimate->number;
        $this->status = $estimate->status->value;

        $this->toast('Estimate '.$estimate->number.' saved with today\'s rates.', 'success');
    }

    public function review(): void
    {
        abort_unless($this->user()->can('estimates.manage'), 403);

        $estimate = app(ReviewEstimate::class)->handle(Estimate::query()->findOrFail($this->estimateId), $this->user());
        $this->status = $estimate->status->value;

        $this->toast('Estimate '.$estimate->number.' marked reviewed.', 'success');
    }

    public function convert(): void
    {
        abort_unless($this->user()->can('estimates.manage'), 403);

        // Convert what is on screen, not a stale row.
        $estimate = app(SaveEstimate::class)->handle($this->payload(), $this->user(), $this->estimateId !== null ? Estimate::query()->find($this->estimateId) : null);
        $quote = app(ConvertEstimateToQuote::class)->handle($estimate, $this->user());

        $this->status = EstimateStatus::Converted->value;

        $this->toast('Quotation '.$quote->number.' created as a draft from '.$estimate->number.'.', 'success');

        $this->redirect(route('admin.quotes.show', $quote));
    }

    /** @return array{title: string, service_key: string, contact_id?: int|null, quantity: string, finish: string, contingency_bp: int, assumptions?: string|null, exclusions?: string|null, catalog_lines: list<array{catalog_item_id: int, qty: string}>} */
    private function payload(): array
    {
        return [
            'title' => $this->title,
            'service_key' => $this->serviceKey,
            'contact_id' => $this->contactId !== '' ? (int) $this->contactId : null,
            'quantity' => $this->quantity,
            'finish' => $this->finish,
            'contingency_bp' => (int) $this->contingencyBp,
            'assumptions' => $this->assumptions !== '' ? $this->assumptions : null,
            'exclusions' => $this->exclusions !== '' ? $this->exclusions : null,
            'catalog_lines' => $this->normalizedLines(),
        ];
    }

    /** @return list<array{catalog_item_id: int, qty: string}> */
    private function normalizedLines(): array
    {
        return collect($this->catalogLines)
            ->filter(static fn (array $line): bool => (int) ($line['catalog_item_id'] ?? 0) > 0 && trim((string) ($line['qty'] ?? '')) !== '')
            ->map(static fn (array $line): array => ['catalog_item_id' => (int) $line['catalog_item_id'], 'qty' => (string) $line['qty']])
            ->values()
            ->all();
    }

    private function currentRate(): ?EstimatorRate
    {
        return $this->serviceKey !== ''
            ? EstimatorRate::query()->where('key', $this->serviceKey)->where('is_active', true)->first()
            : null;
    }
}
