<?php

declare(strict_types=1);

use App\Domain\Website\Enums\BlockType;
use App\Domain\Website\Enums\PageType;
use App\Domain\Website\Models\Page;
use App\Domain\Website\Support\CanvasHtml;
use App\Domain\Website\Support\SectionStyle;
use App\Livewire\Admin\Website\PageEditor;
use App\Models\User;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithPublicWebsite;

/*
| Section settings and the HTML block (19 Oct, builder parity with admin v2.1 pro): an editor can
| frame one section — background, spacing, width, centre — and the page follows. A section with no
| settings renders exactly as designed. The HTML block is the one place the studio writes markup,
| and its scripts never reach a visitor.
*/

uses(InteractsWithPublicWebsite::class);

beforeEach(function () {
    $this->editor = User::factory()->create();
    $this->editor->givePermissionTo(['website.edit', 'website.publish', 'website.raw_html']);
});

it('prints the settings as classes on the section wrapper', function () {
    $page = Page::factory()->published()->asType(PageType::Service)->withSlug('/framed/')->create([
        'title' => 'Framed section',
        'excerpt' => 'A page whose second section is framed.',
    ]);

    $page->blocks()->create([
        'type' => BlockType::Text,
        'data' => ['heading' => 'A quiet room', 'body' => 'Walls, light and a table.'],
        'settings' => ['background' => 'beige', 'spacing' => 'spacious', 'width' => 'full', 'align' => 'centre'],
        'visible' => true,
        'position' => 0,
    ]);

    $body = $this->siteGet('/framed/')->assertOk()->getContent();

    expect($body)
        ->toContain('wx-sec wx-sec--bg-beige wx-sec--sp-spacious wx-sec--full wx-sec--centre')
        ->toContain('A quiet room');
});

it('keeps a section that has no settings exactly as designed', function () {
    $page = Page::factory()->published()->asType(PageType::Service)->withSlug('/plain/')->create([
        'title' => 'Plain section',
        'excerpt' => 'Nothing is framed here.',
    ]);

    $page->blocks()->create([
        'type' => BlockType::Text,
        'data' => ['heading' => 'As designed', 'body' => 'No wrapper classes.'],
        'visible' => true,
        'position' => 0,
    ]);

    $body = $this->siteGet('/plain/')->assertOk()->getContent();

    expect($body)
        ->toContain('class="wx-sec"')
        ->not->toContain('wx-sec--bg-')
        ->not->toContain('wx-sec--sp-');
});

it('drops a value it does not know instead of printing it', function () {
    expect(SectionStyle::clean([
        'background' => 'background:#f00;position:fixed',
        'spacing' => 'compact',
        'label' => '<b>Hero</b> and <script>alert(1)</script>',
    ]))->toBe([
        'label' => 'Hero and alert(1)',
        'background' => '',
        'spacing' => 'compact',
        'width' => '',
        'align' => '',
        'responsive' => [
            'desktop' => ['top' => null, 'bottom' => null, 'hidden' => false],
            'tablet' => ['top' => null, 'bottom' => null, 'hidden' => false],
            'mobile' => ['top' => null, 'bottom' => null, 'hidden' => false],
        ],
    ]);

    expect(SectionStyle::classes(['background' => 'bg-red-500']))
        ->toBe(['wx-sec']);
});

it('saves the section settings from the editor and previews them', function () {
    $page = Page::factory()->create(['title' => 'Editable', 'slug' => '/editable/']);
    $page->blocks()->create([
        'type' => BlockType::Text,
        'data' => ['heading' => 'First', 'body' => 'Text.'],
        'visible' => true,
        'position' => 0,
    ]);

    Livewire::actingAs($this->editor)
        ->test(PageEditor::class, ['page' => $page])
        ->set('blocks.0.settings.label', 'Studio note')
        ->set('blocks.0.settings.background', 'navy')
        ->set('blocks.0.settings.align', 'centre')
        ->call('refreshPreview');

    $block = $page->fresh(['blocks'])->blocks->first();

    expect($block->settings)->toMatchArray([
        'label' => 'Studio note',
        'background' => 'navy',
        'align' => 'centre',
    ]);

    $this->siteGet('/editable/?preview=1', $this->editor)->assertOk()
        ->assertSee('wx-sec--bg-navy', false)
        ->assertSee('wx-sec--centre', false);
});

it('keeps the settings through a version restore', function () {
    // Published with a snapshot: the publish gate needs a real SEO title, a description and two
    // sections, so the version carries what the gate asks for.
    $page = Page::factory()->published()->asType(PageType::Service)->withSlug('/restored/')->withSeo([
        'title' => 'Restored page | Woodex Interior',
        'description' => 'A page with two sections and a framed first one, long enough for the publish gate to accept it, which is what this test needs.',
    ])->create([
        'title' => 'Restored',
        'excerpt' => 'A page with one framed section.',
    ]);

    $page->blocks()->create([
        'type' => BlockType::Text,
        'data' => ['heading' => 'Kept', 'body' => 'Settings survive a restore.'],
        'settings' => ['background' => 'white', 'spacing' => 'compact'],
        'visible' => true,
        'position' => 0,
    ]);

    $page->blocks()->create([
        'type' => BlockType::Text,
        'data' => ['heading' => 'Second', 'body' => 'The gate wants two sections.'],
        'visible' => true,
        'position' => 1,
    ]);

    Livewire::actingAs($this->editor)
        ->test(PageEditor::class, ['page' => $page])
        ->call('save', true);

    $version = $page->fresh()->versions()->first();
    $version->page->blocks()->delete();

    Livewire::actingAs($this->editor)
        ->test(PageEditor::class, ['page' => $page])
        ->call('restore', $version->id);

    expect($page->fresh(['blocks'])->blocks->first()->settings)->toMatchArray([
        'background' => 'white',
        'spacing' => 'compact',
    ]);
});

it('renders the HTML block, with the scripts taken out', function () {
    $page = Page::factory()->published()->asType(PageType::Service)->withSlug('/html-block/')->create([
        'title' => 'HTML block page',
        'excerpt' => 'A section written by hand.',
    ]);

    $page->blocks()->create([
        'type' => BlockType::Html,
        'data' => ['code' => '<div class="own-markup"><h2 onclick="steal()">Made by hand</h2><script>alert(1)</script><a href="javascript:go()">link</a></div>'],
        'visible' => true,
        'position' => 0,
    ]);

    $body = $this->siteGet('/html-block/')->assertOk()->getContent();

    expect($body)
        ->toContain('Made by hand')
        ->toContain('own-markup')
        ->not->toContain('<script>alert(1)</script>')
        ->not->toContain('onclick')
        ->not->toContain('javascript:go()');
});

it('offers the HTML block in the editor with a code box', function () {
    Livewire::actingAs($this->editor)
        ->test(PageEditor::class)
        ->assertSee('HTML block')
        ->call('addBlock', BlockType::Html->value)
        ->assertSee('Your own markup')
        ->assertSee('wire:model.live.debounce.700ms="blocks.0.data.code"', false);
});

it('cleans an HTML block when it is saved, not only when it renders', function () {
    expect(CanvasHtml::clean('<p onmouseover="x()">Hi</p><script>x()</script>'))
        ->toBe('<p>Hi</p>');

    $page = Page::factory()->create(['title' => 'Saved HTML', 'slug' => '/saved-html/']);
    $page->blocks()->create([
        'type' => BlockType::Text,
        'data' => ['heading' => 'One', 'body' => 'Text.'],
        'visible' => true,
        'position' => 0,
    ]);

    Livewire::actingAs($this->editor)
        ->test(PageEditor::class, ['page' => $page])
        ->call('addBlock', BlockType::Html->value)
        ->set('blocks.1.data.code', '<p onclick="x()">Hello</p><script>bad()</script>')
        ->call('refreshPreview');

    $saved = $page->fresh(['blocks'])->blocks->firstWhere('type', BlockType::Html);

    expect($saved)->not->toBeNull()
        ->and($saved->data['code'])->toBe('<p>Hello</p>');
});

it('writes per-device spacing as scoped css, with tablet and mobile inheriting desktop', function () {
    $settings = SectionStyle::clean([
        'responsive' => [
            'desktop' => ['top' => 80, 'bottom' => 64],
            'tablet' => ['top' => 48],
            'mobile' => ['bottom' => 24, 'hidden' => false],
        ],
    ]);

    $css = SectionStyle::css($settings, 'hero1');

    expect($css)
        ->toContain('[data-wx-key="hero1"]{padding-top:80px;padding-bottom:64px;}')
        ->toContain('@media (max-width:1024px){[data-wx-key="hero1"]{padding-top:48px;}}')
        ->toContain('@media (max-width:640px){[data-wx-key="hero1"]{padding-bottom:24px;}}')
        ->and($css)->not->toContain('display:none');
});

it('hides a section on a named device without hiding it everywhere', function () {
    $settings = SectionStyle::clean([
        'responsive' => ['mobile' => ['hidden' => true]],
    ]);

    $css = SectionStyle::css($settings, 'promo');

    expect($css)->toBe('@media (max-width:640px){[data-wx-key="promo"]{display:none;}}')
        ->and(SectionStyle::clean([
            'responsive' => ['desktop' => ['hidden' => true]],
        ])['responsive']['desktop']['hidden'])->toBeFalse();
});

it('clamps spacing and drops values that are not numbers', function () {
    $clean = SectionStyle::clean([
        'responsive' => [
            'desktop' => ['top' => 999, 'bottom' => -20],
            'tablet' => ['top' => '80px;position:fixed'],
        ],
    ]);

    expect($clean['responsive']['desktop']['top'])->toBe(200)
        ->and($clean['responsive']['desktop']['bottom'])->toBe(0)
        ->and($clean['responsive']['tablet']['top'])->toBeNull()
        ->and(SectionStyle::css($clean, 'bad key!"'))->toContain('[data-wx-key="badkey"]');
});

it('puts the section device css in the page head and only when it is set', function () {
    $page = Page::factory()->published()->asType(PageType::Service)->withSlug('/responsive/')->create([
        'title' => 'Responsive section',
        'excerpt' => 'Two sections, one tuned for phones.',
    ]);

    $page->blocks()->create([
        'type' => BlockType::Text,
        'block_key' => 'tuned1',
        'data' => ['heading' => 'Tuned', 'body' => 'Space changes on a phone.'],
        'settings' => ['responsive' => ['mobile' => ['top' => 24, 'bottom' => 24]]],
        'visible' => true,
        'position' => 0,
    ]);

    $page->blocks()->create([
        'type' => BlockType::Text,
        'block_key' => 'plain1',
        'data' => ['heading' => 'Plain', 'body' => 'No device rules.'],
        'visible' => true,
        'position' => 1,
    ]);

    $body = $this->siteGet('/responsive/')->assertOk()->getContent();

    expect($body)
        ->toContain('@media (max-width:640px){[data-wx-key="tuned1"]{padding-top:24px;padding-bottom:24px;}}')
        ->toContain('data-wx-key="tuned1"')
        ->toContain('data-wx-key="plain1"')
        ->not->toContain('[data-wx-key="plain1"]{');
});
