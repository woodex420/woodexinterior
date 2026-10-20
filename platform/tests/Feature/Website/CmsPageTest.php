<?php

use App\Domain\Website\Enums\BlockType;
use App\Domain\Website\Enums\PageStatus;
use App\Domain\Website\Enums\PageType;
use App\Domain\Website\Models\Page;
use App\Domain\Website\Models\PageBlock;
use App\Domain\Website\Models\Redirect;
use App\Domain\Website\Services\PageCache;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\File;
use Tests\Concerns\InteractsWithPublicWebsite;

uses(InteractsWithPublicWebsite::class);

/*
| A published page from the CMS answers at its own URL (docs/09 §2, D-67): the legacy URLs keep their promise
| through one 301 each, only one canonical form exists per page, and a draft is invisible to visitors.
*/
it('serves a published page at its slug', function () {
    $page = Page::factory()->published()->asType(PageType::Service)->withSlug('/interior-design/')->create([
        'title' => 'Interior Design in Lahore',
        'excerpt' => 'Design, drawings and site supervision for homes and workplaces.',
    ]);

    PageBlock::factory()->for($page)->asType(BlockType::Hero, [
        'eyebrow' => 'Design & build',
        'heading' => 'Interior Design in Lahore',
        'text' => 'One team from concept to handover.',
    ])->create(['position' => 0]);

    $body = $this->siteGet('/interior-design/')
        ->assertOk()
        ->assertSee('Interior Design in Lahore')
        ->assertSee('Design & build')
        ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
        ->getContent();

    // One H1, supplied by the hero: the page never prints the title twice.
    preg_match_all('/<h1[^>]*>/', $body, $h1);
    expect($h1[0])->toHaveCount(1);

    expect($body)->toContain('<link rel="canonical" href="'.$this->siteUrl('/interior-design/').'">');
});

it('redirects the version without a trailing slash to the canonical one', function () {
    Page::factory()->published()->withSlug('/about/')->create(['title' => 'About the studio']);

    $this->siteGet('/about')->assertRedirect($this->siteUrl('/about/'))->assertStatus(301);
    $this->siteGet('/about?utm=1')->assertRedirect($this->siteUrl('/about/').'?utm=1')->assertStatus(301);

    // The same redirect through the HTTP kernel: the 404 handler reaches the site router.
    $this->get('/about')->assertRedirect($this->siteUrl('/about/'))->assertStatus(301);
});

it('renders a page without a hero with its own H1 and the enquiry form', function () {
    $page = Page::factory()->published()->asType(PageType::Contact)->withSlug('/contact/')->create(['title' => 'Contact']);
    PageBlock::factory()->for($page)->asType(BlockType::Form, ['heading' => 'Talk to the studio'])->create(['position' => 0]);

    $body = $this->siteGet('/contact/')->assertOk()->getContent();

    preg_match_all('/<h1[^>]*>/', $body, $h1);
    expect($h1[0])->toHaveCount(1)
        ->and($body)->toContain('Contact')
        ->toContain('id="lf-name"');
});

it('hides drafts and pages scheduled for later from visitors', function () {
    Page::factory()->withSlug('/draft-page/')->create(['title' => 'Not ready yet']);
    Page::factory()->scheduled()->withSlug('/later/')->create(['title' => 'Next month']);

    $this->siteGet('/draft-page/')->assertNotFound();
    $this->siteGet('/later/')->assertNotFound();

    // Whoever may edit the website can open a draft before it goes live.
    $editor = User::factory()->create();
    $editor->givePermissionTo('website.edit');

    $this->siteGet('/draft-page/?preview=1', $editor)->assertOk()->assertSee('Not ready yet');
    $this->siteGet('/draft-page/', $editor)->assertNotFound();

    $guest = User::factory()->create();
    $this->siteGet('/draft-page/?preview=1', $guest)->assertNotFound();
});

it('sends an old URL to its new page with one 301 and counts the hit', function () {
    Page::factory()->published()->withSlug('/interior-design/')->create(['title' => 'Interior Design']);
    // A URL of its own: the 54 redirects of the old site are seeded by a migration and have their own test.
    $redirect = Redirect::factory()->create([
        'from_path' => '/old-flat-design/',
        'to_path' => '/interior-design/',
        'code' => 301,
    ]);

    $this->siteGet('/old-flat-design/')
        ->assertRedirect($this->siteUrl('/interior-design/'))
        ->assertStatus(301);

    expect($redirect->fresh()->hits)->toBe(1);

    $this->siteGet('/old-flat-design/')->assertStatus(301);
    expect($redirect->fresh()->hits)->toBe(2);
});

it('skips hidden blocks', function () {
    $page = Page::factory()->published()->withSlug('/renovation/')->create(['title' => 'Renovation']);
    PageBlock::factory()->for($page)->asType(BlockType::Text, ['heading' => 'Visible section', 'body' => 'Shown.'])->create(['position' => 0]);
    PageBlock::factory()->for($page)->asType(BlockType::Text, ['heading' => 'Hidden section', 'body' => 'Not shown.'])->hidden()->create(['position' => 1]);

    $body = $this->siteGet('/renovation/')->assertOk()->getContent();

    expect($body)->toContain('Visible section')->not->toContain('Hidden section');
});

it('adds the questions of a page to the structured data', function () {
    $page = Page::factory()->published()->withSlug('/fit-out/')->create(['title' => 'Fit-Out']);
    PageBlock::factory()->for($page)->asType(BlockType::Faq, ['items' => [
        ['question' => 'How long does a fit-out take?', 'answer' => 'Six to twelve weeks for a typical office.'],
    ]])->create(['position' => 0]);

    $body = $this->siteGet('/fit-out/')->assertOk()->getContent();

    preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $body, $match);
    $graph = json_decode(trim($match[1]), true, flags: JSON_THROW_ON_ERROR)['@graph'];
    $faq = collect($graph)->firstWhere('@type', 'FAQPage');

    expect($faq['mainEntity'][0]['name'])->toBe('How long does a fit-out take?')
        ->and($body)->toContain('Six to twelve weeks for a typical office.');
});

it('shows a helpful 404 page for a URL that belongs to nothing', function () {
    $this->siteGet('/no-such-page/')
        ->assertNotFound()
        ->assertSee('This page has moved')
        ->assertSee('Back to the home page');

    // Through the HTTP kernel the same URL answers the canonical 301 first: Laravel's test client builds its
    // request with url(), which drops the trailing slash.
    $this->get('/no-such-page')->assertRedirect($this->siteUrl('/no-such-page/'))->assertStatus(301);
});

it('lists the files the website serves, plus a CMS page that has no file of its own', function () {
    // Since OS 1 the site is the files, so the sitemap is built from the files: built from the rows it listed 57
    // of 143 pages and was blind to the service, city, area and project pages (20 Oct).
    $site = sys_get_temp_dir().'/woodex-sitemap-'.bin2hex(random_bytes(4));
    File::ensureDirectoryExists($site.'/about');
    File::ensureDirectoryExists($site.'/draft-only');
    File::put($site.'/index.html', '<html><head><title>Home</title></head><body></body></html>');
    File::put($site.'/about/index.html', '<html><head><title>About</title></head><body></body></html>');
    File::put($site.'/draft-only/index.html', '<html><head><meta name="robots" content="noindex"></head><body></body></html>');
    $this->app->usePublicPath($site);

    Page::factory()->published()->withSlug('/cms-only/')->create(['title' => 'CMS only']);
    Page::factory()->withSlug('/still-draft/')->create(['title' => 'Draft']);
    Page::factory()->published()->withSlug('/about/')->create(['title' => 'A row shadowed by its file']);

    $xml = simplexml_load_string($this->get('/sitemap.xml')->assertOk()->getContent());

    $urls = [];
    foreach ($xml->url as $url) {
        $urls[] = (string) $url->loc;
    }

    expect($urls)->toBe([
        Page::urlFor('/'),
        Page::urlFor('/about/'),
        Page::urlFor('/cms-only/'),
    ]);

    File::deleteDirectory($site);
});

it('keeps page slugs unique and always stores them with a trailing slash', function () {
    Page::factory()->withSlug('/interior-design')->create();

    expect(Page::first()->slug)->toBe('/interior-design/')
        ->and(fn () => Page::factory()->withSlug('/interior-design/')->create())->toThrow(QueryException::class);
});

it('serves the new copy of a page from the cache after a publish', function () {
    config()->set('website.cache', true);

    $page = Page::factory()->published()->withSlug('/architecture/')->create(['title' => 'Architecture']);
    PageBlock::factory()->for($page)->asType(BlockType::Text, ['heading' => 'First draft'])->create(['position' => 0]);

    expect($this->siteGet('/architecture/')->getContent())->toContain('First draft');

    $page->blocks()->first()->update(['data' => ['heading' => 'Second version', 'body' => '']]);
    PageCache::invalidate($page); // what the editor does on every save and on publish

    expect($this->siteGet('/architecture/')->getContent())->toContain('Second version');
});

it('marks a page noindex when the editor asks for it', function () {
    Page::factory()->published()->withSlug('/thank-you/')->withSeo(['robots' => 'noindex'])->create(['title' => 'Thank you']);

    $this->siteGet('/thank-you/')->assertOk()->assertSee('noindex');
    $this->get('/sitemap.xml')->assertOk()->assertDontSee('/thank-you/');
});

it('does not answer for the app, only for the website', function () {
    Page::factory()->published()->withSlug('/install/')->create(['title' => 'Not the installer']);
    Page::factory()->published()->withSlug('/admin/')->create(['title' => 'Not the admin']);

    // Those first segments belong to the app: a page may never take them over.
    $this->siteGet('/install/')->assertNotFound();
    $this->siteGet('/admin/')->assertNotFound();
});

it('stores the page status and type as enums', function () {
    $page = Page::factory()->published()->create();

    expect($page->status)->toBe(PageStatus::Published)
        ->and($page->isPublished())->toBeTrue()
        ->and($page->type)->toBeInstanceOf(PageType::class);
});
