<?php

use App\Domain\Website\Enums\BlockType;
use App\Domain\Website\Enums\PageType;
use App\Domain\Website\Models\Page;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Str;

/**
 * A city page points at the areas inside it (audit of 16 Oct 2026, docs/10).
 *
 * The eight Lahore area pages — DHA, Gulberg, Johar Town and the rest — are the pages a person in Lahore actually
 * searches for, and nothing linked to them: not the city page, not the footer, not a guide. Only the sitemap knew
 * they existed. Each city page now ends with the areas it covers, which is both the missing link and the sentence
 * a visitor in DHA wants to read.
 *
 * Runs once; whatever the studio arranges afterwards in Website -> Pages is left alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        $cities = Page::query()
            ->published()
            ->ofType(PageType::City)
            ->orderBy('sort')
            ->get();

        foreach ($cities as $city) {
            $areas = Page::query()
                ->published()
                ->ofType(PageType::Area)
                ->where('slug', 'like', $city->slug.'%')
                ->orderBy('sort')
                ->pluck('id')
                ->all();

            if ($areas === []) {
                continue;
            }

            $existing = $city->blocks()->where('type', BlockType::Related->value)->get();

            foreach ($existing as $block) {
                if (array_intersect($areas, (array) ($block->data['page_ids'] ?? [])) !== []) {
                    continue 2;
                }
            }

            $city->blocks()->create([
                'type' => BlockType::Related->value,
                'position' => ((int) $city->blocks()->max('position')) + 1,
                'visible' => true,
                'data' => [
                    'heading' => 'Areas of '.self::cityName($city).' we work in',
                    'page_ids' => $areas,
                ],
            ]);

            // The rendered page is cached against updated_at (PageCache); without this the old HTML stays.
            $city->touch();
        }
    }

    /** The city's own name, from the slug: `/lahore/` -> "Lahore". Page titles read "Interior design in Lahore". */
    private static function cityName(Page $city): string
    {
        $last = Str::of($city->slug)->trim('/')->afterLast('/');

        return (string) $last->headline();
    }

    public function down(): void
    {
        Page::query()
            ->published()
            ->ofType(PageType::City)
            ->get()
            ->each(function (Page $city): void {
                $areas = Page::query()
                    ->ofType(PageType::Area)
                    ->where('slug', 'like', $city->slug.'%')
                    ->pluck('id')
                    ->all();

                $city->blocks()
                    ->where('type', BlockType::Related->value)
                    ->whereJsonContains('data->page_ids', $areas[0] ?? 0)
                    ->delete();

                $city->touch();
            });
    }
};
