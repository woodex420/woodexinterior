<?php

use App\Domain\Website\Enums\BlockType;
use App\Domain\Website\Models\Page;
use App\Domain\Website\Models\SavedSection;
use App\Domain\Website\Support\PreviewLink;
use App\Livewire\Admin\Website\PageEditor;
use App\Models\User;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithPublicWebsite;

/*
| V2-2 (docs/12 §5.2/§5.3): the section library and the page builder. Sections are named, described and
| categorised; a configured section can be saved and reused on any page; sections drag to reorder and
| duplicate; the page previews at three widths and a shareable signed link opens the draft on a phone
| without a login.
*/

uses(InteractsWithPublicWebsite::class);

beforeEach(function () {
    $this->editor = User::factory()->create();
    $this->editor->givePermissionTo(['website.edit', 'website.publish']);
});

it('declares every section with a name, a blurb and a library category', function () {
    foreach (BlockType::cases() as $type) {
        expect($type->label())->not->toBe('')
            ->and($type->description())->not->toBe('')
            ->and($type->category())->toBeIn(['Hero', 'Proof', 'Content', 'Conversion']);
    }

    // The library's shelf order: hero, proof, content, then the sections that ask for the enquiry.
    expect(BlockType::Hero->category())->toBe('Hero')
        ->and(BlockType::Gallery->category())->toBe('Proof')
        ->and(BlockType::Text->category())->toBe('Content')
        ->and(BlockType::Form->category())->toBe('Conversion');
});

it('shows the library grouped by category, and adding from it works', function () {
    $page = Page::factory()->withSlug('/builder/')->create(['title' => 'Builder']);

    Livewire::actingAs($this->editor)
        ->test(PageEditor::class, ['page' => $page])
        ->assertSee('Section library')
        ->assertSee('Proof')
        ->assertSee('Conversion')
        ->assertSee(BlockType::Gallery->description())
        ->call('addBlock', BlockType::Gallery->value)
        ->assertSet('blocks.0.type', BlockType::Gallery->value)
        ->assertSee('Gallery');
});

it('saves a configured section to the library and drops it onto any page', function () {
    $page = Page::factory()->withSlug('/builder/')->create(['title' => 'Builder']);

    Livewire::actingAs($this->editor)
        ->test(PageEditor::class, ['page' => $page])
        ->call('addBlock', BlockType::Hero->value)
        ->set('blocks.0.data.heading', 'Interior design that finishes')
        ->call('openSectionModal', 0)
        ->assertSet('showSectionModal', true)
        ->set('sectionName', 'Signature hero')
        ->call('storeSection')
        ->assertNotSet('showSectionModal', true);

    $saved = SavedSection::query()->first();
    expect($saved)->not->toBeNull()
        ->and($saved->name)->toBe('Signature hero')
        ->and($saved->type)->toBe(BlockType::Hero)
        ->and($saved->data['heading'])->toBe('Interior design that finishes')
        ->and($saved->created_by)->toBe($this->editor->id);

    // A different page gets the same section, content and all.
    $other = Page::factory()->withSlug('/other-page/')->create(['title' => 'Other']);

    Livewire::actingAs($this->editor)
        ->test(PageEditor::class, ['page' => $other])
        ->call('insertSavedSection', $saved->id)
        ->assertSet('blocks.0.type', BlockType::Hero->value)
        ->assertSet('blocks.0.data.heading', 'Interior design that finishes')
        ->assertSet('blocks.0.id', null) // a copy, not a link: saving this page never touches the library
        ->call('deleteSavedSection', $saved->id);

    expect(SavedSection::query()->count())->toBe(0);
});

it('duplicates a section and drags sections into a new order that survives a save', function () {
    $page = Page::factory()->withSlug('/builder/')->create(['title' => 'Builder']);
    foreach ([BlockType::Hero, BlockType::Text, BlockType::Gallery] as $type) {
        $page->blocks()->create([
            'type' => $type,
            'data' => $type->fields(),
            'visible' => true,
            'position' => $page->blocks()->count(),
        ]);
    }
    $page->refresh();

    $test = Livewire::actingAs($this->editor)->test(PageEditor::class, ['page' => $page]);

    // Duplicate the text section.
    $test->call('duplicateBlock', 1)
        ->assertCount('blocks', 4)
        ->assertSet('blocks.2.id', null);

    // Drag: the gallery goes first — wire:sort hands over the block keys in their new order.
    $keys = collect($test->get('blocks'))->pluck('key')->all(); // hero, text, copy, gallery
    $test->call('sortBlocks', [$keys[3], $keys[0], $keys[1], $keys[2]])
        ->assertSet('blocks.0.type', BlockType::Gallery->value)
        ->assertSet('blocks.3.type', BlockType::Text->value)
        ->set('title', 'Builder, reordered')
        ->call('save', false);

    $order = $page->fresh(['blocks'])->blocks->pluck('type')->map(fn ($type) => $type->value)->all();
    expect($order)->toBe([BlockType::Gallery->value, BlockType::Hero->value, BlockType::Text->value, BlockType::Text->value]);
});

it('previews the draft at three widths in the editor', function () {
    $page = Page::factory()->withSlug('/builder/')->create(['title' => 'Builder']);

    Livewire::actingAs($this->editor)
        ->test(PageEditor::class, ['page' => $page])
        ->assertDontSee('Draft preview')
        ->call('setPreviewDevice', 'mobile')
        ->assertSee('Draft preview (mobile)')
        ->call('setPreviewDevice', 'desktop')
        ->assertSee('Draft preview (desktop)')
        ->call('setPreviewDevice', 'nonsense')
        ->assertDontSee('Draft preview');
});

it('opens a draft through a shareable preview link, and only while it is valid', function () {
    $page = Page::factory()->withSlug('/secret-draft/')->create(['title' => 'Secret Draft']);
    $page->blocks()->create(['type' => BlockType::Text, 'data' => BlockType::Text->fields(), 'visible' => true, 'position' => 0]);

    $link = PreviewLink::make($page->fresh(), now()->addDay());

    // No login at all: the signed link alone shows the draft. (The HTTP test client trims the
    // trailing slash — the canonical URL is why siteGet asks the SiteRouter directly.)
    $this->siteGet(query_of($link))
        ->assertOk()
        ->assertSee('Secret Draft');

    // A tampered token shows nothing, and so does an expired one.
    $this->siteGet(Str::replaceLast(token_of($link), str_repeat('a', 64), $link))->assertNotFound();
    $this->siteGet(query_of(PreviewLink::make($page->fresh(), now()->subMinute())))->assertNotFound();

    // Without any preview flag the draft stays private.
    $this->siteGet('/secret-draft/')->assertNotFound();
});

/** Pull the token query parameter out of a preview URL. */
function token_of(string $url): string
{
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

    return (string) ($query['token'] ?? '');
}

/** The path plus query of an absolute preview URL, for siteGet(). */
function query_of(string $url): string
{
    $path = (string) parse_url($url, PHP_URL_PATH);
    $query = (string) parse_url($url, PHP_URL_QUERY);

    return $query === '' ? $path : $path.'?'.$query;
}

it('keeps the shareable link to a signed token: editors still preview with a session', function () {
    $page = Page::factory()->withSlug('/editor-draft/')->create(['title' => 'Editor Draft']);

    Livewire::actingAs($this->editor)
        ->test(PageEditor::class, ['page' => $page])
        ->call('makeShareLink')
        ->assertNotSet('shareUrl', '');

    // The editor's own session preview keeps working.
    $this->siteGet('/editor-draft/?preview=1', $this->editor)->assertOk()->assertSee('Editor Draft');

    // And the shareable link works for a stranger too — no session at all.
    auth()->logout();
    $this->siteGet(query_of(PreviewLink::make($page->fresh(), now()->addDay())))->assertOk();
});
