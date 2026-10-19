<?php

declare(strict_types=1);

use App\Domain\Website\Models\Page;
use App\Livewire\Admin\Website\PageEditor;
use App\Models\User;
use Livewire\Livewire;

/*
| Multi-select and the section clipboard (Phase 2, builder parity). Sections can be ticked, copied,
| pasted on another page, duplicated and deleted — with the same undo step as every other structural
| change. Pasting is a boundary: unknown types and leftover markup never become a section.
*/

beforeEach(function () {
    $this->editor = User::factory()->create();
    $this->editor->givePermissionTo(['website.edit', 'website.publish']);
});

/** @return array{0: Page, 1: list<string>} */
function clipboardPageWithSections(string $slug, int $count = 3): array
{
    $page = Page::factory()->create(['title' => 'Clipboard '.$slug, 'slug' => $slug]);
    $keys = [];

    for ($i = 0; $i < $count; $i++) {
        $block = $page->blocks()->create([
            'type' => 'text',
            'block_key' => 'sec'.$i.'_'.substr(md5($slug.$i), 0, 6),
            'data' => ['heading' => 'Section '.$i, 'body' => 'Body '.$i],
            'settings' => ['background' => 'beige'],
            'visible' => true,
            'position' => $i,
        ]);

        $keys[] = $block->block_key;
    }

    return [$page, $keys];
}

it('copies ticked sections and pastes them on another page with fresh keys', function () {
    [$source, $keys] = clipboardPageWithSections('/source-page/');
    [$target] = clipboardPageWithSections('/target-page/', 1);

    Livewire::actingAs($this->editor)
        ->test(PageEditor::class, ['page' => $source])
        ->call('toggleSelect', $keys[0])
        ->call('toggleSelect', $keys[2])
        ->call('copySelected');

    $component = Livewire::actingAs($this->editor)->test(PageEditor::class, ['page' => $target])
        ->call('pasteClipboard');

    $blocks = $component->get('blocks');

    expect($blocks)->toHaveCount(3)
        ->and($blocks[1]['data']['heading'])->toBe('Section 0')
        ->and($blocks[2]['data']['heading'])->toBe('Section 2')
        ->and($blocks[1]['settings']['background'])->toBe('beige')
        ->and($blocks[1]['key'])->not->toBe($keys[0])
        ->and($blocks[1]['id'])->toBeNull();
});

it('never pastes a type it does not know', function () {
    [$target] = clipboardPageWithSections('/paste-target/', 1);

    session(['website.section_clipboard' => [
        ['type' => 'rm -rf', 'data' => [], 'settings' => [], 'visible' => true],
        ['type' => 'text', 'data' => ['heading' => 'Safe'], 'settings' => [], 'visible' => true],
    ]]);

    $component = Livewire::actingAs($this->editor)->test(PageEditor::class, ['page' => $target])
        ->call('pasteClipboard');

    expect($component->get('blocks'))->toHaveCount(2)
        ->and($component->get('blocks')[1]['type'])->toBe('text')
        ->and($component->get('blocks')[1]['data']['heading'])->toBe('Safe');
});

it('deletes every ticked section as one undoable step', function () {
    [$page, $keys] = clipboardPageWithSections('/delete-page/');

    $component = Livewire::actingAs($this->editor)->test(PageEditor::class, ['page' => $page])
        ->call('toggleSelect', $keys[0])
        ->call('toggleSelect', $keys[1])
        ->call('deleteSelected');

    expect($component->get('blocks'))->toHaveCount(1)
        ->and($component->get('blocks')[0]['key'])->toBe($keys[2])
        ->and($component->get('selectedKeys'))->toBe([]);

    $component->call('undo');

    expect($component->get('blocks'))->toHaveCount(3);
});

it('duplicates the selection after the last section and selects the copies', function () {
    [$page, $keys] = clipboardPageWithSections('/dupe-page/', 2);

    $component = Livewire::actingAs($this->editor)->test(PageEditor::class, ['page' => $page])
        ->call('toggleSelect', $keys[1])
        ->call('duplicateSelected');

    $blocks = $component->get('blocks');

    expect($blocks)->toHaveCount(3)
        ->and($blocks[2]['data']['heading'])->toBe('Section 1')
        ->and($component->get('selectedKeys'))->toBe([$blocks[2]['key']]);
});

it('selects everything on the first press and clears on the second', function () {
    [$page, $keys] = clipboardPageWithSections('/select-all/', 2);

    Livewire::actingAs($this->editor)->test(PageEditor::class, ['page' => $page])
        ->call('toggleSelectAll')
        ->assertSet('selectedKeys', $keys)
        ->call('toggleSelectAll')
        ->assertSet('selectedKeys', []);
});
