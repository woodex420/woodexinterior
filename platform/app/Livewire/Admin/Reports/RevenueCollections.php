<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Reports;

use App\Settings\SalesSettings;
use App\Support\Reports\Csv;
use App\Support\Reports\ReportRange;
use App\Support\Reports\RevenueCollectionsReport;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Revenue & Collections (RPT-005): quoted → accepted → invoiced → collected per month, by business line,
 * by method and bank — with the previous period for comparison and a CSV for the accountant's records.
 */
#[Layout('layouts::admin')]
#[Title('Revenue & collections')]
class RevenueCollections extends Component
{
    public string $preset = 'this_quarter';

    public ?string $from = null;

    public ?string $to = null;

    public function setPreset(string $preset): void
    {
        if (in_array($preset, ReportRange::PRESETS, true)) {
            $this->preset = $preset;
        }
    }

    /**
     * @throws \InvalidArgumentException
     */
    #[Computed]
    public function range(): ReportRange
    {
        return ReportRange::fromPreset($this->preset, $this->from, $this->to);
    }

    /**
     * @return array<string, mixed>
     */
    #[Computed]
    public function report(): array
    {
        return app(RevenueCollectionsReport::class)->handle($this->range());
    }

    public function export(): StreamedResponse
    {
        $this->authorize('reports.finance');

        $report = $this->report();
        $range = $this->range();

        $rows = [
            ['Revenue & collections', $range->label()],
            ['Metric', 'This period', 'Previous period'],
            ['Quoted (Rs)', round($report['totals']['quoted_minor'] / 100, 2), round($report['previous']['quoted_minor'] / 100, 2)],
            ['Accepted (Rs)', round($report['totals']['accepted_minor'] / 100, 2), round($report['previous']['accepted_minor'] / 100, 2)],
            ['Invoiced (Rs)', round($report['totals']['invoiced_minor'] / 100, 2), round($report['previous']['invoiced_minor'] / 100, 2)],
            ['Collected (Rs)', round($report['totals']['collected_minor'] / 100, 2), round($report['previous']['collected_minor'] / 100, 2)],
            ['Outstanding today (Rs)', round($report['totals']['outstanding_minor'] / 100, 2)],
            [],
            ['Month', 'Quoted (Rs)', 'Accepted (Rs)', 'Invoiced (Rs)', 'Collected (Rs)'],
        ];

        foreach ($report['months'] as $row) {
            $rows[] = [$row['month'], round($row['quoted_minor'] / 100, 2), round($row['accepted_minor'] / 100, 2), round($row['invoiced_minor'] / 100, 2), round($row['collected_minor'] / 100, 2)];
        }

        $rows[] = [];
        $rows[] = ['Business line', 'Accepted (Rs)', 'Invoiced (Rs)', 'Collected (Rs)'];

        foreach ($report['by_line'] as $row) {
            $rows[] = [$row['label'], round($row['accepted_minor'] / 100, 2), round($row['invoiced_minor'] / 100, 2), round($row['collected_minor'] / 100, 2)];
        }

        $rows[] = [];
        $rows[] = ['Method', 'Payments', 'Collected (Rs)'];

        foreach ($report['by_method'] as $row) {
            $rows[] = [$row['label'], $row['count'], round($row['amount_minor'] / 100, 2)];
        }

        return Csv::download('revenue-collections-'.now()->format('Y-m-d').'.csv', ['Revenue and collections report'], $rows);
    }

    public function render(SalesSettings $settings): View
    {
        return view('livewire.admin.reports.revenue-collections', [
            'lakh' => $settings->lakh_grouping,
        ]);
    }
}
