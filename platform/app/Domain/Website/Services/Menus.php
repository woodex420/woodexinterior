<?php

declare(strict_types=1);

namespace App\Domain\Website\Services;

use App\Domain\Website\Enums\PageType;
use App\Domain\Website\Models\NavigationItem;
use App\Domain\Website\Models\Page;
use Illuminate\Support\Facades\Cache;

/**
 * Menus and the service links used by the header, the footer and the structured data (docs/09 §2.1). Menus are
 * editable in the dashboard; until someone fills them in, the views fall back to their built-in defaults.
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

        return $items;
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

    public static function flush(): void
    {
        Cache::forget('website.services');

        foreach (['header', 'footer', 'services'] as $menu) {
            Cache::forget('website.menu.'.$menu);
        }
    }
}
