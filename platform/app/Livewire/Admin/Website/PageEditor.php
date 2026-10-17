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
use App\Domain\Website\Models\PageVersion;
use App\Domain\Website\Services\PageCache;
use App\Domain\Website\Services\PageValidator;
use App\Livewire\Concerns\InteractsWithAdmin;
use Illuminate\Contracts\View\View;
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

    /** @var list<array{id: ?int, type: string, visible: bool, data: array<string, mixed>}> */
    public array $blocks = [];

    public string $newBlockType = '';

    /** @var list<string> What still stands between this page and publishing. */
    public array $issues = [];

    public bool $showVersions = false;

    public function mount(?Page $page = null): void
    {
        abort_unless($this->user()->can('website.edit'), 403);

        $this->reload($page);
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

        $this->blocks[] = [
            'id' => null,
            'type' => $block->value,
            'visible' => true,
            'data' => $block->fields(),
        ];

        $this->newBlockType = '';
    }

    public function removeBlock(int $index): void
    {
        unset($this->blocks[$index]);
        $this->blocks = array_values($this->blocks);
    }

    public function moveBlock(int $index, int $direction): void
    {
        $to = $index + $direction;

        if (! isset($this->blocks[$to])) {
            return;
        }

        [$this->blocks[$index], $this->blocks[$to]] = [$this->blocks[$to], $this->blocks[$index]];
        $this->blocks = array_values($this->blocks);
    }

    public function toggleBlock(int $index): void
    {
        $this->blocks[$index]['visible'] = ! (bool) ($this->blocks[$index]['visible'] ?? true);
    }

    public function addItem(int $index, string $key): void
    {
        $this->blocks[$index]['data'][$key][] = $this->emptyItem($index, $key);
    }

    public function removeItem(int $index, string $key, int $position): void
    {
        unset($this->blocks[$index]['data'][$key][$position]);
        $this->blocks[$index]['data'][$key] = array_values($this->blocks[$index]['data'][$key]);
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

    public function save(bool $publish = false): void
    {
        $page = $this->page();
        $input = $this->input();

        if ($publish) {
            abort_unless($this->user()->can('website.publish'), 403);

            $issues = PageValidator::issues($input, $page);

            if ($issues !== []) {
                $this->issues = $issues;
                $this->toast('This page is not ready to publish: '.$issues[0], 'error');

                return;
            }
        }

        $this->validate();
        $this->issues = [];

        $saved = (new SavePage)($page, $input, $this->blocks, $publish, $this->user());

        $this->toast($publish ? '“'.$saved->title.'” is live at '.$saved->path() : 'Draft saved.');

        $this->redirectRoute('admin.website.pages.edit', $saved, navigate: true);
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

    public function render(): View
    {
        return view('livewire.admin.website.page-editor', [
            'page' => $this->pageId !== null ? Page::query()->with(['versions.user'])->find($this->pageId) : null,
            'types' => collect(PageType::cases())->mapWithKeys(fn (PageType $type): array => [$type->value => $type->label()])->all(),
            'blockTypes' => collect(BlockType::cases())->mapWithKeys(fn (BlockType $type): array => [$type->value => $type->label()])->all(),
            'pages' => Page::query()->orderBy('title')->get(['id', 'title', 'slug']),
            'media' => Media::query()->orderByDesc('id')->get(['id', 'title', 'alt', 'path']),
        ]);
    }

    private function page(): Page
    {
        return $this->pageId !== null ? Page::query()->findOrFail($this->pageId) : new Page;
    }

    /**
     * @return array{type: PageType, slug: string, title: string, excerpt: ?string, seo: array<string, mixed>, schema_type: ?string, sort: int, blocks: list<array<string, mixed>>}
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
        ];
    }

    private function reload(?Page $page): void
    {
        if ($page === null || ! $page->exists) {
            $this->pageId = null;
            $this->type = PageType::Service->value;
            $this->status = PageStatus::Draft->value;
            $this->blocks = [];

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
        $this->blocks = $page->blocks->map(fn ($block): array => [
            'id' => $block->id,
            'type' => $block->type->value,
            'visible' => $block->visible,
            'data' => $block->data ?? $block->type->fields(),
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
