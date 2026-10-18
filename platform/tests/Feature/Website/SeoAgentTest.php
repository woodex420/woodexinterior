<?php

use App\Domain\Website\Enums\BlockType;
use App\Domain\Website\Models\Media;
use App\Domain\Website\Models\Page;
use App\Domain\Website\Models\PageBlock;
use App\Domain\Website\Models\Redirect;
use App\Livewire\Admin\Website\SeoAgentIndex;
use App\Models\User;
use App\Settings\BotSettings;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithPublicWebsite;

/*
| V2-3 F8 + F10 (docs/14 §5a): the SEO agent. Deterministic, keyless-first — every finding is a
| plain rule; one-click fixes mend what a rule can mend. AI proposals wake only when the studio's
| model key exists (the BOT pattern) and land only by the owner's click.
*/

uses(InteractsWithPublicWebsite::class);

beforeEach(function () {
    $this->editor = User::factory()->create();
    $this->editor->givePermissionTo('website.edit');

    // The studio starts keyless (the shipped default) — the AI half must stay asleep.
    app(BotSettings::class)->fill(['provider' => 'none', 'api_key' => null])->save();
});

it('audits every page with plain rules, no key asked', function () {
    $thin = Page::factory()->published()->withSlug('/Thin_Page/')->create(['title' => 'Short', 'excerpt' => '']);
    PageBlock::factory()->for($thin)->asType(BlockType::Text, ['text' => 'Tiny.'])->create(['position' => 0]);

    $described = Page::factory()->published()->withSlug('/described-page/')->create(['title' => 'A properly long, well described title for a page', 'seo' => ['description' => 'Described.']]);
    PageBlock::factory()->for($described)->asType(BlockType::Text, ['text' => str_repeat('Interior design words. ', 40)])->create(['position' => 0]);

    $component = Livewire::actingAs($this->editor)->test(SeoAgentIndex::class);

    // The untidy page is found with its rules; the healthy page is absent.
    $component->assertSee('Thin_Page')
        ->assertSee('Slug is not lowercase-with-hyphens')
        ->assertSee('No meta description')
        ->assertSee('Only about 1 words')
        ->assertDontSee('described-page');

    // Keyless: the AI card is shown, the draft button is not.
    $component->assertSee('AI proposals are asleep')
        ->assertDontSee('Draft title &amp; description with AI')
        ->assertDontSee('Draft title & description with AI');

    $component->assertSee('fixable in one click');
});

it('fixes what a rule can mend: description, slug with a redirect behind it, alt texts', function () {
    $page = Page::factory()->published()->withSlug('/Mixed_Case page/')->create(['title' => 'A page that needs mending', 'excerpt' => '', 'seo' => ['title' => 'A page that needs mending']]);
    PageBlock::factory()->for($page)->asType(BlockType::Text, ['text' => 'This page sells thoughtful interiors in Lahore with natural materials. '.str_repeat('Every room is planned, drawn and built by our own team. ', 15)])->create(['position' => 0]);
    $image = Media::factory()->create(['path' => 'website/media/atrium.webp', 'title' => 'The atrium']);
    $gallery = PageBlock::factory()->for($page)->asType(BlockType::Gallery, ['items' => [['id' => $image->id, 'alt' => '']]])->create(['position' => 1]);

    $component = Livewire::actingAs($this->editor)->test(SeoAgentIndex::class);

    $component->call('fixDescription', $page->id)
        ->assertDispatched('toast', message: 'Meta description taken from the page itself.');

    $component->call('fixSlug', $page->id)
        ->assertDispatched('toast', message: 'Slug tidied — the old address now redirects to it.');

    $component->call('fixAlt', $page->id)
        ->assertDispatched('toast', message: '1 alt text(s) filled from the media library.');

    $page->refresh();
    expect($page->slug)->toBe('/mixed-case-page/')
        ->and(mb_substr((string) $page->seo['description'], 0, 40))->toBe('This page sells thoughtful interiors in ')
        ->and($gallery->fresh()->data['items'][0]['alt'])->not->toBe('');

    // The old address now redirects to the tidy one.
    $redirect = Redirect::query()->where('from_path', '/Mixed_Case page/')->first();
    expect($redirect)->not->toBeNull()
        ->and($redirect->to_path)->toBe('/mixed-case-page/');

    // And the audit no longer lists the page.
    $this->travel(1)->seconds();
    Livewire::actingAs($this->editor)
        ->test(SeoAgentIndex::class)
        ->assertDontSee('Mixed_Case')
        ->assertDontSee('mixed-case-page');
});

it('carries the F10 website health sweep: broken redirects, dead links, orphan pages', function () {
    // A published page nothing links to…
    $orphan = Page::factory()->published()->withSlug('/orphan-page/')->create(['title' => 'An orphan page with a long enough title', 'seo' => ['description' => 'Described.']]);
    PageBlock::factory()->for($orphan)->asType(BlockType::Text, ['text' => str_repeat('Words about interiors. ', 20)])->create(['position' => 0]);

    // …a redirect to a page that does not exist…
    Redirect::query()->create(['from_path' => '/gone/', 'to_path' => '/nowhere/']);

    // …and a link in the orphan's own text to another page that does not exist.
    $orphan->blocks()->first()->update(['data' => ['text' => 'See <a href="/also-nowhere/">our missing page</a> '.str_repeat('Words about interiors. ', 20)]]);

    $component = Livewire::actingAs($this->editor)->test(SeoAgentIndex::class)
        ->set('tab', 'site');

    $component->assertSee('redirect(s) point at no page')
        ->assertSee('/gone/ → /nowhere/')
        ->assertSee('internal link(s) lead nowhere')
        ->assertSee('also-nowhere')
        ->assertSee('Nothing links to 1 published page(s)')
        ->assertSee('/orphan-page/');

    // Broken redirects are mended by removal — the rest is advice.
    $component->call('fixRedirect', Redirect::query()->first()->id)
        ->assertDispatched('toast', message: 'Redirect removed.');

    expect(Redirect::query()->count())->toBe(0);
});

it('proposes with the studio key and lands nothing until the owner clicks Apply', function () {
    app(BotSettings::class)->fill(['provider' => 'openai', 'api_key' => 'sk-test', 'model' => ''])->save();

    $page = Page::factory()->published()->withSlug('/proposed-page/')->create(['title' => 'A page worth proposing for', 'excerpt' => '']);
    PageBlock::factory()->for($page)->asType(BlockType::Text, ['text' => 'The studio designs calm, warm interiors across Lahore.'])->create(['position' => 0]);

    Http::fake(['api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => '{"title": "Calm interiors in Lahore", "description": "Thoughtful interior design for homes across Lahore, in natural materials."}']]]])]);

    $component = Livewire::actingAs($this->editor)->test(SeoAgentIndex::class)
        ->assertDontSee('AI proposals are asleep');

    $component->call('propose', $page->id)
        ->assertSee('Proposal — read it, then apply or walk away.')
        ->assertSee('Calm interiors in Lahore');

    // The proposal is on the table, not on the page.
    expect($page->fresh()->seo['title'] ?? null)->toBeNull();

    $component->call('applyProposal');

    $page->refresh();
    expect($page->seo['title'])->toBe('Calm interiors in Lahore')
        ->and($page->seo['description'])->toBe('Thoughtful interior design for homes across Lahore, in natural materials.')
        ->and($component->get('proposal'))->toBe([]);
});

it('stays keyless when the key is empty, whatever the provider says', function () {
    app(BotSettings::class)->fill(['provider' => 'openai', 'api_key' => null])->save();

    $page = Page::factory()->published()->withSlug('/still-keyless/')->create(['title' => 'A keyless page with a long enough title', 'seo' => ['description' => 'Described.']]);
    PageBlock::factory()->for($page)->asType(BlockType::Text, ['text' => str_repeat('Interior words. ', 20)])->create(['position' => 0]);

    Http::fake();

    Livewire::actingAs($this->editor)
        ->test(SeoAgentIndex::class)
        ->assertSee('AI proposals are asleep');

    Http::assertNothingSent();
});
