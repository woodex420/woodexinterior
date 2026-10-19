<?php

declare(strict_types=1);

use App\Domain\Website\Enums\BlockType;
use App\Domain\Website\Models\Page;
use App\Domain\Website\Services\PageAudit;
use App\Livewire\Admin\Website\PageEditor;
use App\Models\User;
use Livewire\Livewire;

/*
| 19 Oct, builder parity with admin v2.1 pro: undo / redo for structural edits, a Layers panel that
| lists the page without its forms, and the audit that tells an editor what to fix before publishing.
*/

beforeEach(function () {
    $this->editor = User::factory()->create();
    $this->editor->givePermissionTo(['website.edit', 'website.publish']);
});

/** A page with two text sections — enough for the publish gate and for a reorder. */
function editor_page(): Page
{
    $page = Page::factory()->withSlug('/undo-page/')->create(['title' => 'Undo page']);

    foreach (['First section' => 0, 'Second section' => 1] as $heading => $position) {
        $page->blocks()->create([
            'type' => BlockType::Text,
            'block_key' => 'b'.$position.'key',
            'data' => ['heading' => $heading, 'body' => 'Text of '.$heading.'.'],
            'visible' => true,
            'position' => $position,
        ]);
    }

    return $page;
}

it('takes back an added section with undo, and puts it back with redo', function () {
    $page = editor_page();

    $test = Livewire::actingAs($this->editor)
        ->test(PageEditor::class, ['page' => $page])
        ->call('addBlock', BlockType::Faq->value)
        ->assertCount('blocks', 3)
        ->assertViewHas('canUndo', true)
        ->call('undo')
        ->assertCount('blocks', 2)
        ->call('redo')
        ->assertCount('blocks', 3);

    expect($test->get('undoStack'))->toHaveCount(1);
});

it('takes back a deletion and keeps the section’s own key', function () {
    $page = editor_page();
    $test = Livewire::actingAs($this->editor)->test(PageEditor::class, ['page' => $page]);

    $key = $test->get('blocks')[0]['key'];

    $test->call('removeBlock', 0)
        ->assertCount('blocks', 1)
        ->call('undo')
        ->assertCount('blocks', 2)
        ->assertSet('blocks.0.key', $key)
        ->assertSet('activeBlock', '');
});

it('takes back a reorder', function () {
    $page = editor_page();

    Livewire::actingAs($this->editor)
        ->test(PageEditor::class, ['page' => $page])
        ->call('moveBlock', 0, 1)
        ->assertSet('blocks.0.data.heading', 'Second section')
        ->call('undo')
        ->assertSet('blocks.0.data.heading', 'First section')
        ->call('redo')
        ->assertSet('blocks.0.data.heading', 'Second section');
});

it('takes back a style choice', function () {
    $page = editor_page();

    Livewire::actingAs($this->editor)
        ->test(PageEditor::class, ['page' => $page])
        ->set('blocks.0.settings.background', 'navy')
        ->assertSet('blocks.0.settings.background', 'navy')
        ->call('undo')
        ->assertSet('blocks.0.settings.background', '');
});

it('drops the redo history as soon as something new happens', function () {
    $page = editor_page();

    Livewire::actingAs($this->editor)
        ->test(PageEditor::class, ['page' => $page])
        ->call('addBlock', BlockType::Faq->value)
        ->call('undo')
        ->assertViewHas('canRedo', true)
        ->call('addBlock', BlockType::Map->value)
        ->assertViewHas('canRedo', false)
        ->call('redo')
        ->assertCount('blocks', 3); // nothing to redo: the new section is still the only extra
});

it('stops at the start of the history instead of failing', function () {
    $page = editor_page();

    Livewire::actingAs($this->editor)
        ->test(PageEditor::class, ['page' => $page])
        ->call('undo')
        ->assertCount('blocks', 2)
        ->call('redo')
        ->assertCount('blocks', 2);
});

it('lists the page in layers and filters the list, not the page', function () {
    $page = editor_page();
    $page->blocks()->create([
        'type' => BlockType::Faq,
        'block_key' => 'bfaqkey',
        'data' => ['heading' => 'Common questions', 'items' => []],
        'settings' => ['label' => 'Questions block'],
        'visible' => true,
        'position' => 2,
    ]);

    Livewire::actingAs($this->editor)
        ->test(PageEditor::class, ['page' => $page])
        ->assertSee('Layers')
        ->assertSee('Questions block')
        ->set('layerQuery', 'question')
        ->assertSee('Questions block')
        ->assertDontSee('First section')
        ->assertCount('blocks', 3); // the page still has all three sections
});

it('jumps to a section from its layer row', function () {
    $page = editor_page();

    Livewire::actingAs($this->editor)
        ->test(PageEditor::class, ['page' => $page])
        ->call('focusBlock', 1)
        ->assertSet('activeBlock', 'b1key')
        ->assertDispatched('scroll-to-block', key: 'b1key')
        ->call('focusBlock', 99)
        ->assertSet('activeBlock', 'b1key'); // an index that is not there changes nothing
});

it('passes the audit to the editor and shows what it found', function () {
    $page = editor_page(); // no SEO title, no description, no keyword, one image short of nothing

    Livewire::actingAs($this->editor)
        ->test(PageEditor::class, ['page' => $page])
        ->assertSee('Page check')
        ->assertSee('No SEO title')
        ->assertSee('No meta description');
});

/* -------------------------------------------------------------------------------------------------
| The audit itself
* ------------------------------------------------------------------------------------------------ */

function clean_page_blocks(): array
{
    return [
        ['id' => null, 'key' => 'bhero', 'type' => 'hero', 'visible' => true, 'data' => ['eyebrow' => 'Design & build', 'heading' => 'Interior design in Lahore', 'text' => 'One team.', 'image_id' => 1, 'primary_label' => 'Start', 'primary_url' => '/contact/', 'secondary_label' => '', 'secondary_url' => '', 'facts' => []]],
        ['id' => null, 'key' => 'btext', 'type' => 'text', 'visible' => true, 'data' => ['heading' => 'How we work', 'body' => 'We measure, draw and build.']],
    ];
}

it('says a page is clean when it is', function () {
    expect(PageAudit::findings(
        'Interior design in Lahore',
        'Interior design in Lahore | Woodex Interior',
        'Interior design, fit-out and furniture in Lahore, from one studio team that measures, draws and builds — with the same people from the first sketch to handover.',
        'interior design lahore',
        clean_page_blocks(),
        [1 => 'A living room in Model Town'],
    ))->toBe([]);
});

it('flags an SEO title and description that are missing or the wrong length', function () {
    $findings = PageAudit::findings('Page', '', str_repeat('a', 40), '', clean_page_blocks(), [1 => 'Alt']);

    $messages = implode(' | ', array_column($findings, 'message'));

    expect($messages)
        ->toContain('No SEO title')
        ->toContain('120 to 165')
        ->toContain('No focus keyword');
});

it('flags a focus keyword that never appears', function () {
    $findings = PageAudit::findings(
        'Interior design in Lahore',
        'Interior design in Lahore | Woodex Interior',
        str_repeat('Design, drawings and site supervision for homes. ', 4),
        'kitchen renovation',
        clean_page_blocks(),
        [1 => 'Alt'],
    );

    expect(implode(' ', array_column($findings, 'message')))->toContain('kitchen renovation');
});

it('flags two H1s and an image without alt text in an HTML block', function () {
    $blocks = clean_page_blocks();
    $blocks[] = ['id' => null, 'key' => 'bhtml', 'type' => 'html', 'visible' => true, 'data' => ['code' => '<h1>Another opening</h1><img src="/x.jpg">']];

    $messages = implode(' | ', array_column(PageAudit::findings(
        'Page', 'Interior design in Lahore | Woodex Interior', str_repeat('A description of the right length. ', 4), 'interior design', $blocks, [1 => 'Alt'],
    ), 'message'));

    expect($messages)
        ->toContain('2 H1 headings')
        ->toContain('no alt text');
});

it('flags an image in a section that has no alt text in the media library', function () {
    $messages = implode(' | ', array_column(PageAudit::findings(
        'Page', 'Interior design in Lahore | Woodex Interior', str_repeat('A description of the right length. ', 4), 'interior design', clean_page_blocks(), [1 => ''],
    ), 'message'));

    expect($messages)->toContain('has no alt text');
});

it('flags a button that leads nowhere and one with no label', function () {
    $blocks = clean_page_blocks();
    $blocks[0]['data']['secondary_label'] = 'Our work';
    $blocks[0]['data']['secondary_url'] = '';
    $blocks[0]['data']['primary_label'] = '';

    $messages = implode(' | ', array_column(PageAudit::findings(
        'Page', 'Interior design in Lahore | Woodex Interior', str_repeat('A description of the right length. ', 4), 'interior design', $blocks, [1 => 'Alt'],
    ), 'message'));

    expect($messages)
        ->toContain('has nowhere to go')
        ->toContain('has an address but no label');
});

it('flags a page with no opening section', function () {
    $messages = implode(' | ', array_column(PageAudit::findings(
        'Page', 'Interior design in Lahore | Woodex Interior', str_repeat('A description of the right length. ', 4), 'interior design',
        [['id' => null, 'key' => 'bform', 'type' => 'form', 'visible' => true, 'data' => ['heading' => 'Start', 'text' => '', 'show_contact' => true]]],
        [],
    ), 'message'));

    expect($messages)->toContain('No opening section');
});

it('counts a slider as the page’s one H1', function () {
    $blocks = [
        ['id' => null, 'key' => 'bslider', 'type' => 'slider', 'visible' => true, 'data' => ['slides' => [
            ['image_id' => 1, 'kicker' => 'Woodex', 'heading' => 'Spaces shaped by purpose', 'text' => '', 'display' => '', 'primary_label' => 'Start', 'primary_url' => '/contact/', 'secondary_label' => '', 'secondary_url' => ''],
        ]]],
        ['id' => null, 'key' => 'btext', 'type' => 'text', 'visible' => true, 'data' => ['heading' => 'How we work', 'body' => 'Text.']],
    ];

    expect(PageAudit::h1Count($blocks))->toBe(1);
});
