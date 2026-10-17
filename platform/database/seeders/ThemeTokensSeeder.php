<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Website\Models\ThemeToken;
use Illuminate\Database\Seeder;

/**
 * v1's own design as tokens (V2-1, D-116): the palette, type and shape the live site already speaks, taken
 * from the final :root cascade of its stylesheets (site-p21.css → v1-p21.css → the inline wx-crit layer).
 * These keys are the live CSS's own vocabulary, so /assets/theme.css — the stylesheet every v1 page already
 * links last — re-skins the live site the moment a value changes. The Theme Builder edits these; the seeder
 * only adds missing tokens and never overwrites a saved value, so "back to v1" is always available.
 */
class ThemeTokensSeeder extends Seeder
{
    /**
     * @return list<array{group: string, key: string, value: string, label: string, type: string}>
     */
    public static function tokens(): array
    {
        return [
            // Colour — the studio palette, ordered by how much of the site it covers.
            ['group' => 'colour', 'key' => '--navy', 'value' => '#0C1628', 'label' => 'Brand navy', 'type' => 'color'],
            ['group' => 'colour', 'key' => '--navy-2', 'value' => '#121E34', 'label' => 'Navy hover', 'type' => 'color'],
            ['group' => 'colour', 'key' => '--wood', 'value' => '#B8956A', 'label' => 'Wood accent', 'type' => 'color'],
            ['group' => 'colour', 'key' => '--wood-2', 'value' => '#C9A97A', 'label' => 'Wood accent, light', 'type' => 'color'],
            ['group' => 'colour', 'key' => '--ink', 'value' => '#12151C', 'label' => 'Headings & text', 'type' => 'color'],
            ['group' => 'colour', 'key' => '--muted', 'value' => '#6A6560', 'label' => 'Muted text', 'type' => 'color'],
            ['group' => 'colour', 'key' => '--charcoal', 'value' => '#525252', 'label' => 'Charcoal text', 'type' => 'color'],
            ['group' => 'colour', 'key' => '--paper', 'value' => '#FFFFFF', 'label' => 'Page background', 'type' => 'color'],
            ['group' => 'colour', 'key' => '--surface', 'value' => '#F4EFE7', 'label' => 'Beige surface', 'type' => 'color'],
            ['group' => 'colour', 'key' => '--line', 'value' => 'rgba(12,22,40,.14)', 'label' => 'Borders, light', 'type' => 'text'],
            ['group' => 'colour', 'key' => '--navy-line', 'value' => 'rgba(255,255,255,.22)', 'label' => 'Borders, dark', 'type' => 'text'],
            ['group' => 'colour', 'key' => '--light-gray', 'value' => '#E2E2E2', 'label' => 'Light grey border', 'type' => 'color'],
            ['group' => 'colour', 'key' => '--shadow', 'value' => '0 18px 50px rgba(12,22,40,.10)', 'label' => 'Shadow', 'type' => 'text'],

            // Typography — the site's one family, headings and body alike.
            ['group' => 'typography', 'key' => '--wx-font', 'value' => '"Plus Jakarta Sans", system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif', 'label' => 'Site font', 'type' => 'font'],

            // Shape
            ['group' => 'shape', 'key' => '--r-lg', 'value' => '16px', 'label' => 'Corner radius', 'type' => 'text'],
        ];
    }

    public function run(): void
    {
        foreach (self::tokens() as $index => $token) {
            ThemeToken::query()->firstOrCreate(
                ['key' => $token['key']],
                $token + ['sort' => ($index + 1) * 10],
            );
        }
    }
}
