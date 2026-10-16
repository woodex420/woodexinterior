<?php

declare(strict_types=1);

namespace App\Domain\Website\Actions;

use App\Domain\Website\Enums\BlockType;
use App\Domain\Website\Enums\PageStatus;
use App\Domain\Website\Models\Page;
use App\Domain\Website\Models\PageBlock;
use App\Domain\Website\Models\PageVersion;
use App\Domain\Website\Services\Menus;
use App\Domain\Website\Services\PageCache;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Writes a page and its sections (docs/09 §2.2). Every publish takes a snapshot first, so a page can be put back
 * exactly as it was, and drops the cached copy of the page so the change is live at once.
 */
final class SavePage
{
    /**
     * @param  array<string, mixed>  $input
     * @param  list<array<string, mixed>>  $blocks
     */
    public function __invoke(Page $page, array $input, array $blocks, bool $publish, User $user): Page
    {
        DB::transaction(function () use ($page, $input, $blocks, $publish, $user): void {
            $page->fill([
                'type' => $input['type'],
                'slug' => Page::normalizeSlug((string) $input['slug']),
                'title' => $input['title'],
                'excerpt' => $input['excerpt'] ?: null,
                'seo' => $input['seo'],
                'schema_type' => $input['schema_type'] ?: $input['type']->schemaType(),
                'author_id' => $page->author_id ?? $user->id,
                'sort' => (int) ($input['sort'] ?? 0),
            ]);

            if ($publish) {
                $page->status = PageStatus::Published;
                $page->published_at ??= now();
            }

            $page->save();

            $this->syncBlocks($page, $blocks);

            if ($publish) {
                $this->snapshot($page->fresh(['blocks']), $user);
            }

            PageCache::invalidate($page);
            Menus::flush(); // a title or a URL change must reach the menus and the sitemap
        });

        return $page->fresh(['blocks']);
    }

    /** @param  list<array<string, mixed>>  $blocks */
    private function syncBlocks(Page $page, array $blocks): void
    {
        $keep = [];

        foreach (array_values($blocks) as $position => $block) {
            $type = BlockType::from((string) $block['type']);
            $data = $this->clean($type, (array) ($block['data'] ?? []));
            $visible = (bool) ($block['visible'] ?? true);

            /** @var PageBlock $model */
            $model = PageBlock::query()->updateOrCreate(
                ['id' => $block['id'] ?? null, 'page_id' => $page->id],
                ['type' => $type, 'data' => $data, 'visible' => $visible, 'position' => $position],
            );

            $keep[] = $model->id;
        }

        $page->blocks()->whereKeyNot($keep)->delete();
    }

    /**
     * Numbers stay numbers, empty strings stay empty, and only keys the section knows about are stored — so a
     * section can be renamed or reordered without leaving junk behind.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function clean(BlockType $type, array $data): array
    {
        $clean = [];

        foreach ($type->fields() as $key => $default) {
            $value = $data[$key] ?? $default;

            if (is_string($value) && $key !== 'body') {
                $value = trim($value);
            }

            $clean[$key] = match (true) {
                is_bool($default) => (bool) $value,
                is_array($default) => is_array($value) ? $this->rows($value) : [],
                is_int($default) || is_numeric($value) => $this->number($value),
                default => (string) $value,
            };
        }

        return $clean;
    }

    /**
     * Rows of a repeating field, without the empty ones.
     *
     * @param  array<int|string, mixed>  $rows
     * @return list<mixed>
     */
    private function rows(array $rows): array
    {
        return array_values(array_filter($rows, fn (mixed $row): bool => $row !== null && $row !== []));
    }

    /** Image ids and counts: empty stays empty, so "no image" is null rather than 0. */
    private function number(mixed $value): ?int
    {
        return $value === '' || $value === null ? null : (int) $value;
    }

    /** A snapshot of the page as it is published now: one click puts it back (docs/09 §2.1). */
    private function snapshot(Page $page, User $user): void
    {
        PageVersion::query()->create([
            'page_id' => $page->id,
            'user_id' => $user->id,
            'data' => [
                'title' => $page->title,
                'excerpt' => $page->excerpt,
                'seo' => $page->seo,
                'blocks' => $page->blocks->map(fn (PageBlock $block): array => [
                    'type' => $block->type->value,
                    'data' => $block->data,
                    'visible' => $block->visible,
                ])->all(),
            ],
        ]);
    }
}
