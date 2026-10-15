<?php

declare(strict_types=1);

namespace App\Domain\Sales\Services;

use App\Domain\Crm\Enums\BusinessLine;
use App\Domain\Sales\Enums\DiscountType;
use App\Domain\Sales\Enums\Unit;
use App\Domain\Sales\Models\BankAccount;
use App\Domain\Sales\Models\Quote;
use App\Domain\Sales\Models\QuoteItem;
use App\Domain\Sales\Models\QuoteMilestone;
use App\Domain\Sales\Models\QuoteSection;
use App\Settings\BusinessSettings;
use App\Settings\SalesSettings;
use App\Support\AmountInWords;
use App\Support\Html;
use App\Support\Money;

/**
 * The client-facing content of a quotation, frozen at send time (D-15, QUO-008). The PDF, the client page and the
 * revision diff all read this array, never the live (editable) quote. Internal costs and margins are not included:
 * clients see snapshots.
 */
final class QuoteSnapshot
{
    public const int SCHEMA = 1;

    public function __construct(
        private readonly BusinessSettings $business,
        private readonly SalesSettings $sales,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Quote $quote, ?int $revision = null): array
    {
        $quote->loadMissing(['sections.items', 'milestones', 'company', 'owner:id,name', 'contact' => fn ($query) => $query->withTrashed()]);
        $contact = $quote->contact;
        $furniture = $quote->business_line === BusinessLine::Furniture;

        $discountLabel = 'Discount';
        if ($quote->discount_type === DiscountType::Percent && $quote->discount_value > 0) {
            $discountLabel .= ' ('.rtrim(rtrim(number_format($quote->discount_value / 100, 2), '0'), '.').'%)';
        }

        return [
            'schema' => self::SCHEMA,
            'number' => $quote->number,
            'revision' => $revision ?? (int) $quote->revision,
            'label' => $quote->number.(($revision ?? $quote->revision) > 0 ? ' R'.($revision ?? $quote->revision) : '')
                .($quote->option_label !== '' && $quote->option_label !== null ? ' · '.$quote->option_label : ''),
            'title' => $quote->title,
            'business_line' => $quote->business_line->value,
            'issued_on' => now()->toDateString(),
            'valid_until' => $quote->valid_until?->toDateString(),
            'currency' => $quote->currency,
            'client' => [
                'name' => $contact->name,
                'company' => $quote->company->name ?? $contact->company?->name,
                'phone' => $contact->phone,
                'email' => $contact->email,
                'address' => trim(implode(', ', array_filter([$contact->area ?? null, $contact->city ?? null]))),
            ],
            'scope_html' => Html::sanitize($quote->scope_html),
            'sections' => $quote->sections->map(fn (QuoteSection $section): array => [
                'name' => $section->name,
                'note' => $section->note,
                'subtotal_minor' => (int) $section->subtotal_minor,
                'items' => $section->items->map(fn (QuoteItem $item): array => [
                    'description' => $item->description,
                    'unit' => $item->unit,
                    'unit_label' => Unit::normalize($item->unit)->label(),
                    'qty' => (string) $item->qty,
                    'rate_minor' => (int) $item->rate_minor,
                    'discount_minor' => (int) $item->discount_minor,
                    'line_total_minor' => (int) $item->line_total_minor,
                    'is_optional' => (bool) $item->is_optional,
                ])->values()->all(),
            ])->values()->all(),
            'totals' => [
                'subtotal_minor' => (int) $quote->subtotal_minor,
                'discount_label' => $discountLabel,
                'discount_minor' => (int) $quote->discount_minor,
                'other_charges_label' => $quote->other_charges_label ?: 'Other charges',
                'other_charges_minor' => (int) $quote->other_charges_minor,
                'tax_bp' => (int) $quote->tax_bp,
                'tax_minor' => (int) $quote->tax_minor,
                'rounding_minor' => (int) $quote->rounding_minor,
                'total_minor' => (int) $quote->total_minor,
                'total_words' => AmountInWords::rupees(Money::ofMinor((int) $quote->total_minor)),
            ],
            'milestones' => $quote->milestones->map(fn (QuoteMilestone $milestone): array => [
                'name' => $milestone->name,
                'percent_bp' => (int) $milestone->percent_bp,
                'amount_minor' => (int) $milestone->amount_minor,
            ])->values()->all(),
            'terms' => array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $quote->terms) ?: []))),
            'notes' => trim((string) $quote->notes),
            'company' => [
                'brand' => $furniture ? $this->business->furniture_brand_name : $this->business->brand_name,
                'legal_name' => $this->business->legal_name,
                'tagline' => $this->business->tagline,
                'address' => self::withCity($this->business->address, $this->business->city),
                'phone' => $this->business->phone,
                'whatsapp' => $this->business->whatsapp,
                'email' => $this->business->email,
                'website' => $this->business->website,
                'ntn' => $this->business->ntn,
            ],
            'bank_accounts' => BankAccount::query()->forLine($quote->business_line)->limit(3)->get()
                ->map(fn (BankAccount $account): array => [
                    'title' => $account->title,
                    'bank_name' => $account->bank_name,
                    'account_number' => $account->account_number,
                    'iban' => $account->iban,
                    'branch' => $account->branch,
                ])->values()->all(),
            'signatories' => array_values($quote->signatories ?: $this->sales->signatories),
            'prepared_by' => $quote->owner?->name,
        ];
    }

    /** "…, Lahore" + city "Lahore" stays "…, Lahore" (the settings address often ends with the city already). */
    private static function withCity(string $address, string $city): string
    {
        $address = trim($address, " ,\t");
        $city = trim($city);

        if ($city === '' || preg_match('/(^|[\s,])'.preg_quote($city, '/').'$/iu', $address) === 1) {
            return $address;
        }

        return trim($address.', '.$city, ', ');
    }
}
