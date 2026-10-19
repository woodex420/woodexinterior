<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Website;

use App\Domain\Website\Models\Page;
use App\Domain\Website\Services\PageCache;
use App\Livewire\Concerns\InteractsWithAdmin;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The SEO overview (the old admin's own 'SEO' screen): every page's search listing in one list —
 * the title and the description Google shows, their lengths against the cut-offs, the canonical
 * and the robots rule — with the two fields editable right here. The per-page editor stays the
 * place for everything else (focus keyword, OG image); this is the one-screen health of the
 * studio's search presence.
 */
#[Layout('layouts::admin')]
#[Title('SEO')]
class SeoOverviewIndex extends Component
{
    use InteractsWithAdmin;
    /** Filter: all / published / drafts. */
    public string $filter = 'published';

    /** The quick-fix modal's page and its two fields. */
    public int $editId = 0;

    public bool $showEdit = false;

    public string $editTitle = '';

    public string $editDescription = '';

    /** @return array<string, list<string>> */
    protected function rules(): array
    {
        return [
            'editTitle' => ['required', 'string', 'max:90'],
            'editDescription' => ['nullable', 'string', 'max:320'],
        ];
    }

    public function setFilter(string $filter): void
    {
        $this->filter = in_array($filter, ['published', 'draft', 'all'], true) ? $filter : 'published';
    }

    public function edit(int $id): void
    {
        $page = Page::query()->findOrFail($id);

        $this->editId = $id;
        $this->editTitle = (string) ($page->seo['title'] ?? '');
        $this->editDescription = (string) ($page->seo['description'] ?? '');
        $this->showEdit = true;
    }

    /**
     * Save the two listing fields onto the page — through the same seo array the editor writes, so
     * 'last reviewed' travels with the edit and nothing else about the page moves.
     */
    public function saveEdit(): void
    {
        $this->validate();

        $page = Page::query()->findOrFail($this->editId);
        $seo = array_merge((array) $page->seo, [
            'title' => trim($this->editTitle),
            'description' => trim($this->editDescription),
            'last_reviewed' => now()->toDateString(),
        ]);

        $page->forceFill(['seo' => $seo])->save();
        PageCache::invalidate($page);

        $this->showEdit = false;
        $this->toast('Search listing saved for "'.$page->title.'".');
    }

    public function render(): View
    {
        $pages = Page::query()->orderBy('slug')->get()->filter(function (Page $page): bool {
            return $this->filter === 'all'
                || ($this->filter === 'published' ? $page->isPublished() : ! $page->isPublished());
        })->map(function (Page $page): array {
            $title = trim((string) ($page->seo['title'] ?? '')) ?: $page->title;
            $description = trim((string) ($page->seo['description'] ?? '')) ?: trim((string) $page->excerpt);

            return [
                'id' => $page->id,
                'slug' => $page->slug,
                'title' => $page->title,
                'meta_title' => $title,
                'meta_title_source' => trim((string) ($page->seo['title'] ?? '')) !== '' ? 'set' : 'from the page title',
                'meta_title_len' => mb_strlen($title),
                'meta_description' => $description,
                'meta_description_source' => trim((string) ($page->seo['description'] ?? '')) !== '' ? 'set' : (trim((string) $page->excerpt) !== '' ? 'from the excerpt' : 'missing'),
                'meta_description_len' => mb_strlen($description),
                'robots' => (string) ($page->seo['robots'] ?? ''),
                'status' => $page->status->value,
                'score' => $this->score($title, $description),
            ];
        })->values();

        return view('livewire.admin.website.seo-overview-index', [
            'rows' => $pages,
            'counts' => [
                'published' => Page::query()->published()->count(),
                'all' => Page::query()->count(),
            ],
        ]);
    }

    /** A one-glance score per row: green when the listing is complete and inside the cut-offs. */
    private function score(string $title, string $description): string
    {
        if ($description === '') {
            return 'missing';
        }

        if (mb_strlen($title) > 65 || mb_strlen($description) > 165) {
            return 'long';
        }

        if (mb_strlen($title) < 20 || mb_strlen($description) < 50) {
            return 'short';
        }

        return 'good';
    }
}
