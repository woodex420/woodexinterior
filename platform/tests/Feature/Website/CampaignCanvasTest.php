<?php

use App\Domain\Website\Enums\BlockType;
use App\Domain\Website\Models\Page;
use App\Domain\Website\Models\PageBlock;
use App\Domain\Website\Support\CanvasHtml;
use App\Livewire\Admin\Website\PageEditor;
use App\Livewire\Admin\Website\PagesIndex;
use App\Models\User;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithPublicWebsite;

/*
| V2-2 leftover (D-107, docs/12 §5.3): the campaign free canvas. A page switched to the canvas
| renders hand-placed HTML instead of structured sections — scripts stripped at the door, the page
| flagged in the Pages list, the sections waiting underneath so switching back loses nothing.
*/

uses(InteractsWithPublicWebsite::class);

beforeEach(function () {
    $this->editor = User::factory()->create();
    $this->editor->givePermissionTo(['website.edit', 'website.raw_html']);
});

it('renders hand-placed HTML instead of sections, with no section chrome', function () {
    $page = Page::factory()->published()->withSlug('/summer-offer/')->create(['title' => 'Summer offer', 'template' => 'canvas']);
    $page->update(['canvas_html' => '<section class="campaign"><h1>Summer, hand-placed</h1><p>One page, one offer.</p></section>']);
    PageBlock::factory()->for($page)->asType(BlockType::Text, ['heading' => 'A dormant section', 'body' => 'Must not render.'])->create(['position' => 0]);

    $body = $this->siteGet('/summer-offer/')->assertOk()->getContent();

    expect($body)->toContain('Summer, hand-placed')
        ->and($body)->toContain('<section class="campaign">')
        // The structured section stays dormant, and the standard fallback h1 does not double up.
        ->and($body)->not->toContain('A dormant section')
        ->and(preg_match_all('/<h1[\s>]/', $body))->toBe(1);
});

it('strips scripts at the door, and keeps the rest of the studio\'s hand intact', function () {
    $dirty = <<<'HTML'
        <section onclick="steal()">
            <h1 onmouseover="x()">Safe</h1>
            <a href="javascript:alert(1)">bad</a>
            <a href="/contact/">good</a>
            <iframe src="https://maps.example/embed"></iframe>
            <style>.campaign { color: var(--wood); }</style>
        </section>
        <script>alert('nope')</script>
        HTML;

    $clean = CanvasHtml::clean($dirty);

    expect($clean)->not->toContain('<script')
        ->and($clean)->not->toContain('onclick')
        ->and($clean)->not->toContain('onmouseover')
        ->and($clean)->not->toContain('javascript:')
        ->and($clean)->toContain('href="/contact/"')
        ->and($clean)->toContain('<iframe src="https://maps.example/embed">')
        ->and($clean)->toContain('<style>');
});

it('switches to the canvas and back from the editor, saving through to the site', function () {
    $page = Page::factory()->published()->withSlug('/spring-campaign/')->create(['title' => 'Spring campaign', 'seo' => ['description' => 'Described.']]);
    PageBlock::factory()->for($page)->asType(BlockType::Text, ['heading' => 'The section', 'body' => str_repeat('Words about rooms. ', 20)])->create(['position' => 0]);

    $component = Livewire::actingAs($this->editor)
        ->test(PageEditor::class, ['page' => $page])
        ->assertSee('Switch to the campaign canvas')
        ->call('toggleCanvas')
        ->assertSet('isCanvas', true);

    // The starter scaffold is in the editor state, not on the site yet.
    $this->travel(1)->seconds();
    $body = $this->siteGet('/spring-campaign/')->assertOk()->getContent();
    expect($body)->toContain('The section');

    $component->set('canvasHtml', '<section><h1>Spring, hand-placed</h1></section>')
        ->call('save')
        ->assertHasNoErrors();

    $page->refresh();
    expect($page->template)->toBe('canvas')
        ->and($page->canvas_html)->toBe('<section><h1>Spring, hand-placed</h1></section>')
        // The section still waits underneath.
        ->and($page->blocks()->count())->toBe(1);

    $this->travel(1)->seconds();
    $body = $this->siteGet('/spring-campaign/')->assertOk()->getContent();
    expect($body)->toContain('Spring, hand-placed')
        ->and($body)->not->toContain('The section');

    // Switching back returns the sections to the site.
    $component->call('toggleCanvas')
        ->assertSet('isCanvas', false)
        ->call('save');

    expect($page->fresh()->template)->toBeNull()
        ->and($page->fresh()->canvas_html)->toBeNull();

    $this->travel(1)->seconds();
    expect($this->siteGet('/spring-campaign/')->assertOk()->getContent())->toContain('The section');
});

it('flags canvas pages in the Pages list', function () {
    Page::factory()->published()->withSlug('/flagged-campaign/')->create(['title' => 'A flagged campaign', 'template' => 'canvas']);

    Livewire::actingAs($this->editor)
        ->test(PagesIndex::class)
        ->assertSee('A flagged campaign')
        ->assertSee('canvas');
});
