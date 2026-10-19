<?php

declare(strict_types=1);

use App\Domain\Website\Enums\PageStatus;
use App\Domain\Website\Enums\PageType;
use App\Domain\Website\Models\Page;
use App\Domain\Website\Models\PageTemplate;
use App\Livewire\Admin\Website\PageTemplatesIndex;
use Livewire\Livewire;

/*
| Page templates as a screen (V2-3 F5): a layout is copied onto a page at creation and never linked
| afterwards, so renaming or deleting a template cannot change a page that already exists.
*/

function layout(array $attributes = []): PageTemplate
{
    return PageTemplate::query()->create(array_merge([
        'name' => 'Kitchen layout',
        'page_type' => PageType::Service,
        'description' => 'Hero, intro and gallery',
        'sections' => [
            ['type' => 'hero', 'data' => ['heading' => 'Kitchen']],
            ['type' => 'text', 'data' => ['body' => 'Words for this page']],
        ],
    ], $attributes));
}

beforeEach(function () {
    $this->owner = $this->userWithRole('owner');
    $this->actingAs($this->owner);
});

it('lists the layouts and their sections', function () {
    layout();
    layout(['name' => 'About layout', 'page_type' => PageType::About, 'sections' => [['type' => 'text', 'data' => []]]]);

    Livewire::test(PageTemplatesIndex::class)
        ->assertSee('Kitchen layout')
        ->assertSee('About layout')
        ->assertSee('Hero')
        ->assertSee('About')
        ->assertSee('Page templates');
});

it('creates a draft page from a layout with fresh block keys', function () {
    $template = layout();

    Livewire::test(PageTemplatesIndex::class)
        ->call('createPage', $template->id)
        ->assertRedirect(route('admin.website.pages.edit', Page::query()->latest('id')->first()));

    $page = Page::query()->where('title', 'Kitchen layout')->firstOrFail();

    expect($page->status)->toBe(PageStatus::Draft)
        ->and($page->slug)->toBe('/kitchen-layout/')
        ->and($page->blocks()->count())->toBe(2)
        ->and($page->blocks()->orderBy('position')->first()->data['heading'])->toBe('Kitchen');
});

it('adds a suffix instead of reusing a URL', function () {
    $template = layout();
    Page::query()->create(['type' => PageType::Service, 'slug' => '/kitchen-layout/', 'title' => 'Taken', 'status' => PageStatus::Draft]);

    Livewire::test(PageTemplatesIndex::class)->call('createPage', $template->id);

    expect(Page::query()->where('title', 'Kitchen layout')->value('slug'))->toBe('/kitchen-layout-2/');
});

it('renames a layout and keeps one default per page type', function () {
    $first = layout(['is_default' => true]);
    $second = layout(['name' => 'Second kitchen', 'sections' => [['type' => 'text', 'data' => []]]]);

    Livewire::test(PageTemplatesIndex::class)
        ->call('edit', $second->id)
        ->assertSet('editName', 'Second kitchen')
        ->set('editName', 'Second kitchen, revised')
        ->set('editDescription', 'Now the default')
        ->set('editDefault', true)
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('showEdit', false);

    expect($second->refresh()->name)->toBe('Second kitchen, revised')
        ->and($second->is_default)->toBeTrue()
        ->and($first->refresh()->is_default)->toBeFalse();
});

it('duplicates a layout without stealing the default', function () {
    $template = layout(['is_default' => true]);

    Livewire::test(PageTemplatesIndex::class)->call('duplicate', $template->id);

    $copy = PageTemplate::query()->where('name', 'Kitchen layout (copy)')->firstOrFail();

    expect($copy->is_default)->toBeFalse()
        ->and($copy->sections)->toHaveCount(2)
        ->and($template->refresh()->is_default)->toBeTrue();
});

it('refuses to delete the default layout but deletes the rest', function () {
    $default = layout(['is_default' => true]);
    $other = layout(['name' => 'Retired layout', 'sections' => []]);

    Livewire::test(PageTemplatesIndex::class)
        ->call('delete', $default->id)
        ->assertDispatched('toast');

    expect(PageTemplate::query()->whereKey($default->id)->exists())->toBeTrue();

    Livewire::test(PageTemplatesIndex::class)->call('delete', $other->id);

    expect(PageTemplate::query()->whereKey($other->id)->exists())->toBeFalse();
});
