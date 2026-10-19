<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Website;

use App\Domain\Website\Models\Media;
use App\Domain\Website\Models\Page;
use App\Domain\Website\Models\Redirect;
use App\Domain\Website\Seo\SeoAudit;
use App\Livewire\Concerns\InteractsWithAdmin;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Site health (the old admin's own screen, F10): the website as a whole, not one page — broken
 * internal links, redirects pointing at nothing, orphan pages nothing links to, images without alt
 * text, and whether the robots.txt and sitemap routes are live. Findings are computed fresh on
 * every load from the same SeoAudit the SEO agent runs; the rescan button is for peace of mind.
 */
#[Layout('layouts::admin')]
#[Title('Site health')]
class SiteHealthIndex extends Component
{
    use InteractsWithAdmin;

    public function rescan(): void
    {
        $this->toast('Site scanned — '.$this->verdict());
    }

    public function render(): View
    {
        $audit = (new SeoAudit)->handle();
        $site = collect($audit['site']);

        $published = Page::query()->published()->count();
        $altPages = $this->pagesWithoutAlt($audit);

        $findings = collect([
            [
                'code' => 'broken_links',
                'label' => 'Internal links that lead nowhere',
                'hint' => 'A visitor clicks, the page answers 404 — and the ranking that link earned dies with it. Fix the page or the link.',
                'ok' => $site->firstWhere('code', 'broken_links') === null,
                'message' => (string) ($site->firstWhere('code', 'broken_links')['message'] ?? 'Every internal link resolves.'),
                'action' => null,
            ],
            [
                'code' => 'broken_redirects',
                'label' => 'Redirects pointing at no page',
                'hint' => 'An old URL is being sent to a page that does not exist. The Redirects screen has the fix.',
                'ok' => $site->firstWhere('code', 'broken_redirects') === null,
                'message' => (string) ($site->firstWhere('code', 'broken_redirects')['message'] ?? 'Every redirect lands on a real page.'),
                'action' => ['label' => 'Open Redirects', 'route' => 'admin.website.redirects'],
            ],
            [
                'code' => 'orphan_pages',
                'label' => 'Orphan pages — published, yet unlinked',
                'hint' => 'No menu and no page links to them, so visitors and Google both arrive by luck. Link them or unpublish them.',
                'ok' => $site->firstWhere('code', 'orphan_pages') === null,
                'message' => (string) ($site->firstWhere('code', 'orphan_pages')['message'] ?? 'Every published page is linked from somewhere.'),
                'action' => ['label' => 'Open All pages', 'route' => 'admin.website.pages'],
            ],
            [
                'code' => 'alt_text',
                'label' => 'Images without alt text',
                'hint' => 'Screen readers hear nothing, and Google neither. The SEO agent fills them from the media library in one click.',
                'ok' => $altPages === 0,
                'message' => $altPages === 0
                    ? 'Every image on every page carries alt text.'
                    : sprintf('%d page(s) still have images without alt text.', $altPages),
                'action' => ['label' => 'Open SEO agent', 'route' => 'admin.website.seo'],
            ],
            [
                'code' => 'robots',
                'label' => 'robots.txt is generated',
                'hint' => 'The file that tells search engines what to crawl — written from the pages and the redirects, so it cannot drift.',
                'ok' => Route::has('robots'),
                'message' => Route::has('robots')
                    ? 'The robots route is live at /robots.txt.'
                    : 'The robots route is missing — a deploy problem, not a content one.',
                'action' => null,
            ],
            [
                'code' => 'sitemap',
                'label' => 'sitemap.xml is generated',
                'hint' => 'The map search engines read first, rebuilt from the published pages on every request.',
                'ok' => Route::has('sitemap'),
                'message' => Route::has('sitemap')
                    ? sprintf('The sitemap route is live — %d published page%s.', $published, $published === 1 ? '' : 's')
                    : 'The sitemap route is missing — a deploy problem, not a content one.',
                'action' => null,
            ],
        ]);

        return view('livewire.admin.website.site-health-index', [
            'findings' => $findings,
            'unhealthy' => $findings->where('ok', false)->count(),
            'stats' => [
                'pages' => Page::query()->count(),
                'published' => $published,
                'redirects' => Redirect::query()->count(),
                'media' => Media::query()->count(),
            ],
        ]);
    }

    private function verdict(): string
    {
        $audit = (new SeoAudit)->handle();
        $issues = count($audit['site']) + ($this->pagesWithoutAlt($audit) > 0 ? 1 : 0);

        return $issues === 0 ? 'everything checks out' : $issues.' site-wide finding(s)';
    }

    /** Pages carrying a missing-alt finding (the audit counts the images per page). */
    private function pagesWithoutAlt(array $audit): int
    {
        return collect($audit['pages'])
            ->filter(fn (array $page): bool => collect($page['issues'])->contains(fn (array $issue): bool => $issue['code'] === 'missing_alt'))
            ->count();
    }
}
