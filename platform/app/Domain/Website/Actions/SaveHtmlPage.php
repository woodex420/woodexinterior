<?php

declare(strict_types=1);

namespace App\Domain\Website\Actions;

use App\Domain\Website\Builder\StalePageException;
use App\Domain\Website\Enums\PageStatus;
use App\Domain\Website\Models\Page;
use App\Domain\Website\Models\PageBlock;
use App\Domain\Website\Models\PageVersion;
use App\Domain\Website\Services\Menus;
use App\Domain\Website\Services\PageCache;
use App\Domain\Website\Support\HtmlGuard;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Saves a whole page document from the Visual Page Builder (HTML page mode).
 *
 * It is deliberately separate from SavePage: that action owns blocks, the publish gate and the
 * CanvasHtml sanitiser, and an HTML page has none of those. The rules here are the ones the v2.1
 * builder enforced: size and document checks, no editor markup, a conflict check on the timestamp,
 * a script guard for everyone below the owner, and a version snapshot before every write.
 */
final class SaveHtmlPage
{
    /** The same ceiling the old builder had (MAX_HTML). */
    public const int MAX_BYTES = 3 * 1024 * 1024;

    /** How many versions one page keeps, as the old builder kept 30 file backups. */
    private const int MAX_VERSIONS = 30;

    public function __invoke(Page $page, string $html, ?int $mtime, bool $force, ?User $user): Page
    {
        $html = trim($html);

        $this->validateDocument($page, $html, $user);

        $current = (int) ($page->updated_at?->getTimestamp() ?? 0);

        if (! $force && $mtime !== null && $mtime !== $current) {
            throw StalePageException::since($page->title);
        }

        DB::transaction(function () use ($page, $html, $user): void {
            $this->snapshot($page, $user);

            $page->forceFill([
                'template' => 'html',
                'canvas_html' => $html,
                'author_id' => $page->author_id ?? $user?->id,
            ]);

            // The builder's button says "Save" and the old one wrote the file straight to the live
            // site. Keep that promise for anyone allowed to publish; everyone else saves a draft.
            if ($user?->can('website.publish')) {
                $page->status = PageStatus::Published;
                $page->published_at ??= now();
            }

            $page->save();

            $this->prune($page);

            PageCache::invalidate($page);
            Menus::flush();
        });

        return $page->fresh() ?? $page;
    }

    private function validateDocument(Page $page, string $html, ?User $user): void
    {
        if ($html === '') {
            throw ValidationException::withMessages(['html' => 'The page is empty — nothing was saved.']);
        }

        if (strlen($html) > self::MAX_BYTES) {
            throw ValidationException::withMessages(['html' => 'The page is larger than 3 MB — nothing was saved.']);
        }

        if (stripos($html, '<html') === false || stripos($html, '</html>') === false) {
            throw ValidationException::withMessages(['html' => 'That is not a complete page document — nothing was saved.']);
        }

        // If the builder's own markers reach the file, the next load would start from editor state.
        if (preg_match('~<[^>]+\s(contenteditable|data-wx-ed)[\s=>]~i', $html) === 1) {
            throw ValidationException::withMessages(['html' => 'Editor markup detected — save aborted to protect the page.']);
        }

        if (! $user?->can('website.raw_html')) {
            $others = Page::query()
                ->where('template', 'html')
                ->whereKeyNot($page->id)
                ->pluck('canvas_html')
                ->filter()
                ->map(static fn (mixed $document): string => (string) $document)
                ->values()
                ->all();

            HtmlGuard::assertNoNewCode($html, (string) $page->canvas_html, $others);
        }
    }

    /** The page exactly as it is now, before this save replaces it. */
    private function snapshot(Page $page, ?User $user): void
    {
        PageVersion::query()->create([
            'page_id' => $page->id,
            'user_id' => $user?->id,
            'data' => [
                'title' => $page->title,
                'excerpt' => $page->excerpt,
                'seo' => $page->seo,
                'template' => $page->template,
                'canvas_html' => $page->canvas_html,
                'blocks' => $page->blocks->map(static fn (PageBlock $block): array => [
                    'type' => $block->type->value,
                    'key' => $block->block_key,
                    'data' => $block->data,
                    'settings' => $block->settings,
                    'visible' => $block->visible,
                ])->all(),
            ],
        ]);
    }

    private function prune(Page $page): void
    {
        $old = PageVersion::query()
            ->where('page_id', $page->id)
            ->latest('id')
            ->skip(self::MAX_VERSIONS)
            ->take(100)
            ->get();

        foreach ($old as $version) {
            $version->delete();
        }
    }
}
