<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Sales;

use App\Domain\Crm\Enums\BusinessLine;
use App\Domain\Sales\Data\QuoteBlueprint;
use App\Domain\Sales\Enums\MilestonePlanKey;
use App\Domain\Sales\Enums\TemplateKind;
use App\Domain\Sales\Models\Quote;
use App\Domain\Sales\Models\QuoteTemplate;
use App\Livewire\Concerns\InteractsWithAdmin;
use App\Settings\SalesSettings;
use App\Support\Html;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Quote templates (QUO-002): the 16 Woodex templates and any saved from quotations. Details are edited here;
 * sections and lines are edited by building a quotation and using "Save as template" → "Update a template".
 */
#[Layout('layouts::admin')]
#[Title('Quote templates')]
class QuoteTemplates extends Component
{
    use InteractsWithAdmin;

    #[Url(except: '')]
    public string $kind = '';

    #[Url(except: false)]
    public bool $showInactive = false;

    public bool $showForm = false;

    #[Locked]
    public ?int $editingId = null;

    public bool $showPreview = false;

    #[Locked]
    public ?int $previewId = null;

    public string $formName = '';

    public string $formDescription = '';

    public string $formLine = 'interior';

    public string $formKind = 'execution';

    public string $formPlan = 'execution_50_40_10';

    public string $formTerms = '';

    public string $formNotes = '';

    public string $formScope = '';

    public bool $formActive = true;

    public function mount(): void
    {
        $this->authorize('viewAny', Quote::class);
    }

    public function openEdit(int $id): void
    {
        $this->authorize('manageCatalog', Quote::class);
        $template = QuoteTemplate::query()->findOrFail($id);
        $this->resetValidation();
        $this->editingId = $template->id;
        $this->formName = $template->name;
        $this->formDescription = (string) $template->description;
        $this->formLine = $template->business_line->value;
        $this->formKind = $template->kind->value;
        $this->formPlan = $template->milestone_plan->value;
        $this->formTerms = (string) $template->terms;
        $this->formNotes = (string) $template->notes;
        $this->formScope = Html::toPlainText($template->scope_html);
        $this->formActive = (bool) $template->is_active;
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorize('manageCatalog', Quote::class);

        $this->validate([
            'formName' => ['required', 'string', 'max:120'],
            'formDescription' => ['nullable', 'string', 'max:500'],
            'formLine' => ['required', Rule::enum(BusinessLine::class)],
            'formKind' => ['required', Rule::enum(TemplateKind::class)],
            'formPlan' => ['required', Rule::enum(MilestonePlanKey::class)],
            'formTerms' => ['nullable', 'string', 'max:10000'],
            'formNotes' => ['nullable', 'string', 'max:5000'],
            'formScope' => ['nullable', 'string', 'max:20000'],
            'formActive' => ['boolean'],
        ], [], ['formName' => 'name', 'formLine' => 'line', 'formKind' => 'type', 'formPlan' => 'milestone plan']);

        QuoteTemplate::query()->findOrFail($this->editingId)->update([
            'name' => trim($this->formName),
            'description' => trim($this->formDescription) !== '' ? trim($this->formDescription) : null,
            'business_line' => $this->formLine,
            'kind' => $this->formKind,
            'milestone_plan' => $this->formPlan,
            'terms' => trim($this->formTerms) !== '' ? trim($this->formTerms) : null,
            'notes' => trim($this->formNotes) !== '' ? trim($this->formNotes) : null,
            'scope_html' => Html::fromPlainText($this->formScope),
            'is_active' => $this->formActive,
        ]);

        $this->showForm = false;
        $this->toast('Template saved. New quotations use it from now on.');
    }

    public function toggleActive(int $id): void
    {
        $this->authorize('manageCatalog', Quote::class);
        $template = QuoteTemplate::query()->findOrFail($id);
        $template->update(['is_active' => ! $template->is_active]);
        $this->toast($template->is_active ? "\"{$template->name}\" is available again." : "\"{$template->name}\" hidden from new quotations.");
    }

    public function preview(int $id): void
    {
        $this->previewId = QuoteTemplate::query()->findOrFail($id)->id;
        $this->showPreview = true;
    }

    public function render(): View
    {
        $previewTemplate = $this->showPreview && $this->previewId !== null ? QuoteTemplate::query()->find($this->previewId) : null;

        return view('livewire.admin.sales.quote-templates', [
            'templates' => QuoteTemplate::query()
                ->withCount('quotes')
                ->when(! $this->showInactive, fn (Builder $q) => $q->where('is_active', true))
                ->when($this->kind !== '', fn (Builder $q) => $q->where('kind', $this->kind))
                ->orderBy('position')
                ->get(),
            'kinds' => TemplateKind::options(),
            'lines' => BusinessLine::options(),
            'plans' => MilestonePlanKey::options(),
            'previewTemplate' => $previewTemplate,
            'previewSections' => $previewTemplate !== null ? QuoteBlueprint::sections($previewTemplate->blueprint) : [],
            'canEdit' => $this->user()->can('manageCatalog', Quote::class),
            'canCreateQuote' => $this->user()->can('create', Quote::class),
            'lakh' => app(SalesSettings::class)->lakh_grouping,
        ]);
    }
}
