<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Website;

use App\Domain\Website\Actions\RestorePageVersion;
use App\Domain\Website\Actions\SavePage;
use App\Domain\Website\Enums\BlockType;
use App\Domain\Website\Enums\PageStatus;
use App\Domain\Website\Enums\PageType;
use App\Domain\Website\Models\Media;
use App\Domain\Website\Models\Page;
use App\Domain\Website\Models\PageTemplate;
use App\Domain\Website\Models\PageVersion;
use App\Domain\Website\Models\SavedSection;
use App\Domain\Website\Services\PageAudit;
use App\Domain\Website\Services\PageCache;
use App\Domain\Website\Services\PageValidator;
use App\Domain\Website\Support\Columns;
use App\Domain\Website\Support\PageFingerprint;
use App\Domain\Website\Support\PreviewLink;
use App\Domain\Website\Support\SectionStyle;
use App\Domain\Website\Support\StaticShadow;
use App\Livewire\Concerns\InteractsWithAdmin;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * One page: its fields, its sections and its SEO (docs/09 §2.2). A draft can be saved at any time; publishing
 * runs the completeness gate first, because a thin page published today stays thin in search results for a year.
 */
#[Layout('layouts::admin')]
#[Title('Page')]
class PageEditor extends Component
{
    use InteractsWithAdmin;

    #[Locked]
    public ?int $pageId = null;

    public string $title = '';

    public string $slug = '';

    public string $type = '';

    public string $excerpt = '';

    public string $status = '';

    public int $sort = 0;

    public string $seoTitle = '';

    public string $seoDescription = '';

    public string $seoRobots = '';

    public string $focusKeyword = '';

    public ?int $ogImageId = null;

    /** @var list<array{id: ?int, key: string, type: string, visible: bool, data: array<string, mixed>}> */
    public array $blocks = [];

    /** The campaign canvas (D-107): a page whose template is "canvas" renders hand-placed HTML. */
    public bool $isCanvas = false;

    public string $canvasHtml = '';

    public string $newBlockType = '';

    /** @var list<string> What still stands between this page and publishing. */
    public array $issues = [];

    /** V2-2 (docs/12 §5.2/§5.3): the section library, drag-to-reorder, duplication, device preview. */
    public bool $showSectionModal = false;

    public int $sectionIndex = 0;

    public string $sectionName = '';

    /** V2-3 F5: saving this page's layout as a template, for reuse on future pages. */
    public bool $showTemplateModal = false;

    public string $templateName = '';

    /** desktop, tablet or mobile — the width of the inline preview frame. */
    public string $previewDevice = 'desktop';

    /** Live preview (19 Oct): the editor saves the draft as you type and reloads the frame. */
    public bool $previewLive = true;

    /** Bumped on every refresh so the iframe reloads instead of showing a stale copy. */
    public int $previewNonce = 0;

    /** True while a change waits for the debounce — the frame is showing an older draft. */
    public bool $previewStale = false;

    /**
     * Phase 2: a fingerprint of the page as this editor opened it. If the page in the database no
     * longer matches, somebody else saved it — no schema change, no counter to trust.
     */
    #[Locked]
    public string $sourceHash = '';

    /** Set when a save was refused because the page moved on; cleared by reloadLatest(). */
    public bool $conflict = false;

    /** Why the preview cannot show yet (an unsaved page without a title, a taken URL, …). */
    public string $previewNotice = '';

    public string $shareUrl = '';

    public bool $showVersions = false;

    /** The key of the section clicked in the preview — its card is scrolled to and highlighted (V2-3). */
    public string $activeBlock = '';

    /**
     * Undo / redo (19 Oct, builder parity with admin v2.1 pro): one snapshot per structural change,
     * so an add, a drag, a delete or a style choice can be taken back before it is saved. Typing is
     * left to the browser's own undo — the shortcuts only fire outside a field.
     *
     * @var list<array{blocks: list<array<string, mixed>>, activeBlock: string}>
     */
    #[Locked]
    public array $undoStack = [];

    /** @var list<array{blocks: list<array<string, mixed>>, activeBlock: string}> */
    #[Locked]
    public array $redoStack = [];

    /** The layers panel's search: it filters the short list, never the page. */
    public string $layerQuery = '';

    /**
     * Multi-select (Phase 2, builder parity): the keys ticked in the Sections list. Actions apply to
     * the ticked sections only, so a long page can be copied or cleared in one go.
     *
     * @var list<string>
     */
    #[Locked]
    public array $selectedKeys = [];

    /** How many changes can be taken back (the stacks themselves stay out of the view). */
    protected const int HISTORY_LIMIT = 25;

    public function mount(?Page $page = null): void
    {
        abort_unless($this->user()->can('website.edit'), 403);

        // An HTML page is a whole document edited in the Visual Page Builder. Saving it here would
        // write blocks and null the document, so the editor hands it over instead of showing forms.
        if ($page?->template === 'html') {
            $this->redirectRoute('admin.website.builder', ['page' => $page], navigate: true);

            return;
        }

        $this->reload($page);
    }

    /**
     * Any change to the page marks the preview stale. The browser debounces the refresh, so a burst
     * of typing costs one draft save, not one per keystroke (Livewire 4 `updated` hook).
     */
    public function updated(string $property): void
    {
        if (in_array($property, ['previewLive', 'previewDevice', 'previewNonce', 'previewStale', 'previewNotice', 'shareUrl', 'activeBlock', 'layerQuery', 'undoStack', 'redoStack'], true)) {
            return;
        }

        $this->markPreviewStale();
    }

    /**
     * Before a *style* choice lands, keep the state it replaces. A select is one change, so a
     * background or spacing can be undone; the section's name is typed and keeps the browser's undo.
     */
    public function updating(string $property): void
    {
        if (preg_match('/^blocks\.\d+\.settings\.(background|spacing|width|align)$/', $property) === 1) {
            $this->remember();
        }
    }

    /** Keep the current state, for one Ctrl+Z. Every structural action calls this before it changes anything. */
    private function remember(): void
    {
        $this->undoStack[] = ['blocks' => $this->blocks, 'activeBlock' => $this->activeBlock];

        if (count($this->undoStack) > self::HISTORY_LIMIT) {
            array_shift($this->undoStack);
        }

        $this->redoStack = [];
    }

    /** Take the last structural change back. The preview follows, like every other edit. */
    public function undo(): void
    {
        $snapshot = array_pop($this->undoStack);

        if ($snapshot === null) {
            return;
        }

        $this->redoStack[] = ['blocks' => $this->blocks, 'activeBlock' => $this->activeBlock];
        $this->blocks = $snapshot['blocks'];
        $this->activeBlock = $snapshot['activeBlock'];

        $this->markPreviewStale();
        $this->toast('Undone.');
    }

    /** Put the change that was just undone back. */
    public function redo(): void
    {
        $snapshot = array_pop($this->redoStack);

        if ($snapshot === null) {
            return;
        }

        $this->undoStack[] = ['blocks' => $this->blocks, 'activeBlock' => $this->activeBlock];
        $this->blocks = $snapshot['blocks'];
        $this->activeBlock = $snapshot['activeBlock'];

        $this->markPreviewStale();
        $this->toast('Redone.');
    }

    /** A row in the layers panel: highlight that section and bring its form into view. */
    public function focusBlock(int $index): void
    {
        $block = $this->blocks[$index] ?? null;

        if ($block === null) {
            return;
        }

        $this->activeBlock = (string) $block['key'];
        $this->dispatch('scroll-to-block', key: $this->activeBlock);
    }

    /** The frame is showing an older draft; the debounce in the blade asks for a refresh shortly. */
    public function markPreviewStale(): void
    {
        $this->previewStale = true;

        if ($this->previewLive) {
            $this->dispatch('preview-stale');
        }
    }

    /**
     * Live preview (19 Oct): save the draft quietly, then reload the frame. A page that does not
     * exist yet is created here — so the builder previews before the first explicit Save, the way
     * the old admin did.
     */
    public function refreshPreview(): void
    {
        if (! $this->previewLive) {
            $this->previewStale = false;

            return;
        }

        if ($this->persistDraft()) {
            $this->previewNonce++;
        }

        $this->previewStale = false;
    }

    public function updatedTitle(string $value): void
    {
        // A new page takes its URL from its title until someone edits the URL by hand.
        if ($this->pageId === null && trim($this->slug) === '') {
            $this->slug = Page::normalizeSlug('/'.Str::slug($value).'/');
        }
    }

    public function addSelectedBlock(): void
    {
        if ($this->newBlockType !== '') {
            $this->addBlock($this->newBlockType);
        }
    }

    public function addBlock(string $type): void
    {
        $block = BlockType::from($type);

        if ($block === BlockType::Html) {
            abort_unless($this->canUseRawHtml(), 403);
        }

        $this->remember();

        $this->blocks[] = [
            'id' => null,
            'key' => $this->newKey(),
            'type' => $block->value,
            'visible' => true,
            'data' => $block->fields(),
            'settings' => SectionStyle::defaults(),
        ];

        $this->newBlockType = '';
        $this->markPreviewStale();
    }

    public function removeBlock(int $index): void
    {
        $this->remember();

        unset($this->blocks[$index]);
        $this->blocks = array_values($this->blocks);
        $this->markPreviewStale();
    }

    public function moveBlock(int $index, int $direction): void
    {
        $to = $index + $direction;

        if (! isset($this->blocks[$to])) {
            return;
        }

        $this->remember();

        [$this->blocks[$index], $this->blocks[$to]] = [$this->blocks[$to], $this->blocks[$index]];
        $this->blocks = array_values($this->blocks);
        $this->markPreviewStale();
    }

    public function toggleBlock(int $index): void
    {
        $this->remember();

        $this->blocks[$index]['visible'] = ! (bool) ($this->blocks[$index]['visible'] ?? true);
        $this->markPreviewStale();
    }

    /**
     * Drag-to-reorder (docs/12 §5.3, Livewire 4 API): the dragged section reports its own key and the
     * position it was dropped at — the list is re-ordered around it. Keys come from the markup
     * (`wire:sort:item`), so a section that no longer exists is ignored instead of guessed at.
     */
    public function sortBlocks(string $key, int $position): void
    {
        $blocks = collect($this->blocks)->values();

        $from = $blocks->search(fn (array $block): bool => (string) $block['key'] === $key);

        if ($from === false) {
            return;
        }

        $this->remember();

        $moved = $blocks->pull($from);
        $blocks->splice(max(0, min($position, $blocks->count())), 0, [$moved]);

        $this->blocks = $blocks->values()->all();
        $this->markPreviewStale();
    }

    /**
     * A copy of a section that works, placed right under it — the cheapest way to build a long page.
     */
    /** The selection toolbar, and every action behind it, is one switch (config/website.php). */
    private function selectionEnabled(): bool
    {
        return (bool) config('website.builder.section_clipboard', true);
    }

    public function toggleSelect(string $key): void
    {
        if (! $this->selectionEnabled()) {
            return;
        }

        if (! collect($this->blocks)->contains(fn (array $block): bool => (string) $block['key'] === $key)) {
            return;
        }

        $this->selectedKeys = in_array($key, $this->selectedKeys, true)
            ? array_values(array_diff($this->selectedKeys, [$key]))
            : [...$this->selectedKeys, $key];
    }

    /** Tick every section; the second press clears the selection. */
    public function toggleSelectAll(): void
    {
        if (! $this->selectionEnabled()) {
            return;
        }

        $this->selectedKeys = count($this->selectedKeys) === count($this->blocks)
            ? []
            : array_values(array_map(fn (array $block): string => (string) $block['key'], $this->blocks));
    }

    /**
     * Put the ticked sections on the editor's clipboard (kept in the session, so they can be pasted
     * into another page). The copy is the data only — ids and keys belong to the page they came from.
     */
    public function copySelected(): void
    {
        if (! $this->selectionEnabled()) {
            return;
        }

        $copied = [];

        foreach ($this->blocks as $block) {
            if (! in_array((string) $block['key'], $this->selectedKeys, true)) {
                continue;
            }

            $copied[] = [
                'type' => (string) $block['type'],
                'data' => (array) ($block['data'] ?? []),
                'settings' => SectionStyle::clean((array) ($block['settings'] ?? [])),
                'visible' => (bool) ($block['visible'] ?? true),
            ];
        }

        if ($copied === []) {
            $this->toast('Tick a section first, then copy.', 'error');

            return;
        }

        session(['website.section_clipboard' => $copied]);
        $this->toast(count($copied).' '.(count($copied) === 1 ? 'section' : 'sections').' copied — paste here or on another page.');
    }

    /** Paste the clipboard under the last section. Unknown types are dropped, never guessed at. */
    public function pasteClipboard(): void
    {
        if (! $this->selectionEnabled()) {
            return;
        }

        $copied = session('website.section_clipboard', []);

        if (! is_array($copied) || $copied === []) {
            $this->toast('The clipboard is empty — copy a section first.', 'error');

            return;
        }

        $known = array_map(fn (BlockType $type): string => $type->value, BlockType::cases());

        $pasted = [];

        foreach ($copied as $block) {
            if (! is_array($block) || ! in_array((string) ($block['type'] ?? ''), $known, true)) {
                continue;
            }

            $type = BlockType::from((string) $block['type']);
            $pasted[] = [
                'id' => null,
                'key' => $this->newKey(),
                'type' => $type->value,
                'visible' => (bool) ($block['visible'] ?? true),
                'data' => array_merge($type->fields(), (array) ($block['data'] ?? [])),
                'settings' => SectionStyle::clean((array) ($block['settings'] ?? [])),
            ];
        }

        if ($pasted === []) {
            $this->toast('Nothing on the clipboard can be pasted here.', 'error');

            return;
        }

        $this->remember();
        $this->blocks = [...$this->blocks, ...$pasted];
        $this->selectedKeys = array_values(array_map(fn (array $block): string => (string) $block['key'], $pasted));
        $this->markPreviewStale();
        $this->toast(count($pasted).' '.(count($pasted) === 1 ? 'section' : 'sections').' pasted.');
    }

    /** Duplicate every ticked section, in page order, under the last section. */
    public function duplicateSelected(): void
    {
        if (! $this->selectionEnabled()) {
            return;
        }

        $copies = [];

        foreach ($this->blocks as $block) {
            if (! in_array((string) $block['key'], $this->selectedKeys, true)) {
                continue;
            }

            $copy = $block;
            $copy['id'] = null;
            $copy['key'] = $this->newKey();
            $copies[] = $copy;
        }

        if ($copies === []) {
            return;
        }

        $this->remember();
        $this->blocks = [...$this->blocks, ...$copies];
        $this->selectedKeys = array_values(array_map(fn (array $block): string => (string) $block['key'], $copies));
        $this->markPreviewStale();
        $this->toast(count($copies).' '.(count($copies) === 1 ? 'section' : 'sections').' duplicated.');
    }

    /** Delete the ticked sections — one undo step, so a slip of the hand is recoverable. */
    public function deleteSelected(): void
    {
        if (! $this->selectionEnabled() || $this->selectedKeys === []) {
            return;
        }

        $this->remember();
        $this->blocks = array_values(array_filter(
            $this->blocks,
            fn (array $block): bool => ! in_array((string) $block['key'], $this->selectedKeys, true),
        ));
        $count = count($this->selectedKeys);
        $this->selectedKeys = [];
        $this->markPreviewStale();
        $this->toast($count.' '.($count === 1 ? 'section' : 'sections').' deleted — undo puts them back.');
    }

    /**
     * The columns section's 2–4 switch (Phase 2). Removing a column drops its content from the editor
     * state; one undo brings it back. Adding pads with an empty text column.
     */
    public function setColumnCount(int $index, int $count): void
    {
        if (! isset($this->blocks[$index]) || (string) $this->blocks[$index]['type'] !== BlockType::Columns->value) {
            return;
        }

        $count = max(Columns::MIN, min(Columns::MAX, $count));
        $children = Columns::clean((array) ($this->blocks[$index]['data'] ?? []))['blocks'];

        if (count($children) === $count) {
            return;
        }

        $this->remember();

        while (count($children) < $count) {
            $children[] = Columns::blank('text');
        }

        $this->blocks[$index]['data'] = ['blocks' => array_slice($children, 0, $count)];
        $this->markPreviewStale();
    }

    public function duplicateBlock(int $index): void
    {
        if (! isset($this->blocks[$index])) {
            return;
        }

        $this->remember();

        $copy = $this->blocks[$index];
        $copy['id'] = null;
        $copy['key'] = $this->newKey();

        array_splice($this->blocks, $index + 1, 0, [$copy]);
        $this->markPreviewStale();
    }

    /**
     * Save this section under a name, for reuse on any page (docs/12 §5.2 saved sections).
     */
    public function openSectionModal(int $index): void
    {
        if (! isset($this->blocks[$index])) {
            return;
        }

        $this->sectionIndex = $index;
        $this->sectionName = BlockType::from((string) $this->blocks[$index]['type'])->label();
        $this->showSectionModal = true;
    }

    public function storeSection(): void
    {
        $this->validateOnly('sectionName', ['sectionName' => ['required', 'string', 'max:120']]);

        $block = $this->blocks[$this->sectionIndex] ?? null;

        if ($block === null) {
            $this->showSectionModal = false;

            return;
        }

        SavedSection::query()->create([
            'name' => trim($this->sectionName),
            'type' => BlockType::from((string) $block['type']),
            'data' => (array) $block['data'],
            'created_by' => $this->user()->id,
        ]);

        $this->showSectionModal = false;
        $this->toast('Saved to the library as “'.trim($this->sectionName).'”.');
    }

    /**
     * Drop a saved section onto the page — content and all.
     */
    public function insertSavedSection(int $savedId): void
    {
        $saved = SavedSection::query()->findOrFail($savedId);

        $this->remember();

        $this->blocks[] = [
            'id' => null,
            'key' => $this->newKey(),
            'type' => $saved->type->value,
            'visible' => true,
            'data' => $saved->data,
            'settings' => SectionStyle::defaults(),
        ];

        $this->markPreviewStale();
    }

    public function deleteSavedSection(int $savedId): void
    {
        SavedSection::query()->findOrFail($savedId)->delete();
    }

    /**
     * Apply a template to this page (V2-3 F5): the sections are replaced by the template's layout.
     * Nothing is written to the database until Save draft — a template applied by mistake is one
     * click away from gone.
     */
    public function applyTemplate(int $templateId): void
    {
        $this->remember();

        $template = PageTemplate::query()->findOrFail($templateId);

        $this->blocks = $template->toBlocks();
        $this->activeBlock = '';
        $this->markPreviewStale();
        $this->toast('“'.$template->name.'” applied — press Save draft to keep it.');
    }

    /**
     * Save this page's section layout as a template: which sections, in what order, with their
     * starter text — so the next page of this kind starts one click from done.
     */
    public function saveAsTemplate(): void
    {
        $this->validateOnly('templateName', ['templateName' => ['required', 'string', 'max:120']]);

        if ($this->blocks === []) {
            $this->showTemplateModal = false;
            $this->toast('Add sections first — a template is a layout, not an empty page.', 'error');

            return;
        }

        PageTemplate::query()->create([
            'name' => trim($this->templateName),
            'page_type' => PageType::from($this->type),
            'description' => 'Saved from this page by '.$this->user()->name.'.',
            'sections' => collect($this->blocks)->map(fn (array $block): array => [
                'type' => (string) $block['type'],
                'data' => (array) $block['data'],
            ])->values()->all(),
            'created_by' => $this->user()->id,
        ]);

        $this->showTemplateModal = false;
        $this->templateName = '';
        $this->toast('Saved as a template — start the next page from it.');
    }

    /**
     * The inline preview width (docs/12 §5.3): desktop, tablet or mobile. The frame is always there —
     * choosing a device changes its width, it does not switch it off.
     */
    public function setPreviewDevice(string $device): void
    {
        if (in_array($device, ['desktop', 'tablet', 'mobile'], true)) {
            $this->previewDevice = $device;
        }
    }

    /**
     * Click-to-edit (V2-3, docs/14): the preview reports which section was clicked; the editor jumps
     * to that section's form. Keys it does not know (a section deleted since the draft was saved) are
     * ignored instead of guessed at.
     */
    public function editSectionFromPreview(string $key): void
    {
        if (! collect($this->blocks)->contains(fn (array $block): bool => (string) $block['key'] === $key)) {
            return;
        }

        $this->activeBlock = $key;
        $this->dispatch('scroll-to-block', key: $key);
    }

    /**
     * A link the owner can open on their phone, no login, for a day.
     */
    public function makeShareLink(): void
    {
        $page = Page::query()->findOrFail($this->pageId);
        $this->shareUrl = PreviewLink::make($page, now()->addDay());
    }

    public function addItem(int $index, string $key): void
    {
        $this->remember();

        $this->blocks[$index]['data'][$key][] = $this->emptyItem($index, $key);
        $this->markPreviewStale();
    }

    public function removeItem(int $index, string $key, int $position): void
    {
        $this->remember();

        unset($this->blocks[$index]['data'][$key][$position]);
        $this->blocks[$index]['data'][$key] = array_values($this->blocks[$index]['data'][$key]);
        $this->markPreviewStale();
    }

    /**
     * What one more row of a repeating field needs: one empty value per sub-field.
     *
     * @return array<string, mixed>
     */
    private function emptyItem(int $index, string $key): array
    {
        $item = [];

        foreach (BlockType::from($this->blocks[$index]['type'])->schema() as $field) {
            if (($field['key'] ?? '') !== $key || ($field['type'] ?? '') !== 'repeater') {
                continue;
            }

            foreach ($field['fields'] ?? [] as $sub) {
                $item[$sub['key']] = ($sub['type'] ?? 'text') === 'image' ? null : '';
            }
        }

        return $item;
    }

    /**
     * Switch this page between the structured sections and the campaign canvas (D-107). The
     * sections are never touched — they wait underneath, so switching back loses nothing. The
     * switch itself is editor state only, like applying a template: nothing changes on the site
     * until Save draft.
     */
    public function toggleCanvas(): void
    {
        abort_unless($this->canUseRawHtml(), 403);

        $this->isCanvas = ! $this->isCanvas;

        if ($this->isCanvas && trim($this->canvasHtml) === '') {
            $this->canvasHtml = <<<'HTML'
                <section class="mx-auto max-w-3xl px-5 py-20 text-center">
                    <h1 class="text-4xl font-medium">A campaign headline, in your own words</h1>
                    <p class="mt-4 text-lg">One page, hand-placed — free of the theme's sections. Scripts are stripped on save.</p>
                </section>
                HTML;
        }

        $this->toast($this->isCanvas
            ? 'Campaign canvas on — this page now renders hand-placed HTML (save to apply).'
            : 'Back to the structured sections (save to apply).');
    }

    public function save(bool $publish = false): void
    {
        $page = $this->page();

        if ($publish) {
            abort_unless($this->user()->can('website.publish'), 403);
        }

        if ($this->pageMovedOn()) {
            $this->conflict = true;
            $this->toast('Someone saved this page after you opened it. Reload the latest version before saving.', 'error');

            return;
        }

        $this->validate();

        // One opening section per page: the first hero wins, the rest keep their content but stop
        // showing. Done before the publish gate so the gate sees the page a visitor would see.
        $hiddenHeroes = $this->hideExtraHeroes();

        $input = $this->input();

        if ($publish) {
            $issues = PageValidator::issues($input, $page);

            if ($issues !== []) {
                $this->issues = $issues;
                $this->toast('This page is not ready to publish: '.$issues[0], 'error');

                return;
            }
        }

        $this->issues = [];

        $saved = (new SavePage)($page, $input, $this->blocks, $publish, $this->user());

        // Our own write is not somebody else's: the editor continues on the new fingerprint.
        $this->sourceHash = PageFingerprint::of($saved->fresh(['blocks']));

        if ($hiddenHeroes > 0) {
            $this->toast('One opening section per page: '.$hiddenHeroes.' other hero '.
                ($hiddenHeroes === 1 ? 'is' : 'are').' kept below but hidden — its content is still there.');
        }

        $this->toast($publish ? '“'.$saved->title.'” is live at '.$saved->path() : 'Draft saved.');

        $this->redirectRoute('admin.website.pages.edit', $saved, navigate: true);
    }

    /**
     * Save the draft without the explicit-save ceremony: what the live preview does behind the
     * editor. A page that has never been saved is created here, so the preview exists before the
     * first Save draft. Returns false (and explains why) when the page is not saveable yet.
     */
    private function persistDraft(): bool
    {
        if ($this->pageMovedOn()) {
            $this->conflict = true;
            $this->previewNotice = 'This page changed elsewhere. Reload the latest version to keep previewing — nothing was saved.';

            return false;
        }

        if (trim($this->title) === '' || trim($this->slug) === '') {
            $this->previewNotice = 'Give the page a title and a URL — the preview appears the moment it can be saved.';

            return false;
        }

        $validator = Validator::make(
            ['title' => $this->title, 'slug' => $this->slug],
            [
                'title' => ['required', 'string', 'max:190'],
                'slug' => ['required', 'string', 'max:190', 'regex:#^/[a-z0-9][a-z0-9\-/]*$#', 'unique:pages,slug'.($this->pageId !== null ? ','.$this->pageId : '')],
            ],
            $this->messages(),
        );

        if ($validator->fails()) {
            $this->previewNotice = (string) $validator->errors()->first();

            return false;
        }

        $wasNew = $this->pageId === null;

        $saved = (new SavePage)($this->page(), $this->input(), $this->blocks, false, $this->user());

        $this->pageId = $saved->id;
        // The rows are the truth now: take back their ids and keys so the next save updates them
        // instead of inserting a second copy of every section.
        $this->reload($saved);
        $this->previewNotice = '';

        if ($wasNew) {
            // The address bar follows the page that now exists: a reload lands on the editor, not
            // on a blank "new page" with an orphaned draft behind it.
            $this->dispatch('page-created', url: route('admin.website.pages.edit', $saved));
        }

        return true;
    }

    /**
     * A page opens once. When two visible heroes are saved, the first one with content stays and
     * the others are hidden (never deleted) — the section is still in the editor, one tick away.
     */
    private function hideExtraHeroes(): int
    {
        $heroTypes = [BlockType::Hero->value, BlockType::Slider->value];
        $visible = [];

        foreach ($this->blocks as $index => $block) {
            if (in_array((string) $block['type'], $heroTypes, true) && ($block['visible'] ?? true)) {
                $visible[$index] = $block;
            }
        }

        if (count($visible) < 2) {
            return 0;
        }

        $keeper = null;

        foreach ($visible as $index => $block) {
            if ($this->heroHasContent($block)) {
                $keeper = $index;

                break;
            }
        }

        $keeper ??= array_key_first($visible);
        $hidden = 0;

        foreach (array_keys($visible) as $index) {
            if ($index === $keeper) {
                continue;
            }

            $this->blocks[$index]['visible'] = false;
            $hidden++;
        }

        return $hidden;
    }

    /** @param array<string, mixed> $block */
    private function heroHasContent(array $block): bool
    {
        if ((string) $block['type'] === BlockType::Slider->value) {
            foreach ((array) ($block['data']['slides'] ?? []) as $slide) {
                if (is_array($slide) && (($slide['image_id'] ?? null) !== null || trim((string) ($slide['heading'] ?? '')) !== '')) {
                    return true;
                }
            }

            return false;
        }

        return trim((string) ($block['data']['heading'] ?? '')) !== ''
            || ($block['data']['image_id'] ?? null) !== null;
    }

    public function unpublish(): void
    {
        abort_unless($this->user()->can('website.publish'), 403);

        $page = $this->page();
        $page->forceFill(['status' => PageStatus::Draft])->save();
        PageCache::invalidate($page);

        $this->status = PageStatus::Draft->value;
        $this->toast('The page is no longer public.');
    }

    public function restore(int $versionId): void
    {
        abort_unless($this->user()->can('website.edit'), 403);

        $version = PageVersion::query()->where('page_id', $this->pageId)->findOrFail($versionId);
        $page = (new RestorePageVersion)($version, $this->user());

        $this->reload($page);
        $this->toast('The page is back to the version of '.$version->created_at?->format('d M Y, H:i').'.');
    }

    /** Free HTML is the owner's tool until the sanitiser has been exercised in production (S-03). */
    private function canUseRawHtml(): bool
    {
        return $this->user()->can('website.raw_html');
    }

    /**
     * The section library without the HTML block for anyone who may not write markup.
     *
     * @return list<BlockType>
     */
    private function availableBlockTypes(): array
    {
        return $this->canUseRawHtml()
            ? BlockType::cases()
            : array_values(array_filter(BlockType::cases(), static fn (BlockType $type): bool => $type !== BlockType::Html));
    }

    public function render(): View
    {
        $media = Media::query()->orderByDesc('id')->get(['id', 'title', 'alt', 'path']);

        return view('livewire.admin.website.page-editor', [
            'page' => $this->pageId !== null ? Page::query()->with(['versions.user'])->find($this->pageId) : null,
            'types' => collect(PageType::cases())->mapWithKeys(fn (PageType $type): array => [$type->value => $type->label()])->all(),
            'blockTypes' => collect($this->availableBlockTypes())->mapWithKeys(fn (BlockType $type): array => [$type->value => $type->label()])->all(),
            'library' => collect($this->availableBlockTypes())
                ->map(fn (BlockType $type): array => ['value' => $type->value, 'label' => $type->label(), 'description' => $type->description(), 'category' => $type->category()])
                ->groupBy('category'),
            'canUseRawHtml' => $this->canUseRawHtml(),
            'shadowedBy' => config('website.builder.shadow_notice', true) ? StaticShadow::file($this->slug) : null,
            'savedSections' => SavedSection::query()->with('creator')->orderBy('name')->get(),
            'templates' => PageTemplate::query()->orderBy('page_type')->orderByDesc('is_default')->orderBy('name')->get(),
            'pages' => Page::query()->orderBy('title')->get(['id', 'title', 'slug']),
            'media' => $media,
            // The builder's own check (19 Oct): what would be improved before Publish is pressed.
            'audit' => PageAudit::findings(
                $this->title,
                $this->seoTitle,
                $this->seoDescription,
                $this->focusKeyword,
                $this->blocks,
                $media->mapWithKeys(fn (Media $image): array => [$image->id => (string) $image->alt])->all(),
            ),
            'canUndo' => $this->undoStack !== [],
            'canRedo' => $this->redoStack !== [],
        ]);
    }

    /** A key Livewire's drag-to-reorder can hold on to across requests — and the preview can click on. */
    private function newKey(): string
    {
        return 'b'.Str::random(8);
    }

    private function page(): Page
    {
        return $this->pageId !== null ? Page::query()->findOrFail($this->pageId) : new Page;
    }

    /** Has anyone saved this page since this editor loaded it? */
    private function pageMovedOn(): bool
    {
        if ($this->pageId === null || ! config('website.builder.conflict_check', true)) {
            return false;
        }

        if ($this->sourceHash === '') {
            return false;
        }

        $current = Page::query()->with('blocks')->find($this->pageId);

        return $current !== null && PageFingerprint::of($current) !== $this->sourceHash;
    }

    /** Drop the local copy and open the version that is really in the database. */
    public function reloadLatest(): void
    {
        abort_unless($this->user()->can('website.edit'), 403);

        if ($this->pageId === null) {
            return;
        }

        $this->reload(Page::query()->with('blocks')->findOrFail($this->pageId));
        $this->conflict = false;
        $this->issues = [];
        $this->previewNotice = '';
        $this->previewNonce++;
        $this->toast('Loaded the latest saved version.');
    }

    /**
     * @return array{type: PageType, slug: string, title: string, excerpt: ?string, seo: array<string, mixed>, schema_type: ?string, sort: int, template: ?string, canvas_html: string, blocks: list<array<string, mixed>>}
     */
    private function input(): array
    {
        return [
            'blocks' => $this->blocks,
            'type' => PageType::from($this->type),
            'slug' => $this->slug,
            'title' => trim($this->title),
            'excerpt' => trim($this->excerpt) ?: null,
            'seo' => [
                'title' => trim($this->seoTitle),
                'description' => trim($this->seoDescription),
                'robots' => $this->seoRobots,
                'focus_keyword' => trim($this->focusKeyword),
                'og_image_id' => $this->ogImageId,
                'last_reviewed' => now()->toDateString(),
            ],
            'schema_type' => null,
            'sort' => $this->sort,
            'template' => $this->isCanvas ? 'canvas' : null,
            'canvas_html' => $this->canvasHtml,
        ];
    }

    private function reload(?Page $page): void
    {
        if ($page === null || ! $page->exists) {
            $this->pageId = null;
            $this->type = PageType::Service->value;
            $this->status = PageStatus::Draft->value;
            $this->blocks = [];
            $this->isCanvas = false;
            $this->canvasHtml = '';
            $this->sourceHash = '';

            return;
        }

        $this->pageId = $page->id;
        $this->title = $page->title;
        $this->slug = $page->slug;
        $this->type = $page->type->value;
        $this->excerpt = (string) $page->excerpt;
        $this->status = $page->status->value;
        $this->sort = (int) $page->sort;
        $this->seoTitle = (string) ($page->seo['title'] ?? '');
        $this->seoDescription = (string) ($page->seo['description'] ?? '');
        $this->seoRobots = (string) ($page->seo['robots'] ?? '');
        $this->focusKeyword = (string) ($page->seo['focus_keyword'] ?? '');
        $this->ogImageId = $page->seo['og_image_id'] ?? null;
        $this->isCanvas = $page->template === 'canvas';
        $this->canvasHtml = (string) $page->canvas_html;
        $this->sourceHash = PageFingerprint::of($page);
        $this->blocks = $page->blocks->map(fn ($block): array => [
            'id' => $block->id,
            'key' => $block->block_key ?? $this->newKey(),
            'type' => $block->type->value,
            'visible' => $block->visible,
            'data' => $block->data ?? $block->type->fields(),
            'settings' => SectionStyle::clean($block->settings),
        ])->all();
    }

    /** @return array<string, array<int, mixed>> */
    protected function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:190'],
            'slug' => ['required', 'string', 'max:190', 'regex:#^/[a-z0-9][a-z0-9\-/]*$#', 'unique:pages,slug'.($this->pageId !== null ? ','.$this->pageId : '')],
            'excerpt' => ['nullable', 'string', 'max:400'],
            'seoTitle' => ['nullable', 'string', 'max:70'],
            'seoDescription' => ['nullable', 'string', 'max:180'],
            'focusKeyword' => ['nullable', 'string', 'max:80'],
            'sort' => ['integer', 'min:0', 'max:9999'],
        ];
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return [
            'slug.regex' => 'Use lower-case letters, numbers and dashes only, for example /interior-design/.',
            'slug.unique' => 'Another page already uses this URL.',
        ];
    }
}
