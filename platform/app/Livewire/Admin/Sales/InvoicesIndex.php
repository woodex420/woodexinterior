<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Sales;

use App\Domain\Sales\Enums\InvoiceStatus;
use App\Domain\Sales\Models\Invoice;
use App\Livewire\Concerns\InteractsWithAdmin;
use App\Settings\SalesSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Invoices list (INV-001/003): number, client, project, status, amount and balance, with the overdue ones first.
 * An invoice is raised when a quotation is accepted (the advance) and later when the work reaches a milestone.
 */
#[Layout('layouts::admin')]
#[Title('Invoices')]
class InvoicesIndex extends Component
{
    use InteractsWithAdmin, WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $status = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Invoice::class);
    }

    public function updating(string $name): void
    {
        if (in_array($name, ['search', 'status'], true)) {
            $this->resetPage();
        }
    }

    public function render(): View
    {
        $invoices = Invoice::query()
            ->with(['contact:id,name,phone', 'project:id,code,name', 'quote:id,number,title'])
            ->when($this->search !== '', fn (Builder $q) => $q->where(fn (Builder $inner) => $inner
                ->where('number', 'like', '%'.$this->search.'%')
                ->orWhereHas('contact', fn (Builder $c) => $c->where('name', 'like', '%'.$this->search.'%'))
                ->orWhereHas('quote', fn (Builder $c) => $c->where('number', 'like', '%'.$this->search.'%'))))
            ->when($this->status !== '', fn (Builder $q) => $q->where('status', $this->status))
            ->orderByRaw("case when due_date is not null and due_date < ? and status not in ('paid','void') then 0 else 1 end", [now()->toDateString()])
            ->orderByDesc('issue_date')
            ->latest('id')
            ->paginate(25);

        $counts = Invoice::query()->toBase()->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');

        $overdue = Invoice::query()
            ->whereNotIn('status', [InvoiceStatus::Paid->value, InvoiceStatus::Void->value])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now()->toDateString())
            ->sum('balance_minor');

        return view('livewire.admin.sales.invoices-index', [
            'invoices' => $invoices,
            'counts' => $counts,
            'total' => (int) $counts->sum(),
            'overdue' => (int) $overdue,
            'chips' => [
                InvoiceStatus::Draft, InvoiceStatus::Issued, InvoiceStatus::PartiallyPaid,
                InvoiceStatus::Paid, InvoiceStatus::Void,
            ],
            'lakh' => app(SalesSettings::class)->lakh_grouping,
        ]);
    }
}
