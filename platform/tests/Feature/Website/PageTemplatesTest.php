<?php

use App\Domain\Website\Enums\BlockType;
use App\Domain\Website\Enums\PageStatus;
use App\Domain\Website\Enums\PageType;
use App\Domain\Website\Models\Page;
use App\Domain\Website\Models\PageBlock;
use App\Domain\Website\Models\PageTemplate;
use App\Livewire\Admin\Website\PageEditor;
use App\Livewire\Admin\Website\PagesIndex;
use App\Models\User;
use Database\Seeders\PageTemplatesSeeder;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithPublicWebsite;

/*
| V2-3 F5 (docs/14 §5a): page templates. A template is a named, ordered section list for one page
| type; the studio starts a page from one, applies one to a page it is editing, or saves a page's
| own layout back as a template. The template decides the layout, never the page's photos.
*/

uses(InteractsWithPublicWebsite::class);

beforeEach(function () {
    $this->editor = User::factory()->create();
    $this->editor->givePermissionTo(['website.edit', 'website.publish']);
});

it('seeds the studio layouts, each with sections its types really have', function () {
    (new PageTemplatesSeeder)->run();

    $templates = PageTemplate::query()->get();

    expect($templates)->count()->toBeGreaterThanOrEqual(8);

    // Every template: a valid page type, a default flag, and sections whose data fits their type.
    foreach ($templates as $template) {
        expect($template->page_type)->toBeInstanceOf(PageType::class)
            ->and($template->sections)->not->toBeEmpty();

        foreach ($template->sections as $section) {
            $type = BlockType::tryFrom((string) $section['type']);
            expect($type)->not->toBeNull();

            foreach (array_keys((array) ($section['data'] ?? [])) as $key) {
                expect($type->fields())->toHaveKey($key);
            }
        }
    }

    // Exactly one default per page type that has one.
    $defaults = $templates->where('is_default', true)->groupBy(fn (PageTemplate $t) => $t->page_type->value);
    foreach ($defaults as $list) {
        expect(count($list))->toBe(1);
    }

    expect($templates->contains(fn (PageTemplate $t) => $t->name === 'Home — v26'))->toBeTrue();
});

it('starts a page from a template, sections and all', function () {
    (new PageTemplatesSeeder)->run();
    $template = PageTemplate::query()->where('name', 'Service page — short')->first();

    Livewire::actingAs($this->editor)
        ->test(PagesIndex::class)
        ->assertSee('page templates — start from one')
        ->call('createFromTemplate', $template->id)
        ->assertRedirect();

    $page = Page::query()->where('title', 'Service page — short')->first();

    expect($page)->not->toBeNull()
        ->and($page->status)->toBe(PageStatus::Draft)
        ->and($page->type)->toBe(PageType::Service)
        ->and($page->blocks)->toHaveCount(4)
        ->and($page->blocks->pluck('type')->all())->toBe([BlockType::Hero, BlockType::Text, BlockType::Faq, BlockType::Form])
        // Sections arrive clickable from the first save (V2-3 click-to-edit).
        ->and($page->blocks->every(fn (PageBlock $block) => $block->block_key !== null))->toBeTrue();

    // A second start from the same template gets its own URL, never a collision.
    Livewire::actingAs($this->editor)->test(PagesIndex::class)->call('createFromTemplate', $template->id);
    expect(Page::query()->where('slug', 'like', '/service-page-short%/')->count())->toBe(2);
});

it('applies a template to the page being edited, without saving it to the database', function () {
    (new PageTemplatesSeeder)->run();
    $template = PageTemplate::query()->where('name', 'City page')->first();

    $page = Page::factory()->withSlug('/apply-here/')->create(['title' => 'Apply here', 'type' => PageType::City->value]);
    PageBlock::factory()->for($page)->asType(BlockType::Text, ['heading' => 'Old section', 'body' => 'Old body'])->create(['position' => 0, 'block_key' => 'bold00001']);

    Livewire::actingAs($this->editor)
        ->test(PageEditor::class, ['page' => $page])
        ->call('applyTemplate', $template->id)
        ->assertSet('blocks.0.type', BlockType::Hero->value)
        ->assertSet('blocks.6.type', BlockType::Form->value)
        // The old section is gone from the editor's state…
        ->assertDontSee('Old section');

    expect($page->fresh(['blocks'])->blocks)->toHaveCount(1)
        ->and($page->fresh(['blocks'])->blocks->first()->data['heading'])->toBe('Old section');
});

it('saves a page layout back as a template for the next page of that kind', function () {
    $page = Page::factory()->withSlug('/our-layout/')->create(['title' => 'Our layout', 'type' => PageType::Service->value]);
    PageBlock::factory()->for($page)->asType(BlockType::Hero, ['heading' => 'Our hero'])->create(['position' => 0, 'block_key' => 'bourhero1']);
    PageBlock::factory()->for($page)->asType(BlockType::Form)->create(['position' => 1, 'block_key' => 'bourform1']);

    Livewire::actingAs($this->editor)
        ->test(PageEditor::class, ['page' => $page])
        ->set('templateName', 'Service page — our layout')
        ->call('saveAsTemplate')
        ->assertHasNoErrors();

    $template = PageTemplate::query()->where('name', 'Service page — our layout')->first();

    expect($template)->not->toBeNull()
        ->and($template->page_type)->toBe(PageType::Service)
        ->and($template->is_default)->toBeFalse()
        ->and($template->sections)->toHaveCount(2)
        ->and($template->sections[0]['type'])->toBe(BlockType::Hero->value)
        // The starter text travels with the layout; the editor's internal keys do not.
        ->and($template->sections[0]['data']['heading'])->toBe('Our hero')
        ->and(array_key_exists('key', $template->sections[0]))->toBeFalse();

    // An empty page is not a layout.
    $empty = Page::factory()->withSlug('/empty/')->create(['title' => 'Empty']);

    Livewire::actingAs($this->editor)
        ->test(PageEditor::class, ['page' => $empty])
        ->set('templateName', 'Nothing here')
        ->call('saveAsTemplate');

    expect(PageTemplate::query()->where('name', 'Nothing here')->exists())->toBeFalse();
});
