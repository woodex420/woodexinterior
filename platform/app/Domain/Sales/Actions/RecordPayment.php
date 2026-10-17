<?php

declare(strict_types=1);

namespace App\Domain\Sales\Actions;

use App\Domain\Crm\Models\Contact;
use App\Domain\Sales\Enums\PaymentMethod;
use App\Domain\Sales\Enums\PaymentStatus;
use App\Domain\Sales\Mail\PaymentMail;
use App\Domain\Sales\Models\Payment;
use App\Domain\Sales\Notifications\PaymentNotification;
use App\Domain\Sales\Services\ReceiptPdf;
use App\Models\User;
use App\Support\Numbering;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

/**
 * Money the studio received (INV-005): cash at the site, a transfer the owner saw in the bank app, a cheque handed
 * over at a meeting. Staff record it and it is verified at once — a receipt goes out with it.
 *
 * @see SubmitPaymentProof for the money a client claims to have sent, which waits for the accountant.
 */
final class RecordPayment
{
    public function __construct(
        private readonly AllocatePayment $allocate,
        private readonly Numbering $numbering,
        private readonly ReceiptPdf $receipts,
    ) {}

    /**
     * @param  array<string, mixed>  $input  contact_id, amount_minor, method, received_on, reference, bank_account_id,
     *                                       project_id, notes, and either invoice_id or allocations
     *                                       (invoice id => amount; empty allocates to the oldest open invoices)
     */
    public function handle(array $input, User $by): Payment
    {
        $contact = Contact::query()->findOrFail((int) $input['contact_id']);

        return DB::transaction(function () use ($input, $by, $contact): Payment {
            $payment = Payment::query()->create([
                'contact_id' => $contact->id,
                'invoice_id' => $input['invoice_id'] ?? null,
                'project_id' => $input['project_id'] ?? null,
                'amount_minor' => (int) $input['amount_minor'],
                'received_on' => $input['received_on'],
                'method' => PaymentMethod::from((string) $input['method']),
                'reference' => $input['reference'] ?? null,
                'bank_account_id' => $input['bank_account_id'] ?? null,
                'status' => PaymentStatus::Verified,
                'submitted_by' => 'staff',
                'created_by' => $by->id,
                'verified_by' => $by->id,
                'verified_at' => now(),
                'notes' => $input['notes'] ?? null,
            ]);

            $allocations = isset($input['invoice_id'])
                ? [(int) $input['invoice_id'] => (int) $input['amount_minor']]
                : [];

            $this->allocate->handle($payment, $allocations);

            return $this->receipt($payment, $by);
        });
    }

    /** The receipt for a confirmed payment (INV-007): number, PDF, the client's copy by email, the team alerted. */
    public function receipt(Payment $payment, User $by): Payment
    {
        $payment->forceFill([
            'receipt_number' => $this->numbering->next('receipt'),
            'verified_by' => $by->id,
            'verified_at' => now(),
        ])->save();

        $bytes = $this->receipts->render($payment->fresh(['contact', 'allocations.invoice', 'bankAccount']));
        $path = 'receipts/'.$payment->public_id.'/'.ReceiptPdf::filename($payment);

        Storage::disk('local')->put($path, $bytes);

        $payment->forceFill(['receipt_pdf_path' => $path])->save();

        $payment->loadMissing('contact');

        if (filled($payment->contact?->email)) {
            Mail::to((string) $payment->contact->email)->queue(new PaymentMail($payment->id, 'verified'));
        }

        Notification::send(
            User::query()->with('telegramLink')->permission('invoices.view')->where('is_active', true)->get(),
            new PaymentNotification($payment, 'verified'),
        );

        return $payment;
    }
}
