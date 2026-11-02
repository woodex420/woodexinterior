<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Website;

use App\Domain\Website\Seo\FileAudit;
use App\Domain\Website\Seo\SearchMetadataAudit;
use App\Livewire\Concerns\InteractsWithAdmin;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The SEO overview: every page's search listing, read from the page itself (Woodex OS 1, step 6).
 *
 * It used to list the `pages` table and edit its `seo` array — a field no file reads, so an edit here changed
 * nothing a visitor or Google saw. The listing Google shows *is* the file's `<title>` and description, so the
 * screen reads those, and the place to change one is the builder, on that file.
 */
#[Layout('layouts::admin')]
#[Title('SEO')]
class SeoOverviewIndex extends Component
{
    use InteractsWithAdmin;

    /** `all`, or only the pages with a finding. */
    public string $filter = 'issues';

    public string $search = '';

    public string $tab = 'listings';

    public function setTab(string $tab): void
    {
        $this->tab = $tab === 'metadata' ? 'metadata' : 'listings';
    }

    public function mount(): void
    {
        abort_unless($this->user()->can('website.edit'), 403);
    }

    public function setFilter(string $filter): void
    {
        $this->filter = $filter === 'all' ? 'all' : 'issues';
    }

    public function render(): View
    {
        $audit = (new FileAudit)->handle();

        $rows = collect($audit['entries'])
            ->when($this->filter === 'issues', fn ($rows) => $rows->where('issues', '!=', []))
            ->when($this->search !== '', fn ($rows) => $rows->filter(fn (array $row): bool => str_contains(
                mb_strtolower($row['url'].' '.$row['title'].' '.$row['description']),
                mb_strtolower(trim($this->search)),
            )))
            ->sortBy('url')
            ->values();

        $metadata = $this->tab === 'metadata' ? app(SearchMetadataAudit::class)->handle() : null;

        return view('livewire.admin.website.seo-overview-index', [
            'metadata' => $metadata,
            'rows' => $rows,
            'counts' => [
                'files' => $audit['counts']['files'],
                'flagged' => $audit['counts']['flagged'],
                'missing_description' => collect($audit['entries'])->where('description', '')->count(),
                'missing_title' => collect($audit['entries'])->where('title', '')->count(),
            ],
            'filter' => $this->filter,
        ]);
    }
}
