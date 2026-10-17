<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Accounting;

use App\Domain\Accounting\Enums\AccountSubtype;
use App\Domain\Accounting\Enums\AccountType;
use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Models\JournalLine;
use App\Domain\Accounting\Services\ChartOfAccounts as Chart;
use App\Livewire\Concerns\InteractsWithAdmin;
use App\Support\Money;
use App\Support\NumberInput;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * System → Chart of accounts (P9-A). System accounts can be renamed but never deleted: the posting rules refer to
 * their codes. The book value shown is the signed ledger balance, not the opening figure.
 */
#[Layout('layouts::admin')]
#[Title('Chart of accounts')]
class ChartOfAccounts extends Component
{
    use InteractsWithAdmin;

    public bool $showForm = false;

    #[Locked]
    public ?int $editingId = null;

    public string $formCode = '';

    public string $formName = '';

    public string $formType = 'expense';

    public string $formSubtype = 'other';

    public string $formOpening = '';

    public bool $formActive = true;

    public function mount(): void
    {
        abort_unless($this->user()->can('accounting.view'), 403);
        app(Chart::class)->ensure();
    }

    public function openCreate(): void
    {
        abort_unless($this->user()->can('accounting.manage'), 403);
        $this->resetForm();
        $this->showForm = true;
    }

    public function openEdit(int $id): void
    {
        abort_unless($this->user()->can('accounting.manage'), 403);
        $account = Account::query()->findOrFail($id);

        $this->resetForm();
        $this->editingId = $account->id;
        $this->formCode = $account->code;
        $this->formName = $account->name;
        $this->formType = $account->type->value;
        $this->formSubtype = $account->subtype->value;
        $this->formOpening = $account->opening_minor !== 0 ? NumberInput::fromMinor((int) $account->opening_minor) : '';
        $this->formActive = (bool) $account->is_active;
        $this->showForm = true;
    }

    public function save(): void
    {
        abort_unless($this->user()->can('accounting.manage'), 403);

        $this->validate([
            'formCode' => ['required', 'string', 'max:20', 'regex:/^[0-9A-Za-z.-]+$/'],
            'formName' => ['required', 'string', 'max:120'],
            'formType' => ['required', 'in:asset,liability,equity,income,expense'],
            'formSubtype' => ['required', 'in:'.implode(',', array_column(AccountSubtype::cases(), 'value'))],
            'formOpening' => ['nullable', 'string', 'max:20'],
            'formActive' => ['boolean'],
        ], [], ['formCode' => 'code', 'formName' => 'name', 'formType' => 'type', 'formSubtype' => 'subtype']);

        $code = strtoupper(trim($this->formCode));
        $clash = Account::query()->where('code', $code)->when($this->editingId, fn ($q) => $q->whereKeyNot($this->editingId))->exists();

        if ($clash) {
            $this->addError('formCode', 'Another account already uses this code.');

            return;
        }

        try {
            $opening = trim($this->formOpening) === '' ? 0 : NumberInput::rupees($this->formOpening);
        } catch (\InvalidArgumentException $e) {
            $this->addError('formOpening', $e->getMessage());

            return;
        }

        Account::query()->updateOrCreate(['id' => $this->editingId], [
            'code' => $code,
            'name' => trim($this->formName),
            'type' => $this->formType,
            'subtype' => $this->formSubtype,
            'opening_minor' => $opening,
            'is_active' => $this->formActive,
        ]);

        $this->showForm = false;
        $this->resetForm();
        $this->toast('Account saved.');
    }

    public function toggleActive(int $id): void
    {
        abort_unless($this->user()->can('accounting.manage'), 403);
        $account = Account::query()->findOrFail($id);

        if ($account->is_system) {
            $this->toast('System accounts are used by posting rules and cannot be hidden.', 'error');

            return;
        }

        $account->forceFill(['is_active' => ! $account->is_active])->save();
        $this->toast($account->is_active ? 'Account is available again.' : 'Account hidden from new entries.');
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->formCode = '';
        $this->formName = '';
        $this->formType = 'expense';
        $this->formSubtype = 'other';
        $this->formOpening = '';
        $this->formActive = true;
        $this->resetErrorBag();
    }

    public function render(): View
    {
        $accounts = Account::query()->orderBy('code')->get();
        $balances = JournalLine::query()
            ->selectRaw('account_id, COALESCE(SUM(debit_minor),0) AS debit, COALESCE(SUM(credit_minor),0) AS credit')
            ->groupBy('account_id')
            ->get()
            ->keyBy('account_id');

        $signed = $accounts->mapWithKeys(fn (Account $a): array => [
            $a->id => $a->type->signed((int) ($balances[$a->id]->debit ?? 0), (int) ($balances[$a->id]->credit ?? 0)),
        ]);

        $groups = collect(AccountType::cases())->map(fn (AccountType $type): array => [
            'type' => $type,
            'accounts' => $accounts->where('type', $type)->values(),
            'total' => $accounts->where('type', $type)->sum(fn (Account $a): int => (int) ($signed[$a->id] ?? 0)),
        ]);

        return view('livewire.admin.accounting.chart-of-accounts', [
            'groups' => $groups,
            'signed' => $signed,
            'format' => fn (int $minor): string => Money::ofMinor($minor)->format(),
        ]);
    }
}
