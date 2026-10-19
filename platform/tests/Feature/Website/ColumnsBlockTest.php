<?php

declare(strict_types=1);

use App\Domain\Website\Enums\BlockType;
use App\Domain\Website\Enums\PageType;
use App\Domain\Website\Models\Page;
use App\Domain\Website\Support\Columns;
use App\Livewire\Admin\Website\PageEditor;
use App\Models\User;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithPublicWebsite;

/*
| Columns (Phase 2, layout primitive): 2–4 leaf children in one section — text, photo or quote.
| They live in the section's JSON, so versions, undo, copy/paste and the fingerprint need no schema
| change. No nesting, no raw HTML; every value is escaped or stripped at the boundary.
*/

uses(InteractsWithPublicWebsite::class);

beforeEach(function () {
    $this->editor = User::factory()->create();
    $this->editor->givePermissionTo(['website.edit', 'website.publish']);
});

it('renders two to four columns side by side, stacking on a phone', function () {
    $page = Page::factory()->published()->asType(PageType::Service)->withSlug('/columns/')->create([
        'title' => 'Columns page',
        'excerpt' => 'A page laid out in columns.',
    ]);

    $page->blocks()->create([
        'type' => BlockType::Columns,
        'block_key' => 'cols1',
        'data' => ['blocks' => [
            ['kind' => 'text', 'heading' => 'One', 'body' => "First body.\nSecond line."],
            ['kind' => 'quote', 'quote' => 'A quiet room.', 'cite' => 'A client'],
            ['kind' => 'text', 'heading' => 'Three', 'body' => 'Third body.'],
        ]],
        'visible' => true,
        'position' => 0,
    ]);

    $body = $this->siteGet('/columns/')->assertOk()->getContent();

    expect($body)
        ->toContain('md:grid-cols-3')
        ->toContain('<h2 class="text-[clamp(20px,2.4vw,28px)]">One</h2>')
        ->toContain('A quiet room.')
        ->toContain('<cite')
        ->toContain('A client')
        ->toContain('Third body.');
});

it('cleans children: clamps to 2–4, drops unknown kinds, strips markup and keeps only used fields', function () {
    $clean = Columns::clean(['blocks' => [
        ['kind' => 'text', 'heading' => '<b>Keep</b>', 'body' => '<script>alert(1)</script>Words', 'image_id' => 9],
        ['kind' => 'rm -rf', 'heading' => 'Falls back to text'],
        ['kind' => 'image', 'image_id' => '12', 'caption' => '<i>Cap</i>'],
        ['kind' => 'quote', 'quote' => 'Q', 'cite' => 'C'],
        ['kind' => 'text', 'heading' => 'Fifth is dropped'],
    ]]);

    expect($clean['blocks'])->toHaveCount(4)
        ->and($clean['blocks'][0])->toBe(['kind' => 'text', 'heading' => 'Keep', 'body' => 'alert(1)Words'])
        ->and($clean['blocks'][1]['kind'])->toBe('text')
        ->and($clean['blocks'][2])->toBe(['kind' => 'image', 'image_id' => 12, 'caption' => 'Cap'])
        ->and($clean['blocks'][3])->toBe(['kind' => 'quote', 'quote' => 'Q', 'cite' => 'C']);

    expect(Columns::clean(['blocks' => []])['blocks'])->toHaveCount(2);
});

it('strips markup that was already in the database before it reaches a visitor', function () {
    $page = Page::factory()->published()->asType(PageType::Service)->withSlug('/stored/')->create([
        'title' => 'Stored columns',
        'excerpt' => 'Old JSON still renders safely.',
    ]);

    $page->blocks()->create([
        'type' => BlockType::Columns,
        'block_key' => 'stored1',
        'data' => ['blocks' => [
            ['kind' => 'text', 'heading' => '<img src=x onerror=alert(1)>', 'body' => '<script>bad()</script>'],
            ['kind' => 'text', 'heading' => 'Safe', 'body' => 'Text'],
        ]],
        'visible' => true,
        'position' => 0,
    ]);

    $body = $this->siteGet('/stored/')->assertOk()->getContent();

    expect($body)
        ->not->toContain('onerror=')
        ->not->toContain('<script>bad()')
        ->not->toContain('<img src=x')
        ->toContain('bad()')
        ->toContain('Safe');
});

it('adds a columns section with two children and switches to three, reversibly', function () {
    $page = Page::factory()->create(['title' => 'Builder columns', 'slug' => '/builder-columns/']);

    $component = Livewire::actingAs($this->editor)->test(PageEditor::class, ['page' => $page])
        ->call('addBlock', 'columns');

    $block = $component->get('blocks.0');

    expect($block['type'])->toBe('columns')
        ->and($block['data']['blocks'])->toHaveCount(2);

    $component->call('setColumnCount', 0, 3);

    expect($component->get('blocks.0.data.blocks'))->toHaveCount(3)
        ->and($component->get('blocks.0.data.blocks.2.kind'))->toBe('text');

    $component->call('undo');

    expect($component->get('blocks.0.data.blocks'))->toHaveCount(2);
});

it('ignores a column count on a section that is not columns', function () {
    $page = Page::factory()->create(['title' => 'Plain', 'slug' => '/plain-columns/']);

    $component = Livewire::actingAs($this->editor)->test(PageEditor::class, ['page' => $page])
        ->call('addBlock', 'text')
        ->call('setColumnCount', 0, 4);

    expect($component->get('blocks.0.type'))->toBe('text')
        ->and($component->get('blocks.0.data'))->not->toHaveKey('blocks');
});
