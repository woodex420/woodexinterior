<?php

declare(strict_types=1);

use App\Domain\Website\Enums\BlockType;
use App\Domain\Website\Enums\PageStatus;
use App\Domain\Website\Enums\PageType;
use App\Domain\Website\Models\Page;
use App\Support\AdminNavigation;

/*
| The Service pages screen, ported from the v2.1 admin (docs/audit/16.8). v2.1 listed built HTML files and
| edited them by rewriting the file; Woodex OS renders pages from blocks, so the screen lists the page
| table and opens the section editor, and keeps the two actions that matter: duplicate a row, start a new
| page from an existing layout.
*/

beforeEach(function () {
    $this->owner = $this->userWithRole('owner');
    $this->actingAs($this->owner);
});

function servicePage(string $title, string $slug): Page
{
    $page = Page::query()->create([
        'type' => PageType::Service,
        'slug' => $slug,
        'title' => $title,
        'status' => PageStatus::Published,
        'published_at' => now(),
    ]);

    $page->blocks()->create(['type' => BlockType::Hero, 'block_key' => 'bhero01', 'position' => 0, 'data' => ['heading' => $title]]);
    $page->blocks()->create(['type' => BlockType::Text, 'block_key' => 'btext01', 'position' => 1, 'data' => ['heading' => 'What we do', 'body' => 'Fitted furniture.']]);

    return $page;
}

it('opens the service pages screen inside the OS session', function () {
    $this->get(route('admin.website.services'))
        ->assertOk()
        ->assertSee('Service &amp; site pages', false)
        ->assertSee('/assets/admin-v21/admin-services.js', false);
});

it('keeps people without website.edit out', function () {
    $this->actingAs($this->userWithRole('sales'));

    $this->get(route('admin.website.services'))->assertForbidden();
    $this->postJson(route('admin.website.studio.api'), ['action' => 'pages'])->assertForbidden();
});

it('lists the pages the screen manages, with their type', function () {
    $service = servicePage('Modular kitchens', '/modular-kitchens/');
    Page::query()->create(['type' => PageType::Insight, 'slug' => '/insights/thing/', 'title' => 'An article', 'status' => PageStatus::Draft]);

    $response = $this->postJson(route('admin.website.studio.api'), ['action' => 'pages'])
        ->assertOk()
        ->assertJsonPath('ok', true);

    $pages = collect($response->json('pages'));

    expect($pages->pluck('title'))->toContain('Modular kitchens')
        ->and($pages->pluck('title'))->not->toContain('An article')
        ->and($pages->firstWhere('id', $service->id)['kind'])->toBe('service')
        ->and($pages->firstWhere('id', $service->id)['path'])->toBe('modular-kitchens/index.html');
});

it('answers the kind map the screen badges with', function () {
    servicePage('Modular kitchens', '/modular-kitchens/');

    // Read the map whole: the keys are file-shaped ("index.html"), so a dotted JSON path would split them.
    $kinds = $this->postJson(route('admin.website.studio.api'), ['action' => 'cms_page_kinds'])
        ->assertOk()
        ->json('kinds');

    expect($kinds['modular-kitchens/index.html'])->toBe('service');
});

it('creates a service page from an existing layout as a draft', function () {
    $source = servicePage('Modular kitchens', '/modular-kitchens/');

    $response = $this->postJson(route('admin.website.studio.api'), [
        'action' => 'cms_page_new',
        'title' => 'Modular kitchens in Islamabad',
        'slug' => '/modular-kitchens-islamabad/',
        'from' => $source->id,
    ])->assertOk()->assertJsonPath('ok', true);

    $page = Page::query()->findOrFail($response->json('item.id'));

    expect($page->title)->toBe('Modular kitchens in Islamabad')
        ->and($page->slug)->toBe('/modular-kitchens-islamabad/')
        ->and($page->type)->toBe(PageType::Service)
        ->and($page->status)->toBe(PageStatus::Draft)
        ->and($page->published_at)->toBeNull()
        ->and($page->blocks()->count())->toBe(2);

    // The layout is copied, not shared: editing the new page cannot change the old one.
    $page->blocks()->first()->update(['data' => ['heading' => 'Changed']]);
    expect($source->blocks()->first()->data['heading'])->toBe('Modular kitchens');
});

it('creates a blank service page when no layout is chosen', function () {
    $response = $this->postJson(route('admin.website.studio.api'), [
        'action' => 'cms_page_new',
        'title' => 'Walk-in wardrobes',
        'slug' => '/walk-in-wardrobes/',
    ])->assertOk();

    $page = Page::query()->findOrFail($response->json('item.id'));

    expect($page->status)->toBe(PageStatus::Draft)
        ->and($page->blocks()->count())->toBe(0);
});

it('refuses an address that is already in use', function () {
    servicePage('Modular kitchens', '/modular-kitchens/');

    $this->postJson(route('admin.website.studio.api'), [
        'action' => 'cms_page_new',
        'title' => 'Another one',
        'slug' => '/modular-kitchens/',
    ])->assertOk()->assertJsonPath('ok', false)
        ->assertJsonPath('error', 'Another page already uses that address.');
});

it('duplicates a row as a draft with a free address', function () {
    $source = servicePage('Modular kitchens', '/modular-kitchens/');
    Page::query()->create(['type' => PageType::Service, 'slug' => '/modular-kitchens-copy/', 'title' => 'Taken', 'status' => PageStatus::Draft]);

    $response = $this->postJson(route('admin.website.studio.api'), [
        'action' => 'cms_page_duplicate',
        'id' => $source->id,
    ])->assertOk();

    $copy = Page::query()->findOrFail($response->json('item.id'));

    expect($copy->slug)->toBe('/modular-kitchens-copy-2/')
        ->and($copy->title)->toBe('Modular kitchens (copy)')
        ->and($copy->status)->toBe(PageStatus::Draft)
        ->and($copy->blocks()->count())->toBe(2);
});

it('lists Service pages as a live destination in the sidebar', function () {
    $items = collect(AdminNavigation::for($this->owner))
        ->flatMap(fn (array $group): array => $group['items'])
        ->keyBy('label');

    expect($items['Service pages']['route'])->toBe('admin.website.services')
        ->and($items['Service pages']['phase'])->toBeNull()
        ->and(AdminNavigation::FULL_DOCUMENT)->toContain('admin.website.services');
});
