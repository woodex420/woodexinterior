<?php

declare(strict_types=1);

namespace App\Domain\Sales\Actions;

use App\Domain\Sales\Enums\PaymentMethod;
use App\Domain\Sales\Enums\PaymentStatus;
use App\Domain\Sales\Models\Invoice;
use App\Domain\Sales\Models\Payment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * A client says they have paid (INV-006): the amount, the method, a reference and a screenshot or a bank slip.
 *
 * Nothing is allocated yet and no receipt is issued — the payment waits for the accountant, who either confirms it
 * (`VerifyPayment`) or sends it back with a reason (`RejectPayment`).
 */
final class SubmitPaymentProof
{
    /**
     * @param  array<string, mixed>  $input  amount_minor, method, reference, received_on, notes
     */
    public function handle(Invoice $invoice, array $input, ?UploadedFile $proof = null): Payment
    {
        return DB::transaction(function () use ($invoice, $input, $proof): Payment {
            $payment = Payment::query()->create([
                'contact_id' => $invoice->contact_id,
                'invoice_id' => $invoice->id,
                'project_id' => $invoice->project_id,
                'amount_minor' => (int) $input['amount_minor'],
                'received_on' => $input['received_on'],
                'method' => PaymentMethod::tryFrom((string) $input['method']) ?? PaymentMethod::BankTransfer,
                'reference' => $input['reference'] ?? null,
                'bank_account_id' => $invoice->bank_account_id,
                'status' => PaymentStatus::PendingVerification,
                'submitted_by' => 'client',
                'notes' => $input['notes'] ?? null,
            ]);

            if ($proof !== null) {
                $payment->addMedia($proof)->toMediaCollection('files');
            }

            return $payment;
        });
    }
}
