<?php

declare(strict_types=1);

use App\Domain\Website\Actions\SavePage;
use App\Domain\Website\Enums\BlockType;
use App\Domain\Website\Enums\PageType;
use App\Domain\Website\Models\Page;
use App\Livewire\Admin\Website\PageEditor;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;

/*
| Phase 1 (audit finding S-03): free HTML — the HTML block and the campaign canvas — is the only
| staff input that reaches a visitor as markup. Until the sanitiser has been exercised in production
| it is owner-only, enforced by the website.raw_html permission on the server, not just by hiding
| the button.
*/

/** @return array<string, mixed> */
function pageInput(array $overrides = []): array
{
    return array_merge([
        'type' => PageType::Service,
        'slug' => '/raw-html-test/',
        'title' => 'Raw HTML test',
        'excerpt' => '',
        'seo' => [],
        'schema_type' => null,
        'sort' => 0,
        'template' => null,
        'canvas_html' => '',
    ], $overrides);
}

it('hides the HTML block and the canvas from an editor without the raw HTML permission', function () {
    $editor = User::factory()->create();
    $editor->givePermissionTo(['website.edit', 'website.publish']);

    Livewire::actingAs($editor)
        ->test(PageEditor::class)
        ->assertDontSee('HTML block')
        ->assertDontSee('Switch to the campaign canvas');
});

it('refuses an HTML block over Livewire when the permission is missing', function () {
    $editor = User::factory()->create();
    $editor->givePermissionTo('website.edit');

    Livewire::actingAs($editor)
        ->test(PageEditor::class)
        ->call('addBlock', BlockType::Html->value)
        ->assertStatus(403);
});

it('refuses the campaign canvas over Livewire when the permission is missing', function () {
    $editor = User::factory()->create();
    $editor->givePermissionTo('website.edit');

    Livewire::actingAs($editor)
        ->test(PageEditor::class)
        ->call('toggleCanvas')
        ->assertStatus(403);
});

it('refuses to save a page containing an HTML block unless the saver has the permission', function () {
    $editor = User::factory()->create();
    $editor->givePermissionTo('website.edit');
    $page = Page::factory()->create();

    expect(fn () => (new SavePage)($page, pageInput(), [
        ['type' => BlockType::Html->value, 'data' => ['code' => '<p>Hello</p>']],
    ], false, $editor))->toThrow(AuthorizationException::class);
});

it('refuses to save a canvas page unless the saver has the permission', function () {
    $editor = User::factory()->create();
    $editor->givePermissionTo('website.edit');
    $page = Page::factory()->create();

    expect(fn () => (new SavePage)($page, pageInput([
        'template' => 'canvas',
        'canvas_html' => '<section><h1>Hand placed</h1></section>',
    ]), [], false, $editor))->toThrow(AuthorizationException::class);
});

it('lets the owner save an HTML block and stores it cleaned', function () {
    $owner = $this->userWithRole('owner');
    $page = Page::factory()->create();

    $saved = (new SavePage)($page, pageInput(), [
        ['type' => BlockType::Html->value, 'data' => ['code' => '<p onclick="x()">Hello</p><script>bad()</script>']],
    ], false, $owner);

    $block = $saved->blocks->firstWhere('type', BlockType::Html);

    expect($block)->not->toBeNull()
        ->and((string) $block->data['code'])->toBe('<p>Hello</p>');
});
