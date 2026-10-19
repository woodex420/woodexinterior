<?php

declare(strict_types=1);

namespace App\Domain\Website\Builder;

use App\Domain\Website\Models\Page;
use Illuminate\Validation\ValidationException;

/**
 * The bridge between the page table and the Visual Page Builder's file-shaped world.
 *
 * The v2.1 builder addresses a page by its file path ("about/index.html") and shows the URL it maps
 * to. Woodex OS stores a slug ("/about/"). This class is the only place that translates between the
 * two, so the builder never has to know about the database and the database never stores file paths.
 */
final class BuilderPages
{
    /** The builder's page id for a CMS page: "/" → "index.html", "/about/" → "about/index.html". */
    public static function pathOf(Page $page): string
    {
        $slug = trim($page->path(), '/');

        return $slug === '' ? 'index.html' : $slug.'/index.html';
    }

    /** The CMS slug for a builder path. Rejects anything that is not a clean …/index.html path. */
    public static function slugOf(string $path): string
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');

        if ($path === '' || $path === 'index.html') {
            return '/';
        }

        if (! str_ends_with($path, '/index.html')
            || str_contains($path, '..')
            || preg_match('~^[a-z0-9][a-z0-9/_.-]*$~i', $path) !== 1) {
            throw ValidationException::withMessages(['path' => 'That is not a page address the builder can open.']);
        }

        return Page::normalizeSlug(substr($path, 0, -strlen('index.html')));
    }

    /**
     * The page picker: HTML pages only. A block page opens in the block editor, because loading it
     * here and saving would flatten its sections into one document.
     *
     * @return list<array{path: string, url: string, title: string, preview_url: string}>
     */
    public static function list(): array
    {
        return Page::query()
            ->where('template', 'html')
            ->orderByRaw("CASE WHEN slug = '/' THEN 0 ELSE 1 END")
            ->orderBy('slug')
            ->get()
            ->map(static fn (Page $page): array => [
                'path' => self::pathOf($page),
                'url' => $page->path(),
                'title' => $page->title,
                'preview_url' => Page::urlFor($page->path()).'?preview=1',
            ])
            ->values()
            ->all();
    }

    /** The document the canvas opens: the saved one, or a starter document for a new HTML page. */
    public static function document(Page $page): string
    {
        $html = trim((string) $page->canvas_html);

        return $html !== '' ? $html : self::seed($page);
    }

    /**
     * A brand-new HTML page. It uses the shipped v1 stylesheets and one editable intro section, so the
     * canvas has something real to click and the result matches the rest of the site.
     */
    public static function seed(Page $page): string
    {
        $title = e($page->title);
        $description = e((string) ($page->seo['description'] ?? $page->excerpt ?? ''));
        $canonical = e($page->canonical());

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$title} | Woodex Interior</title>
<meta name="description" content="{$description}">
<link rel="canonical" href="{$canonical}">
<link rel="icon" href="/assets/img/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="/assets/site-p21.css">
<link rel="stylesheet" href="/assets/v1-p21.css">
<style id="wx-crit">body{margin:0;background:#fff;color:#000;font-family:"Plus Jakarta Sans",sans-serif;line-height:1.62}.wrap{width:min(1240px,100% - 72px);margin-inline:auto}img{display:block;max-width:100%;height:auto}</style>
</head>
<body>
<main>
<section class="wx-block wx-bg-white" data-wx-label="Intro"><div class="wrap"><p class="wx-kicker">New page</p><h1>{$title}</h1><p class="wx-lead">Click this text to edit it, or add sections from the left panel.</p></div></section>
</main>
<script src="/assets/site.js?v=p21"></script>
</body>
</html>
HTML;
    }
}
