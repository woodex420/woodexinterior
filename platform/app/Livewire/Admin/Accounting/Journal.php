<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Accounting;

use App\Domain\Accounting\Exceptions\PeriodClosed;
use App\Domain\Accounting\Exceptions\UnbalancedEntry;
use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Models\JournalEntry;
use App\Domain\Accounting\Services\PostJournalEntry;
use App\Domain\Accounting\Services\ReverseJournalEntry;
use App\Livewire\Concerns\InteractsWithAdmin;
use App\Support\Money;
use App\Support\NumberInput;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Accounting → Journal (P9-A). Every entry is read-only once posted: corrections are reversals, never edits.
 * A manual entry exists for opening balances and the accountant's adjustments; it obeys the same balance and
 * period rules as the automatic postings.
 */
#[Layout('layouts::admin')]
#[Title('Journal')]
class Journal extends Component
{
    use InteractsWithAdmin, WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $status = '';

    public bool $showView = false;

    public bool $showEntry = false;

    public bool $showReverse = false;

    #[Locked]
    public ?int $viewingId = null;

    #[Locked]
    public ?int $reversingId = null;

    public string $entryDate = '';

    public string $entryMemo = '';

    /** @var list<array{account_id: string, debit: string, credit: string, memo: string}> */
    public array $rows = [];

    public string $reversalReason = '';

    public function mount(): void
    {
        abort_unless($this->user()->can('accounting.view'), 403);
        $this->entryDate = now()->toDateString();
        $this->rows = [$this->blankRow(), $this->blankRow()];
    }

    public function updating(string $name): void
    {
        if (in_array($name, ['search', 'status'], true)) {
            $this->resetPage();
        }
    }

    public function view(int $id): void
    {
        $this->viewingId = $id;
        $this->showView = true;
    }

    public function addRow(): void
    {
        if (count($this->rows) < 12) {
            $this->rows[] = $this->blankRow();
        }
    }

    public function removeRow(int $index): void
    {
        unset($this->rows[$index]);
        $this->rows = array_values($this->rows);

        if (count($this->rows) < 2) {
            $this->rows[] = $this->blankRow();
        }
    }

    public function post(PostJournalEntry $poster): void
    {
        abort_unless($this->user()->can('accounting.manage'), 403);

        $this->validate([
            'entryDate' => ['required', 'date'],
            'entryMemo' => ['required', 'string', 'max:255'],
            'rows.*.account_id' => ['required', 'integer', 'exists:accounts,id'],
            'rows.*.debit' => ['nullable', 'string', 'max:20'],
            'rows.*.credit' => ['nullable', 'string', 'max:20'],
        ], [], ['entryDate' => 'date', 'entryMemo' => 'memo']);

        $lines = [];

        foreach ($this->rows as $index => $row) {
            $debit = trim($row['debit']);
            $credit = trim($row['credit']);

            if ($debit === '' && $credit === '') {
                continue;
            }

            try {
                $lines[] = [
                    'account' => (int) $row['account_id'],
                    'debit' => $debit === '' ? 0 : NumberInput::rupees($debit),
                    'credit' => $credit === '' ? 0 : NumberInput::rupees($credit),
                    'memo' => $row['memo'] === '' ? null : $row['memo'],
                ];
            } catch (\InvalidArgumentException $e) {
                $this->addError("rows.{$index}.debit", $e->getMessage());

                return;
            }
        }

        if (count($lines) < 2) {
            $this->toast('An entry needs at least two lines.', 'error');

            return;
        }

        try {
            $entry = $poster->handle($this->entryDate, trim($this->entryMemo), $lines, by: $this->user());
        } catch (UnbalancedEntry|PeriodClosed $e) {
            $this->toast($e->getMessage(), 'error');

            return;
        } catch (ModelNotFoundException) {
            $this->toast('One of the accounts no longer exists.', 'error');

            return;
        }

        $this->showEntry = false;
        $this->reset(['entryMemo', 'rows']);
        $this->entryDate = now()->toDateString();
        $this->rows = [$this->blankRow(), $this->blankRow()];
        $this->toast('Posted '.$entry->number.'.');
    }

    public function confirmReverse(int $id): void
    {
        abort_unless($this->user()->can('accounting.manage'), 403);
        $this->reversingId = $id;
        $this->reversalReason = '';
        $this->showReverse = true;
    }

    public function reverse(ReverseJournalEntry $reverser): void
    {
        abort_unless($this->user()->can('accounting.manage'), 403);

        $entry = JournalEntry::query()->findOrFail($this->reversingId);
        $reverser->handle($entry, $this->reversalReason !== '' ? trim($this->reversalReason) : null, $this->user());

        $this->showReverse = false;
        $this->toast('Reversal posted. The original stays in the journal, marked reversed.');
    }

    /** @return array{account_id: string, debit: string, credit: string, memo: string} */
    private function blankRow(): array
    {
        return ['account_id' => '', 'debit' => '', 'credit' => '', 'memo' => ''];
    }

    public function render(): View
    {
        $entries = JournalEntry::query()
            ->with(['lines.account'])
            ->withCount('lines')
            ->when($this->search !== '', function ($q): void {
                $term = '%'.trim($this->search).'%';
                $q->where(fn ($q) => $q->where('number', 'like', $term)
                    ->orWhere('memo', 'like', $term)
                    ->orWhere('source_type', 'like', $term));
            })
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate(25);

        $viewing = $this->viewingId === null ? null : JournalEntry::query()->with(['lines.account', 'period', 'postedBy'])->find($this->viewingId);

        return view('livewire.admin.accounting.journal', [
            'entries' => $entries,
            'viewing' => $viewing,
            'accounts' => Account::query()->active()->orderBy('code')->get(),
            'format' => fn (int $minor): string => Money::ofMinor($minor)->format(),
        ]);
    }
}
