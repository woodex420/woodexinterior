<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Website\Enums\PageType;
use App\Domain\Website\Models\Page;
use App\Domain\Website\Models\PageBlock;
use App\Domain\Website\Models\PageVersion;
use App\Domain\Website\Services\PageRenderer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Deletes the database copies of pages that v1 HTML files answer (Woodex OS 1, docs/audit/16.19).
 *
 * The files under `public/` are the website: the web server answers a file before Laravel is asked, so a
 * service, city, area or project row was a shadow no visitor could see — and no editor could put a word on
 * a live page with. They go, with their blocks and versions.
 *
 * `--home` adds the home row (slug `/`): since step 4 the home page is `public/index.html` too, so the CMS
 * row behind it is another copy that can never reach the page.
 *
 * Two rules keep the URLs alive while the rows go:
 *
 *  - **A page with no file is not a shadow.** It is the only copy of something a visitor can reach, so a
 *    published page is exported to its file first (`/furniture/` was live from its row alone; deleting it
 *    took a linked URL off the site on 20 Oct).
 *  - **A draft is left alone.** Exporting it would publish it, which is worse than keeping the row.
 *
 * Runs as a report first. `--force` writes a JSON backup, then deletes in one transaction. The rows are
 * force-deleted, not soft-deleted: a trashed row still holds its unique slug and its content, and "deleted"
 * should mean deleted once the backup exists.
 */
final class RetireShadowPages extends Command
{
    /** The types whose pages are copies of v1 files. */
    private const array TYPES = ['service', 'city', 'area', 'project'];

    protected $signature = 'website:retire-shadow-pages
        {--force : Delete them. Without this the command only reports what it would delete}
        {--home : Also delete the home page row (slug /), now that public/index.html answers it}
        {--backup-dir= : Where the JSON backup goes (default storage/app/private/os1-backup)}';

    protected $description = 'Delete the database copies of service, city, area and project pages; the v1 files are the site.';

    public function handle(): int
    {
        $query = Page::withTrashed()
            ->whereIn('type', array_map(static fn (string $type): PageType => PageType::from($type), self::TYPES));

        if ((bool) $this->option('home')) {
            $query->orWhere('slug', '/')->orWhere('type', PageType::Home);
        }

        $pages = $query->with(['blocks', 'featuredImage'])->orderBy('id')->get();

        if ($pages->isEmpty()) {
            $this->info('Nothing to do: there are no shadow pages'.((bool) $this->option('home') ? ' or home row' : '').'.');

            return self::SUCCESS;
        }

        $ids = $pages->pluck('id')->all();

        $this->table(['Pages', 'Blocks', 'Versions', 'Pages after'], [[
            count($ids),
            PageBlock::query()->whereIn('page_id', $ids)->count(),
            PageVersion::query()->whereIn('page_id', $ids)->count(),
            Page::query()->count() - $pages->whereNull('deleted_at')->count(),
        ]]);

        $this->line('By type:');

        foreach (self::TYPES as $type) {
            $this->line('  '.str_pad($type, 9).Page::withTrashed()->where('type', $type)->count());
        }

        if ((bool) $this->option('home')) {
            $this->line('  '.str_pad('home', 9).Page::withTrashed()->where('slug', '/')->count());
        }

        $this->line('Already retired (soft-deleted): '.$pages->whereNotNull('deleted_at')->count());

        if (! $this->option('force')) {
            $this->newLine();
            $this->comment('Nothing was deleted. Run again with --force to delete these '.count($ids).' pages.');

            return self::SUCCESS;
        }

        $directory = (string) ($this->option('backup-dir') ?: storage_path('app/private/os1-backup'));
        $backup = $directory.'/shadow-pages-'.now()->format('Ymd-His').'.json';

        if (! is_dir($directory)) {
            mkdir($directory, 0o775, true);
        }

        file_put_contents($backup, json_encode([
            'at' => now()->toIso8601String(),
            'types' => array_values(array_filter([...self::TYPES, (bool) $this->option('home') ? 'home' : null])),
            'pages' => $pages->map(fn (Page $page): array => [
                'id' => $page->id,
                'type' => $page->type->value,
                'slug' => $page->slug,
                'title' => $page->title,
                'status' => $page->status?->value,
                'template' => $page->template,
                'canvas_html' => $page->canvas_html,
                'seo' => $page->seo,
                'deleted_at' => $page->deleted_at?->toIso8601String(),
                'blocks' => $page->blocks->map(static fn (PageBlock $block): array => [
                    'type' => $block->type->value,
                    'key' => $block->block_key,
                    'position' => $block->position,
                    'visible' => $block->visible,
                    'data' => $block->data,
                    'settings' => $block->settings,
                ])->all(),
                'versions' => PageVersion::query()->where('page_id', $page->id)->get()
                    ->map(static fn (PageVersion $version): array => [
                        'id' => $version->id,
                        'user_id' => $version->user_id,
                        'data' => $version->data,
                        'created_at' => $version->created_at?->toIso8601String(),
                    ])->all(),
            ])->values()->all(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        $exported = [];
        $kept = [];

        foreach ($pages as $page) {
            // A row that is already retired needs no export: it is not live.
            if ($page->trashed()) {
                continue;
            }

            $path = ltrim($page->path(), '/').'index.html';

            if (self::fileFor($path) !== null) {
                continue; // the file is the page; the row is the copy
            }

            if (! $page->isPublished()) {
                $kept[] = $page->slug;

                continue;
            }

            $this->export($page, $path);
            $exported[] = $page->slug;
        }

        $delete = array_values(array_diff($ids, array_map(
            static fn (string $slug): int => (int) Page::withTrashed()->where('slug', $slug)->value('id'),
            $kept,
        )));

        $blocks = PageBlock::query()->whereIn('page_id', $delete)->count();
        $versions = PageVersion::query()->whereIn('page_id', $delete)->count();

        $deleted = 0;

        DB::transaction(function () use ($delete, &$deleted): void {
            PageBlock::query()->whereIn('page_id', $delete)->delete();
            PageVersion::query()->whereIn('page_id', $delete)->delete();
            $deleted = Page::withTrashed()->whereIn('id', $delete)->forceDelete();
        });

        $this->newLine();

        if ($exported !== []) {
            $this->line('Exported to files first (the page had no file of its own): '.implode(', ', $exported));
        }

        if ($kept !== []) {
            $this->warn('Kept (draft, no file — exporting it would publish it): '.implode(', ', $kept));
        }

        $this->info('Deleted '.$deleted.' pages, '.$blocks.' blocks and '.$versions.' versions.');
        $this->line('Backup: '.$backup);
        $this->line('Pages now: '.Page::query()->count());

        return self::SUCCESS;
    }

    /** The file that already answers this page, or null. */
    private static function fileFor(string $path): ?string
    {
        $file = public_path(ltrim($path, '/'));

        return is_file($file) ? $file : null;
    }

    /**
     * Write the page the CMS renders to the file the web server serves: an HTML-template page is its stored
     * document, a block page is the same `site.page` view a visitor would have received.
     */
    private function export(Page $page, string $path): void
    {
        $html = $page->template === 'html' && trim((string) $page->canvas_html) !== ''
            ? (string) $page->canvas_html
            : (string) view('site.page', app(PageRenderer::class)->viewData($page->loadMissing(['blocks', 'featuredImage'])))->render();

        // Media URLs come out absolute (APP_URL). A page file must not depend on the host it was exported
        // on: the rest of the v1 site links /media/… root-relative, so this does too — a domain change
        // must not break every image on the page.
        $origin = rtrim((string) config('app.url'), '/');

        if ($origin !== '') {
            $html = (string) preg_replace('~'.preg_quote($origin, '~').'(?=/)~', '', $html);
            $html = str_replace('"'.$origin.'"', '"/"', $html);
        }

        $file = public_path(ltrim($path, '/'));

        if (! is_dir(dirname($file))) {
            mkdir(dirname($file), 0o775, true);
        }

        file_put_contents($file, $html);
        $this->line('  exported '.$page->slug.' → /'.ltrim($path, '/'));
    }
}
