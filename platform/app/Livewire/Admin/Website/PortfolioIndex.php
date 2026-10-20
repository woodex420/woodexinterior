<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Website;

use App\Domain\Projects\Models\Project;
use App\Domain\Website\Enums\PageStatus;
use App\Domain\Website\Enums\PageType;
use App\Domain\Website\Models\Page;
use App\Domain\Website\Services\ListingIndex;
use App\Domain\Website\Services\Menus;
use App\Domain\Website\Services\PageCache;
use App\Livewire\Concerns\InteractsWithAdmin;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Portfolio (Website → Portfolio): every page of type `project`, worked as case studies.
 *
 * A study is still a page — same block editor, same SEO fields, same publish rules — so this screen is a
 * view over `/projects/*`, not a second content system. What it adds is the two things a portfolio needs
 * and a page list cannot show: whether the study is linked to a real, consented project in the OS, and
 * whether it appears on the `/projects/` index (whose card list is hand-curated, because Frontend v1 is
 * final). Publishing is what puts a study on the site; the index card is a separate, explicit step so a
 * live page never links to a draft.
 */
#[Layout('layouts::admin')]
#[Title('Portfolio')]
class PortfolioIndex extends Component
{
    use InteractsWithAdmin, WithPagination;

    public string $search = '';

    public string $status = '';

    public bool $showNew = false;

    public string $newTitle = '';

    public string $newSlug = '';

    public string $newExcerpt = '';

    public string $newProjectId = '';

    public bool $showLink = false;

    public ?int $linkPageId = null;

    public string $linkTitle = '';

    public string $linkProjectId = '';

    public function mount(): void
    {
        abort_unless($this->user()->can('website.edit'), 403);
    }

    public function updatedNewTitle(): void
    {
        $this->newSlug = Str::slug($this->newTitle);
        $this->resetValidation('newSlug');
    }

    public function createStudy(): void
    {
        abort_unless($this->user()->can('website.edit'), 403);

        $this->validate([
            'newTitle' => ['required', 'string', 'max:120'],
            'newSlug' => ['required', 'string', 'max:90', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'newExcerpt' => ['nullable', 'string', 'max:300'],
            'newProjectId' => ['nullable', 'integer', Rule::exists('projects', 'id')->whereNull('deleted_at')->whereNull('case_study_page_id')],
        ], [
            'newProjectId.exists' => 'That project already has a case study.',
        ], [
            'newTitle' => 'title',
            'newSlug' => 'URL',
            'newExcerpt' => 'summary',
            'newProjectId' => 'project',
        ]);

        $slug = '/projects/'.trim($this->newSlug, '/').'/';

        if (Page::withTrashed()->where('slug', $slug)->exists()) {
            $this->addError('newSlug', 'Another page already uses that URL.');

            return;
        }

        $listing = Page::query()->where('slug', '/projects/')->first();
        $title = trim($this->newTitle);
        $excerpt = trim($this->newExcerpt);
        $projectId = $this->newProjectId === '' ? null : (int) $this->newProjectId;

        $page = DB::transaction(function () use ($title, $excerpt, $slug, $listing, $projectId): Page {
            $page = Page::query()->create([
                'type' => PageType::Project,
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

            $page->blocks()->create([
                'type' => 'hero',
                'block_key' => 'b'.Str::lower(Str::random(8)),
                'data' => [
                    'eyebrow' => 'Portfolio',
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

            if ($projectId !== null) {
                Project::query()->whereKey($projectId)->update(['case_study_page_id' => $page->id]);
            }

            return $page;
        });

        PageCache::invalidate($page);

        $this->showNew = false;
        $this->newTitle = '';
        $this->newSlug = '';
        $this->newExcerpt = '';
        $this->newProjectId = '';

        $this->toast('Draft created — write the study, publish it, then show it on /projects/.');
        $this->redirectRoute('admin.website.pages.edit', $page, navigate: true);
    }

    public function duplicate(int $pageId): void
    {
        abort_unless($this->user()->can('website.edit'), 403);

        $page = $this->study($pageId);
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
        $this->toast("“{$copy->title}” created as a draft, not linked to a project.");
    }

    public function unpublish(int $pageId): void
    {
        abort_unless($this->user()->can('website.publish'), 403);

        $page = $this->study($pageId);

        ListingIndex::remove($page);
        $page->forceFill(['status' => PageStatus::Draft])->save();

        PageCache::invalidate($page);
        Menus::flush();

        $this->toast("“{$page->title}” is a draft again and off the /projects/ index.");
    }

    /** Puts the study's card on the index page, or takes it off. */
    public function toggleIndex(int $pageId): void
    {
        abort_unless($this->user()->can('website.publish'), 403);

        $page = $this->study($pageId);

        if (ListingIndex::isListed($page)) {
            ListingIndex::remove($page);
            $this->toast("“{$page->title}” is off the /projects/ index.");

            return;
        }

        if ($page->status !== PageStatus::Published) {
            $this->toast('Publish the study first — a card on a live page must not lead to a draft.', 'error');

            return;
        }

        if (! ListingIndex::add($page)) {
            $this->toast('The /projects/ page has no card list to add it to.', 'error');

            return;
        }

        $this->toast("“{$page->title}” is on the /projects/ index.");
    }

    public function openLink(int $pageId): void
    {
        $page = $this->study($pageId);
        $project = Project::query()->where('case_study_page_id', $page->id)->first();

        $this->linkPageId = $page->id;
        $this->linkTitle = $page->title;
        $this->linkProjectId = $project === null ? '' : (string) $project->id;
        $this->showLink = true;
        $this->resetValidation('linkProjectId');
    }

    public function linkProject(): void
    {
        abort_unless($this->user()->can('website.edit'), 403);

        if ($this->linkPageId === null) {
            return;
        }

        $page = $this->study($this->linkPageId);

        $this->validate([
            'linkProjectId' => ['nullable', 'integer', Rule::exists('projects', 'id')->whereNull('deleted_at')],
        ], [], ['linkProjectId' => 'project']);

        $projectId = $this->linkProjectId === '' ? null : (int) $this->linkProjectId;

        if ($projectId !== null) {
            $project = Project::query()->find($projectId);

            if ($project !== null && $project->case_study_page_id !== null && $project->case_study_page_id !== $page->id) {
                $this->addError('linkProjectId', 'That project already has a case study.');

                return;
            }
        }

        DB::transaction(function () use ($page, $projectId): void {
            Project::query()->where('case_study_page_id', $page->id)->update(['case_study_page_id' => null]);

            if ($projectId !== null) {
                Project::query()->whereKey($projectId)->update(['case_study_page_id' => $page->id]);
            }
        });

        $this->showLink = false;
        $this->toast($projectId === null
            ? "“{$page->title}” is a design study again — no project linked."
            : 'The study is linked to the project.');
    }

    public function render(): View
    {
        $studies = Page::query()
            ->where('type', PageType::Project)
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

        $listing = Page::query()->where('slug', '/projects/')->first();

        return view('livewire.admin.website.portfolio-index', [
            'studies' => $studies,
            'linked' => Project::query()
                ->whereIn('case_study_page_id', $studies->pluck('id')->all())
                ->get()
                ->keyBy('case_study_page_id'),
            'listing' => $listing,
            'listed' => ListingIndex::urls($listing),
            'published' => Page::query()->where('type', PageType::Project)->where('status', PageStatus::Published)->count(),
            'drafts' => Page::query()->where('type', PageType::Project)->where('status', PageStatus::Draft)->count(),
            'projects' => $this->availableProjects(),
        ]);
    }

    /**
     * Projects that can still carry a case study: not deleted, and not already linked to one.
     *
     * @return Collection<int, Project>
     */
    private function availableProjects(): Collection
    {
        return Project::query()
            ->whereNull('case_study_page_id')
            ->orderByDesc('code')
            ->get(['id', 'code', 'name', 'city', 'case_study_consent']);
    }

    private function study(int $pageId): Page
    {
        return Page::query()->where('type', PageType::Project)->findOrFail($pageId);
    }
}
