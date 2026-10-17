<?php

declare(strict_types=1);

namespace App\Domain\Projects\Actions;

use App\Domain\Projects\Models\ProjectMilestone;
use App\Domain\Sales\Enums\InvoiceKind;
use App\Domain\Sales\Enums\InvoiceStatus;
use App\Domain\Sales\Models\BankAccount;
use App\Domain\Sales\Models\Invoice;
use App\Domain\Sales\Models\InvoiceItem;
use App\Domain\Sales\Services\InvoicePdf;
use App\Domain\Sales\Services\InvoiceSnapshot;
use App\Models\User;
use App\Settings\SalesSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * The invoice a milestone unlocks (INV-002, PRJ-003): "civil + wood work complete" asks for the 40%.
 *
 * It always lands as a **draft**. A milestone reached on site is the team's word that the money is due, and the
 * owner still checks the number and sends it — nothing reaches a client without a person saying so (D-81).
 * One invoice per milestone, however often the button is pressed.
 */
final class RaiseMilestoneInvoice
{
    public function __construct(
        private readonly SalesSettings $sales,
        private readonly InvoiceSnapshot $snapshot,
        private readonly InvoicePdf $pdf,
    ) {}

    public function handle(ProjectMilestone $milestone, User $by, bool $issue = false): Invoice
    {
        $milestone->loadMissing(['project.quote', 'project.contact', 'project.company']);

        $existing = Invoice::query()->where('project_milestone_id', $milestone->id)->first();

        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(function () use ($milestone, $by, $issue): Invoice {
            $project = $milestone->project;
            $quote = $project->quote;

            $percent = (int) $milestone->percent_bp;
            $amount = (int) $milestone->amount_minor;
            $taxBp = $quote === null ? 0 : (int) $quote->tax_bp;

            $taxMinor = $taxBp > 0 ? (int) round($amount * $taxBp / (10000 + $taxBp)) : 0;
            $subtotal = $amount - $taxMinor;

            $sequence = Invoice::query()->where('project_id', $project->id)->withTrashed()->count() + 1;

            $invoice = Invoice::query()->create([
                'number' => $project->code.'-'.$sequence,
                'kind' => InvoiceKind::Milestone,
                'status' => $issue ? InvoiceStatus::Issued : InvoiceStatus::Draft,
                'contact_id' => $project->contact_id,
                'company_id' => $project->company_id,
                'quote_id' => $project->quote_id,
                'project_id' => $project->id,
                'project_milestone_id' => $milestone->id,
                'business_line' => $project->business_line,
                'bank_account_id' => BankAccount::query()->forLine($project->business_line)->first()?->id,
                'subtotal_minor' => $subtotal,
                'discount_minor' => 0,
                'tax_minor' => $taxMinor,
                'total_minor' => $amount,
                'paid_minor' => 0,
                'balance_minor' => $amount,
                'issue_date' => $issue ? today() : null,
                'due_date' => $issue ? today()->addDays((int) $this->sales->invoice_due_days) : null,
                'terms' => $this->sales->invoice_terms,
                'notes' => sprintf('%s of %s (%s).', $milestone->name, $project->code, $project->name),
                'created_by' => $by->id,
            ]);

            InvoiceItem::query()->create([
                'invoice_id' => $invoice->id,
                'description' => sprintf(
                    '%s — %s of %s',
                    $milestone->name,
                    rtrim(rtrim(number_format($percent / 100, 2), '0'), '.').'%',
                    $project->code,
                ),
                'unit' => 'job',
                'qty' => 1,
                'rate_minor' => $subtotal,
                'discount_minor' => 0,
                'tax_bp' => $taxBp,
                'line_total_minor' => $amount,
                'position' => 0,
            ]);

            if ($issue) {
                $snapshot = $this->snapshot->build($invoice->fresh(['items', 'contact', 'company', 'bankAccount', 'project', 'quote']));
                $bytes = $this->pdf->render($snapshot);
                $path = 'invoices/'.$invoice->public_id.'/'.InvoicePdf::filename($invoice);

                Storage::disk('local')->put($path, $bytes);

                $invoice->forceFill([
                    'snapshot' => $snapshot,
                    'pdf_path' => $path,
                    'pdf_sha256' => hash('sha256', $bytes),
                ])->save();
            }

            return $invoice;
        });
    }
}
