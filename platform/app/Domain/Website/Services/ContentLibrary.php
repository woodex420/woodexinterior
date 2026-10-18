<?php

declare(strict_types=1);

namespace App\Domain\Website\Services;

use App\Domain\Website\Contracts\LibraryEntry;
use App\Domain\Website\Models\ContentEntry;
use App\Domain\Website\Models\TeamMember;
use Illuminate\Support\Facades\Cache;

/**
 * The shared library behind the FAQ, Reviews, Clients and Team sections (V2-3 F6/F7, docs/14 §5a).
 * Entries are managed once in Website → Content (or → Team for the studio's people); a page's
 * section points at the ones it wants and renders them in the library's order. Cached per kind — a
 * day, like the menus — and flushed by the dashboard screens on every change.
 */
final class ContentLibrary
{
    /**
     * The entries of one kind, in the library's order.
     *
     * @return list<LibraryEntry>
     */
    public static function entries(string $kind): array
    {
        // The studio's people live in their own table (F7); everything else is a content entry (F6).
        if ($kind === 'team') {
            return TeamMember::query()->ordered()->get()->all();
        }

        /** @var list<int> $ids */
        $ids = Cache::remember('website.library.'.$kind, now()->addDay(), fn (): array => ContentEntry::query()
            ->ofKind($kind)
            ->orderBy('position')
            ->pluck('id')
            ->all());

        if ($ids === []) {
            return [];
        }

        $byId = ContentEntry::query()->whereIn('id', $ids)->get()->keyBy('id');

        return array_values(array_filter(array_map(fn (int $id): ?ContentEntry => $byId->get($id), $ids)));
    }

    /**
     * Specific entries, in the library's order (not the order they were picked in) — the library is
     * one list the studio curates; pages choose members, never sequence.
     *
     * @param  list<int>  $ids
     * @return list<LibraryEntry>
     */
    public static function pick(array $ids, string $kind): array
    {
        $wanted = array_map('intval', $ids);

        return array_values(array_filter(
            self::entries($kind),
            fn (LibraryEntry $entry): bool => in_array($entry->entryId(), $wanted, true),
        ));
    }

    /**
     * The picked entries as section rows.
     *
     * @param  list<int>  $ids
     * @return list<array<string, mixed>>
     */
    public static function rows(array $ids, string $kind): array
    {
        return array_map(fn (LibraryEntry $entry): array => $entry->toRow(), self::pick($ids, $kind));
    }

    public static function flush(): void
    {
        foreach ([...ContentEntry::KINDS, 'team'] as $kind) {
            Cache::forget('website.library.'.$kind);
        }
    }
}
