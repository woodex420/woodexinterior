<?php

declare(strict_types=1);

use App\Domain\Website\Enums\PageStatus;
use App\Domain\Website\Models\Page;
use App\Domain\Website\Models\PageVersion;
use App\Domain\Website\Services\SiteRouter;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;

/*
| The Visual Page Builder (v2.1 front-end, Woodex OS back-end). Slice 1: an HTML page opens,
| saves with a conflict check and a version snapshot, restores, and is served as a whole document.
| The script guard keeps the old rule: editors may edit, only the owner may add code.
*/

beforeEach(function () {
    $this->editor = User::factory()->create();
    $this->editor->givePermissionTo(['website.edit', 'website.publish']);

    $this->owner = User::factory()->create();
    $this->owner->givePermissionTo(['website.edit', 'website.publish', 'website.raw_html']);

    $this->document = '<!DOCTYPE html><html lang="en"><head><title>Built page</title></head><body><main><h1>Hello</h1></main></body></html>';

    $this->page = Page::factory()->create([
        'title' => 'Built page',
        'slug' => '/built/',
        'template' => 'html',
        'canvas_html' => $this->document,
        'status' => PageStatus::Published,
        'published_at' => now()->subDay(),
    ]);
});

function builder(User $user, array $payload): TestResponse
{
    return test()->actingAs($user)->postJson(route('admin.website.builder.api'), $payload);
}

it('sends a block page to its section editor instead of opening the builder', function () {
    $block = Page::factory()->create(['slug' => '/blocks-only/', 'title' => 'Blocks only']);

    $this->actingAs($this->editor)
        ->get(route('admin.website.builder', $block))
        ->assertRedirect(route('admin.website.pages.edit', $block));
});

it('requires the website.edit permission', function () {
    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->get(route('admin.website.builder', $this->page))
        ->assertForbidden();
});

it('opens the builder screen for an editor', function () {
    $this->actingAs($this->editor)
        ->get(route('admin.website.builder', $this->page))
        ->assertOk()
        ->assertSee('WX_BUILDER_BOOT', false)
        ->assertSee('/builder/builder.js', false);
});

it('lists and loads HTML pages only', function () {
    $block = Page::factory()->create(['slug' => '/blocks/', 'title' => 'Blocks']);

    builder($this->editor, ['action' => 'pages'])
        ->assertOk()
        ->assertJsonPath('pages.0.path', 'built/index.html');

    builder($this->editor, ['action' => 'load', 'path' => 'built/index.html'])
        ->assertOk()
        ->assertJsonPath('html', $this->document);

    builder($this->editor, ['action' => 'load', 'path' => 'blocks/index.html'])
        ->assertOk()
        ->assertJsonPath('ok', false);

    expect($block->fresh()->template)->toBeNull();
});

it('saves a document, publishes it and keeps the previous version', function () {
    $response = builder($this->editor, [
        'action' => 'save',
        'path' => 'built/index.html',
        'html' => str_replace('Hello', 'Hello again', $this->document),
        'mtime' => $this->page->updated_at->getTimestamp(),
    ])->assertOk();

    expect($response->json('mtime'))->toBeInt()
        ->and($this->page->fresh()->canvas_html)->toContain('Hello again')
        ->and($this->page->fresh()->status->value)->toBe('published');

    $version = PageVersion::query()->where('page_id', $this->page->id)->first();

    expect($version)->not->toBeNull()
        ->and($version->data['canvas_html'])->toBe($this->document);
});

it('refuses a stale save with 409 and writes nothing', function () {
    builder($this->editor, [
        'action' => 'save',
        'path' => 'built/index.html',
        'html' => str_replace('Hello', 'Mine', $this->document),
        'mtime' => $this->page->updated_at->getTimestamp() - 60,
    ])->assertStatus(409);

    expect($this->page->fresh()->canvas_html)->toBe($this->document);
});

it('forces a save when the editor confirms the overwrite', function () {
    builder($this->editor, [
        'action' => 'save',
        'path' => 'built/index.html',
        'html' => str_replace('Hello', 'Mine', $this->document),
        'mtime' => $this->page->updated_at->getTimestamp() - 60,
        'force' => true,
    ])->assertOk();

    expect($this->page->fresh()->canvas_html)->toContain('Mine');
});

it('rejects editor markup left in the document', function () {
    builder($this->editor, [
        'action' => 'save',
        'path' => 'built/index.html',
        'html' => '<html><body><h1 contenteditable="true">oops</h1></body></html>',
    ])->assertStatus(422);

    expect($this->page->fresh()->canvas_html)->toBe($this->document);
});

it('lets an editor keep existing code but not add new code', function () {
    $this->page->forceFill([
        'canvas_html' => '<html><head><script src="/assets/site.js"></script></head><body>x</body></html>',
    ])->save();

    // Same script, different text: allowed.
    builder($this->editor, [
        'action' => 'save',
        'path' => 'built/index.html',
        'html' => '<html><head><script src="/assets/site.js"></script></head><body>edited</body></html>',
        'force' => true,
    ])->assertOk();

    // A new script: refused for an editor, allowed for the owner.
    builder($this->editor, [
        'action' => 'save',
        'path' => 'built/index.html',
        'html' => '<html><head><script>alert(1)</script></head><body>x</body></html>',
        'force' => true,
    ])->assertStatus(422);

    builder($this->owner, [
        'action' => 'save',
        'path' => 'built/index.html',
        'html' => '<html><head><script>alert(1)</script></head><body>x</body></html>',
        'force' => true,
    ])->assertOk();

    expect($this->page->fresh()->canvas_html)->toContain('alert(1)');
});

it('lists backups and restores one', function () {
    builder($this->editor, [
        'action' => 'save',
        'path' => 'built/index.html',
        'html' => str_replace('Hello', 'Second', $this->document),
        'force' => true,
    ])->assertOk();

    $backups = builder($this->editor, ['action' => 'backups', 'path' => 'built/index.html'])->json('backups');

    expect($backups)->toHaveCount(1);

    builder($this->editor, [
        'action' => 'restore',
        'path' => 'built/index.html',
        'file' => $backups[0]['file'],
    ])->assertOk();

    expect($this->page->fresh()->canvas_html)->toBe($this->document);
});

it('creates a new HTML page from the builder', function () {
    builder($this->editor, [
        'action' => 'page_new',
        'folder' => '',
        'slug' => 'new-page',
        'html' => '<!DOCTYPE html><html><head><title>New page</title></head><body><p>New</p></body></html>',
    ])->assertOk()->assertJsonPath('path', 'new-page/index.html');

    $created = Page::query()->where('slug', '/new-page/')->first();

    expect($created)->not->toBeNull()
        ->and($created->template)->toBe('html')
        ->and($created->title)->toBe('New page');
});

it('serves an HTML page as the whole document', function () {
    // Request::create keeps the trailing slash; the HTTP test client trims it and would only
    // exercise the canonical redirect.
    $request = Request::create('http://localhost/built/', 'GET');

    $response = app(SiteRouter::class)->respond($request);

    expect($response)->not->toBeNull()
        ->and($response->getStatusCode())->toBe(200)
        ->and($response->headers->get('Content-Type'))->toContain('text/html')
        ->and($response->getContent())->toBe($this->document);
});

it('sends an HTML page to the builder instead of the block editor', function () {
    $this->actingAs($this->editor)
        ->get(route('admin.website.pages.edit', $this->page))
        ->assertRedirect(route('admin.website.builder', $this->page));
});
