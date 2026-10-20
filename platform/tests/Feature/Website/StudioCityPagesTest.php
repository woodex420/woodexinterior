<?php

declare(strict_types=1);

use App\Domain\Website\Enums\BlockType;
use App\Domain\Website\Enums\PageStatus;
use App\Domain\Website\Enums\PageType;
use App\Domain\Website\Models\Page;
use App\Support\AdminNavigation;

/*
| The City pages screen, ported from the v2.1 admin (docs/audit/16.8). v2.1 kept city drafts as content
| rows and published them by writing HTML files; the OS has one kind of page, so a city draft is a Page of
| type `city` with draft status. A new city copies a template city and swaps the city's name in the words
| only — never in an address or an image id.
*/

beforeEach(function () {
    $this->owner = $this->userWithRole('owner');
    $this->actingAs($this->owner);
});

function cityPage(string $title, string $slug): Page
{
    $page = Page::query()->create([
        'type' => PageType::City,
        'slug' => $slug,
        'title' => $title,
        'status' => PageStatus::Published,
        'published_at' => now(),
    ]);

    $page->blocks()->create([
        'type' => BlockType::Hero,
        'block_key' => 'bhero01',
        'position' => 0,
        'data' => ['heading' => 'Interior Design in Lahore', 'sub' => 'Homes across Lahore', 'image_id' => 7],
    ]);
    $page->blocks()->create([
        'type' => BlockType::Text,
        'block_key' => 'btext01',
        'position' => 1,
        'data' => ['heading' => 'Why Lahore', 'body' => 'We work across Lahore.', 'link' => '/lahore/areas/'],
    ]);

    return $page;
}

it('opens the city pages screen inside the OS session', function () {
    $this->get(route('admin.website.cities'))
        ->assertOk()
        ->assertSee('City pages')
        ->assertSee('/assets/admin-v21/admin-cities.js', false);
});

it('keeps people without website.edit out', function () {
    $this->actingAs($this->userWithRole('sales'));

    $this->get(route('admin.website.cities'))->assertForbidden();
    $this->postJson(route('admin.website.studio.api'), ['action' => 'cms_city_new', 'name' => 'Gujrat'])->assertForbidden();
});

it('creates a city draft from a template and swaps the name in the words only', function () {
    $template = cityPage('Interior Design in Lahore', '/lahore/');

    $response = $this->postJson(route('admin.website.studio.api'), [
        'action' => 'cms_city_new',
        'name' => 'Gujrat',
        'from' => $template->id,
    ])->assertOk()->assertJsonPath('ok', true);

    $city = Page::query()->findOrFail($response->json('item.id'));

    expect($city->title)->toBe('Gujrat')
        ->and($city->slug)->toBe('/gujrat/')
        ->and($city->type)->toBe(PageType::City)
        ->and($city->status)->toBe(PageStatus::Draft)
        ->and($city->blocks()->count())->toBe(2);

    $hero = $city->blocks()->orderBy('position')->first();
    $text = $city->blocks()->orderBy('position')->skip(1)->first();

    expect($hero->data['heading'])->toBe('Interior Design in Gujrat')
        ->and($hero->data['sub'])->toBe('Homes across Gujrat')
        // An image id is not a word: it is copied untouched.
        ->and($hero->data['image_id'])->toBe(7)
        ->and($text->data['heading'])->toBe('Why Gujrat')
        // An address is not a word either, even when it contains the old city.
        ->and($text->data['link'])->toBe('/lahore/areas/');

    // The template is untouched.
    expect($template->blocks()->orderBy('position')->first()->data['heading'])->toBe('Interior Design in Lahore');
});

it('refuses a city that already has a page', function () {
    cityPage('Interior Design in Lahore', '/lahore/');

    $this->postJson(route('admin.website.studio.api'), ['action' => 'cms_city_new', 'name' => 'Lahore'])
        ->assertOk()
        ->assertJsonPath('ok', false)
        ->assertJsonPath('error', '“Lahore” already has a page.');
});

it('creates several cities at once and reports the ones it had to skip', function () {
    cityPage('Interior Design in Lahore', '/lahore/');

    $response = $this->postJson(route('admin.website.studio.api'), [
        'action' => 'cms_city_bulk',
        'names' => ['Gujrat', 'Lahore', 'Sargodha'],
    ])->assertOk()->assertJsonPath('ok', true);

    expect($response->json('created'))->toHaveCount(2)
        ->and($response->json('failed.0.name'))->toBe('Lahore')
        ->and($response->json('failed.0.error'))->toBe('“Lahore” already has a page.');

    expect(Page::query()->where('type', PageType::City)->where('status', PageStatus::Draft)->count())->toBe(2)
        ->and(Page::query()->where('slug', '/gujrat/')->exists())->toBeTrue()
        ->and(Page::query()->where('slug', '/sargodha/')->exists())->toBeTrue();
});

it('lists City pages as a live destination in the sidebar', function () {
    $items = collect(AdminNavigation::for($this->owner))
        ->flatMap(fn (array $group): array => $group['items'])
        ->keyBy('label');

    expect($items['City pages']['route'])->toBe('admin.website.cities')
        ->and($items['City pages']['phase'])->toBeNull()
        ->and(AdminNavigation::FULL_DOCUMENT)->toContain('admin.website.cities');
});
