<?php

use App\Domain\Website\Enums\BlockType;
use App\Domain\Website\Models\NavigationItem;
use App\Domain\Website\Models\Page;
use App\Domain\Website\Models\PageBlock;
use App\Domain\Website\Services\Menus;
use App\Livewire\Admin\Website\MenusIndex;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithPublicWebsite;

/*
| V2-3 navigation (docs/14): menus are a tree. A top link with children opens a dropdown; its mega switch
| turns that dropdown into the wide numbered panel of the v26 design. Children move only inside their own
| level, and the tree is cached like the flat menus always were.
*/

uses(InteractsWithPublicWebsite::class);

beforeEach(function () {
    $this->editor = User::factory()->create();
    $this->editor->givePermissionTo('website.edit');

    Cache::forget('website.menu.tree.header');
    Cache::forget('website.menu.header');
    NavigationItem::query()->delete();
});

it('builds the header menu as a tree with children and grandchildren', function () {
    $services = NavigationItem::factory()->create(['menu' => NavigationItem::HEADER, 'label' => 'Services', 'url' => '/services/', 'position' => 0, 'is_mega' => true]);
    $design = NavigationItem::factory()->create(['menu' => NavigationItem::HEADER, 'label' => 'Interior design', 'url' => '/interior-design/', 'parent_id' => $services->id, 'position' => 0]);
    NavigationItem::factory()->create(['menu' => NavigationItem::HEADER, 'label' => 'Kitchen design', 'url' => '/kitchen-design/', 'parent_id' => $design->id, 'position' => 0]);
    NavigationItem::factory()->create(['menu' => NavigationItem::HEADER, 'label' => 'About', 'url' => '/about/', 'position' => 1]);

    $tree = Menus::tree('header');

    expect($tree)->toHaveCount(2)
        ->and($tree[0])->toMatchArray(['label' => 'Services', 'url' => '/services/', 'is_mega' => true])
        ->and($tree[0]['children'])->toHaveCount(1)
        ->and($tree[0]['children'][0]['label'])->toBe('Interior design')
        ->and($tree[0]['children'][0]['children'][0]['label'])->toBe('Kitchen design')
        ->and($tree[1])->toMatchArray(['label' => 'About', 'is_mega' => false, 'children' => []]);
});

it('caches the tree and flushes it when the dashboard changes a menu', function () {
    expect(Cache::has('website.menu.tree.header'))->toBeFalse();

    NavigationItem::factory()->create(['menu' => NavigationItem::HEADER, 'label' => 'About', 'url' => '/about/', 'position' => 0]);
    Menus::tree('header');
    expect(Cache::has('website.menu.tree.header'))->toBeTrue();

    // A change through the dashboard flushes the cached tree along with the flat menus.
    Livewire::actingAs($this->editor)
        ->test(MenusIndex::class)
        ->set('menu', 'header')
        ->set('label', 'Projects')
        ->set('url', '/projects/')
        ->call('add');

    expect(Cache::has('website.menu.tree.header'))->toBeFalse();

    $tree = Menus::tree('header');
    expect($tree)->toHaveCount(2)
        ->and(collect($tree)->pluck('label'))->toContain('Projects');
});

it('falls back to the built-in links as a flat tree until the menu is filled in', function () {
    $tree = Menus::tree('header');

    expect($tree)->not->toBeEmpty()
        ->and($tree[0]['children'])->toBe([])
        ->and($tree[0]['is_mega'])->toBeFalse();
});

it('adds a link under a parent, and refuses a fourth level', function () {
    $services = NavigationItem::factory()->create(['menu' => NavigationItem::HEADER, 'label' => 'Services', 'url' => '/services/', 'position' => 0]);
    $design = NavigationItem::factory()->create(['menu' => NavigationItem::HEADER, 'label' => 'Interior design', 'url' => '/interior-design/', 'parent_id' => $services->id, 'position' => 0]);
    $kitchen = NavigationItem::factory()->create(['menu' => NavigationItem::HEADER, 'label' => 'Kitchen design', 'url' => '/kitchen-design/', 'parent_id' => $design->id, 'position' => 0]);

    // Under a top link: a dropdown entry.
    Livewire::actingAs($this->editor)
        ->test(MenusIndex::class)
        ->set('parent', (string) $services->id)
        ->set('label', 'Fit out')
        ->set('url', '/fit-out/')
        ->call('add')
        ->assertOk();

    expect(NavigationItem::query()->where('parent_id', $services->id)->count())->toBe(2)
        ->and(Menus::tree('header')[0]['children'])->toHaveCount(2);

    // A link under a third-level link is one level too deep.
    Livewire::actingAs($this->editor)
        ->test(MenusIndex::class)
        ->set('parent', (string) $kitchen->id)
        ->set('label', 'Too deep')
        ->set('url', '/too-deep/')
        ->call('add');

    expect(NavigationItem::query()->where('label', 'Too deep')->doesntExist())->toBeTrue();
});

it('moves a link only inside its own level', function () {
    $services = NavigationItem::factory()->create(['menu' => NavigationItem::HEADER, 'label' => 'Services', 'url' => '/services/', 'position' => 0]);
    $about = NavigationItem::factory()->create(['menu' => NavigationItem::HEADER, 'label' => 'About', 'url' => '/about/', 'position' => 1]);
    $fitOut = NavigationItem::factory()->create(['menu' => NavigationItem::HEADER, 'label' => 'Fit out', 'url' => '/fit-out/', 'parent_id' => $services->id, 'position' => 0]);
    $design = NavigationItem::factory()->create(['menu' => NavigationItem::HEADER, 'label' => 'Interior design', 'url' => '/interior-design/', 'parent_id' => $services->id, 'position' => 1]);

    // The child moves within the dropdown, not past its parent's siblings.
    Livewire::actingAs($this->editor)
        ->test(MenusIndex::class)
        ->call('move', $fitOut->id, 1);

    expect($fitOut->fresh()->position)->toBe(1)
        ->and($design->fresh()->position)->toBe(0)
        ->and($services->fresh()->position)->toBe(0)
        ->and($about->fresh()->position)->toBe(1)
        ->and(Menus::tree('header')[0]['children'][0]['label'])->toBe('Interior design');
});

it('flips the mega switch on a top link only', function () {
    $services = NavigationItem::factory()->create(['menu' => NavigationItem::HEADER, 'label' => 'Services', 'url' => '/services/', 'position' => 0, 'is_mega' => false]);
    $design = NavigationItem::factory()->create(['menu' => NavigationItem::HEADER, 'label' => 'Interior design', 'url' => '/interior-design/', 'parent_id' => $services->id, 'position' => 0]);

    Livewire::actingAs($this->editor)
        ->test(MenusIndex::class)
        ->call('toggleMega', $services->id)
        ->call('toggleMega', $design->id);

    expect($services->fresh()->is_mega)->toBeTrue()
        ->and($design->fresh()->is_mega)->toBeFalse();
});

it('renders dropdowns, mega panels and mobile disclosures on the site', function () {
    $services = NavigationItem::factory()->create(['menu' => NavigationItem::HEADER, 'label' => 'Services', 'url' => '/services/', 'position' => 0, 'is_mega' => true]);
    $design = NavigationItem::factory()->create(['menu' => NavigationItem::HEADER, 'label' => 'Interior design', 'url' => '/interior-design/', 'parent_id' => $services->id, 'position' => 0]);
    NavigationItem::factory()->create(['menu' => NavigationItem::HEADER, 'label' => 'Kitchen design', 'url' => '/kitchen-design/', 'parent_id' => $design->id, 'position' => 0]);

    $about = NavigationItem::factory()->create(['menu' => NavigationItem::HEADER, 'label' => 'About', 'url' => '/about/', 'position' => 1, 'is_mega' => false]);
    NavigationItem::factory()->create(['menu' => NavigationItem::HEADER, 'label' => 'The studio', 'url' => '/about/', 'parent_id' => $about->id, 'position' => 0]);

    Menus::flush();

    // Any published page carries the header; one is enough to read it from.
    $page = Page::factory()->published()->withSlug('/menus-page/')->create(['title' => 'Menus page']);
    PageBlock::factory()->for($page)->asType(BlockType::Text, ['heading' => 'Body', 'body' => 'Text'])->create(['position' => 0]);

    $body = $this->siteGet('/menus-page/', $this->editor)->assertOk()->getContent();

    // The mega parent: numbered columns with the child's own links under it.
    expect($body)->toContain('aria-haspopup="true"')
        ->and($body)->toContain('01')
        ->and($body)->toContain('Interior design')
        ->and($body)->toContain('Kitchen design')
        // The dropdown parent and its single child.
        ->and($body)->toContain('The studio')
        // The mobile drawer folds parents into disclosures.
        ->and($body)->toContain('<details');
});
