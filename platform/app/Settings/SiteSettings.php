<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Analytics and search-console settings for the public website (docs/09 §11.3, D-69): GA4, Google Tag Manager,
 * Search Console verification and Microsoft Clarity. The owner pastes the IDs here; nothing is loaded until an
 * ID is filled in, and no script loads for signed-in staff (their visits are not visitors).
 *
 * Note: never use @var on properties: spatie/laravel-settings parses @var to infer casts and rejects generic
 * types such as array<string, int>. Use @phpstan-var for those (spatie ignores it).
 */
class SiteSettings extends Settings
{
    /** GA4 measurement ID, for example G-XXXXXXXXXX */
    public ?string $ga4_id;

    /** Google Tag Manager container ID, for example GTM-XXXXXXX */
    public ?string $gtm_id;

    /** The content of the Google Search Console HTML verification meta tag (P-13) */
    public ?string $google_site_verification;

    /** Microsoft Clarity project ID */
    public ?string $clarity_id;

    /** Turn every tracking script off (useful while testing, or where the law requires consent first) */
    public bool $tracking_enabled;

    /** The web chat widget on the public site (P8.5): off until the owner switches it on (A0). */
    public bool $chat_widget_enabled;

    /** @phpstan-var list<string> Events sent to GA4 as conversions (docs/09 §11.3) */
    public array $conversion_events;

    /*
     * V2-3 (docs/14): the structured header/footer editor. The layout is fixed and professional —
     * the owner edits the words, the links and the switches here; the columns themselves live in
     * Website → Header & footer (footer), where a top link with children is one footer column.
     */

    /** The header button's text. Empty keeps "Start your project". */
    public ?string $header_cta_label = null;

    /** Where the header button points. Empty keeps the enquiry form anchor. */
    public ?string $header_cta_url = null;

    /** @phpstan-var array<string, string> Studio social profiles: facebook, instagram, linkedin, youtube, tiktok — full URL or empty */
    public array $social_links = [];

    /** Overrides the business tagline in the footer when filled in. */
    public ?string $footer_tagline = null;

    /** Show the "What we do" services column in the footer. */
    public bool $footer_show_services = true;

    /** Show the "Where we work" cities row in the footer. */
    public bool $footer_show_cities = true;

    /** Show the studio's address, phone and hours column in the footer. */
    public bool $footer_show_contact = true;

    /** Overrides the footer's copyright line when filled in. */
    public ?string $footer_copyright = null;

    public static function group(): string
    {
        return 'site';
    }
}
