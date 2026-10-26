<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Actions;

use App\Domain\Accounts\Enums\BillStatus;
use App\Domain\Accounts\Exceptions\JournalPostingFailed;
use App\Domain\Accounts\Models\Account;
use App\Domain\Accounts\Models\Bill;
use App\Domain\Accounts\Models\BillPayment;
use App\Domain\Sales\Enums\PaymentMethod;
use App\Models\User;
use App\Support\Money;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Money leaves the bank against a received invoice (slice I). Dr Accounts payable / Cr the method's cash or
 * bank account, one entry per payment, idempotent per payment row. Part payments are normal: the bill stays
 * part-paid until the last rupee lands.
 */
final class PayBill
{
    public function __construct(
        private readonly ResolveMethodAccount $resolveMethod,
    ) {}

    public function handle(Bill $bill, int $amountMinor, PaymentMethod $method, User $actor, ?string $reference = null, ?string $paidOn = null): BillPayment
    {
        $actor = User::query()->findOrFail($actor->id);

        if (! $actor->is_active || ! $actor->can('accounts.manage')) {
            throw new AuthorizationException;
        }

        if ($amountMinor <= 0) {
            throw ValidationException::withMessages(['amount' => 'The amount paid must be positive.']);
        }

        return DB::transaction(function () use ($bill, $amountMinor, $method, $actor, $reference, $paidOn): BillPayment {
            $bill = Bill::query()->lockForUpdate()->findOrFail($bill->id);

            if (! $bill->isOpen()) {
                throw ValidationException::withMessages(['bill' => "Bill {$bill->number} is {$bill->status->label()} and cannot take a payment."]);
            }

            if ($amountMinor > $bill->balanceMinor()) {
                throw ValidationException::withMessages([
                    'amount' => sprintf(
                        'Bill %s has only %s left — pay that amount or record the rest as a separate bill.',
                        $bill->number,
                        $bill->balance()->format(),
                    ),
                ]);
            }

            $paidOn = $paidOn ?? today()->toDateString();
            $methodAccount = $this->resolveMethod->handle($method);
            $payable = Account::query()->where('code', '2010')->where('is_active', true)->first();

            if ($payable === null) {
                throw new JournalPostingFailed('Account 2010 (Accounts payable) is missing from the chart. Restore it before paying bills.');
            }

            $entry = app(PostJournalEntry::class)->handle($actor, [
                ['account_id' => $payable->id, 'debit_minor' => $amountMinor, 'memo' => "Bill {$bill->number} — paid by {$method->label()}"],
                ['account_id' => $methodAccount->id, 'credit_minor' => $amountMinor, 'memo' => "Bill {$bill->number} — paid by {$method->label()}"],
            ], [
                'source_type' => 'bill_payment',
                'source_id' => null,
                'business_date' => $paidOn,
                'reference' => $reference ?? $bill->number,
                'note' => "Payment for received invoice {$bill->number}.",
            ]);

            $payment = BillPayment::query()->create([
                'bill_id' => $bill->id,
                'amount_minor' => $amountMinor,
                'paid_on' => $paidOn,
                'method' => $method,
                'reference' => $reference !== null && trim($reference) !== '' ? trim($reference) : null,
                'journal_entry_id' => $entry->id,
                'created_by' => $actor->id,
            ]);

            $paid = (int) $bill->paid_minor + $amountMinor;
            $bill->update([
                'paid_minor' => $paid,
                'status' => $paid >= (int) $bill->total_minor ? BillStatus::Paid : BillStatus::PartiallyPaid,
            ]);

            return $payment;
        });
    }
}
