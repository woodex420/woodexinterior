<?php

declare(strict_types=1);

namespace App\Domain\Sales\Actions;

use App\Domain\Sales\Data\QuoteBlueprint;
use App\Domain\Sales\Enums\MilestonePlanKey;
use App\Domain\Sales\Enums\TemplateKind;
use App\Domain\Sales\Models\Quote;
use App\Domain\Sales\Models\QuoteTemplate;
use App\Domain\Sales\Services\DocumentStyles;
use Illuminate\Support\Str;

/**
 * Template management through the builder (QUO-002): save a quote's sections, lines, terms, scope, notes and
 * milestone plan as a new template, or replace the content of an existing one. Client data, discounts and
 * charges are never copied into templates.
 */
final class SaveQuoteAsTemplate
{
    public function handle(Quote $quote, string $name, ?QuoteTemplate $replace = null): QuoteTemplate
    {
        $plan = $quote->milestonePlan();

        $attributes = [
            'name' => trim($name),
            'business_line' => $quote->business_line,
            'milestone_plan' => $plan,
            'terms' => $quote->terms,
            'notes' => $quote->notes,
            'scope_html' => $quote->scope_html,
            'blueprint' => [...QuoteBlueprint::fromQuote($quote), 'presentation' => [
                'quote_layout' => DocumentStyles::quoteLayout($quote->meta['document_design']['quote_layout'] ?? null),
                'pdf_theme' => DocumentStyles::theme($quote->meta['document_design']['pdf_theme'] ?? null),
                'quote_blocks' => DocumentStyles::blocks('quote', $quote->meta['document_design']['quote_blocks'] ?? null),
            ]],
            'is_active' => true,
        ];

        if ($replace !== null) {
            $replace->update($attributes);

            return $replace->refresh();
        }

        return QuoteTemplate::create($attributes + [
            'slug' => $this->uniqueSlug($name),
            'kind' => $plan === MilestonePlanKey::Design ? TemplateKind::Design : TemplateKind::Execution,
            'description' => "Saved from {$quote->label()}.",
            'position' => (int) QuoteTemplate::query()->max('position') + 1,
        ]);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'template';
        $slug = $base;
        $n = 2;

        while (QuoteTemplate::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-".$n++;
        }

        return $slug;
    }
}
