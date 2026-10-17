<?php

declare(strict_types=1);

namespace App\Domain\Sales\Actions;

use App\Domain\Crm\Enums\BusinessLine;
use App\Domain\Crm\Models\Contact;
use App\Domain\Crm\Models\Lead;
use App\Domain\Sales\Data\QuoteBlueprint;
use App\Domain\Sales\Enums\MilestonePlanKey;
use App\Domain\Sales\Enums\QuoteStatus;
use App\Domain\Sales\Enums\TemplateKind;
use App\Domain\Sales\Models\Quote;
use App\Domain\Sales\Models\QuoteTemplate;
use App\Domain\Sales\Services\DocumentStyles;
use App\Domain\Sales\Services\MilestonePlan;
use App\Models\User;
use App\Settings\SalesSettings;
use App\Support\Html;
use App\Support\Numbering;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Creates a draft quotation from a template or blank (QUO-003). The number is allocated on this first save:
 * WI-10100+ for interior/project work, WF-10050+ for furniture (QUO-006). Sections, items, terms, scope and the
 * payment-milestone plan come from the template; totals are calculated on the server (QUO-004).
 */
final class CreateQuote
{
    public function __construct(
        private readonly Numbering $numbering,
        private readonly RecalculateQuote $recalculate,
        private readonly LogQuoteEvent $logQuoteEvent,
        private readonly SalesSettings $settings,
    ) {}

    public function handle(
        Contact $contact,
        BusinessLine $line,
        User $by,
        ?Lead $lead = null,
        ?QuoteTemplate $template = null,
        ?string $title = null,
    ): Quote {
        if ($lead !== null && $lead->contact_id !== $contact->id) {
            throw new InvalidArgumentException('That enquiry belongs to another client.');
        }

        $quote = DB::transaction(function () use ($contact, $line, $by, $lead, $template, $title): Quote {
            $plan = $template !== null
                ? $template->milestone_plan
                : MilestonePlanKey::Execution;
            $design = $plan === MilestonePlanKey::Design || $template?->kind === TemplateKind::Design;

            $quote = Quote::create([
                'number' => $this->numbering->next($line->quoteSequenceKey()),
                'sequence_key' => $line->quoteSequenceKey(),
                'option_label' => '',
                'revision' => 0,
                'lead_id' => $lead?->id,
                'contact_id' => $contact->id,
                'company_id' => $lead->company_id ?? $contact->company_id,
                'template_id' => $template?->id,
                'business_line' => $line,
                'title' => trim((string) $title) !== '' ? trim((string) $title) : ($template->name ?? 'Quotation'),
                'status' => QuoteStatus::Draft,
                'currency' => 'PKR',
                'valid_until' => today()->addDays($this->settings->quote_validity_days),
                'terms' => trim((string) $template?->terms) !== ''
                    ? $template?->terms
                    : ($design ? $this->settings->design_terms : $this->settings->execution_terms),
                'notes' => $template?->notes,
                'scope_html' => Html::sanitize($template?->scope_html),
                'owner_id' => $by->id,
                'meta' => ['milestone_plan' => $plan->value, 'document_design' => app(DocumentStyles::class)->copy($line)],
            ]);

            $sections = $template !== null ? QuoteBlueprint::sections($template->blueprint) : [];

            if ($sections === []) {
                $sections = [['name' => 'Work items', 'note' => null, 'items' => [[
                    'description' => '', 'unit' => 'job', 'qty' => '1', 'rate_minor' => 0,
                    'catalog_item_id' => null, 'is_optional' => false, 'internal_cost_minor' => null,
                ]]]];
            }

            foreach ($sections as $s => $section) {
                $model = $quote->sections()->create(['name' => $section['name'], 'note' => $section['note'], 'position' => $s]);

                foreach ($section['items'] as $i => $item) {
                    $model->items()->create($item + ['quote_id' => $quote->id, 'position' => $i]);
                }
            }

            foreach (MilestonePlan::definition($plan) as $position => $milestone) {
                $quote->milestones()->create($milestone + ['position' => $position]);
            }

            $this->recalculate->handle($quote);

            $this->logQuoteEvent->handle(
                $quote,
                'created',
                $by,
                $template !== null
                    ? "Quotation {$quote->label()} started from the \"{$template->name}\" template."
                    : "Quotation {$quote->label()} started.",
                array_filter(['template' => $template?->name]),
            );

            return $quote;
        });

        return $quote->refresh();
    }
}
