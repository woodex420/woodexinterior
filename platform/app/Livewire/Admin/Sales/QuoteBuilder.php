<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Sales;

use App\Domain\Sales\Actions\DecideQuoteApproval;
use App\Domain\Sales\Actions\DeleteQuote;
use App\Domain\Sales\Actions\DuplicateQuote;
use App\Domain\Sales\Actions\ReopenQuote;
use App\Domain\Sales\Actions\RequestQuoteApproval;
use App\Domain\Sales\Actions\ReviseQuote;
use App\Domain\Sales\Actions\SaveQuoteAsTemplate;
use App\Domain\Sales\Actions\SendQuote;
use App\Domain\Sales\Enums\DiscountType;
use App\Domain\Sales\Enums\MilestonePlanKey;
use App\Domain\Sales\Enums\QuoteStatus;
use App\Domain\Sales\Enums\Unit;
use App\Domain\Sales\Exceptions\QuoteActionDenied;
use App\Domain\Sales\Exceptions\QuoteLocked;
use App\Domain\Sales\Models\CatalogItem;
use App\Domain\Sales\Models\Quote;
use App\Domain\Sales\Models\QuoteItem;
use App\Domain\Sales\Models\QuoteMilestone;
use App\Domain\Sales\Models\QuoteSection;
use App\Domain\Sales\Models\QuoteTemplate;
use App\Domain\Sales\Models\QuoteVersion;
use App\Domain\Sales\Services\QuoteApprovalRules;
use App\Domain\Sales\Services\QuoteDiff;
use App\Domain\Sales\Services\QuoteEditor;
use App\Domain\Sales\Services\QuoteLinks;
use App\Domain\System\Enums\ApprovalStatus;
use App\Domain\System\Models\Approval;
use App\Livewire\Concerns\InteractsWithAdmin;
use App\Settings\SalesSettings;
use App\Support\Html;
use App\Support\NumberInput;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

/**
 * The quote builder (QUO-003/004/005/015/020; docs/05 §6 "Quote builder"). Every field saves when it loses focus
 * (`wire:model.live.blur`: in Livewire 4 a plain `.blur` never reaches the server, D-55) and the server recalculates
 * the totals (D-14, D-54): the browser never computes a total that gets saved.
 * Enter in a quantity, rate or discount adds a new line below; sections and lines move by drag and drop or with
 * the arrow buttons.
 *
 * @property-read Quote $quote
 */
#[Layout('layouts::admin')]
class QuoteBuilder extends Component
{
    use InteractsWithAdmin;

    #[Locked]
    public int $quoteId;

    /** @var array<int|string, array{name: string, note: string}> */
    public array $sections = [];

    /** @var array<int|string, array{description: string, unit: string, qty: string, rate: string, discount_type: string, discount_value: string, is_optional: bool, internal_cost: string}> */
    public array $lines = [];

    /** @var array<int|string, array{name: string, percent: string}> */
    public array $milestones = [];

    /** @var array<string, string> */
    public array $details = [];

    public string $plan = '';

    // Rate book picker
    public bool $showCatalog = false;

    #[Locked]
    public ?int $catalogSectionId = null;

    public string $catalogSearch = '';

    public string $catalogCategory = '';

    // Save as template
    public bool $showTemplate = false;

    public string $templateMode = 'new';

    public string $templateName = '';

    public ?int $templateId = null;

    // Send, approval and revisions (P2 part 2)
    public bool $showSend = false;

    public bool $sendEmail = false;

    public string $approvalNote = '';

    public bool $showReject = false;

    public string $rejectReason = '';

    public function mount(Quote $quote): void
    {
        $this->authorize('view', $quote);
        $this->quoteId = $quote->id;
        $this->fillForm();
    }

    #[Computed]
    public function quote(): Quote
    {
        /** @var Quote $quote */
        $quote = Quote::query()
            ->with([
                'sections.items', 'milestones', 'company', 'lead.stage', 'owner:id,name', 'template:id,name',
                'contact' => fn ($query) => $query->withTrashed()->with('company'),
            ])
            ->findOrFail($this->quoteId);

        return $quote;
    }

    /**
     * Routes every field change to the matching save method ("lines.12.qty", "details.tax", "plan"...).
     */
    public function updated(string $property): void
    {
        [$group, $key, $field] = array_pad(explode('.', $property, 3), 3, '');

        match ($group) {
            'lines' => $this->saveLine((int) $key, $field),
            'sections' => $this->saveSection((int) $key, $field),
            'milestones' => $this->saveMilestone((int) $key, $field),
            'details' => $this->saveDetail($key),
            'plan' => $this->savePlan(),
            default => null,
        };
    }

    // ----- Sections -----------------------------------------------------------------------------------------

    public function addSection(): void
    {
        $this->attempt(function (): void {
            $section = $this->editor()->addSection($this->editableQuote(), 'New section');
            $this->refreshAll();
            $this->dispatch('quote-focus', target: "section-{$section->id}-name");
        });
    }

    public function deleteSection(int $sectionId): void
    {
        $this->attempt(function () use ($sectionId): void {
            $this->editor()->deleteSection($this->section($sectionId));
            $this->refreshAll();
        });
    }

    public function moveSection(int $sectionId, int $toIndex): void
    {
        $this->attempt(function () use ($sectionId, $toIndex): void {
            $this->editor()->moveSection($this->section($sectionId), $toIndex);
            $this->refreshAll();
        });
    }

    public function shiftSection(int $sectionId, int $direction): void
    {
        $ids = $this->quote->sections->pluck('id')->values();
        $index = $ids->search($sectionId);

        if ($index !== false) {
            $this->moveSection($sectionId, max(0, (int) $index + ($direction < 0 ? -1 : 1)));
        }
    }

    // ----- Lines --------------------------------------------------------------------------------------------

    public function addLine(int $sectionId, ?int $afterItemId = null): void
    {
        $this->attempt(function () use ($sectionId, $afterItemId): void {
            $section = $this->section($sectionId);
            $after = $afterItemId !== null ? $this->item($afterItemId) : null;
            $item = $this->editor()->addLine($section, [], $after);
            $this->refreshAll();
            $this->dispatch('quote-focus', target: "line-{$item->id}-description");
        });
    }

    public function deleteLine(int $itemId): void
    {
        $this->attempt(function () use ($itemId): void {
            $this->editor()->deleteLine($this->item($itemId));
            $this->refreshAll();
        });
    }

    public function moveLine(int $itemId, int $toSectionId, int $toIndex): void
    {
        $this->attempt(function () use ($itemId, $toSectionId, $toIndex): void {
            $this->editor()->moveLine($this->item($itemId), $this->section($toSectionId), $toIndex);
            $this->refreshAll();
        });
    }

    public function shiftLine(int $itemId, int $direction): void
    {
        $item = $this->item($itemId);
        $section = $this->quote->sections->firstWhere('id', $item->quote_section_id);
        $index = $section?->items->pluck('id')->search($itemId);

        if ($section !== null && $index !== false) {
            $this->moveLine($itemId, $section->id, max(0, (int) $index + ($direction < 0 ? -1 : 1)));
        }
    }

    // ----- Rate book ----------------------------------------------------------------------------------------

    public function openCatalog(int $sectionId): void
    {
        $this->authorize('update', $this->quote);
        $this->catalogSectionId = $this->section($sectionId)->id;
        $this->catalogSearch = '';
        $this->catalogCategory = '';
        $this->showCatalog = true;
    }

    public function addFromCatalog(int $catalogItemId): void
    {
        $this->attempt(function () use ($catalogItemId): void {
            if ($this->catalogSectionId === null) {
                return;
            }

            /** @var CatalogItem $catalogItem */
            $catalogItem = CatalogItem::query()->where('is_active', true)->findOrFail($catalogItemId);
            $this->editor()->addCatalogLine($this->section($this->catalogSectionId), $catalogItem);
            $this->refreshAll();
            $this->toast('Added: '.str($catalogItem->description)->limit(48));
        });
    }

    // ----- Milestones ---------------------------------------------------------------------------------------

    public function addMilestone(): void
    {
        $this->attempt(function (): void {
            $this->editor()->addMilestone($this->editableQuote());
            $this->refreshAll();
        });
    }

    public function deleteMilestone(int $milestoneId): void
    {
        $this->attempt(function () use ($milestoneId): void {
            $this->editor()->deleteMilestone($this->milestone($milestoneId));
            $this->refreshAll();
        });
    }

    // ----- Document actions ---------------------------------------------------------------------------------

    public function duplicate(): void
    {
        $this->authorize('duplicate', $this->quote);
        $copy = app(DuplicateQuote::class)->handle($this->quote, $this->user());
        $this->toast("{$copy->label()} created as a copy of {$this->quote->label()}.");
        $this->redirectRoute('admin.quotes.show', $copy, navigate: true);
    }

    public function openSaveTemplate(): void
    {
        $this->authorize('manageCatalog', Quote::class);
        $this->templateMode = 'new';
        $this->templateName = $this->quote->title;
        $this->templateId = $this->quote->template_id;
        $this->resetValidation();
        $this->showTemplate = true;
    }

    public function saveTemplate(): void
    {
        $this->authorize('manageCatalog', Quote::class);

        $this->validate([
            'templateMode' => ['required', 'in:new,replace'],
            'templateName' => ['required', 'string', 'max:120'],
            'templateId' => ['nullable', 'required_if:templateMode,replace', 'integer', 'exists:quote_templates,id'],
        ], [], ['templateName' => 'template name', 'templateId' => 'template']);

        $replace = $this->templateMode === 'replace' ? QuoteTemplate::query()->findOrFail($this->templateId) : null;
        $template = app(SaveQuoteAsTemplate::class)->handle($this->quote, $this->templateName, $replace);

        $this->showTemplate = false;
        $this->toast($replace !== null ? "Template \"{$template->name}\" updated from this quotation." : "Template \"{$template->name}\" saved.");
    }

    public function deleteQuote(): void
    {
        $this->authorize('delete', $this->quote);
        $label = $this->quote->label();

        try {
            app(DeleteQuote::class)->handle($this->quote, $this->user());
        } catch (QuoteLocked $exception) {
            $this->toast($exception->getMessage(), 'error');

            return;
        }

        $this->toast("{$label} moved to the recycle bin.");
        $this->redirectRoute('admin.quotes.index', navigate: true);
    }

    // ----- Send, approval, revisions (QUO-007/008/013) ----------------------------------------------------------

    public function openSend(): void
    {
        $this->authorize('send', $this->quote);
        $this->resetErrorBag();
        $this->sendEmail = filled($this->quote->contact->email) && $this->quote->sent_at === null;
        $this->approvalNote = '';
        $this->showSend = true;
    }

    public function send(): void
    {
        $this->authorize('send', $this->quote);

        try {
            $version = app(SendQuote::class)->handle($this->quote, $this->user(), 'link', $this->sendEmail);
        } catch (ValidationException $exception) {
            foreach ($exception->validator->errors()->all() as $message) {
                $this->addError('send', $message);
            }

            return;
        } catch (QuoteActionDenied $exception) {
            $this->addError('send', $exception->getMessage());

            return;
        }

        unset($this->quote);
        $this->fillForm();
        $this->toast("{$version->snapshot['label']} sent. Share the link with the client.");
    }

    public function requestApproval(): void
    {
        $this->authorize('send', $this->quote);
        $this->validate(['approvalNote' => ['nullable', 'string', 'max:500']], [], ['approvalNote' => 'note']);

        $this->act(function (): void {
            app(RequestQuoteApproval::class)->handle($this->quote, $this->user(), $this->approvalNote);
            $this->showSend = false;
            unset($this->quote);
            $this->toast('Sent for approval. You will be notified when it is decided.');
        });
    }

    public function approve(): void
    {
        $this->authorize('approve', Quote::class);

        $this->act(function (): void {
            app(DecideQuoteApproval::class)->handle($this->pendingApproval() ?? throw new QuoteActionDenied('Nothing is waiting for approval.'), $this->user(), true);
            unset($this->quote);
            $this->toast("{$this->quote->label()} approved.");
        });
    }

    public function openReject(): void
    {
        $this->authorize('approve', Quote::class);
        $this->rejectReason = '';
        $this->resetValidation();
        $this->showReject = true;
    }

    public function reject(): void
    {
        $this->authorize('approve', Quote::class);
        $this->validate(['rejectReason' => ['required', 'string', 'min:3', 'max:500']], [], ['rejectReason' => 'reason']);

        $this->act(function (): void {
            app(DecideQuoteApproval::class)->handle($this->pendingApproval() ?? throw new QuoteActionDenied('Nothing is waiting for approval.'), $this->user(), false, $this->rejectReason);
            $this->showReject = false;
            unset($this->quote);
            $this->fillForm();
            $this->toast('Sent back to draft with your reason.');
        });
    }

    public function reopen(): void
    {
        $this->authorize('revise', $this->quote);

        $this->act(function (): void {
            app(ReopenQuote::class)->handle($this->quote, $this->user());
            unset($this->quote);
            $this->fillForm();
            $this->toast("{$this->quote->label()} is a draft again.");
        });
    }

    public function revise(): void
    {
        $this->authorize('revise', $this->quote);

        $this->act(function (): void {
            app(ReviseQuote::class)->handle($this->quote, $this->user());
            unset($this->quote);
            $this->fillForm();
            $this->toast("Revision {$this->quote->label()} started. The client keeps seeing the last sent version until you send this one.");
        });
    }

    public function render(): View
    {
        $quote = $this->quote;
        $settings = app(SalesSettings::class);
        $items = $quote->sections->flatMap(fn (QuoteSection $section) => $section->items);

        return view('livewire.admin.sales.quote-builder', [
            'quote' => $quote,
            // State first: the owner passes every permission check (Gate::before), but a sent quote stays locked.
            'editable' => $quote->isEditable() && $this->user()->can('update', $quote),
            'lakh' => $settings->lakh_grouping,
            'units' => Unit::options(),
            'plans' => MilestonePlanKey::options(),
            'lineCount' => $items->count(),
            'needsPricing' => $items->filter(fn (QuoteItem $item): bool => ! $item->is_optional && ((float) $item->qty <= 0 || $item->rate_minor <= 0))->count(),
            'milestoneTotal' => (int) $quote->milestones->sum('percent_bp'),
            'project' => $quote->project()->with('milestones')->first(),
            'invoices' => $quote->invoices()->with('projectMilestone')->latest('id')->get(),
            'catalogItems' => $this->showCatalog ? $this->catalogResults() : collect(),
            'catalogCategories' => $this->showCatalog ? CatalogItem::query()->where('is_active', true)->distinct()->orderBy('category')->pluck('category') : collect(),
            'templates' => $this->showTemplate ? QuoteTemplate::query()->orderBy('position')->get(['id', 'name']) : collect(),
            ...$this->sendState($quote, $settings->lakh_grouping),
        ])->title($quote->label().' · '.$quote->title);
    }

    /**
     * @return array<string, mixed>
     */
    private function sendState(Quote $quote, bool $lakh): array
    {
        $user = $this->user();
        $sender = app(SendQuote::class);
        $versions = $quote->versions()->with('creator:id,name')->reorder('revision', 'desc')->get();
        $sendable = $sender->isSendable($quote);
        $assessment = $sendable ? app(QuoteApprovalRules::class)->assess($quote, $user) : null;

        $diffs = [];
        foreach ($versions->values() as $index => $version) {
            $previous = $versions->get($index + 1);
            if ($previous instanceof QuoteVersion) {
                $diffs[$version->id] = QuoteDiff::summarize($previous->snapshot, $version->snapshot, $lakh);
            }
        }

        return [
            'versions' => $versions,
            'diffs' => $diffs,
            'canSend' => $user->can('send', $quote),
            'canApprove' => $user->can('approve', Quote::class),
            'canRevise' => $user->can('revise', $quote) && in_array($quote->status, ReviseQuote::REVISABLE, true),
            'sendable' => $sendable,
            'sendProblems' => $sendable && $this->showSend ? $sender->problems($quote) : [],
            'assessment' => $assessment,
            'approvedAsIs' => $assessment !== null && $assessment->needed && app(QuoteApprovalRules::class)->isApprovedAsIs($quote),
            'pendingApproval' => $quote->status === QuoteStatus::PendingApproval ? $this->pendingApproval() : null,
            'portalUrl' => $versions->isNotEmpty() ? QuoteLinks::portal($quote) : null,
            'whatsappShare' => $versions->isNotEmpty() ? QuoteLinks::whatsappShare($quote) : null,
            'clientComment' => $quote->status === QuoteStatus::ChangesRequested
                ? ($quote->events()->where('type', 'changes_requested')->latest('occurred_at')->first()?->data['comment'] ?? null)
                : null,
        ];
    }

    private function pendingApproval(): ?Approval
    {
        return Approval::query()->with('requester:id,name')
            ->where('approvable_type', 'quote')->where('approvable_id', $this->quoteId)
            ->where('status', ApprovalStatus::Pending)->latest()->first();
    }

    // ----- Field saving -------------------------------------------------------------------------------------

    private function saveLine(int $itemId, string $field): void
    {
        $item = QuoteItem::query()->where('quote_id', $this->quoteId)->find($itemId);

        if ($item === null || ! isset($this->lines[$itemId])) {
            unset($this->lines[$itemId]);

            return;
        }

        $raw = $this->lines[$itemId][$field] ?? '';

        $saved = $this->saveField("lines.{$itemId}.{$field}", function () use ($item, $field, $raw): void {
            $this->editor()->updateLine($item, match ($field) {
                'description' => ['description' => mb_substr(trim((string) $raw), 0, 2000)],
                'unit' => ['unit' => Unit::tryFrom((string) $raw)->value ?? throw new InvalidArgumentException('Choose a unit from the list.')],
                'qty' => ['qty' => NumberInput::quantity((string) $raw)],
                'rate' => ['rate_minor' => NumberInput::rupees((string) $raw)],
                'discount_type' => ['discount_type' => DiscountType::tryFrom((string) $raw), 'discount_value' => 0],
                'discount_value' => ['discount_value' => $this->discountValue($item->discount_type, (string) $raw)],
                'is_optional' => ['is_optional' => (bool) $raw],
                'internal_cost' => ['internal_cost_minor' => trim((string) $raw) === '' ? null : NumberInput::rupees((string) $raw)],
                default => throw new InvalidArgumentException('Unknown field.'),
            });
        });

        // A rejected value stays in the field with its message, so it can be corrected.
        if ($saved) {
            $this->lines[$itemId] = $this->lineState($item->refresh());
        }
    }

    private function saveSection(int $sectionId, string $field): void
    {
        $section = QuoteSection::query()->where('quote_id', $this->quoteId)->find($sectionId);

        if ($section === null || ! isset($this->sections[$sectionId])) {
            unset($this->sections[$sectionId]);

            return;
        }

        $raw = trim((string) ($this->sections[$sectionId][$field] ?? ''));

        $saved = $this->saveField("sections.{$sectionId}.{$field}", function () use ($section, $field, $raw): void {
            $this->editor()->updateSection($section, match ($field) {
                'name' => ['name' => $raw !== '' ? mb_substr($raw, 0, 160) : throw new InvalidArgumentException('Give the section a name.')],
                'note' => ['note' => $raw !== '' ? mb_substr($raw, 0, 1000) : null],
                default => throw new InvalidArgumentException('Unknown field.'),
            });
        });

        if ($saved) {
            $section->refresh();
            $this->sections[$sectionId] = ['name' => $section->name, 'note' => (string) $section->note];
        }
    }

    private function saveMilestone(int $milestoneId, string $field): void
    {
        $milestone = QuoteMilestone::query()->where('quote_id', $this->quoteId)->find($milestoneId);

        if ($milestone === null || ! isset($this->milestones[$milestoneId])) {
            unset($this->milestones[$milestoneId]);

            return;
        }

        $raw = trim((string) ($this->milestones[$milestoneId][$field] ?? ''));

        $saved = $this->saveField("milestones.{$milestoneId}.{$field}", function () use ($milestone, $field, $raw): void {
            $this->editor()->updateMilestone($milestone, match ($field) {
                'name' => ['name' => $raw !== '' ? mb_substr($raw, 0, 160) : throw new InvalidArgumentException('Give the milestone a name.')],
                'percent' => ['percent_bp' => NumberInput::percent($raw)],
                default => throw new InvalidArgumentException('Unknown field.'),
            });
        });

        if ($saved) {
            $this->refreshAll();
        }
    }

    private function saveDetail(string $field): void
    {
        $raw = trim((string) ($this->details[$field] ?? ''));
        $quote = $this->quote;

        $saved = $this->saveField("details.{$field}", function () use ($quote, $field, $raw): void {
            $this->editor()->updateDetails($quote, match ($field) {
                'title' => ['title' => $raw !== '' ? mb_substr($raw, 0, 200) : throw new InvalidArgumentException('Give the quotation a title.')],
                'valid_until' => ['valid_until' => $this->validUntil($raw)],
                'discount_type' => ['discount_type' => DiscountType::tryFrom($raw), 'discount_value' => 0],
                'discount_value' => ['discount_value' => $this->discountValue($quote->discount_type, $raw)],
                'other_charges_label' => ['other_charges_label' => $raw !== '' ? mb_substr($raw, 0, 120) : null],
                'other_charges' => ['other_charges_minor' => $raw === '' ? 0 : NumberInput::rupees($raw)],
                'tax' => ['tax_bp' => $raw === '' ? 0 : NumberInput::percent($raw)],
                'terms' => ['terms' => $raw !== '' ? mb_substr($raw, 0, 10000) : null],
                'notes' => ['notes' => $raw !== '' ? mb_substr($raw, 0, 5000) : null],
                'internal_notes' => ['internal_notes' => $raw !== '' ? mb_substr($raw, 0, 5000) : null],
                'scope' => ['scope_html' => Html::fromPlainText(mb_substr($raw, 0, 20000))],
                default => throw new InvalidArgumentException('Unknown field.'),
            });
        });

        if ($saved) {
            unset($this->quote);
            $this->details = $this->detailsState($this->quote);
        }
    }

    private function savePlan(): void
    {
        $plan = MilestonePlanKey::tryFrom($this->plan);

        if ($plan === null) {
            $this->plan = $this->quote->milestonePlan()->value;

            return;
        }

        $this->attempt(function () use ($plan): void {
            $this->editor()->setMilestonePlan($this->editableQuote(), $plan);
            $this->refreshAll();
        });
    }

    /**
     * Runs one field save: validation messages stay under the field, a locked quote shows a toast.
     */
    private function saveField(string $errorKey, callable $save): bool
    {
        $this->authorize('update', $this->quote);

        try {
            $save();
        } catch (InvalidArgumentException $exception) {
            $this->addError($errorKey, $exception->getMessage());

            return false;
        } catch (QuoteLocked $exception) {
            $this->toast($exception->getMessage(), 'error');

            return false;
        }

        $this->resetErrorBag($errorKey);
        unset($this->quote);

        return true;
    }

    private function attempt(callable $action): void
    {
        $this->authorize('update', $this->quote);

        try {
            $action();
        } catch (InvalidArgumentException|QuoteLocked $exception) {
            $this->toast($exception->getMessage(), 'error');
        }
    }

    /** Document actions that do not edit the quote (approve, reopen, revise): the action itself checks the state. */
    private function act(callable $action): void
    {
        try {
            $action();
        } catch (QuoteActionDenied|InvalidArgumentException $exception) {
            $this->toast($exception->getMessage(), 'error');
        }
    }

    private function discountValue(?DiscountType $type, string $raw): int
    {
        if ($type === null || trim($raw) === '') {
            return 0;
        }

        return $type === DiscountType::Percent ? NumberInput::percent($raw) : NumberInput::rupees($raw);
    }

    private function validUntil(string $raw): CarbonImmutable
    {
        try {
            $date = CarbonImmutable::createFromFormat('Y-m-d', $raw);
        } catch (Throwable) {
            $date = null;
        }

        if (! $date instanceof CarbonImmutable) {
            throw new InvalidArgumentException('Choose a valid date.');
        }

        if ($date->startOfDay()->lt(today())) {
            throw new InvalidArgumentException('The validity date cannot be in the past.');
        }

        return $date->startOfDay();
    }

    // ----- State --------------------------------------------------------------------------------------------

    private function fillForm(): void
    {
        $quote = $this->quote;
        $this->sections = [];
        $this->lines = [];
        $this->milestones = [];

        foreach ($quote->sections as $section) {
            $this->sections[$section->id] = ['name' => $section->name, 'note' => (string) $section->note];

            foreach ($section->items as $item) {
                $this->lines[$item->id] = $this->lineState($item);
            }
        }

        foreach ($quote->milestones as $milestone) {
            $this->milestones[$milestone->id] = ['name' => $milestone->name, 'percent' => NumberInput::fromBp($milestone->percent_bp)];
        }

        $this->details = $this->detailsState($quote);
        $this->plan = $quote->milestonePlan()->value;
    }

    private function refreshAll(): void
    {
        unset($this->quote);
        $this->fillForm();
    }

    /**
     * @return array{description: string, unit: string, qty: string, rate: string, discount_type: string, discount_value: string, is_optional: bool, internal_cost: string}
     */
    private function lineState(QuoteItem $item): array
    {
        return [
            'description' => $item->description,
            'unit' => $item->unit,
            'qty' => NumberInput::fromQuantity((string) $item->qty),
            'rate' => NumberInput::fromMinor((int) $item->rate_minor),
            'discount_type' => $item->discount_type->value ?? '',
            'discount_value' => match ($item->discount_type) {
                DiscountType::Percent => NumberInput::fromBp((int) $item->discount_value),
                DiscountType::Amount => NumberInput::fromMinor((int) $item->discount_value),
                null => '',
            },
            'is_optional' => (bool) $item->is_optional,
            'internal_cost' => $item->internal_cost_minor === null ? '' : NumberInput::fromMinor((int) $item->internal_cost_minor),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function detailsState(Quote $quote): array
    {
        return [
            'title' => $quote->title,
            'valid_until' => $quote->valid_until?->format('Y-m-d') ?? '',
            'discount_type' => $quote->discount_type->value ?? '',
            'discount_value' => match ($quote->discount_type) {
                DiscountType::Percent => NumberInput::fromBp((int) $quote->discount_value),
                DiscountType::Amount => NumberInput::fromMinor((int) $quote->discount_value),
                null => '',
            },
            'other_charges_label' => (string) $quote->other_charges_label,
            'other_charges' => $quote->other_charges_minor > 0 ? NumberInput::fromMinor((int) $quote->other_charges_minor) : '',
            'tax' => $quote->tax_bp > 0 ? NumberInput::fromBp((int) $quote->tax_bp) : '',
            'terms' => (string) $quote->terms,
            'notes' => (string) $quote->notes,
            'internal_notes' => (string) $quote->internal_notes,
            'scope' => Html::toPlainText($quote->scope_html),
        ];
    }

    // ----- Lookups (always scoped to this quote) ------------------------------------------------------------

    private function editor(): QuoteEditor
    {
        return app(QuoteEditor::class);
    }

    private function editableQuote(): Quote
    {
        if (! $this->quote->isEditable()) {
            throw QuoteLocked::for($this->quote);
        }

        return $this->quote;
    }

    private function section(int $id): QuoteSection
    {
        /** @var QuoteSection $section */
        $section = QuoteSection::query()->where('quote_id', $this->quoteId)->findOrFail($id);

        return $section;
    }

    private function item(int $id): QuoteItem
    {
        /** @var QuoteItem $item */
        $item = QuoteItem::query()->where('quote_id', $this->quoteId)->findOrFail($id);

        return $item;
    }

    private function milestone(int $id): QuoteMilestone
    {
        /** @var QuoteMilestone $milestone */
        $milestone = QuoteMilestone::query()->where('quote_id', $this->quoteId)->findOrFail($id);

        return $milestone;
    }

    /**
     * @return Collection<int, CatalogItem>
     */
    private function catalogResults(): Collection
    {
        $term = trim($this->catalogSearch);
        $line = $this->quote->business_line->value;

        return CatalogItem::query()
            ->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('business_line')->orWhere('business_line', $line))
            ->when($this->catalogCategory !== '', fn (Builder $q) => $q->where('category', $this->catalogCategory))
            ->when($term !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w->where('description', 'like', "%{$term}%")->orWhere('category', 'like', "%{$term}%")))
            ->orderBy('position')
            ->limit(40)
            ->get();
    }
}
