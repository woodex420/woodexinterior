<?php

use App\Domain\Website\Actions\SavePage;
use App\Domain\Website\Enums\BlockType;
use App\Domain\Website\Enums\PageType;
use App\Domain\Website\Models\Page;
use App\Domain\Website\Models\PageBlock;
use App\Domain\Website\Services\PageCache;
use App\Livewire\Admin\Website\PageEditor;
use App\Models\User;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithPublicWebsite;

/*
| V2-3 click-to-edit (docs/14): every section carries a stable key; a staff editor previewing the draft
| gets one small script that reports the section they click, and the page editor jumps to that section's
| form. Visitors and share-link previews never see the tooling.
*/

uses(InteractsWithPublicWebsite::class);

beforeEach(function () {
    $this->editor = User::factory()->create();
    $this->editor->givePermissionTo('website.edit');

    $this->page = Page::factory()->published()->asType(PageType::Service)->withSlug('/click-to-edit/')->create([
        'title' => 'Click to edit',
    ]);

    PageBlock::factory()->for($this->page)->asType(BlockType::Hero, [
        'heading' => 'The section the owner will click',
    ])->create(['position' => 0, 'block_key' => 'bhero0001']);

    PageCache::invalidate($this->page);
});

it('marks every section with its stable key on the public page', function () {
    $this->siteGet('/click-to-edit/')
        ->assertOk()
        ->assertSee('data-wx-key="bhero0001"', false)
        ->assertDontSee('wx-edit-section', false);
});

it('gives a previewing editor the click-to-edit layer, and nobody else', function () {
    // A staff editor with ?preview=1 gets the bridge to the page editor.
    $this->siteGet('/click-to-edit/?preview=1', $this->editor)
        ->assertOk()
        ->assertSee('data-wx-editor-tools', false)
        ->assertSee('wx-edit-section', false)
        ->assertSee('data-wx-key="bhero0001"', false);

    // The same editor without ?preview=1 reads the page like everybody else.
    $this->siteGet('/click-to-edit/', $this->editor)
        ->assertOk()
        ->assertDontSee('data-wx-editor-tools', false)
        ->assertDontSee('wx-edit-section', false);

    // And the tooling is never baked into the page a visitor downloads.
    $this->siteGet('/click-to-edit/')
        ->assertOk()
        ->assertDontSee('data-wx-editor-tools', false);
});

it('jumps from a clicked section to its form, and ignores keys it does not know', function () {
    Livewire::actingAs($this->editor)
        ->test(PageEditor::class, ['page' => $this->page])
        ->assertSet('blocks.0.key', 'bhero0001')
        ->call('editSectionFromPreview', 'bhero0001')
        ->assertSet('activeBlock', 'bhero0001')
        ->assertDispatched('scroll-to-block', key: 'bhero0001')
        ->assertSee('id="blk-bhero0001"', false)
        // A section deleted since the draft was saved is ignored, not guessed at.
        ->call('editSectionFromPreview', 'bnope999')
        ->assertSet('activeBlock', 'bhero0001');
});

it('persists section keys across saves, and mints one for a block that arrives without', function () {
    $input = [
        'type' => PageType::Service,
        'slug' => '/click-to-edit/',
        'title' => 'Click to edit',
        'excerpt' => null,
        'seo' => [],
        'schema_type' => null,
        'sort' => 0,
    ];

    (new SavePage)($this->page, $input, [
        ['id' => $this->page->blocks->first()->id, 'key' => 'bkeep0001', 'type' => BlockType::Hero->value, 'visible' => true, 'data' => ['heading' => 'Kept key']],
        ['id' => null, 'type' => BlockType::Text->value, 'visible' => true, 'data' => ['heading' => 'Minted key', 'body' => 'Text']],
    ], false, $this->editor);

    $keys = $this->page->fresh(['blocks'])->blocks->pluck('block_key')->all();

    expect($keys[0])->toBe('bkeep0001')
        ->and($keys[1])->toStartWith('b')
        ->toHaveLength(9);
});
