<?php

use App\Domain\Website\Data\LegacyUrlMap;
use App\Domain\Website\Enums\BlockType;
use App\Domain\Website\Models\Page;
use App\Domain\Website\Models\PageBlock;
use App\Domain\Website\Models\Redirect;
use Tests\Concerns\InteractsWithPublicWebsite;

/*
| The parity test of the website (docs/09 §13 step 8): every URL the old site answered still answers, either as a
| page with its canonical trailing slash or as one 301 to a page that does. It runs from the URL map, so it also
| catches a redirect added to the map but not to the database, and a page that was never published.
*/
uses(InteractsWithPublicWebsite::class);

/** A published page for every slug of the map, with two sections so it renders like a real one. */
function publishLegacyPages(): void
{
    foreach (LegacyUrlMap::pages() as $slug => $page) {
        if ($slug === '/') {
            continue; // the home page is still its own Blade page in this batch
        }

        $model = Page::factory()->published()->asType(LegacyUrlMap::typeFor($slug))->withSlug($slug)->create([
            'title' => trim((string) preg_replace('#/#', ' ', $slug)),
            'excerpt' => 'Imported from the previous website, waiting for a human to read it.',
        ]);

        PageBlock::factory()->for($model)->asType(BlockType::Hero, ['heading' => $model->title])->create(['position' => 0]);
        PageBlock::factory()->for($model)->asType(BlockType::Text, ['heading' => 'On this page', 'body' => $model->excerpt])->create(['position' => 1]);
    }
}

/** The 301s of the map, as rows in the database (a migration seeds them; this fills in any that are missing). */
function seedLegacyRedirects(): void
{
    foreach (LegacyUrlMap::redirects() as $from => $to) {
        Redirect::query()->firstOrCreate(
            ['from_path' => Redirect::normalize($from)],
            ['to_path' => Redirect::normalize($to), 'code' => 301, 'hits' => 0],
        );
    }
}

it('answers every page URL of the old site with a published page', function () {
    publishLegacyPages();
    seedLegacyRedirects();

    $slugs = array_values(array_filter(array_keys(LegacyUrlMap::pages()), fn (string $slug): bool => $slug !== '/'));

    foreach ($slugs as $slug) {
        $body = $this->siteGet($slug)
            ->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
            ->getContent();

        expect($body, "no canonical on {$slug}")->toContain('<link rel="canonical" href="'.$this->siteUrl($slug).'">');
    }

    expect($slugs)->toHaveCount(142);
});

it('sends every redirected URL of the old site to a page that answers', function () {
    publishLegacyPages();
    seedLegacyRedirects();

    // Since 17 Oct 2026 every URL of the old site is a page, so the map has no redirects of its own: the
    // redirector itself is still checked, with a URL of ours.
    $redirects = LegacyUrlMap::redirects();

    if ($redirects === []) {
        Redirect::query()->create(['from_path' => '/old-flat-design/', 'to_path' => '/interior-design/', 'code' => 301, 'hits' => 0]);
        $redirects = ['/old-flat-design/' => '/interior-design/'];
    }

    foreach ($redirects as $from => $to) {
        $this->siteGet($from)
            ->assertStatus(301)
            ->assertRedirect($this->siteUrl($to));

        // The target answers: a 301 to a 404 is worse than the 404.
        $this->siteGet($to)->assertOk();
    }
});

it('redirects the version without the trailing slash of an old page URL', function () {
    publishLegacyPages();

    $this->siteGet('/renovation')->assertStatus(301)->assertRedirect($this->siteUrl('/renovation/'));
    $this->siteGet('/insights/small-space-ideas')->assertStatus(301)->assertRedirect($this->siteUrl('/insights/small-space-ideas/'));
});

it('serves the home page from the CMS, like every other page', function () {
    $page = $this->publishHomePage();
    PageBlock::factory()->for($page)->asType(BlockType::Text, [
        'heading' => 'Spaces shaped by purpose',
        'body' => 'From the first sketch to the final handover.',
    ])->create(['position' => 0]);

    $this->siteGet('/')
        ->assertOk()
        ->assertSee('Spaces shaped by purpose');

    // No home page in the CMS, no home page on the website: it is not a route any more.
    $page->update(['status' => 'draft', 'published_at' => null]);

    $this->siteGet('/')->assertNotFound();
});
