<?php

declare(strict_types=1);

namespace App\Domain\Website\Seo;

use App\Domain\Website\Models\Media;
use App\Domain\Website\Models\NavigationItem;
use App\Domain\Website\Models\Page;
use App\Domain\Website\Models\Redirect;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * The SEO agent's deterministic core (V2-3 F8, docs/14 §5a): every rule below is a plain check a
 * person can repeat by hand — no key, no model, no guessing. Findings carry a `fix` code when a
 * rule can mend them in one click (the component owns the mending); the rest are advice.
 *
 * F10's website health rides along (owner's call): broken links, missing images, orphan pages and
 * broken redirects are site findings, not page ones.
 */
final class SeoAudit
{
    /**
     * The whole audit: per-page findings first (worst page first), then the site's own.
     *
     * @return array{
     *     pages: list<array{id: int, title: string, slug: string, draft: bool, issues: list<array{code: string, message: string, fix: ?string}>}>,
     *     site: list<array{code: string, message: string, fix: ?string, ids: list<int>}>,
     *     counts: array{pages: int, issues: int, fixable: int}
     * }
     */
    public function handle(): array
    {
        $pages = Page::query()->with('blocks')->orderBy('slug')->get();
        $mediaIds = Media::query()->pluck('id');

        $titles = $pages->filter(fn (Page $page): bool => $page->isPublished())
            ->groupBy(fn (Page $page): string => trim((string) ($page->seo['title'] ?? $page->title)))
            ->filter(fn (Collection $group): bool => $group->count() > 1);

        $findings = [];

        foreach ($pages as $page) {
            $issues = [];

            $title = trim((string) ($page->seo['title'] ?? $page->title));
            $description = trim((string) ($page->seo['description'] ?? ''));

            if ($description === '' && trim((string) $page->excerpt) === '') {
                $issues[] = ['code' => 'missing_description', 'message' => 'No meta description — search engines show whatever they find.', 'fix' => 'description'];
            }

            if (mb_strlen($title) > 65) {
                $issues[] = ['code' => 'long_title', 'message' => sprintf('Title is %d characters — search engines cut around 65.', mb_strlen($title)), 'fix' => null];
            } elseif (mb_strlen($title) < 20 && $page->isPublished()) {
                $issues[] = ['code' => 'short_title', 'message' => sprintf('Title is only %d characters — room for the studio and the subject.', mb_strlen($title)), 'fix' => null];
            }

            if ($titles->has($title) && $page->isPublished()) {
                $others = $titles->get($title)->pluck('slug')->reject(fn (string $slug): bool => $slug === $page->slug)->all();
                $issues[] = ['code' => 'duplicate_title', 'message' => 'Another published page uses the same title: '.implode(', ', $others), 'fix' => null];
            }

            $cleanSlug = '/'.implode('/', array_map(
                static fn (string $part): string => Str::slug($part),
                array_filter(explode('/', trim((string) $page->slug, '/'))),
            )).'/';

            if ($cleanSlug !== $page->slug && $page->slug !== '/') {
                $issues[] = ['code' => 'slug_hygiene', 'message' => sprintf('Slug is not lowercase-with-hyphens — the tidy form would be %s.', $cleanSlug), 'fix' => 'slug'];
            }

            foreach ($this->missingAltTexts($page, $mediaIds) as $where => $count) {
                $issues[] = ['code' => 'missing_alt', 'message' => sprintf('%d image(s) without alt text (%s) — screen readers hear nothing, and Google neither.', $count, $where), 'fix' => 'alt'];
            }

            $words = $this->wordCount($page);
            if ($page->isPublished() && $page->template !== 'canvas' && $words < 120) {
                $issues[] = ['code' => 'thin_content', 'message' => sprintf('Only about %d words of body text — search engines read thin pages as thin answers.', $words), 'fix' => null];
            }

            if ($issues !== []) {
                $findings[] = ['id' => $page->id, 'title' => $page->title, 'slug' => (string) $page->slug, 'draft' => ! $page->isPublished(), 'issues' => $issues];
            }
        }

        usort($findings, static fn (array $a, array $b): int => count($b['issues']) <=> count($a['issues']));

        return [
            'pages' => $findings,
            'site' => $this->siteFindings($pages),
            'counts' => [
                'pages' => count($findings),
                'issues' => array_sum(array_map(static fn (array $page): int => count($page['issues']), $findings)),
                'fixable' => array_sum(array_map(
                    static fn (array $page): int => count(array_filter($page['issues'], static fn (array $issue): bool => $issue['fix'] !== null)),
                    $findings,
                )),
            ],
        ];
    }

    /**
     * F10's site findings: the website as a whole, not one page.
     *
     * @param  Collection<int, Page>  $pages
     * @return list<array{code: string, message: string, fix: ?string, ids: list<int>}>
     */
    private function siteFindings(Collection $pages): array
    {
        $findings = [];
        $publishedSlugs = $pages->filter(fn (Page $page): bool => $page->isPublished())->map(fn (Page $page): string => (string) $page->slug)->all();
        $allSlugs = $pages->map(fn (Page $page): string => (string) $page->slug)->all();

        // 1. Redirects pointing at no page of ours.
        $broken = Redirect::query()->get()->filter(function (Redirect $redirect) use ($allSlugs): bool {
            $target = trim((string) $redirect->to_path);
            if ($target === '' || str_starts_with($target, 'http://') || str_starts_with($target, 'https://')) {
                return false;
            }

            return ! in_array('/'.ltrim($target, '/'), $allSlugs, true);
        });

        if ($broken->isNotEmpty()) {
            $findings[] = [
                'code' => 'broken_redirects',
                'message' => sprintf('%d redirect(s) point at no page: %s', $broken->count(), $broken->map(fn (Redirect $redirect): string => $redirect->from_path.' → '.$redirect->to_path)->take(6)->implode(', ')),
                'fix' => 'redirect',
                'ids' => $broken->pluck('id')->all(),
            ];
        }

        // 2. Internal links that lead nowhere (menus and text, against real slugs and real routes).
        $known = array_merge($allSlugs, $this->staticPaths());
        $menuUrls = NavigationItem::query()->pluck('url')->all();
        $linked = [];

        foreach (array_merge($menuUrls, $this->internalLinks($pages)) as $url) {
            $path = '/'.ltrim(trim((string) $url), '/');

            if ($path !== '/' && ! str_starts_with($path, '//') && ! in_array(rtrim($path, '/') ?: '/', $known, true) && ! in_array($path, $known, true)) {
                $linked[$path] = ($linked[$path] ?? 0) + 1;
            }
        }

        if ($linked !== []) {
            $findings[] = ['code' => 'broken_links', 'message' => sprintf('%d internal link(s) lead nowhere: %s', array_sum($linked), implode(', ', array_slice(array_keys($linked), 0, 6))), 'fix' => null, 'ids' => []];
        }

        // 3. Orphan pages: published, yet nothing links to them — not a menu, not another page.
        $referenced = array_map(
            static fn (string $url): string => '/'.rtrim(ltrim(trim($url), '/'), '/'),
            array_merge($menuUrls, $this->internalLinks($pages)),
        );
        $orphans = $pages->filter(function (Page $page) use ($referenced): bool {
            if (! $page->isPublished() || $page->slug === '/') {
                return false;
            }

            return ! in_array(rtrim((string) $page->slug, '/') ?: '/', $referenced, true);
        });

        if ($orphans->isNotEmpty()) {
            $findings[] = ['code' => 'orphan_pages', 'message' => sprintf('Nothing links to %d published page(s): %s', $orphans->count(), $orphans->pluck('slug')->take(6)->implode(', ')), 'fix' => null, 'ids' => []];
        }

        return $findings;
    }

    /**
     * Images on the page whose alt text is empty — grouped by where they sit.
     *
     * @param  Collection<int, int>  $mediaIds
     * @return array<string, int>
     */
    private function missingAltTexts(Page $page, Collection $mediaIds): array
    {
        $missing = [];

        foreach ($page->blocks as $block) {
            foreach (['logos' => 'logos', 'items' => 'gallery'] as $key => $where) {
                foreach ((array) ($block->data[$key] ?? []) as $item) {
                    if (is_array($item) && isset($item['id']) && is_numeric($item['id']) && trim((string) ($item['alt'] ?? '')) === '' && $mediaIds->contains((int) $item['id'])) {
                        $missing[$block->type->value.'/'.$where] = ($missing[$block->type->value.'/'.$where] ?? 0) + 1;
                    }
                }
            }
        }

        return $missing;
    }

    /**
     * Roughly how many words of prose the page carries: section headings and body text tell the
     * visitor (and the engine) what the page is about.
     */
    private function wordCount(Page $page): int
    {
        $text = '';

        foreach ($page->blocks as $block) {
            $text .= ' '.(string) ($block->data['heading'] ?? '');
            $text .= ' '.(string) ($block->data['text'] ?? '');
            $text .= ' '.(string) ($block->data['body'] ?? '');

            foreach ((array) ($block->data['items'] ?? []) as $item) {
                if (is_array($item)) {
                    $text .= ' '.implode(' ', array_map(
                        static fn ($value): string => is_string($value) ? $value : '',
                        array_intersect_key($item, ['question' => true, 'answer' => true, 'text' => true, 'title' => true, 'description' => true]),
                    ));
                }
            }
        }

        return str_word_count(strip_tags($text));
    }

    /**
     * Every /-path the pages link to in their prose.
     *
     * @param  Collection<int, Page>  $pages
     * @return list<string>
     */
    private function internalLinks(Collection $pages): array
    {
        $urls = [];

        foreach ($pages as $page) {
            foreach ($page->blocks as $block) {
                foreach (['text', 'body'] as $key) {
                    preg_match_all('/href="([^"]+)"/', (string) ($block->data[$key] ?? ''), $matches);

                    foreach ($matches[1] ?? [] as $href) {
                        if (str_starts_with((string) $href, '/')) {
                            $urls[] = $href;
                        }
                    }
                }
            }
        }

        return $urls;
    }

    /**
     * The paths the site itself serves outside the pages — links to them are never broken.
     *
     * @return list<string>
     */
    private function staticPaths(): array
    {
        $paths = [];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            $uri = $route->uri();

            if (str_contains($uri, '{')) {
                continue; // parameterised routes — the audit only judges concrete paths
            }

            $paths[] = '/'.ltrim($uri, '/');
        }

        return $paths;
    }
}
