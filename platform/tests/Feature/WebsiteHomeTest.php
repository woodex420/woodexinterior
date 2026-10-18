<?php

use App\Domain\Website\Enums\BlockType;
use App\Domain\Website\Enums\PageType;
use App\Domain\Website\Models\Media;
use App\Domain\Website\Models\Page;
use App\Domain\Website\Models\PageBlock;
use App\Settings\BusinessSettings;
use App\Support\Phone;
use Tests\Concerns\InteractsWithPublicWebsite;

/*
| The public home page. It is a page in the CMS like any other (slug "/"), served by the SiteRouter: what the
| studio edits in Website -> Pages is what a visitor gets. There is no home page template in the code any more.
|
| These tests hold the promises of the front page whatever it says: it renders, it is indexable, its structured
| data is valid and agrees with the visible copy, and the enquiry form works before JavaScript starts (D-49).
*/

uses(InteractsWithPublicWebsite::class);

/**
 * The home page as the studio edits it: a hero, the services, the process, questions and an enquiry form.
 */
function homePage(): Page
{
    $image = Media::factory()->create();

    $page = Page::factory()
        ->published()
        ->asType(PageType::Home)
        ->withSlug('/')
        ->withSeo([
            'title' => 'Interior Design Lahore | Woodex Interior',
            'description' => 'Interior design, fit-out and renovation in Lahore from one studio: brief, drawings, '
                .'site work and handover, with the whole scope costed before work begins.',
            'robots' => 'index, follow',
        ])
        ->create(['title' => 'Interior Design Lahore', 'excerpt' => 'One studio from brief to handover.']);

    $sections = [
        [BlockType::Hero, ['eyebrow' => 'Design and build', 'heading' => 'Spaces shaped by purpose', 'text' => 'From the first sketch to the final handover.', 'image_id' => $image->id, 'primary_label' => 'Book a visit', 'primary_url' => '/book-a-visit/']],
        [BlockType::Services, ['heading' => 'What we deliver', 'items' => [
            ['number' => '01', 'name' => 'Interior design', 'copy' => 'Layouts, finishes and light.', 'url' => ''],
            ['number' => '02', 'name' => 'Fit-out', 'copy' => 'One team on one programme.', 'url' => ''],
        ]]],
        [BlockType::Process, ['heading' => 'How the work runs', 'stages' => [
            ['number' => '01', 'name' => 'Brief & survey', 'copy' => 'We measure and listen.'],
            ['number' => '02', 'name' => 'Build', 'copy' => 'One programme, one set of drawings.'],
            ['number' => '03', 'name' => 'Handover', 'copy' => 'Snagging and a walkthrough.'],
        ]]],
        [BlockType::Faq, ['heading' => 'Clear answers', 'items' => [
            ['question' => 'Do you work outside Lahore?', 'answer' => 'Yes, across Pakistan, from our Lahore studio.'],
        ]]],
        [BlockType::Form, ['heading' => 'Tell us about your space', 'text' => 'Share the location, size and timeline.', 'show_contact' => true]],
    ];

    foreach ($sections as $position => [$type, $data]) {
        PageBlock::factory()->for($page)->asType($type, $data)->create(['position' => $position]);
    }

    return $page;
}

beforeEach(function (): void {
    homePage();
});

it('renders the home page with the studio name, address, phone and email', function () {
    $business = app(BusinessSettings::class);

    $this->siteGet('/')
        ->assertOk()
        ->assertSee($business->brand_name)
        ->assertSee($business->address)
        ->assertSee($business->city)
        ->assertSee($business->email)
        ->assertSee(Phone::display($business->phone));
});

it('is indexable and carries the SEO and social tags', function () {
    $body = $this->siteGet('/')->assertOk()->getContent();

    expect($body)
        ->toContain('<meta name="robots" content="index')
        ->toContain('<link rel="canonical" href="'.Page::urlFor('/').'">')
        ->toContain('og:title')
        ->toContain('og:image')
        ->toContain('twitter:card" content="summary_large_image"')
        ->not->toContain('noindex');

    // One H1, and the title is short enough for a search result.
    preg_match_all('/<h1[^>]*>/', $body, $h1);
    expect($h1[0])->toHaveCount(1);

    preg_match('/<title>(.*?)<\/title>/', $body, $title);
    expect(strlen($title[1]))->toBeLessThan(65);

    preg_match('/<meta name="description" content="(.*?)"/', $body, $description);
    expect(strlen($description[1]))->toBeGreaterThan(120)->toBeLessThan(165);
});

it('publishes valid JSON-LD: studio, page, breadcrumb and the questions on the page', function () {
    $body = $this->siteGet('/')->assertOk()->getContent();

    preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $body, $match);
    $data = json_decode(trim($match[1]), true, flags: JSON_THROW_ON_ERROR);
    $types = array_map(fn (array $node): string => is_array($node['@type']) ? $node['@type'][0] : $node['@type'], $data['@graph']);

    expect($types)->toBe(['LocalBusiness', 'WebSite', 'WebPage', 'BreadcrumbList', 'FAQPage']);

    $business = app(BusinessSettings::class);
    $studio = $data['@graph'][0];

    expect($studio['telephone'])->toBe(Phone::display($business->phone))
        ->and($studio['address']['addressLocality'])->toBe($business->city)
        ->and($studio['openingHoursSpecification']['opens'])->toBe($business->opens_at)
        ->and($studio['aggregateRating']['ratingValue'])->toBe('5.0');

    // The offers are the services the page lists, so the structured data cannot drift from the copy.
    expect($studio['hasOfferCatalog']['itemListElement'])->toHaveCount(2);

    // Every question in the structured data is also visible on the page (Google requires it).
    foreach ($data['@graph'][4]['mainEntity'] as $question) {
        expect($body)->toContain(e($question['name']))->toContain(e($question['acceptedAnswer']['text']));
    }
});

it('asks for the enquiry with a form that works without JavaScript', function () {
    $body = $this->siteGet('/')->assertOk()->getContent();

    // The fields are plain HTML, not inside a template, so they render and work before Alpine starts.
    expect($body)->toContain('id="lf-name"')->toContain('id="lf-phone"')->toContain('id="lf-service"');

    $this->postJson('/api/public/leads', [
        'name' => 'Ayesha Khan',
        'phone' => '0322 4000768',
        'service' => 'interior-design',
        'message' => 'Living room and kitchen, 5 marla house in Johar Town.',
        'consent' => true,
        'page_url' => Page::urlFor('/'),
    ])->assertCreated();

    $this->assertDatabaseHas('contacts', ['name' => 'Ayesha Khan']);
});

it('shows experience and trust signals: process, questions and opening hours', function () {
    $this->siteGet('/')
        ->assertOk()
        ->assertSee('Brief & survey')
        ->assertSee('Handover')
        ->assertSee('Monday to Saturday');
});

it('labels every content image with alt text and lazy-loads the ones below the fold', function () {
    $body = $this->siteGet('/')->assertOk()->getContent();

    preg_match_all('/<img\b[^>]*>/', $body, $images);
    expect($images[0])->not->toBeEmpty();

    foreach ($images[0] as $image) {
        expect($image)->toContain('alt=');
        expect($image)->toMatch('/width="\d+"/')->toMatch('/height="\d+"/'); // no layout shift
    }

    // The hero is the LCP image: eager and high priority; the rest wait.
    expect($images[0][0])->toContain('fetchpriority="high"')->not->toContain('loading="lazy"');
});

it('is a page in the CMS, so editing it in the dashboard changes the website', function () {
    $page = Page::query()->where('slug', '/')->firstOrFail();
    $page->update(['seo' => [...$page->seo, 'title' => 'Home edited in the dashboard']]);

    expect($this->siteGet('/')->assertOk()->getContent())->toContain('Home edited in the dashboard');
});

it('falls back to the 404 page when the home page is unpublished', function () {
    Page::query()->where('slug', '/')->firstOrFail()->update(['status' => 'draft', 'published_at' => null]);

    $this->siteGet('/')->assertNotFound();
});
