<?php

declare(strict_types=1);

namespace App\Domain\Sales\Services;

use App\Domain\Crm\Enums\BusinessLine;
use App\Domain\Sales\Models\Invoice;
use App\Domain\Sales\Models\InvoiceItem;
use App\Settings\BusinessSettings;
use App\Support\AmountInWords;
use App\Support\Money;

/**
 * The client-facing content of an invoice, frozen when it is issued (INV-003).
 *
 * The PDF, the client's payment link and the reminder messages all read this array and never the live invoice, so
 * editing a bank account or a rate later cannot change a document that has already been sent.
 */
final class InvoiceSnapshot
{
    public const int SCHEMA = 1;

    public function __construct(private readonly BusinessSettings $business) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Invoice $invoice): array
    {
        $invoice->loadMissing(['items', 'contact', 'company', 'bankAccount', 'project', 'quote']);

        $business = [
            'name' => $invoice->business_line === BusinessLine::Furniture ? $this->business->furniture_brand_name : $this->business->brand_name,
            'legal_name' => $this->business->legal_name,
            'address' => $this->business->address,
            'city' => $this->business->city,
            'phone' => $this->business->phone,
            'email' => $this->business->email,
            'website' => $this->business->website,
            'ntn' => $this->business->ntn,
            'tagline' => $this->business->tagline,
            'whatsapp' => $this->business->whatsapp,
        ];

        $bank = $invoice->bankAccount;
        $quoteVersion = $invoice->quote?->versions()->reorder('revision', 'desc')->first();
        $documentDesign = $quoteVersion !== null
            ? ($quoteVersion->snapshot['document_design'] ?? null)
            : ($invoice->quote !== null ? ($invoice->quote->meta['document_design'] ?? null) : app(DocumentStyles::class)->copy($invoice->business_line));

        $client = [
            'name' => $invoice->contact->name,
            'company' => $invoice->company?->name,
            'phone' => $invoice->contact->phone,
            'email' => $invoice->contact->email,
            'address' => $invoice->contact->address,
            'city' => $invoice->contact->city,
        ];

        $items = $invoice->items
            ->sortBy('position')
            ->values()
            ->map(fn (InvoiceItem $item): array => [
                'description' => $item->description,
                'unit' => $item->unit,
                'qty' => (float) $item->qty,
                'rate' => Money::ofMinor((int) $item->rate_minor, $invoice->currency)->format(),
                'discount' => (int) $item->discount_minor,
                'tax_bp' => (int) $item->tax_bp,
                'line_total' => Money::ofMinor((int) $item->line_total_minor, $invoice->currency)->format(),
            ])->all();

        $total = Money::ofMinor((int) $invoice->total_minor, $invoice->currency);

        return [
            'schema' => self::SCHEMA,
            'document_design' => $documentDesign,
            'number' => $invoice->number,
            'kind' => $invoice->kind->value,
            'status' => $invoice->status->value,
            'currency' => $invoice->currency,
            'issue_date' => $invoice->issue_date?->format('j M Y'),
            'due_date' => $invoice->due_date?->format('j M Y'),
            'business' => $business,
            'client' => $client,
            'bank' => $bank === null ? null : [
                'title' => $bank->title,
                'bank_name' => $bank->bank_name,
                'account_number' => $bank->account_number,
                'iban' => $bank->iban,
                'branch' => $bank->branch,
                'wallet_provider' => $bank->wallet_provider,
                'kind' => $bank->kind,
            ],
            'project' => $invoice->project === null ? null : [
                'code' => $invoice->project->code,
                'name' => $invoice->project->name,
            ],
            'quote' => $invoice->quote === null ? null : [
                'number' => $invoice->quote->number,
                'title' => $invoice->quote->title,
                'total' => Money::ofMinor((int) $invoice->quote->total_minor, $invoice->currency)->format(),
            ],
            'items' => $items,
            'subtotal' => Money::ofMinor((int) $invoice->subtotal_minor, $invoice->currency)->format(),
            'discount' => Money::ofMinor((int) $invoice->discount_minor, $invoice->currency)->format(),
            'tax' => Money::ofMinor((int) $invoice->tax_minor, $invoice->currency)->format(),
            'tax_bp' => (int) $invoice->items->max('tax_bp'),
            'total' => $total->format(),
            'paid' => Money::ofMinor((int) $invoice->paid_minor, $invoice->currency)->format(),
            'balance' => Money::ofMinor((int) $invoice->balance_minor, $invoice->currency)->format(),
            'total_in_words' => AmountInWords::rupees($total),
            'notes' => $invoice->notes,
            'terms' => $invoice->terms,
        ];
    }
}
