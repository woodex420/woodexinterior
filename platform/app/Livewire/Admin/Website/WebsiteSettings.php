<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Website;

use App\Domain\Website\Models\Page;
use App\Domain\Website\Services\PageCache;
use App\Livewire\Concerns\InteractsWithAdmin;
use App\Settings\SiteSettings;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Where the owner pastes the analytics IDs (docs/09 §11.3, D-69, P-14). Nothing is loaded on the website until
 * an ID is filled in here, and the website never loads a script for signed-in staff.
 */
#[Layout('layouts::admin')]
#[Title('Website settings')]
class WebsiteSettings extends Component
{
    use InteractsWithAdmin;

    public ?string $ga4_id = null;

    public ?string $gtm_id = null;

    public ?string $google_site_verification = null;

    public ?string $clarity_id = null;

    public bool $tracking_enabled = true;

    public bool $chat_widget_enabled = false;

    /** V2-3: the structured header/footer editor. */
    public string $header_cta_label = '';

    public string $header_cta_url = '';

    public string $social_facebook = '';

    public string $social_instagram = '';

    public string $social_linkedin = '';

    public string $social_youtube = '';

    public string $social_tiktok = '';

    public string $footer_tagline = '';

    public bool $footer_show_services = true;

    public bool $footer_show_cities = true;

    public bool $footer_show_contact = true;

    public string $footer_copyright = '';

    public function mount(SiteSettings $settings): void
    {
        abort_unless($this->user()->can('settings.manage'), 403);

        $this->ga4_id = $settings->ga4_id;
        $this->gtm_id = $settings->gtm_id;
        $this->google_site_verification = $settings->google_site_verification;
        $this->clarity_id = $settings->clarity_id;
        $this->tracking_enabled = $settings->tracking_enabled;
        $this->chat_widget_enabled = $settings->chat_widget_enabled;

        $this->header_cta_label = (string) ($settings->header_cta_label ?? '');
        $this->header_cta_url = (string) ($settings->header_cta_url ?? '');
        $this->social_facebook = (string) ($settings->social_links['facebook'] ?? '');
        $this->social_instagram = (string) ($settings->social_links['instagram'] ?? '');
        $this->social_linkedin = (string) ($settings->social_links['linkedin'] ?? '');
        $this->social_youtube = (string) ($settings->social_links['youtube'] ?? '');
        $this->social_tiktok = (string) ($settings->social_links['tiktok'] ?? '');
        $this->footer_tagline = (string) ($settings->footer_tagline ?? '');
        $this->footer_show_services = $settings->footer_show_services;
        $this->footer_show_cities = $settings->footer_show_cities;
        $this->footer_show_contact = $settings->footer_show_contact;
        $this->footer_copyright = (string) ($settings->footer_copyright ?? '');
    }

    public function save(SiteSettings $settings): void
    {
        abort_unless($this->user()->can('settings.manage'), 403);

        $data = $this->validate([
            'ga4_id' => ['nullable', 'string', 'max:40', 'regex:/^(G-[A-Z0-9]+|UA-\d+-\d+)?$/'],
            'gtm_id' => ['nullable', 'string', 'max:40', 'regex:/^GTM-[A-Z0-9]+$/'],
            'google_site_verification' => ['nullable', 'string', 'max:120'],
            'clarity_id' => ['nullable', 'string', 'max:40'],
            'tracking_enabled' => ['boolean'],
            'chat_widget_enabled' => ['boolean'],
            'header_cta_label' => ['nullable', 'string', 'max:60'],
            'header_cta_url' => ['nullable', 'string', 'max:500'],
            'social_facebook' => ['nullable', 'string', 'max:500', 'regex:#^https://#'],
            'social_instagram' => ['nullable', 'string', 'max:500', 'regex:#^https://#'],
            'social_linkedin' => ['nullable', 'string', 'max:500', 'regex:#^https://#'],
            'social_youtube' => ['nullable', 'string', 'max:500', 'regex:#^https://#'],
            'social_tiktok' => ['nullable', 'string', 'max:500', 'regex:#^https://#'],
            'footer_tagline' => ['nullable', 'string', 'max:300'],
            'footer_show_services' => ['boolean'],
            'footer_show_cities' => ['boolean'],
            'footer_show_contact' => ['boolean'],
            'footer_copyright' => ['nullable', 'string', 'max:200'],
        ], [
            'ga4_id.regex' => 'A GA4 measurement ID looks like G-XXXXXXXXXX.',
            'gtm_id.regex' => 'A Google Tag Manager ID looks like GTM-XXXXXXX.',
        ]);

        $settings->fill([
            'ga4_id' => $data['ga4_id'] ?: null,
            'gtm_id' => $data['gtm_id'] ?: null,
            'google_site_verification' => $data['google_site_verification'] ?: null,
            'clarity_id' => $data['clarity_id'] ?: null,
            'tracking_enabled' => (bool) $data['tracking_enabled'],
            'chat_widget_enabled' => (bool) $data['chat_widget_enabled'],
            'header_cta_label' => $data['header_cta_label'] ?: null,
            'header_cta_url' => $data['header_cta_url'] ?: null,
            'social_links' => array_filter([
                'facebook' => trim((string) $data['social_facebook']),
                'instagram' => trim((string) $data['social_instagram']),
                'linkedin' => trim((string) $data['social_linkedin']),
                'youtube' => trim((string) $data['social_youtube']),
                'tiktok' => trim((string) $data['social_tiktok']),
            ], fn (string $url): bool => $url !== ''),
            'footer_tagline' => $data['footer_tagline'] ?: null,
            'footer_show_services' => (bool) $data['footer_show_services'],
            'footer_show_cities' => (bool) $data['footer_show_cities'],
            'footer_show_contact' => (bool) $data['footer_show_contact'],
            'footer_copyright' => $data['footer_copyright'] ?: null,
        ]);
        $settings->save();

        // Published pages are cached whole (docs/09 §2.3), so switching the chat widget must drop
        // every cached page — otherwise visitors keep yesterday's HTML (with or without the bubble).
        PageCache::invalidateAll(Page::query()->published()->get());

        $this->toast('Saved. The change is live on the next page view.');
    }

    public function render(SiteSettings $settings): View
    {
        return view('livewire.admin.website.website-settings', [
            'live' => [
                'ga4' => filled($settings->ga4_id),
                'gtm' => filled($settings->gtm_id),
                'clarity' => filled($settings->clarity_id),
                'gsc' => filled($settings->google_site_verification),
            ],
        ]);
    }
}
