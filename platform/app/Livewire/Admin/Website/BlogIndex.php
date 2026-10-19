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
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The blog (Website → Blog): every page of type `insight`, worked as posts rather than as pages.
 *
 * A post is still a page — the same block editor, the same SEO fields, the same publish rules, the
 * same sitemap and health checks — so this screen is a view over `/insights/*`, not a second content
 * system. It only adds what a blog needs: a short list, a fast "new post" form that fills the URL and
 * the first sections for you, duplication, and taking a post down without hunting for it in Pages.
 */
#[Layout('layouts::admin')]
#[Title('Blog')]
class BlogIndex extends Component
{
    use InteractsWithAdmin, WithPagination;

    public string $search = '';

    public string $status = '';

    public bool $showNew = false;

    public string $newTitle = '';

    public string $newSlug = '';

    public string $newExcerpt = '';

    public function mount(): void
    {
        abort_unless($this->user()->can('website.edit'), 403);
    }

    /** Typing a title proposes the URL; the editor can still change it before saving. */
    public function updatedNewTitle(): void
    {
        $this->newSlug = Str::slug($this->newTitle);
        $this->resetValidation('newSlug');
    }

    public function createPost(): void
    {
        abort_unless($this->user()->can('website.edit'), 403);

        $this->validate([
            'newTitle' => ['required', 'string', 'max:120'],
            'newSlug' => ['required', 'string', 'max:90', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'newExcerpt' => ['nullable', 'string', 'max:300'],
        ], [], [
            'newTitle' => 'title',
            'newSlug' => 'URL',
            'newExcerpt' => 'summary',
        ]);

        $slug = '/insights/'.trim($this->newSlug, '/').'/';

        if (Page::withTrashed()->where('slug', $slug)->exists()) {
            $this->addError('newSlug', 'Another page already uses that URL.');

            return;
        }

        $listing = Page::query()->where('slug', '/insights/')->first();
        $title = trim($this->newTitle);
        $excerpt = trim($this->newExcerpt);

        $page = DB::transaction(function () use ($title, $excerpt, $slug, $listing): Page {
            $page = Page::query()->create([
                'type' => PageType::Insight,
                'slug' => $slug,
                'title' => $title,
                'excerpt' => $excerpt !== '' ? $excerpt : null,
                'status' => PageStatus::Draft,
                'parent_id' => $listing?->id,
                'author_id' => $this->user()->id,
                'seo' => [
                    'title' => $title,
                    'description' => $excerpt !== '' ? $excerpt : null,
                    'robots' => 'index,follow',
                ],
            ]);

            // Two sections to write into, not an empty canvas: the post's own hero, then body text.
            $page->blocks()->create([
                'type' => 'hero',
                'block_key' => 'b'.Str::lower(Str::random(8)),
                'data' => [
                    'eyebrow' => 'Insights',
                    'heading' => $title,
                    'text' => $excerpt,
                    'image_id' => '',
                    'cta_label' => '',
                    'cta_url' => '',
                ],
                'visible' => true,
                'position' => 0,
            ]);

            $page->blocks()->create([
                'type' => 'text',
                'block_key' => 'b'.Str::lower(Str::random(8)),
                'data' => ['heading' => '', 'body' => ''],
                'visible' => true,
                'position' => 1,
            ]);

            return $page;
        });

        PageCache::invalidate($page);

        $this->showNew = false;
        $this->newTitle = '';
        $this->newSlug = '';
        $this->newExcerpt = '';

        $this->toast('Draft created — write the post, then press Publish.');
        $this->redirectRoute('admin.website.pages.edit', $page, navigate: true);
    }

    public function duplicate(int $pageId): void
    {
        abort_unless($this->user()->can('website.edit'), 403);

        $page = $this->post($pageId);
        $base = trim((string) $page->slug, '/').'-copy';
        $slug = '/'.$base.'/';

        for ($i = 2; Page::withTrashed()->where('slug', $slug)->exists(); $i++) {
            $slug = '/'.$base.'-'.$i.'/';
        }

        $copy = DB::transaction(function () use ($page, $slug): Page {
            $copy = $page->replicate(['slug', 'published_at']);
            $copy->fill([
                'slug' => $slug,
                'title' => $page->title.' (copy)',
                'status' => PageStatus::Draft,
                'author_id' => $this->user()->id,
            ])->save();

            foreach ($page->blocks()->orderBy('position')->get() as $block) {
                $copy->blocks()->create([
                    'type' => $block->type,
                    'block_key' => 'b'.Str::lower(Str::random(8)),
                    'data' => $block->data,
                    'visible' => $block->visible,
                    'position' => $block->position,
                ]);
            }

            return $copy;
        });

        PageCache::invalidate($copy);
        $this->toast("“{$copy->title}” created as a draft.");
    }

    public function unpublish(int $pageId): void
    {
        abort_unless($this->user()->can('website.publish'), 403);

        $page = $this->post($pageId);
        $page->forceFill(['status' => PageStatus::Draft])->save();

        PageCache::invalidate($page);
        Menus::flush();

        $this->toast("“{$page->title}” is no longer public.");
    }

    public function render(): View
    {
        $posts = Page::query()
            ->where('type', PageType::Insight)
            ->when(trim($this->search) !== '', function ($query): void {
                $needle = '%'.Str::lower(trim($this->search)).'%';
                $query->where(function ($inner) use ($needle): void {
                    $inner->whereRaw('lower(title) like ?', [$needle])
                        ->orWhereRaw('lower(slug) like ?', [$needle])
                        ->orWhereRaw("lower(coalesce(excerpt, '')) like ?", [$needle]);
                });
            })
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate(20);

        return view('livewire.admin.website.blog-index', [
            'posts' => $posts,
            'published' => Page::query()->where('type', PageType::Insight)->where('status', PageStatus::Published)->count(),
            'drafts' => Page::query()->where('type', PageType::Insight)->where('status', PageStatus::Draft)->count(),
            'listing' => Page::query()->where('slug', '/insights/')->first(),
        ]);
    }

    private function post(int $pageId): Page
    {
        return Page::query()->where('type', PageType::Insight)->findOrFail($pageId);
    }
}
