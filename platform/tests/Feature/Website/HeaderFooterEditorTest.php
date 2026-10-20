<?php

use App\Domain\Website\Enums\BlockType;
use App\Domain\Website\Models\NavigationItem;
use App\Domain\Website\Models\Page;
use App\Domain\Website\Models\PageBlock;
use App\Domain\Website\Services\Menus;
use App\Livewire\Admin\Website\WebsiteSettings;
use App\Models\User;
use App\Settings\SiteSettings;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithPublicWebsite;

/*
| V2-3 (docs/14): the structured header/footer editor. The layout is fixed and professional; the words,
| links and switches are editable in Website settings, and the footer's link columns come from the
| footer menu (Website → Menus): a top link with children is one column.
*/

uses(InteractsWithPublicWebsite::class);

beforeEach(function () {
    $this->manager = User::factory()->create();
    $this->manager->givePermissionTo(['settings.manage']);

    NavigationItem::query()->delete();
    Menus::flush();
    footerPage();
});

function footerPage(): Page
{
    $page = Page::factory()->published()->withSlug('/footer-page/')->create(['title' => 'Footer page']);
    PageBlock::factory()->for($page)->asType(BlockType::Text, ['heading' => 'Body', 'body' => 'Text'])->create(['position' => 0]);

    return $page;
}

it('saves the header, social and footer settings from the dashboard', function () {
    Livewire::actingAs($this->manager)
        ->test(WebsiteSettings::class)
        ->set('header_cta_label', 'Get a quote')
        ->set('header_cta_url', '/contact/')
        ->set('social_facebook', 'https://facebook.com/woodexinterior')
        ->set('social_instagram', 'not-a-url')
        ->call('save')
        ->assertHasErrors('social_instagram')
        ->set('social_instagram', 'https://instagram.com/woodexinterior')
        ->set('footer_tagline', 'Design, build and furniture — one team.')
        ->set('footer_show_cities', false)
        ->set('footer_copyright', '© 2026 Woodex.')
        ->call('save')
        ->assertHasNoErrors();

    $settings = app(SiteSettings::class);

    expect($settings->header_cta_label)->toBe('Get a quote')
        ->and($settings->header_cta_url)->toBe('/contact/')
        ->and($settings->social_links)->toBe(['facebook' => 'https://facebook.com/woodexinterior', 'instagram' => 'https://instagram.com/woodexinterior'])
        ->and($settings->footer_tagline)->toBe('Design, build and furniture — one team.')
        ->and($settings->footer_show_cities)->toBeFalse()
        ->and($settings->footer_copyright)->toBe('© 2026 Woodex.');
});
