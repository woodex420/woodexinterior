<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Website;

use App\Domain\Website\Actions\CreatePageFromTemplate;
use App\Domain\Website\Enums\PageStatus;
use App\Domain\Website\Enums\PageType;
use App\Domain\Website\Models\Page;
use App\Domain\Website\Models\PageTemplate;
use App\Domain\Website\Services\Menus;
use App\Domain\Website\Services\PageAudit;
use App\Domain\Website\Services\PageCache;
use App\Livewire\Concerns\InteractsWithAdmin;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * All pages of the public website (docs/09 §2.2). Everything a page shows is edited here: title, URL, sections
 * and the fields search engines read.
 */
#[Layout('layouts::admin')]
#[Title('Pages')]
class PagesIndex extends Component
{
    use InteractsWithAdmin;

    public string $search = '';

    public string $type = '';

    public string $status = '';

    public function mount(): void
    {
        abort_unless($this->user()->can('website.edit'), 403);
    }

    public function unpublish(int $pageId): void
    {
        abort_unless($this->user()->can('website.publish'), 403);

        $page = Page::query()->findOrFail($pageId);
        $page->forceFill(['status' => PageStatus::Draft])->save();

        PageCache::invalidate($page);
        Menus::flush();
        $this->toast('“'.$page->title.'” is no longer public.');
    }

    /**
     * Start a page from a template (V2-3 F5): the layout's sections land on a fresh draft, ready
     * to fill in. Nothing is public until it is edited and published like any other page.
     */
    public function createFromTemplate(int $templateId): void
    {
        $template = PageTemplate::query()->findOrFail($templateId);

        $page = app(CreatePageFromTemplate::class)->handle($template, $this->user());

        $this->toast("“{$page->title}” created as a draft — add the words, then publish.");
        $this->redirectRoute('admin.website.pages.edit', $page, navigate: true);
    }

    public function duplicate(int $pageId): void
    {
        $page = Page::query()->findOrFail($pageId);

        $copy = $page->replicate(['slug', 'published_at']);
        $copy->title = $page->title.' (copy)';
        $copy->slug = Page::normalizeSlug('/'.trim($page->slug, '/').'-copy/');
        $copy->status = PageStatus::Draft;
        $copy->published_at = null;
        $copy->save();

        foreach ($page->blocks as $block) {
            $copy->blocks()->create([
                'type' => $block->type,
                'data' => $block->data,
                'visible' => $block->visible,
                'position' => $block->position,
            ]);
        }

        $this->redirectRoute('admin.website.pages.edit', $copy, navigate: true);
    }

    public function delete(int $pageId): void
    {
        abort_unless($this->user()->can('website.publish'), 403);

        $page = Page::query()->findOrFail($pageId);
        PageCache::invalidate($page);
        $page->delete();
        Menus::flush();

        $this->toast('“'.$page->title.'” moved to the recycle bin.');
    }

    public function render(): View
    {
        $pages = Page::query()
            ->when($this->search !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('title', 'like', '%'.$this->search.'%')
                ->orWhere('slug', 'like', '%'.$this->search.'%')))
            ->when($this->type !== '', fn ($query) => $query->where('type', $this->type))
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->orderBy('type')
            ->orderBy('sort')
            ->orderBy('title')
            ->withCount('blocks')
            ->with('blocks')
            ->get();

        // The page check, on the list (19 Oct): a published page can look fine and still be missing
        // its description or an image's alt text. The count is the editor's own PageAudit, read-only.
        $imageAlt = DB::table('website_media')->pluck('alt', 'id')
            ->map(fn ($alt): string => (string) $alt)
            ->all();

        $checks = $pages->mapWithKeys(function (Page $page) use ($imageAlt): array {
            $findings = PageAudit::findings(
                $page->title,
                (string) ($page->seo['title'] ?? ''),
                (string) ($page->seo['description'] ?? ''),
                (string) ($page->seo['focus_keyword'] ?? ''),
                $page->blocks->map(fn ($block): array => [
                    'type' => $block->type->value,
                    'visible' => (bool) $block->visible,
                    'data' => (array) $block->data,
                ])->all(),
                $imageAlt,
            );

            // Suggestions (a missing focus keyword) do not count here: the list is for pages that
            // need work, not for every page in the site.
            $issues = array_values(array_filter($findings, fn (array $finding): bool => $finding['level'] !== PageAudit::INFO));

            return [$page->id => [
                'count' => count($issues),
                'bad' => count(array_filter($issues, fn (array $finding): bool => $finding['level'] === PageAudit::BAD)),
                'first' => $issues[0]['message'] ?? '',
            ]];
        })->all();

        // How many of the 143 URLs of the previous website are live or redirected (docs/09 §3).
        $counts = [
            'pages' => Page::query()->count(),
            'published' => Page::query()->where('status', PageStatus::Published)->count(),
            'redirects' => DB::table('redirects')->count(),
            'media' => DB::table('website_media')->count(),
        ];

        return view('livewire.admin.website.pages-index', [
            'templates' => PageTemplate::query()->orderBy('page_type')->orderByDesc('is_default')->orderBy('name')->get(),
            'pages' => $pages,
            'checks' => $checks,
            'counts' => $counts,
            'types' => collect(PageType::cases())->mapWithKeys(fn (PageType $type): array => [$type->value => $type->label()])->all(),
            'statuses' => collect(PageStatus::cases())->mapWithKeys(fn (PageStatus $status): array => [$status->value => $status->label()])->all(),
        ]);
    }
}
