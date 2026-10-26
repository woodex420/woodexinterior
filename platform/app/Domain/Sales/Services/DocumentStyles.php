<?php

declare(strict_types=1);

namespace App\Domain\Sales\Services;

use App\Domain\Crm\Enums\BusinessLine;
use App\Domain\Sales\Models\DocumentDesign;

/**
 * The document-design system: brand identity + PDF theme + ordered PDF blocks.
 *
 * A value copy, including logo bytes, is frozen into each new document; documents never follow
 * the mutable design master at render time. Snapshots frozen before themes/blocks existed carry
 * no pdf_theme/quote_blocks/invoice_blocks keys — the normalizers below turn "missing" into the
 * exact pre-Slice-O behaviour (classic theme, every block visible, original order).
 */
final class DocumentStyles
{
    public const THEME_CLASSIC = 'classic';

    /** @var array<string, string> */
    public const THEMES = [
        'classic' => 'Classic',
        'minimal' => 'Minimal',
        'premium' => 'Premium',
    ];

    /** @var array<string, string> */
    public const THEME_DESCRIPTIONS = [
        'classic' => 'Today’s look — accent headings, shaded section rows, dark total band.',
        'minimal' => 'Plain black-and-white — light table headers, no shading, rule above the total.',
        'premium' => 'Stronger brand — solid accent headings and section bands, heavier header rule.',
    ];

    /** @var array<string, string> Block key => label, in the original (default) render order. */
    public const QUOTE_BLOCKS = [
        'scope' => 'Scope of work',
        'boq' => 'Items / BOQ (Price)',
        'totals' => 'Totals & amount in words',
        'payment' => 'Payment schedule',
        'terms' => 'Terms & conditions',
        'notes' => 'Notes',
        'payment_details' => 'Payment details (bank)',
        'signatures' => 'Signatures',
    ];

    /** @var array<string, string> */
    public const INVOICE_BLOCKS = [
        'items' => 'Particulars',
        'totals' => 'Totals & amount in words',
        'payment_details' => 'Pay to (bank)',
        'notes' => 'Notes',
        'terms' => 'Terms',
    ];

    /** Blocks that can move but never hide — a quote/invoice without its lines or totals is not a document. */
    /** @var array<string, list<string>> */
    public const LOCKED_BLOCKS = [
        'quote' => ['boq', 'totals'],
        'invoice' => ['items', 'totals'],
    ];

    /** @return array<string, string> */
    public static function catalogue(string $kind): array
    {
        return $kind === 'invoice' ? self::INVOICE_BLOCKS : self::QUOTE_BLOCKS;
    }

    public static function theme(mixed $theme): string
    {
        return is_string($theme) && isset(self::THEMES[$theme]) ? $theme : self::THEME_CLASSIC;
    }

    /**
     * Normalize a stored/submitted block list: drop unknown keys, keep the first of duplicates,
     * append any known block that is missing (visible), and force locked blocks visible.
     *
     * @return list<array{key: string, visible: bool}>
     */
    public static function blocks(string $kind, mixed $stored): array
    {
        $catalogue = self::catalogue($kind);
        $locked = self::LOCKED_BLOCKS[$kind === 'invoice' ? 'invoice' : 'quote'];
        $blocks = [];
        $seen = [];

        foreach (is_array($stored) ? $stored : [] as $entry) {
            $key = is_array($entry) ? ($entry['key'] ?? null) : null;

            if (! is_string($key) || ! isset($catalogue[$key]) || isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $blocks[] = ['key' => $key, 'visible' => in_array($key, $locked, true) || (bool) ($entry['visible'] ?? true)];
        }

        foreach (array_keys($catalogue) as $key) {
            if (! isset($seen[$key])) {
                $blocks[] = ['key' => $key, 'visible' => true];
            }
        }

        return $blocks;
    }

    /**
     * Ordered visible block keys for a frozen design array (used by the PDF blades).
     *
     * @param  array<string, mixed>|null  $design
     * @return list<string>
     */
    public static function visibleBlocks(string $kind, ?array $design): array
    {
        $stored = is_array($design) ? ($design[$kind === 'invoice' ? 'invoice_blocks' : 'quote_blocks'] ?? null) : null;

        return array_values(array_map(
            static fn (array $block): string => $block['key'],
            array_filter(self::blocks($kind, $stored), static fn (array $block): bool => $block['visible']),
        ));
    }

    /** @return array<string, mixed> */
    public function copy(BusinessLine $line): array
    {
        $design = DocumentDesign::query()->where('business_line', $line === BusinessLine::Furniture ? 'furniture' : 'interior')->firstOrFail();

        return [
            ...$design->only(['id', 'business_line', 'revision', 'brand_name', 'accent_color', 'header_text', 'footer_text', 'logo_data_uri', 'logo_sha256']),
            'pdf_theme' => self::theme($design->pdf_theme ?? null),
            'quote_blocks' => self::blocks('quote', $design->quote_blocks),
            'invoice_blocks' => self::blocks('invoice', $design->invoice_blocks),
        ];
    }
}
