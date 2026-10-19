<?php

declare(strict_types=1);

use App\Domain\Website\Enums\BlockType;
use App\Domain\Website\Models\Page;
use App\Domain\Website\Services\PageValidator;
use App\Http\Middleware\SecurityHeaders;
use App\Livewire\Admin\Website\HeroSlidesIndex;
use App\Livewire\Admin\Website\PageEditor;
use App\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithPublicWebsite;

/*
| The page builder's live half (19 Oct, owner report "not working — no page builder runtime
| preview"): the preview exists before the first save, it follows edits, drag-and-drop speaks
| Livewire 4, a slider counts as an opening, and a page can only have one hero — while the Hero
| slides screen shows the hero an imported page really has.
*/

uses(InteractsWithPublicWebsite::class);

beforeEach(function () {
    $this->editor = User::factory()->create();
    $this->editor->givePermissionTo(['website.edit', 'website.publish']);
});

/** A hero section in the shape the importer and every template but "Home — v26" produce. */
function hero_block(array $overrides = []): array
{
    return [
        'type' => BlockType::Hero,
        'block_key' => 'b'.bin2hex(random_bytes(4)),
        'visible' => true,
        'position' => 0,
        'data' => array_merge([
            'eyebrow' => 'Woodex Interior · Lahore',
            'heading' => 'Spaces shaped by purpose',
            'text' => 'One accountable team, from the first sketch to the last handle.',
            'image_id' => null,
            'primary_label' => 'Start your project',
            'primary_url' => '/contact/',
            'secondary_label' => '',
            'secondary_url' => '',
            'facts' => [['label' => 'Years', 'value' => '10']],
        ], $overrides),
    ];
}

it('previews a brand-new page before the first explicit save', function () {
    $test = Livewire::actingAs($this->editor)
        ->test(PageEditor::class)
        ->assertSet('pageId', null)
        ->assertSee('The preview writes itself')
        ->assertDontSee('Draft preview (desktop)');

    $test->set('title', 'Interior design in Lahore')
        ->call('refreshPreview')
        ->assertSet('previewStale', false)
        ->assertSee('Draft preview (desktop)');

    $page = Page::query()->where('slug', '/interior-design-in-lahore/')->first();

    expect($page)->not->toBeNull()
        ->and($page->title)->toBe('Interior design in Lahore')
        ->and($page->status->value)->toBe('draft')
        ->and($test->get('pageId'))->toBe($page->id);
});

it('does not create a draft from an empty page, and says why', function () {
    Livewire::actingAs($this->editor)
        ->test(PageEditor::class)
        ->call('refreshPreview')
        ->assertSet('pageId', null)
        ->assertSee('Give the page a title and a URL');

    expect(Page::query()->count())->toBe(0);
});

it('updates the rows on every preview instead of inserting the sections again', function () {
    $page = Page::factory()->withSlug('/builder/')->create(['title' => 'Builder', 'excerpt' => 'A page.']);
    $page->blocks()->create(hero_block());

    Livewire::actingAs($this->editor)
        ->test(PageEditor::class, ['page' => $page])
        ->set('blocks.0.data.heading', 'A new opening')
        ->call('refreshPreview')
        ->call('refreshPreview')
        ->assertSet('blocks.0.data.heading', 'A new opening');

    $blocks = $page->fresh(['blocks'])->blocks;

    expect($blocks)->toHaveCount(1)
        ->and($blocks->first()->data['heading'])->toBe('A new opening');
});

it('accepts a slider as the page opening when publishing', function () {
    $input = [
        'title' => 'Slider page',
        'slug' => '/slider-page/',
        'excerpt' => 'Design, drawings and site supervision for homes and workplaces in Lahore.',
        'seo' => [
            'title' => 'Interior design in Lahore | Woodex',
            'description' => 'Design, drawings and site supervision for homes and workplaces across Lahore, from one accountable studio team since 2016.',
        ],
        'blocks' => [
            ['type' => BlockType::Slider->value, 'visible' => true, 'data' => ['slides' => [['image_id' => null, 'heading' => 'Spaces shaped by purpose']]]],
            ['type' => BlockType::Text->value, 'visible' => true, 'data' => ['heading' => 'In short', 'body' => 'The studio.']],
        ],
    ];

    expect(PageValidator::issues($input))->toBe([]);
});

it('keeps one opening section per page, hiding — never deleting — the second hero', function () {
    $page = Page::factory()->withSlug('/two-heroes/')->create(['title' => 'Two heroes', 'excerpt' => 'A page.']);
    $page->blocks()->create(hero_block());
    $second = $page->blocks()->create([
        'type' => BlockType::Hero,
        'block_key' => 'bsecondhero',
        'visible' => true,
        'position' => 1,
        'data' => array_merge(hero_block()['data'], ['heading' => 'The second opening']),
    ]);

    Livewire::actingAs($this->editor)
        ->test(PageEditor::class, ['page' => $page])
        ->call('save', false);

    $blocks = $page->fresh(['blocks'])->blocks;

    expect($blocks)->toHaveCount(2)
        ->and($blocks->firstWhere('id', $second->id)->visible)->toBeFalse()
        ->and($blocks->firstWhere('id', $second->id)->data['heading'])->toBe('The second opening')
        ->and($blocks->where('visible', true))->toHaveCount(1);
});

it('shows the hero an imported page already has instead of "0 slides"', function () {
    $home = $this->publishHomePage();
    $hero = $home->blocks()->create(hero_block());

    Livewire::actingAs($this->editor)
        ->test(HeroSlidesIndex::class)
        ->assertSet('fromHero', true)
        ->assertSee('The home page opens with a single hero')
        ->assertSet('slides', fn (array $slides): bool => count($slides) === 1
            && $slides[0]['heading'] === 'Spaces shaped by purpose')
        ->set('slides.0.heading', 'Spaces shaped by purpose and identity')
        ->call('save', false);

    $saved = $hero->fresh();
    $blocks = $home->fresh(['blocks'])->blocks;

    // One slide writes the Hero back in place: still a hero, key figures untouched, no second
    // opening section added.
    expect($saved->type)->toBe(BlockType::Hero)
        ->and($saved->data['heading'])->toBe('Spaces shaped by purpose and identity')
        ->and($saved->data['facts'])->toBe([['label' => 'Years', 'value' => '10']])
        ->and($blocks)->toHaveCount(1);
});

it('turns a single hero into a slider when a second slide arrives, keeping the old hero hidden', function () {
    $home = $this->publishHomePage();
    $hero = $home->blocks()->create(hero_block());

    Livewire::actingAs($this->editor)
        ->test(HeroSlidesIndex::class)
        ->call('addSlide')
        ->set('slides.1.heading', 'Workplaces planned around people')
        ->call('save', false);

    $blocks = $home->fresh(['blocks'])->blocks;
    $slider = $blocks->firstWhere('type', BlockType::Slider);

    expect($slider)->not->toBeNull()
        ->and($slider->data['slides'])->toHaveCount(2)
        ->and($blocks->firstWhere('id', $hero->id)->visible)->toBeFalse()
        ->and($blocks)->toHaveCount(2);
});

it('serves a draft preview to a signed-in editor through the real route', function () {
    // The CMS router used to run from the 404 handler, before any route middleware: the editor's
    // session never reached it, so ?preview=1 on a draft answered 404 in a real browser while the
    // tests (which call the router directly) passed. The fallback route fixes it; this is the
    // HTTP-level proof, redirect and all.
    $page = Page::factory()->withSlug('/draft-route/')->create(['title' => 'Draft route']);
    $page->blocks()->create(hero_block(['heading' => 'A draft opening']));

    // The kernel, not $this->get(): Laravel's test client trims the trailing slash from the URL,
    // which is the canonical form of every public page — the request would be redirected to itself.
    $kernel = $this->app->make(Kernel::class);

    $this->actingAs($this->editor);
    $preview = $kernel->handle(Request::create('/draft-route/?preview=1', 'GET'));

    expect($preview->getStatusCode())->toBe(200)
        ->and($preview->getContent())->toContain('A draft opening')
        ->and($preview->getContent())->toContain('wx-edit-section');

    auth()->logout();

    // A preview link without permission is sent back to the page itself, which the original website
    // answers with its exported file (the sandbox and .htaccess keep that address off the CMS).
    $guest = $kernel->handle(Request::create('/draft-route/?preview=1', 'GET'));

    expect($guest->getStatusCode())->toBe(302)
        ->and($guest->headers->get('location'))->toBe('/draft-route/');
});

it('lets the admin frame the preview from its own domain, and nobody else', function () {
    // The editor may sit on admin.woodex.com.pk while the site is woodex.com.pk: the preview
    // response must allow that one framer (and only it), and the editor tools must post to the
    // embedder rather than to their own origin. Visitors keep the ordinary header.
    $request = Request::create('/framed/?preview=1', 'GET');
    $request->setUserResolver(fn () => $this->editor);

    $response = (new SecurityHeaders)->handle($request, fn (): Response => new Response('<html></html>'));

    expect($response->headers->get('X-Frame-Options'))->toBeNull()
        ->and($response->headers->get('Content-Security-Policy'))->toContain("frame-ancestors 'self'");

    // The sandbox preview deliberately turns the header off (SECURITY_FRAME_OPTIONS=""), so the
    // visitor's case is asserted against the production value the owner deploys.
    config(['woodex.security.frame_options' => 'SAMEORIGIN']);
    $visitor = Request::create('/framed/', 'GET');
    $visitorResponse = (new SecurityHeaders)->handle($visitor, fn (): Response => new Response('<html></html>'));

    expect($visitorResponse->headers->get('X-Frame-Options'))->toBe('SAMEORIGIN');

    // The preview carries the click-to-edit tools, posting to the embedder.
    $page = Page::factory()->published()->withSlug('/framed/')->create(['title' => 'Framed']);
    $page->blocks()->create(hero_block(['heading' => 'Framed page']));

    $preview = $this->siteGet('/framed/?preview=1', $this->editor)
        ->assertOk()
        ->assertSee('Framed page');

    expect($preview->getContent())->toContain('wx-edit-section')
        ->and($preview->getContent())->toContain('postMessage');
});
