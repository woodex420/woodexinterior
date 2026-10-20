<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Website\Enums\PageType;
use App\Domain\Website\Models\Page;
use App\Domain\Website\Models\PageBlock;
use App\Domain\Website\Models\PageVersion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Deletes the database copies of the v1 site pages (Woodex OS 1, docs/audit/16.19).
 *
 * The v1 HTML files under `public/` are the website: the web server answers a file before Laravel is asked,
 * so a service, city, area or project row was a shadow that no visitor could ever see — and no editor could
 * put a word on a live page with. They go, with their blocks and versions.
 *
 * Nothing else is touched: the insight, listing, home, about, contact, estimator, booking and legal rows stay
 * (home has no file yet, and the rest are still the CMS's own records).
 *
 * Runs as a report first. `--force` writes a JSON backup of everything it will delete, then deletes it in one
 * transaction. The backup is the rollback.
 */
final class RetireShadowPages extends Command
{
    /** The types whose pages are copies of v1 files. */
    private const array TYPES = ['service', 'city', 'area', 'project'];

    protected $signature = 'website:retire-shadow-pages
        {--force : Delete them. Without this the command only reports what it would delete}
        {--backup-dir= : Where the JSON backup goes (default storage/app/private/os1-backup)}';

    protected $description = 'Delete the database copies of service, city, area and project pages; the v1 files are the site.';

    public function handle(): int
    {
        $ids = Page::query()
            ->whereIn('type', array_map(static fn (string $type): PageType => PageType::from($type), self::TYPES))
            ->orderBy('id')
            ->pluck('id')
            ->all();

        if ($ids === []) {
            $this->info('Nothing to do: there are no service, city, area or project pages.');

            return self::SUCCESS;
        }

        $blocks = PageBlock::query()->whereIn('page_id', $ids)->count();
        $versions = PageVersion::query()->whereIn('page_id', $ids)->count();

        $this->table(['Pages', 'Blocks', 'Versions', 'Pages after'], [[
            count($ids), $blocks, $versions, Page::query()->count() - count($ids),
        ]]);

        $this->line('By type:');

        foreach (self::TYPES as $type) {
            $this->line('  '.str_pad($type, 9).Page::query()->where('type', $type)->count());
        }

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

        $pages = Page::query()->whereIn('id', $ids)->with('blocks')->get()->map(static fn (Page $page): array => [
            'id' => $page->id,
            'type' => $page->type->value,
            'slug' => $page->slug,
            'title' => $page->title,
            'status' => $page->status?->value,
            'template' => $page->template,
            'canvas_html' => $page->canvas_html,
            'seo' => $page->seo,
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
        ]);

        file_put_contents($backup, json_encode([
            'at' => now()->toIso8601String(),
            'types' => self::TYPES,
            'pages' => $pages->values()->all(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        $deleted = 0;

        DB::transaction(function () use ($ids, &$deleted): void {
            PageBlock::query()->whereIn('page_id', $ids)->delete();
            PageVersion::query()->whereIn('page_id', $ids)->delete();
            $deleted = Page::query()->whereIn('id', $ids)->delete();
        });

        $this->newLine();
        $this->info('Deleted '.$deleted.' pages, '.$blocks.' blocks and '.$versions.' versions.');
        $this->line('Backup: '.$backup);
        $this->line('Pages now: '.Page::query()->count());

        return self::SUCCESS;
    }
}
