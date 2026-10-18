<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Sales;

use App\Domain\Sales\Actions\CollectPayment;
use App\Domain\Sales\Actions\SendPaymentReminder;
use App\Domain\Sales\Enums\InvoiceStatus;
use App\Domain\Sales\Enums\PaymentMethod;
use App\Domain\Sales\Models\Invoice;
use App\Livewire\Concerns\InteractsWithAdmin;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * P8.4: the invoice tracking board — grouped lists that read status and due_date, never invent
 * them. Overdue first. Collecting from the board is the P8.2 one-act CollectPayment (records,
 * verifies, receipts and books); reminding is the P8.2 consent-gated WhatsApp reminder.
 */
#[Layout('layouts::admin')]
#[Title('Invoice tracking')]
class InvoiceTrackingBoard extends Component
{
    use InteractsWithAdmin;

    public string $group = 'overdue';

    public int $collectId = 0;

    public string $collectAmount = '';

    public string $collectMethod = 'cash';

    public string $collectReference = '';

    public function render(): View
    {
        $open = Invoice::query()
            ->whereIn('status', [InvoiceStatus::Issued->value, InvoiceStatus::PartiallyPaid->value])
            ->where('balance_minor', '>', 0)
            ->with('contact:id,name')
            ->orderBy('due_date')
            ->limit(120)
            ->get();

        $today = now()->toDateString();
        $weekAhead = now()->addDays(7)->toDateString();

        $overdue = $open->filter(static fn (Invoice $invoice): bool => $invoice->due_date !== null && $invoice->due_date->format('Y-m-d') < $today);
        $dueSoon = $open->diffKeys($overdue)->filter(static fn (Invoice $invoice): bool => $invoice->due_date !== null && $invoice->due_date->format('Y-m-d') <= $weekAhead);
        $current = $open->diffKeys($overdue)->diffKeys($dueSoon);

        $paid = Invoice::query()
            ->where(fn ($query) => $query->where('status', InvoiceStatus::Paid->value)->orWhere('balance_minor', '<=', 0))
            ->whereIn('status', [InvoiceStatus::PartiallyPaid->value, InvoiceStatus::Paid->value])
            ->with('contact:id,name')
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get();

        $groups = [
            'overdue' => ['label' => 'Overdue', 'hint' => 'Past the due date with money outstanding — oldest first', 'invoices' => $overdue],
            'due_soon' => ['label' => 'Due this week', 'hint' => 'Due within the next seven days', 'invoices' => $dueSoon],
            'current' => ['label' => 'Issued, not due yet', 'hint' => 'Running invoices with a later due date', 'invoices' => $current],
            'partial' => ['label' => 'Partially paid', 'hint' => 'Money received, balance remains', 'invoices' => $open->filter(static fn (Invoice $invoice): bool => $invoice->status === InvoiceStatus::PartiallyPaid)],
            'paid' => ['label' => 'Recently settled', 'hint' => 'Fully paid, latest ten', 'invoices' => $paid],
        ];

        foreach ($groups as $key => $group) {
            $groups[$key]['count'] = $group['invoices']->count();
            $groups[$key]['minor'] = $group['invoices']->sum('balance_minor');
        }

        return view('livewire.admin.sales.invoice-tracking-board', [
            'groups' => $groups,
            'active' => $groups[$this->group]['invoices'] ?? collect(),
            'methods' => PaymentMethod::cases(),
            'canCollect' => $this->user()->can('payments.record') && $this->user()->can('payments.verify'),
            'canRemind' => $this->user()->can('invoices.view'),
        ]);
    }

    public function showGroup(string $group): void
    {
        $this->group = $group;
        $this->collectId = 0;
    }

    public function startCollect(int $invoiceId): void
    {
        abort_unless($this->user()->can('payments.record') && $this->user()->can('payments.verify'), 403);

        $invoice = Invoice::query()->findOrFail($invoiceId);

        $this->collectId = $invoice->id;
        $this->collectAmount = (string) round(((int) $invoice->balance_minor) / 100, 2);
        $this->collectMethod = 'cash';
        $this->collectReference = '';
    }

    public function collect(): void
    {
        abort_unless($this->user()->can('payments.record') && $this->user()->can('payments.verify'), 403);

        $invoice = Invoice::query()->findOrFail($this->collectId);

        $validated = $this->validate([
            'collectAmount' => ['required', 'numeric', 'min:0.01'],
            'collectMethod' => ['required', 'in:cash,bank_transfer,cheque,jazzcash,easypaisa,card,gateway,other'],
            'collectReference' => ['nullable', 'string', 'max:120'],
        ]);

        $amountMinor = (int) round(((float) $validated['collectAmount']) * 100);

        try {
            $payment = app(CollectPayment::class)->handle(
                $invoice,
                $amountMinor,
                PaymentMethod::from($validated['collectMethod']),
                $this->user(),
                $validated['collectReference'] ?? null,
            );
        } catch (ValidationException $exception) {
            $this->toastValidation($exception);

            return;
        }

        $remaining = (int) $invoice->refresh()->balance_minor;

        $this->collectId = 0;

        $this->toast(sprintf(
            'Receipt %s for %s — booked in the ledger. %s',
            $payment->receipt_number,
            Money::ofMinor($amountMinor)->format(),
            $remaining > 0 ? Money::ofMinor($remaining)->format().' is still outstanding.' : $invoice->number.' is fully settled.',
        ), 'success');
    }

    public function remind(int $invoiceId): void
    {
        $invoice = Invoice::query()->with('contact')->findOrFail($invoiceId);

        abort_unless($invoice->status === InvoiceStatus::Issued || $invoice->status === InvoiceStatus::PartiallyPaid, 404);

        $result = app(SendPaymentReminder::class)->handle($invoice, $this->user());

        if ($result['sent']) {
            $this->toast("Reminder sent to {$invoice->contact?->name} on WhatsApp.", 'success');
        } else {
            $this->toast('Not sent — '.$result['reason'], 'error');
        }
    }
}
