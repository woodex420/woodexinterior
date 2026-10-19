<?php

declare(strict_types=1);

use App\Domain\Website\Models\Page;
use App\Livewire\Admin\Website\PageEditor;
use App\Models\User;
use Livewire\Livewire;

/*
| Two tabs, one page (Phase 2, audit finding R-01). The editor remembers the page's revision and
| refuses to write over a newer one; nothing typed is thrown away — the editor reloads on request.
| Also: the editor says when the exported v1 file, not the CMS, answers the URL (W-01).
*/

beforeEach(function () {
    $this->editor = User::factory()->create();
    $this->editor->givePermissionTo(['website.edit', 'website.publish']);
});

it('refuses a save when the page was saved after the editor opened it', function () {
    $page = Page::factory()->create(['title' => 'Shared', 'slug' => '/shared/']);

    $component = Livewire::actingAs($this->editor)->test(PageEditor::class, ['page' => $page]);

    // Somebody else saves the page in another tab.
    $page->forceFill(['title' => 'Theirs'])->save();

    $component->set('title', 'Mine now')->call('save', false);

    expect($component->get('conflict'))->toBeTrue()
        ->and($page->fresh()->title)->toBe('Theirs');
});

it('keeps the edits on screen when a conflict is reported, then reloads on request', function () {
    $page = Page::factory()->create(['title' => 'Shared', 'slug' => '/shared/']);
    $page->blocks()->create([
        'type' => 'text',
        'data' => ['heading' => 'From the other tab', 'body' => 'Saved first.'],
        'visible' => true,
        'position' => 0,
    ]);

    $component = Livewire::actingAs($this->editor)->test(PageEditor::class, ['page' => $page]);
    $page->forceFill(['title' => 'Their new title'])->save();

    $component->set('excerpt', 'My unsaved sentence')->call('save', false);

    expect($component->get('conflict'))->toBeTrue()
        ->and($component->get('excerpt'))->toBe('My unsaved sentence');

    $component->call('reloadLatest');

    expect($component->get('conflict'))->toBeFalse()
        ->and($component->get('excerpt'))->not->toBe('My unsaved sentence');
});

it('saves normally when nobody else touched the page', function () {
    $page = Page::factory()->create(['title' => 'Mine', 'slug' => '/mine/']);

    Livewire::actingAs($this->editor)
        ->test(PageEditor::class, ['page' => $page])
        ->set('title', 'Mine, edited')
        ->call('save', false)
        ->assertHasNoErrors();

    expect($page->fresh()->title)->toBe('Mine, edited');
});

it('warns in the editor when an exported v1 file answers the URL', function () {
    $page = Page::factory()->create(['title' => 'Services', 'slug' => '/services/']);

    $dir = public_path('services');
    $created = ! is_dir($dir);
    is_dir($dir) || mkdir($dir, 0777, true);
    file_put_contents($dir.'/index.html', '<html>v1</html>');

    try {
        Livewire::actingAs($this->editor)
            ->test(PageEditor::class, ['page' => $page])
            ->assertSee('answered by the exported v1 page')
            ->assertSee('services/index.html');
    } finally {
        unlink($dir.'/index.html');

        if ($created) {
            rmdir($dir);
        }
    }
});
