<?php

declare(strict_types=1);

namespace App\Domain\Sales\Actions;

use App\Domain\Sales\Enums\PaymentStatus;
use App\Domain\Sales\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The accountant confirms the money is in the account (INV-005/006/007): the payment is allocated to the invoices it
 * settles, a receipt is numbered and written, and the client gets their copy by email.
 */
final class VerifyPayment
{
    public function __construct(
        private readonly AllocatePayment $allocate,
        private readonly RecordPayment $record,
    ) {}

    public function handle(Payment $payment, User $by): Payment
    {
        if ($payment->status !== PaymentStatus::PendingVerification) {
            return $payment;
        }

        return DB::transaction(function () use ($payment, $by): Payment {
            $payment->forceFill([
                'status' => PaymentStatus::Verified,
                'verified_by' => $by->id,
                'verified_at' => now(),
            ])->save();

            // The invoice it came in against first, then whatever else is open and unpaid.
            $allocations = $payment->invoice_id !== null
                ? [$payment->invoice_id => (int) $payment->amount_minor]
                : [];

            $this->allocate->handle($payment, $allocations);

            // The receipt is numbered and written, and the client gets their copy (INV-007).
            return $this->record->receipt($payment, $by)->refresh();
        });
    }
}
