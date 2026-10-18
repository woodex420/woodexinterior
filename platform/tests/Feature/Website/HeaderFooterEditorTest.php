<?php

use App\Domain\Website\Enums\BlockType;
use App\Domain\Website\Models\NavigationItem;
use App\Domain\Website\Models\Page;
use App\Domain\Website\Models\PageBlock;
use App\Domain\Website\Services\Menus;
use App\Domain\Website\Services\PageCache;
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

it('keeps the header button and the footer words editable', function () {
    $settings = app(SiteSettings::class);
    $settings->header_cta_label = 'Book a design consult';
    $settings->header_cta_url = '/book-a-visit/';
    $settings->footer_tagline = 'One team from sketch to sofa.';
    $settings->footer_copyright = '© 2026 Woodex Interior (Pvt) Ltd, Lahore.';
    $settings->save();
    PageCache::invalidateAll(Page::query()->published()->get());

    $body = $this->siteGet('/footer-page/')->assertOk()->getContent();

    expect($body)->toContain('Book a design consult')
        ->and($body)->toContain('href="/book-a-visit/"')
        ->and($body)->toContain('One team from sketch to sofa.')
        ->and($body)->toContain('© 2026 Woodex Interior (Pvt) Ltd, Lahore.')
        ->and($body)->not->toContain('Start your project</a>');
});

it('shows the social icons that have an address, and only those', function () {
    $settings = app(SiteSettings::class);
    $settings->social_links = ['facebook' => 'https://facebook.com/woodexinterior', 'instagram' => 'https://instagram.com/woodexinterior'];
    $settings->save();
    PageCache::invalidateAll(Page::query()->published()->get());

    $body = $this->siteGet('/footer-page/')->assertOk()->getContent();

    expect($body)->toContain('aria-label="Facebook"')
        ->and($body)->toContain('aria-label="Instagram"')
        ->and($body)->not->toContain('aria-label="LinkedIn"')
        ->and($body)->toContain('https://facebook.com/woodexinterior');
});

it('builds the footer columns from the footer menu', function () {
    $services = NavigationItem::factory()->create(['menu' => NavigationItem::FOOTER, 'label' => 'Services', 'url' => '/services/', 'position' => 0]);
    NavigationItem::factory()->create(['menu' => NavigationItem::FOOTER, 'label' => 'Interior design', 'url' => '/interior-design/', 'parent_id' => $services->id, 'position' => 0]);
    NavigationItem::factory()->create(['menu' => NavigationItem::FOOTER, 'label' => 'Fit out', 'url' => '/fit-out/', 'parent_id' => $services->id, 'position' => 1]);
    NavigationItem::factory()->create(['menu' => NavigationItem::FOOTER, 'label' => 'Privacy', 'url' => '/privacy/', 'position' => 1]);
    Menus::flush();

    $body = $this->siteGet('/footer-page/')->assertOk()->getContent();

    // The parent became a column title; its children the links in it; the plain link stayed a link.
    expect($body)->toContain('>Services</h2>')
        ->and($body)->toContain('Interior design')
        ->and($body)->toContain('Fit out')
        ->and($body)->toContain('>Privacy</a>');
});

it('hides the services column, the NAP column and the cities row at their switches', function () {
    $settings = app(SiteSettings::class);
    $settings->footer_show_services = false;
    $settings->footer_show_contact = false;
    $settings->footer_show_cities = false;
    $settings->save();
    PageCache::invalidateAll(Page::query()->published()->get());

    $body = $this->siteGet('/footer-page/')->assertOk()->getContent();

    expect($body)->not->toContain('>What we do</h2>')
        ->and($body)->not->toContain('>Studio</h2>')
        ->and($body)->not->toContain('Where we work');
});

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
