<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Domain\Website\Enums\PageType;
use App\Domain\Website\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * sitemap.xml and robots.txt for the public website (docs/09 §11.1). Everything listed answers 200 — a sitemap
 * that points at a 404 or a redirect is worse for search than a short sitemap — and the list is rebuilt from the
 * CMS, so a page appears the moment it is published.
 */
final class SitemapController
{
    public function sitemap(): Response
    {
        // Keyed on the newest page: a publish changes the key, so the sitemap is never stale.
        $version = Page::query()->max('updated_at') ?? 'none';
        $xml = Cache::remember('website.sitemap.'.md5((string) $version), now()->addDay(), fn (): string => $this->build());

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    /**
     * One file serves both domains, so it answers for the one that is asking: the admin keeps everything out of
     * search, the public website asks to be crawled. There used to be a static public/robots.txt with "Disallow: /"
     * in it, which silently kept the whole new website out of Google (P-13).
     */
    public function robots(Request $request): Response
    {
        $adminDomain = config('woodex.admin.domain');
        $isAdmin = is_string($adminDomain) && $adminDomain !== ''
            && $request->getHost() === $adminDomain;

        $lines = $isAdmin
            ? ['User-agent: *', 'Disallow: /']
            : [
                'User-agent: *',
                'Allow: /',
                'Disallow: /'.trim((string) config('woodex.admin.path', 'admin'), '/'),
                'Disallow: /q/',
                'Disallow: /i/',
                'Disallow: /p/',
                'Disallow: /portal',
                '',
                'Sitemap: '.url('/sitemap.xml'),
            ];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    private function build(): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        $xml .= $this->url(Page::urlFor('/'), '1.0', 'weekly', $this->lastModified());

        Page::query()->published()
            ->where('slug', '!=', '/')
            ->orderBy('slug')
            ->get(['slug', 'type', 'updated_at', 'seo'])
            ->filter(fn (Page $page): bool => ($page->seo['robots'] ?? '') === '' || ! str_contains((string) $page->seo['robots'], 'noindex'))
            ->each(function (Page $page) use (&$xml): void {
                $xml .= $this->url(
                    $page->canonical(),
                    $this->priority($page->type),
                    in_array($page->type, [PageType::Insight, PageType::Project], true) ? 'monthly' : 'weekly',
                    $page->updated_at?->toDateString() ?? $this->lastModified(),
                );
            });

        return $xml.'</urlset>'."\n";
    }

    private function url(string $loc, string $priority, string $changefreq, string $lastmod): string
    {
        return '  <url>'
            .'<loc>'.e($loc).'</loc>'
            .'<lastmod>'.$lastmod.'</lastmod>'
            .'<changefreq>'.$changefreq.'</changefreq>'
            .'<priority>'.$priority.'</priority>'
            .'</url>'."\n";
    }

    private function priority(PageType $type): string
    {
        return match ($type) {
            PageType::Service => '0.9',
            PageType::City, PageType::About, PageType::Contact, PageType::Estimator, PageType::Booking, PageType::Legal => '0.8',
            PageType::Area => '0.7',
            PageType::Home, PageType::Insight, PageType::Project => '0.6',
            default => '0.5',
        };
    }

    /** Last content change of the pages that are not in the CMS yet (the home page). */
    private function lastModified(): string
    {
        return '2026-10-16';
    }
}
