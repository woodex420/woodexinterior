<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Services;

use App\Domain\Accounting\Enums\EntryStatus;
use App\Domain\Accounting\Models\JournalEntry;
use App\Domain\Crm\Enums\BusinessLine;
use App\Domain\Sales\Enums\InvoiceStatus;
use App\Domain\Sales\Enums\PaymentStatus;
use App\Domain\Sales\Models\Invoice;
use App\Domain\Sales\Models\Payment;
use App\Models\User;

/**
 * Posting rules: one row per document event (docs/17 §4.3). Nothing else in the application writes journal entries.
 *
 *   invoice issued   → Dr Accounts receivable (total) / Cr revenue (by business line) + Cr sales tax
 *   invoice voided   → reversal of the issued entry
 *   payment verified → Dr bank or cash (by method) / Cr accounts receivable
 *   payment rejected → reversal of the verified entry, when one exists
 */
final class LedgerPoster
{
    public function __construct(
        private readonly ChartOfAccounts $chart,
        private readonly PostJournalEntry $post,
        private readonly ReverseJournalEntry $reverse,
    ) {}

    public function invoiceIssued(Invoice $invoice, ?User $by = null): ?JournalEntry
    {
        if ($invoice->status === InvoiceStatus::Draft || $invoice->status === InvoiceStatus::Void) {
            return null;
        }

        $total = (int) $invoice->total_minor;

        if ($total <= 0) {
            return null;
        }

        $tax = max(0, (int) $invoice->tax_minor);
        $revenue = $total - $tax;

        $lines = [
            ['account' => $this->chart->byCode(ChartOfAccounts::RECEIVABLE), 'debit' => $total,
                'contact_id' => $invoice->contact_id, 'project_id' => $invoice->project_id,
                'business_line' => $invoice->business_line->value, 'memo' => 'Invoice '.$invoice->number],
            ['account' => $this->chart->byCode($this->revenueCode($invoice->business_line)), 'credit' => $revenue,
                'contact_id' => $invoice->contact_id, 'project_id' => $invoice->project_id,
                'business_line' => $invoice->business_line->value],
        ];

        if ($tax > 0) {
            $lines[] = ['account' => $this->chart->byCode(ChartOfAccounts::TAX_PAYABLE), 'credit' => $tax,
                'contact_id' => $invoice->contact_id, 'memo' => 'Sales tax on '.$invoice->number];
        }

        return $this->post->handle(
            date: $invoice->issue_date?->toDateString() ?? now()->toDateString(),
            memo: 'Invoice '.$invoice->number.' — '.$invoice->contact?->name,
            lines: $lines,
            sourceType: 'invoice',
            sourceId: (int) $invoice->id,
            sourceEvent: 'issued',
            by: $by,
        );
    }

    public function invoiceVoided(Invoice $invoice, ?User $by = null): ?JournalEntry
    {
        $entry = $this->entryFor('invoice', (int) $invoice->id, 'issued');

        return $entry === null ? null : $this->reverse->handle($entry, 'Void of invoice '.$invoice->number, $by);
    }

    public function paymentVerified(Payment $payment, ?User $by = null): ?JournalEntry
    {
        if ($payment->status !== PaymentStatus::Verified) {
            return null;
        }

        $amount = (int) $payment->amount_minor;

        if ($amount <= 0) {
            return null;
        }

        $account = $this->chart->byCode(
            $payment->method === 'cash' ? ChartOfAccounts::CASH : ChartOfAccounts::BANK,
        );

        return $this->post->handle(
            date: $payment->received_on?->toDateString() ?? now()->toDateString(),
            memo: 'Payment received — '.$payment->contact?->name.($payment->receipt_number ? ' · '.$payment->receipt_number : ''),
            lines: [
                ['account' => $account, 'debit' => $amount, 'contact_id' => $payment->contact_id,
                    'project_id' => $payment->project_id, 'memo' => $payment->reference],
                ['account' => $this->chart->byCode(ChartOfAccounts::RECEIVABLE), 'credit' => $amount,
                    'contact_id' => $payment->contact_id, 'project_id' => $payment->project_id],
            ],
            sourceType: 'payment',
            sourceId: (int) $payment->id,
            sourceEvent: 'verified',
            by: $by,
        );
    }

    public function paymentRejected(Payment $payment, ?User $by = null): ?JournalEntry
    {
        $entry = $this->entryFor('payment', (int) $payment->id, 'verified');

        return $entry === null ? null : $this->reverse->handle($entry, 'Payment set aside — '.($payment->rejected_reason ?? 'not received'), $by);
    }

    public function entryFor(string $type, int $id, string $event): ?JournalEntry
    {
        return JournalEntry::query()
            ->where('source_type', $type)
            ->where('source_id', $id)
            ->where('source_event', $event)
            ->where('status', EntryStatus::Posted)
            ->first();
    }

    private function revenueCode(BusinessLine $line): string
    {
        return match ($line) {
            BusinessLine::Furniture => ChartOfAccounts::REVENUE_FURNITURE,
            BusinessLine::Interior, BusinessLine::Project => ChartOfAccounts::REVENUE_INTERIOR,
        };
    }
}
