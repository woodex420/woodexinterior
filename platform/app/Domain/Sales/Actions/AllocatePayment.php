<?php

declare(strict_types=1);

namespace App\Domain\Sales\Actions;

use App\Domain\Sales\Enums\InvoiceStatus;
use App\Domain\Sales\Models\Invoice;
use App\Domain\Sales\Models\Payment;
use App\Domain\Sales\Models\PaymentAllocation;
use Illuminate\Support\Facades\DB;

/**
 * Puts a payment against invoices (INV-005).
 *
 * Either the caller says where the money goes (invoice id => amount), or it is spread over the client's open
 * invoices oldest-due first — the way a studio works when a client sends one transfer for two jobs. Whatever does
 * not fit an invoice stays unallocated and shows as credit on the client's account.
 */
final class AllocatePayment
{
    /**
     * @param  array<int, int>  $allocations  invoice id => amount in minor units
     */
    public function handle(Payment $payment, array $allocations = []): Payment
    {
        return DB::transaction(function () use ($payment, $allocations): Payment {
            $left = (int) $payment->amount_minor - (int) $payment->allocations()->sum('amount_minor');

            if ($allocations !== [] && $left > 0) {
                foreach ($allocations as $invoiceId => $amount) {
                    if ($left <= 0) {
                        break;
                    }

                    $this->allocate($payment, (int) $invoiceId, min((int) $amount, $left));
                    $left = (int) $payment->amount_minor - (int) $payment->allocations()->sum('amount_minor');
                }
            }

            if ($allocations === [] && $left > 0) {
                $this->auto($payment, $left);
            }

            return $payment->refresh();
        });
    }

    /** Oldest due date first, then the oldest invoice: the oldest debt is settled first. */
    private function auto(Payment $payment, int $available): void
    {
        $invoices = Invoice::query()
            ->where('contact_id', $payment->contact_id)
            ->whereIn('status', [InvoiceStatus::Issued->value, InvoiceStatus::PartiallyPaid->value])
            ->where('balance_minor', '>', 0)
            ->orderByRaw('due_date is null, due_date')
            ->orderBy('id')
            ->get();

        $left = $available;

        foreach ($invoices as $invoice) {
            if ($left <= 0) {
                break;
            }

            $this->allocate($payment, $invoice->id, $left);
            $left = (int) $payment->amount_minor - (int) $payment->allocations()->sum('amount_minor');
        }
    }

    private function allocate(Payment $payment, int $invoiceId, int $amount): void
    {
        if ($amount <= 0) {
            return;
        }

        $invoice = Invoice::query()->find($invoiceId);

        if ($invoice === null) {
            return;
        }

        $applied = min($amount, (int) $invoice->balance_minor);

        if ($applied <= 0) {
            return;
        }

        PaymentAllocation::query()->create([
            'payment_id' => $payment->id,
            'invoice_id' => $invoice->id,
            'amount_minor' => $applied,
        ]);

        $invoice->applyPayment($applied);
    }
}
