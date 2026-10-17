<?php

use App\Domain\Website\Models\ThemeToken;
use App\Domain\Website\Support\ThemeCss;
use App\Livewire\Admin\Website\ThemeBuilder;
use Database\Factories\Website\PageFactory;
use Database\Seeders\ThemeTokensSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;

/*
| V2-1 (docs/12 §5.1, D-116): v1's own design as tokens. The theme lives in the database in the live CSS's
| own vocabulary and is printed as CSS custom properties from /assets/theme.css — the stylesheet every v1
| page already links last — so a saved token re-skins the live site with no deploy. The v2 pages link the
| hashed /theme/{hash}.css; their vocabulary is derived from the same tokens, so the two can never drift.
*/

/**
 * A middleware normalises Cache-Control directive order, so compare the directives as a set.
 *
 * @param  TestResponse  $response
 * @return list<string>
 */
function themeCacheControl($response): array
{
    $directives = array_map('trim', explode(',', (string) $response->headers->get('Cache-Control')));

    return $directives;
}

beforeEach(function () {
    $this->seed(ThemeTokensSeeder::class);
    PageFactory::new()->published()->withSlug('/')->create();
});

it('expresses the live v1 design as tokens', function () {
    expect(ThemeToken::query()->count())->toBe(15)
        ->and(ThemeToken::query()->where('key', '--navy')->value('value'))->toBe('#0C1628')
        ->and(ThemeToken::query()->where('key', '--wood')->value('value'))->toBe('#B8956A')
        ->and(ThemeToken::query()->where('key', '--surface')->value('value'))->toBe('#F4EFE7')
        ->and(ThemeToken::query()->where('key', '--wx-font')->value('value'))->toContain('Plus Jakarta Sans')
        ->and(ThemeToken::query()->where('group', 'typography')->count())->toBe(1)
        ->and(ThemeToken::query()->where('group', 'shape')->count())->toBe(1);
});

it('serves the tokens as CSS, with the v1 vocabulary, its aliases and the v2 vocabulary derived', function () {
    $theme = app(ThemeCss::class);
    $css = $theme->css();

    expect($css)->toStartWith(':root {')
        ->and($css)->toContain('--navy: #0C1628;')          // the stored token
        ->and($css)->toContain('--wx-navy: #0C1628;')       // its alias in the live stylesheets
        ->and($css)->toContain('--surface: #F4EFE7;')
        ->and($css)->toContain('--color-navy-900: var(--navy);')      // the v2 vocabulary, derived
        ->and($css)->toContain('--color-sand-100: var(--surface);')
        ->and($css)->toContain('--font-heading: var(--wx-font);');

    // The live v1 pages link /assets/theme.css last: it answers with the same theme, briefly cacheable.
    $live = $this->get('/assets/theme.css')->assertOk();
    $live->assertHeader('Content-Type', 'text/css; charset=utf-8');
    expect(themeCacheControl($live))->toContain('max-age=60')
        ->and($live->getContent())->toBe($css);

    // The home page links the hashed URL.
    $hashBefore = $theme->hash();
    $home = $this->get('/')->assertOk()->getContent();
    expect($home)->toContain('/theme/'.$hashBefore.'.css');
});

it('re-skins every page when a token is saved: the hash changes and old URLs stay current', function () {
    $theme = app(ThemeCss::class);
    $hashBefore = $theme->hash();

    // A saved change mints a new URL — browsers never serve yesterday's colours.
    ThemeToken::query()->where('key', '--navy')->update(['value' => '#123456']);
    $theme->flush();

    expect($theme->hash())->not()->toBe($hashBefore);

    $fresh = $this->get('/theme/'.$theme->hash().'.css')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/css; charset=utf-8');
    expect(themeCacheControl($fresh))->toContain('immutable', 'max-age=31536000', 'public')
        ->and($fresh->getContent())->toContain('--navy: #123456;');

    // An old hash (a cached page) still gets the current tokens, but never caches them.
    $stale = $this->get('/theme/'.$hashBefore.'.css')->assertOk();
    expect(themeCacheControl($stale))->toContain('max-age=0', 'must-revalidate')
        ->not->toContain('immutable')
        ->and($stale->getContent())->toContain('--navy: #123456;');

    // And the live site's own URL follows along — that is the whole point of the hook.
    expect($this->get('/assets/theme.css')->getContent())->toContain('--navy: #123456;');
});

it('re-skins the site when a token is saved, and returns to the v1 palette on request', function () {
    $owner = $this->userWithRole('owner');

    Livewire::actingAs($owner)
        ->test(ThemeBuilder::class)
        ->assertSee('Brand navy')
        ->assertSee('Wood accent')
        ->assertSee('Site font');

    // Change the brand navy through the builder (flat index 0 = brand navy).
    Livewire::actingAs($owner)
        ->test(ThemeBuilder::class)
        ->set('tokens.0.value', '#1B2A4A')
        ->call('save');

    expect(ThemeToken::query()->where('key', '--navy')->value('value'))->toBe('#1B2A4A');

    // And back to v1 in one press.
    Livewire::actingAs($owner)
        ->test(ThemeBuilder::class)
        ->call('resetToV1');

    expect(ThemeToken::query()->where('key', '--navy')->value('value'))->toBe('#0C1628');
});

it('keeps the theme builder to those who may manage the theme', function () {
    $editor = $this->userWithRole('editor'); // website.edit, not theme.manage
    $owner = $this->userWithRole('owner');

    $this->actingAs($editor)->get('/admin/website/theme')->assertForbidden();
    $this->actingAs($owner)->get('/admin/website/theme')->assertOk();
});

it('caches the rendered CSS until a save flushes it', function () {
    $theme = app(ThemeCss::class);
    $first = $theme->css();
    $hash = $theme->hash();

    // A change behind the cache's back does not show until flush — that is what flush() is for.
    ThemeToken::query()->where('key', '--navy')->update(['value' => '#ABCDEF']);
    expect($theme->css())->toBe($first)
        ->and($theme->hash())->toBe($hash);

    $theme->flush();

    expect($theme->css())->toContain('#ABCDEF')
        ->and($theme->hash())->not()->toBe($hash);

    Cache::flush();
});
