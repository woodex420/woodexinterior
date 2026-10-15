<?php

use App\Settings\BusinessSettings;
use App\Support\Phone;

/*
| The public home page (website phase P6, first release): it renders, it is indexable, its structured data is
| valid and matches the visible facts, and the enquiry form is usable before JavaScript starts (D-49).
*/
it('renders the home page with the studio name, address and phone', function () {
    $business = app(BusinessSettings::class);

    $this->get('/')
        ->assertOk()
        ->assertSee($business->brand_name)
        ->assertSee($business->address)
        ->assertSee($business->city)
        ->assertSee($business->email)
        ->assertSee('Spaces shaped by purpose and identity');
});

it('is indexable and carries the SEO and social tags', function () {
    $body = $this->get('/')->assertOk()->getContent();

    expect($body)
        ->toContain('<meta name="robots" content="index, follow')
        ->toContain('<link rel="canonical" href="'.url('/').'">')
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

it('publishes valid JSON-LD: studio, page, breadcrumb and the FAQ block', function () {
    preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $this->get('/')->getContent(), $match);
    $data = json_decode(trim($match[1]), true, flags: JSON_THROW_ON_ERROR);
    $types = array_map(fn (array $node): string => is_array($node['@type']) ? $node['@type'][0] : $node['@type'], $data['@graph']);

    expect($types)->toBe(['LocalBusiness', 'WebSite', 'WebPage', 'BreadcrumbList', 'FAQPage']);

    $studio = $data['@graph'][0];
    expect($studio['telephone'])->toBe(Phone::display(app(BusinessSettings::class)->phone))
        ->and($studio['address']['addressLocality'])->toBe('Lahore')
        ->and($studio['openingHoursSpecification']['opens'])->toBe(app(BusinessSettings::class)->opens_at)
        ->and($studio['hasOfferCatalog']['itemListElement'])->toHaveCount(6)
        ->and($studio['aggregateRating']['ratingValue'])->toBe('5.0');

    // Every FAQ in the structured data is also visible on the page (Google requires it).
    $page = $this->get('/')->getContent();
    foreach ($data['@graph'][4]['mainEntity'] as $question) {
        expect($page)->toContain(e($question['name']))->toContain(e($question['acceptedAnswer']['text']));
    }
});

it('shows the enquiry form without JavaScript and posts to the public lead API', function () {
    $body = $this->get('/')->assertOk()->getContent();

    // The fields are plain HTML, not inside a template, so they render and work before Alpine starts.
    expect($body)->toContain('id="lf-name"')->toContain('id="lf-phone"')->toContain('id="lf-service"');

    preg_match('/<form[^>]*id="enquiry".*?<\/form>/s', $body, $form);
    expect($form[0])->toContain('x-data="leadForm()"')->toContain('@submit.prevent="submit"')
        ->and(strlen($form[0]) - strlen(strip_tags($form[0])))->toBeGreaterThan(0);

    $this->postJson('/api/public/leads', [
        'name' => 'Ayesha Khan',
        'phone' => '0322 4000768',
        'service' => 'interior-design',
        'message' => 'Living room and kitchen, 5 marla house in Johar Town.',
        'consent' => true,
        'page_url' => url('/'),
    ])->assertCreated();

    $this->assertDatabaseHas('contacts', ['name' => 'Ayesha Khan']);
});

it('shows experience and trust signals: founding year, process, reviews, clients', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('200+')
        ->assertSee('Brief & survey')
        ->assertSee('Handover')
        ->assertSee('Amna Zafar')
        ->assertSee('Shell')
        ->assertSee('Monday to Saturday');
});

it('labels every content image with alt text and lazy-loads the ones below the fold', function () {
    $body = $this->get('/')->assertOk()->getContent();
    preg_match_all('/<img\b[^>]*>/', $body, $images);

    expect($images[0])->not->toBeEmpty();

    foreach ($images[0] as $index => $image) {
        expect($image)->toContain('alt=');
        expect($image)->toMatch('/width="\d+"/')->toMatch('/height="\d+"/'); // no layout shift
    }

    // The hero is the LCP image: eager and high priority; the rest wait.
    expect($images[0][0])->toContain('fetchpriority="high"')->not->toContain('loading="lazy"');
    expect(collect($images[0])->skip(1)->every(fn (string $image): bool => str_contains($image, 'loading="lazy"')))->toBeTrue();
});

it('links only to real targets: no dead internal links and no broken anchors', function () {
    $body = $this->get('/')->assertOk()->getContent();

    // \shref (not :href): Alpine bindings are not real links.
    preg_match_all('/\shref="([^"]+)"/', $body, $links);

    $anchors = collect($links[1])->filter(fn (string $href): bool => str_starts_with($href, '#'))->values();
    expect($anchors)->not->toBeEmpty();

    // Every anchor points at an id that exists: the menu is made of in-page links while the other pages wait.
    foreach ($anchors as $anchor) {
        expect($body)->toContain('id="'.substr($anchor, 1).'"');
    }

    // Other internal links: static files aside (no file server in tests), each must answer 200.
    // asset()/url() write absolute URLs: make them relative again before checking.
    $relative = fn (string $href): string => str_starts_with($href, url('/')) ? (substr($href, strlen(url('/'))) ?: '/') : $href;

    $internal = collect($links[1])->map($relative)->filter(fn (string $href): bool => ! str_starts_with($href, '#')
        && ! preg_match('#^(https?:|mailto:|tel:|/build/)#', $href)
        && ! preg_match('#\.[a-z0-9]{2,5}$#i', $href))->values();

    expect($internal)->not->toBeEmpty();

    foreach ($internal as $href) {
        $this->get($href)->assertOk();
    }
});

it('serves robots.txt and a sitemap that only lists indexable pages', function () {
    $this->get('/robots.txt')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSee('Allow: /')
        ->assertSee('Disallow: /admin')
        ->assertSee('Sitemap: '.url('/sitemap.xml'));

    $sitemap = $this->get('/sitemap.xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->getContent();

    $xml = simplexml_load_string($sitemap);
    expect($xml)->not->toBeFalse()->and(count($xml->url))->toBeGreaterThan(0);

    // Every URL in the sitemap must answer 200: a 404 in a sitemap is worse than no sitemap.
    foreach ($xml->url as $url) {
        $path = parse_url((string) $url->loc, PHP_URL_PATH) ?: '/';
        $this->get($path)->assertOk();
    }
});
