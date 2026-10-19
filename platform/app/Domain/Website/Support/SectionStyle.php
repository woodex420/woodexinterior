<?php

declare(strict_types=1);

namespace App\Domain\Website\Support;

/**
 * Section settings (19 Oct, builder parity with admin v2.1 pro): the handful of choices an editor makes
 * about the *wrapper* of one section — its background, the space above and below it, how wide its
 * content may grow and whether it is centred — plus the label the section is known by in the editor.
 *
 * Kept apart from the section's data on purpose: a block's data is what the section says (its text,
 * photos, rows), while these are how it is framed. Changing the background of a section therefore
 * never touches its content, and a section that keeps no settings renders exactly as designed.
 *
 * This class is the single place where the values are validated and turned into CSS class names, so
 * the editor, the saver and the page template cannot drift apart.
 */
final class SectionStyle
{
    /** @var array<string, string> */
    public const array BACKGROUNDS = [
        '' => 'As designed',
        'white' => 'White',
        'light' => 'Warm light',
        'beige' => 'Sand',
        'navy' => 'Navy',
    ];

    /** @var array<string, string> */
    public const array SPACINGS = [
        '' => 'As designed',
        'compact' => 'Compact',
        'spacious' => 'Spacious',
        'flush' => 'No padding',
    ];

    /** @var array<string, string> */
    public const array WIDTHS = [
        '' => 'Boxed',
        'full' => 'Full width',
    ];

    /** @var array<string, string> */
    public const array ALIGNMENTS = [
        '' => 'As designed',
        'centre' => 'Centred',
    ];

    /** @return array{label: string, background: string, spacing: string, width: string, align: string} */
    public static function defaults(): array
    {
        return ['label' => '', 'background' => '', 'spacing' => '', 'width' => '', 'align' => ''];
    }

    /**
     * Only known keys and known values reach the database — an unknown class posted by hand is
     * dropped, never printed into the page.
     *
     * @param  array<string, mixed>|null  $settings
     * @return array{label: string, background: string, spacing: string, width: string, align: string}
     */
    public static function clean(?array $settings): array
    {
        $settings ??= [];

        return [
            'label' => mb_substr(trim(strip_tags((string) ($settings['label'] ?? ''))), 0, 60),
            'background' => self::oneOf($settings['background'] ?? '', self::BACKGROUNDS),
            'spacing' => self::oneOf($settings['spacing'] ?? '', self::SPACINGS),
            'width' => self::oneOf($settings['width'] ?? '', self::WIDTHS),
            'align' => self::oneOf($settings['align'] ?? '', self::ALIGNMENTS),
        ];
    }

    /**
     * The wrapper's classes. `wx-sec` is always there so the builder's own preview styles have a hook.
     *
     * @param  array<string, mixed>|null  $settings
     * @return list<string>
     */
    public static function classes(?array $settings): array
    {
        $settings = self::clean($settings);

        return array_values(array_filter([
            'wx-sec',
            $settings['background'] !== '' ? 'wx-sec--bg-'.$settings['background'] : null,
            $settings['spacing'] !== '' ? 'wx-sec--sp-'.$settings['spacing'] : null,
            $settings['width'] !== '' ? 'wx-sec--'.$settings['width'] : null,
            $settings['align'] !== '' ? 'wx-sec--'.$settings['align'] : null,
        ]));
    }

    /**
     * The section's name in the editor: the label the studio gave it, or the type's own name.
     *
     * @param  array<string, mixed>|null  $settings
     */
    public static function title(?array $settings, string $fallback): string
    {
        $label = trim((string) ($settings['label'] ?? ''));

        return $label !== '' ? $label : $fallback;
    }

    /**
     * @param  array<string, string>  $allowed
     */
    private static function oneOf(mixed $value, array $allowed): string
    {
        $value = (string) $value;

        return array_key_exists($value, $allowed) ? $value : '';
    }
}
