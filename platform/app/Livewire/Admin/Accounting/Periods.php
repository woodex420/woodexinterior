<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Accounting;

use App\Domain\Accounting\Enums\PeriodStatus;
use App\Domain\Accounting\Models\AccountingPeriod;
use App\Domain\Accounting\Models\JournalLine;
use App\Livewire\Concerns\InteractsWithAdmin;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Accounting → Periods (P9-A, D-118). Closing a month means "the books are signed": no posting can land there
 * afterwards, and a correction becomes a reversal in the next open month.
 */
#[Layout('layouts::admin')]
#[Title('Accounting periods')]
class Periods extends Component
{
    use InteractsWithAdmin;

    public function mount(): void
    {
        abort_unless($this->user()->can('accounting.view'), 403);
        AccountingPeriod::forDate(now());

        foreach ([1, 2] as $back) {
            AccountingPeriod::forDate(now()->copy()->subMonths($back));
        }
    }

    public function close(int $id): void
    {
        abort_unless($this->user()->can('accounting.close'), 403);

        $period = AccountingPeriod::query()->findOrFail($id);

        if (! $period->isOpen()) {
            return;
        }

        $period->forceFill([
            'status' => PeriodStatus::Closed,
            'closed_at' => now(),
            'closed_by' => $this->user()->id,
        ])->save();

        $this->toast($period->name.' is closed. Corrections go to the next open month as reversals.');
    }

    public function reopen(int $id): void
    {
        abort_unless($this->user()->can('accounting.close'), 403);

        $period = AccountingPeriod::query()->findOrFail($id);

        if ($period->isOpen()) {
            return;
        }

        $period->forceFill(['status' => PeriodStatus::Open, 'closed_at' => null, 'closed_by' => null])->save();
        $this->toast($period->name.' is open again. Close it when the month is signed off.');
    }

    public function render(): View
    {
        $periods = AccountingPeriod::query()
            ->withCount('entries')
            ->orderByDesc('starts_on')
            ->get()
            ->map(function (AccountingPeriod $period): array {
                $sums = JournalLine::query()
                    ->whereIn('journal_entry_id', $period->entries()->select('id'))
                    ->selectRaw('COALESCE(SUM(debit_minor),0) AS debit')
                    ->first();

                return ['period' => $period, 'debit' => (int) ($sums->debit ?? 0)];
            });

        return view('livewire.admin.accounting.periods', [
            'periods' => $periods,
            'format' => fn (int $minor): string => Money::ofMinor($minor)->format(),
        ]);
    }
}
