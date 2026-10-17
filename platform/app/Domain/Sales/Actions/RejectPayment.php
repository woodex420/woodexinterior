<?php

declare(strict_types=1);

namespace App\Domain\Sales\Actions;

use App\Domain\Accounting\Services\LedgerPoster;
use App\Domain\Sales\Enums\PaymentStatus;
use App\Domain\Sales\Mail\PaymentMail;
use App\Domain\Sales\Models\Payment;
use App\Domain\Sales\Notifications\PaymentNotification;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

/**
 * The accountant did not see the money (INV-006): the payment is set aside with a reason, nothing is allocated, and
 * the client is told what is missing so they can send it again.
 */
final class RejectPayment
{
    public function __construct(private readonly LedgerPoster $ledger) {}

    public function handle(Payment $payment, string $reason, User $by): Payment
    {
        return DB::transaction(function () use ($payment, $reason, $by): Payment {
            $payment->forceFill([
                'status' => PaymentStatus::Rejected,
                'rejected_reason' => trim($reason),
                'verified_by' => $by->id,
                'verified_at' => now(),
            ])->save();

            // If it had been posted, set the ledger straight with a reversal (nothing is edited).
            $this->ledger->paymentRejected($payment, $by);

            $payment->loadMissing('contact');

            if (filled($payment->contact?->email)) {
                Mail::to((string) $payment->contact->email)->queue(new PaymentMail($payment->id, 'rejected'));
            }

            Notification::send(
                User::query()->with('telegramLink')->permission('invoices.view')->where('is_active', true)->get(),
                new PaymentNotification($payment, 'rejected'),
            );

            return $payment->refresh();
        });
    }
}
