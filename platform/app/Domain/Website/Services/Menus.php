<?php

declare(strict_types=1);

namespace App\Domain\Website\Services;

use App\Domain\Website\Enums\PageType;
use App\Domain\Website\Models\NavigationItem;
use App\Domain\Website\Models\Page;
use Illuminate\Support\Facades\Cache;

/**
 * Menus and the service links used by the header, the footer and the structured data (docs/09 §2.1). Menus are
 * editable in the dashboard; until someone fills them in the header and the footer use the built-in defaults
 * below, so the site always has its structure (docs/09 §2.1, audit of 16 Oct 2026 in docs/10).
 */
final class Menus
{
    /**
     * @return list<array{label: string, url: string, target: string}>
     */
    public static function items(string $menu): array
    {
        /** @var list<array{label: string, url: string, target: string}> $items */
        $items = Cache::remember('website.menu.'.$menu, now()->addDay(), fn (): array => NavigationItem::query()
            ->where('menu', $menu)
            ->orderBy('position')
            ->get(['label', 'url', 'target'])
            ->map(fn (NavigationItem $item): array => [
                'label' => $item->label,
                'url' => $item->url,
                'target' => $item->target ?: '_self',
            ])->all());

        return $items === [] ? self::defaults($menu) : $items;
    }

    /**
     * The navigation a studio's website needs before anybody has opened Website -> Menus.
     *
     * Without this the header and the footer were empty, and with them the site had no structure: 27 of the 89
     * pages (the home page among them) had no link pointing at them anywhere but the sitemap. The owner can
     * replace every line of it in the dashboard; this is only what shows until they do.
     *
     * @return list<array{label: string, url: string, target: string}>
     */
    public static function defaults(string $menu): array
    {
        $link = fn (string $label, string $url): array => ['label' => $label, 'url' => $url, 'target' => '_self'];

        return match ($menu) {
            'header' => [
                $link('Services', '/services/'),
                $link('Cost guides', '/insights/'),
                $link('Projects', '/projects/'),
                $link('About', '/about/'),
                $link('Contact', '/contact/'),
            ],
            'footer' => [
                $link('Interior design', '/interior-design/'),
                $link('Cost guides', '/insights/'),
                $link('Cost estimator', '/estimator/'),
                $link('Projects', '/projects/'),
                $link('Book a visit', '/book-a-visit/'),
                $link('About the studio', '/about/'),
                $link('Contact', '/contact/'),
                $link('Privacy', '/privacy/'),
            ],
            default => [],
        };
    }

    /**
     * The six disciplines, for the footer and the OfferCatalog in the structured data.
     *
     * @return list<array{name: string, copy: string, url: string}>
     */
    public static function serviceLinks(): array
    {
        /** @var list<array{name: string, copy: string, url: string}> $links */
        $links = Cache::remember('website.services', now()->addDay(), fn (): array => Page::query()
            ->published()
            ->ofType(PageType::Service)
            ->orderBy('sort')
            ->get()
            ->map(fn (Page $page): array => [
                'name' => $page->title,
                'copy' => (string) $page->excerpt,
                'url' => $page->path(),
            ])->all());

        return $links;
    }

    /**
     * The cities we work in, for the footer. Before this existed the twelve city pages were reachable from the
     * sitemap and nothing else — every one of them an orphan.
     *
     * @return list<array{name: string, url: string}>
     */
    public static function cityLinks(): array
    {
        /** @var list<array{name: string, url: string}> $links */
        $links = Cache::remember('website.cities', now()->addDay(), fn (): array => Page::query()
            ->published()
            ->ofType(PageType::City)
            ->orderBy('sort')
            ->get()
            ->map(fn (Page $page): array => [
                'name' => $page->title,
                'url' => $page->path(),
            ])->all());

        return $links;
    }

    public static function flush(): void
    {
        Cache::forget('website.services');
        Cache::forget('website.cities');

        foreach (['header', 'footer', 'services'] as $menu) {
            Cache::forget('website.menu.'.$menu);
        }
    }
}
