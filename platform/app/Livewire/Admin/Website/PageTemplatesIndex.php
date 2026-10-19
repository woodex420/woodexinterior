<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Website;

use App\Domain\Website\Actions\CreatePageFromTemplate;
use App\Domain\Website\Enums\PageType;
use App\Domain\Website\Models\PageTemplate;
use App\Livewire\Concerns\InteractsWithAdmin;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Page templates, as a screen of their own (V2-3 F5, docs/14 §5a): every saved layout the studio can
 * start a page from, with the actions that were previously scattered — create a page from it, rename
 * it, describe it, make it the default for its page type, duplicate it, retire it.
 *
 * A template is a layout, never text: pages copy its sections at creation and never look back, so
 * editing or deleting a template cannot change a page that already exists.
 */
#[Layout('layouts::admin')]
#[Title('Page templates')]
class PageTemplatesIndex extends Component
{
    use InteractsWithAdmin;

    public string $search = '';

    public string $type = '';

    public bool $showEdit = false;

    public ?int $editingId = null;

    public string $editName = '';

    public string $editDescription = '';

    public string $editType = PageType::Page->value;

    public bool $editDefault = false;

    /** @return array<string, list<string>> */
    protected function rules(): array
    {
        return [
            'editName' => ['required', 'string', 'max:120'],
            'editDescription' => ['nullable', 'string', 'max:300'],
            'editType' => ['required', 'string', 'in:'.implode(',', array_column(PageType::cases(), 'value'))],
            'editDefault' => ['boolean'],
        ];
    }

    /** A template is copied onto the page at creation — the page is not linked to it. */
    public function createPage(int $templateId): void
    {
        abort_unless($this->user()->can('website.edit'), 403);

        $template = PageTemplate::query()->findOrFail($templateId);
        $page = app(CreatePageFromTemplate::class)->handle($template, $this->user());

        $this->toast("“{$page->title}” created as a draft — add the words, then publish.");
        $this->redirectRoute('admin.website.pages.edit', $page, navigate: true);
    }

    public function edit(int $templateId): void
    {
        abort_unless($this->user()->can('website.edit'), 403);

        $template = PageTemplate::query()->findOrFail($templateId);

        $this->editingId = $template->id;
        $this->editName = $template->name;
        $this->editDescription = (string) ($template->description ?? '');
        $this->editType = $template->page_type->value;
        $this->editDefault = $template->is_default;
        $this->showEdit = true;
    }

    public function save(): void
    {
        abort_unless($this->user()->can('website.edit'), 403);

        $this->validate();

        $template = PageTemplate::query()->findOrFail($this->editingId);
        $wasDefault = $template->is_default;

        DB::transaction(function () use ($template): void {
            // One default per page type: the pages index opens a new page on it.
            if ($this->editDefault) {
                PageTemplate::query()
                    ->where('page_type', $this->editType)
                    ->whereKeyNot($template->id)
                    ->update(['is_default' => false]);
            }

            $template->update([
                'name' => trim($this->editName),
                'description' => trim($this->editDescription) !== '' ? trim($this->editDescription) : null,
                'page_type' => $this->editType,
                'is_default' => $this->editDefault,
            ]);
        });

        if ($wasDefault && ! $this->editDefault) {
            $this->toast('Saved. This layout is no longer the default for its page type.');
        } else {
            $this->toast("“{$template->name}” saved.");
        }

        $this->cancelEdit();
    }

    public function duplicate(int $templateId): void
    {
        abort_unless($this->user()->can('website.edit'), 403);

        $template = PageTemplate::query()->findOrFail($templateId);

        $copy = $template->replicate(['is_default']);
        $copy->name = $this->uniqueName($template->name.' (copy)');
        $copy->is_default = false;
        $copy->created_by = $this->user()->id;
        $copy->save();

        $this->toast("“{$copy->name}” added — edit it to make it your own.");
    }

    public function delete(int $templateId): void
    {
        // Retiring a layout is a destructive act on shared content: publish rights, like deleting a page.
        abort_unless($this->user()->can('website.publish'), 403);

        $template = PageTemplate::query()->findOrFail($templateId);

        if ($template->is_default) {
            $this->toast('This layout is the default for its page type — choose another default first.', 'error');

            return;
        }

        $name = $template->name;
        $template->delete();

        $this->toast("“{$name}” deleted. Pages made from it are untouched.");
    }

    public function cancelEdit(): void
    {
        $this->showEdit = false;
        $this->editingId = null;
        $this->editName = '';
        $this->editDescription = '';
        $this->editDefault = false;
        $this->resetValidation();
    }

    public function render(): View
    {
        $templates = PageTemplate::query()
            ->when(trim($this->search) !== '', function ($query): void {
                $needle = '%'.Str::lower(trim($this->search)).'%';
                $query->where(function ($inner) use ($needle): void {
                    $inner->whereRaw('lower(name) like ?', [$needle])
                        ->orWhereRaw('lower(coalesce(description, \'\')) like ?', [$needle]);
                });
            })
            ->when($this->type !== '', fn ($query) => $query->where('page_type', $this->type))
            ->orderBy('page_type')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        $types = PageTemplate::query()
            ->select('page_type')
            ->distinct()
            ->pluck('page_type')
            ->filter()
            ->sortBy(fn (PageType $type): string => $type->label())
            ->values();

        return view('livewire.admin.website.page-templates-index', [
            'templates' => $templates,
            'types' => $types,
            'total' => PageTemplate::query()->count(),
        ]);
    }

    private function uniqueName(string $base): string
    {
        $name = $base;

        for ($i = 2; PageTemplate::query()->where('name', $name)->exists(); $i++) {
            $name = $base.' '.$i;
        }

        return $name;
    }
}
