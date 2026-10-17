<?php

declare(strict_types=1);

namespace App\Domain\Sales\Actions;

use App\Domain\Accounts\Actions\PostInvoiceToLedger;
use App\Domain\Crm\Enums\BusinessLine;
use App\Domain\Sales\Enums\InvoiceKind;
use App\Domain\Sales\Enums\InvoiceStatus;
use App\Domain\Sales\Enums\QuoteStatus;
use App\Domain\Sales\Exceptions\QuoteActionDenied;
use App\Domain\Sales\Models\BankAccount;
use App\Domain\Sales\Models\Invoice;
use App\Domain\Sales\Models\InvoiceItem;
use App\Domain\Sales\Models\Quote;
use App\Domain\Sales\Services\InvoiceNumbering;
use App\Domain\Sales\Services\InvoicePdf;
use App\Domain\Sales\Services\InvoiceSnapshot;
use App\Models\User;
use App\Settings\SalesSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * The advance invoice that follows an accepted quotation (QUO-011, INV-001).
 *
 * The quotation's milestone with the `on_acceptance` trigger becomes the first invoice: 50% on an execution job,
 * 75% on a design job, whatever the owner set on a custom one. Its number is the quotation number with a suffix
 * (`WI-10123-1`), the bank account is the one for the business line, and it lands as a draft or as issued
 * according to Settings — never sent without the owner's say-so unless they asked for that too.
 *
 * Called once per milestone: accepting a quotation twice does not raise a second invoice.
 */
final class RaiseAdvanceInvoice
{
    public function __construct(
        private readonly SalesSettings $sales,
        private readonly InvoiceSnapshot $snapshot,
        private readonly InvoicePdf $pdf,
        private readonly PostInvoiceToLedger $postToLedger,
    ) {}

    public function handle(Quote $quote, ?User $by = null): ?Invoice
    {
        return DB::transaction(function () use ($quote, $by): ?Invoice {
            $quote = Quote::query()->whereKey($quote->id)->lockForUpdate()->firstOrFail();

            if ($quote->status !== QuoteStatus::Accepted) {
                throw new QuoteActionDenied('Only an accepted quotation can raise an advance invoice.');
            }

            $milestone = $quote->milestones()->where('trigger', 'on_acceptance')->first();
            if ($milestone === null) {
                return null;
            }

            $project = $quote->project()->lockForUpdate()->firstOrFail();
            $projectMilestone = $project->milestones()->where('quote_milestone_id', $milestone->id)->lockForUpdate()->firstOrFail();
            $existing = Invoice::withTrashed()->where('project_milestone_id', $projectMilestone->id)->first();
            if ($existing !== null) {
                if ($existing->trashed()) {
                    throw new QuoteActionDenied('This milestone already has an archived invoice. Ask Accounts to review it.');
                }

                return $existing;
            }

            $number = InvoiceNumbering::next($project);
            $percent = (int) $milestone->percent_bp;
            $amount = (int) $milestone->amount_minor;

            // Tax keeps the quotation's rate: an advance is a slice of the same job, not a different supply.
            $taxMinor = (int) round($amount * (int) $quote->tax_bp / (10000 + (int) $quote->tax_bp));
            $subtotal = $amount - $taxMinor;

            $issued = $this->sales->advance_invoice_mode !== 'draft';

            $invoice = Invoice::query()->create([
                'number' => $number,
                'kind' => InvoiceKind::Milestone,
                'status' => $issued ? InvoiceStatus::Issued : InvoiceStatus::Draft,
                'contact_id' => $quote->contact_id,
                'company_id' => $quote->company_id,
                'quote_id' => $quote->id,
                'project_id' => $project->id,
                'project_milestone_id' => $projectMilestone->id,
                'business_line' => $quote->business_line,
                'bank_account_id' => $this->bankAccountFor($quote->business_line)?->id,
                'currency' => $quote->currency,
                'subtotal_minor' => $subtotal,
                'discount_minor' => 0,
                'tax_minor' => $taxMinor,
                'total_minor' => $amount,
                'paid_minor' => 0,
                'balance_minor' => $amount,
                'issue_date' => $issued ? today() : null,
                'due_date' => $issued ? today()->addDays((int) $this->sales->invoice_due_days) : null,
                'terms' => $this->sales->invoice_terms,
                'notes' => sprintf(
                    '%s of quotation %s (%s).',
                    rtrim(rtrim(number_format($percent / 100, 2), '0'), '.').'% '.$milestone->name,
                    $quote->number,
                    $quote->title,
                ),
                'created_by' => $by?->id,
            ]);

            InvoiceItem::query()->create([
                'invoice_id' => $invoice->id,
                'description' => sprintf('%s — %s of quotation %s', $milestone->name, rtrim(rtrim(number_format($percent / 100, 2), '0'), '.').'%', $quote->number),
                'unit' => 'job',
                'qty' => 1,
                'rate_minor' => $subtotal,
                'discount_minor' => 0,
                'tax_bp' => (int) $quote->tax_bp,
                'line_total_minor' => $amount,
                'position' => 0,
            ]);

            $projectMilestone->forceFill(['due_date' => $invoice->due_date])->save();

            // An issued invoice is frozen: its PDF and snapshot are stored with it…
            if ($issued) {
                $this->freeze($invoice);

                // …and booked: receivable + revenue land in the ledger in the same act (P8.2).
                $this->postToLedger->handle($invoice->refresh(), $by);
            }

            return $invoice;
        });
    }

    /** The PDF and the snapshot of an invoice the client will see (INV-003). */
    public function freeze(Invoice $invoice): Invoice
    {
        return DB::transaction(function () use ($invoice): Invoice {
            $invoice = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if ($invoice->snapshot !== null || $invoice->pdf_path !== null || $invoice->pdf_sha256 !== null) {
                if ($invoice->snapshot === null || $invoice->pdf_path === null || $invoice->pdf_sha256 === null
                    || ! Storage::disk('local')->exists($invoice->pdf_path)
                    || ! hash_equals($invoice->pdf_sha256, hash('sha256', Storage::disk('local')->get($invoice->pdf_path)))) {
                    throw new RuntimeException('The frozen invoice needs a reviewed document repair.');
                }

                return $invoice;
            }

            if ($invoice->status !== InvoiceStatus::Issued) {
                throw new QuoteActionDenied('Only an issued invoice can be frozen.');
            }

            $snapshot = $this->snapshot->build($invoice->load(['items', 'contact', 'company', 'bankAccount', 'project', 'quote']));
            $bytes = $this->pdf->render($snapshot);
            $path = 'invoices/'.$invoice->public_id.'/'.InvoicePdf::filename($invoice);

            if (! Storage::disk('local')->put($path, $bytes)) {
                throw new RuntimeException('The invoice PDF could not be stored.');
            }

            $invoice->forceFill([
                'snapshot' => $snapshot,
                'pdf_path' => $path,
                'pdf_sha256' => hash('sha256', $bytes),
            ])->save();

            return $invoice;
        });
    }

    /** Interior → "WOODEX INTERIOR", Furniture → "WOODEX FURNITURE" (INV-004). */
    private function bankAccountFor(BusinessLine $line): ?BankAccount
    {
        return BankAccount::query()->forLine($line)->first();
    }
}
