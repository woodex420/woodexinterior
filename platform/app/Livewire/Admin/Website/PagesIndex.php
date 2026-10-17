<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Website;

use App\Domain\Website\Enums\PageStatus;
use App\Domain\Website\Enums\PageType;
use App\Domain\Website\Models\Page;
use App\Domain\Website\Services\Menus;
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
            ->get();

        // How many of the 143 URLs of the previous website are live or redirected (docs/09 §3).
        $counts = [
            'pages' => Page::query()->count(),
            'published' => Page::query()->where('status', PageStatus::Published)->count(),
            'redirects' => DB::table('redirects')->count(),
            'media' => DB::table('website_media')->count(),
        ];

        return view('livewire.admin.website.pages-index', [
            'pages' => $pages,
            'counts' => $counts,
            'types' => collect(PageType::cases())->mapWithKeys(fn (PageType $type): array => [$type->value => $type->label()])->all(),
            'statuses' => collect(PageStatus::cases())->mapWithKeys(fn (PageStatus $status): array => [$status->value => $status->label()])->all(),
        ]);
    }
}
