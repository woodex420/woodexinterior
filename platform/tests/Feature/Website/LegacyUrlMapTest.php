<?php

use App\Domain\Website\Data\LegacyUrlMap;
use App\Domain\Website\Enums\PageType;

/*
| The URL map is the contract with the previous website (docs/09 §3, D-68): 89 pages that stay and 54 URLs that
| 301. If a redirect points at a page that does not exist, or a slug is both a page and a redirect, visitors get
| a 404 where the old site had a page — so the map is checked itself, before anything is imported.
*/
it('holds the pages and the redirects of the old site', function () {
    expect(LegacyUrlMap::pages())->toHaveCount(89)
        ->and(LegacyUrlMap::redirects())->toHaveCount(54)
        ->and(LegacyUrlMap::count())->toBe(143);
});

it('gives every URL one home: a slug is either a page or a redirect, never both', function () {
    $pages = array_keys(LegacyUrlMap::pages());
    $redirects = array_keys(LegacyUrlMap::redirects());

    expect(array_intersect($pages, $redirects))->toBe([])
        ->and(array_unique($pages))->toHaveCount(count($pages))
        ->and(array_unique($redirects))->toHaveCount(count($redirects));
});

it('redirects only to pages that exist in the map', function () {
    foreach (LegacyUrlMap::redirects() as $from => $to) {
        expect(LegacyUrlMap::pages())->toHaveKey($to, message: "{$from} redirects to {$to}, which is not a page");
    }
});

it('sends the 54 old service URLs to the six core services', function () {
    $targets = array_count_values(LegacyUrlMap::redirects());

    expect($targets)->toBe([
        '/interior-design/' => 21,
        '/architecture/' => 13,
        '/renovation/' => 10,
        '/fit-out/' => 7,
        '/furniture/' => 3,
    ]);

    foreach (['/interior-design/', '/fit-out/', '/renovation/', '/architecture/', '/3d-visualization/', '/furniture/'] as $service) {
        expect(LegacyUrlMap::typeFor($service))->toBe(PageType::Service);
    }
});

it('stores every slug with its trailing slash and the page type of the old page', function () {
    foreach (array_keys(LegacyUrlMap::pages()) as $slug) {
        expect($slug)->toEndWith('/')->and($slug)->toStartWith('/');
    }

    expect(LegacyUrlMap::typeFor('/'))->toBe(PageType::Home)
        ->and(LegacyUrlMap::typeFor('/lahore/'))->toBe(PageType::City)
        ->and(LegacyUrlMap::typeFor('/lahore/dha/'))->toBe(PageType::Area)
        ->and(LegacyUrlMap::typeFor('/insights/'))->toBe(PageType::Listing)
        ->and(LegacyUrlMap::typeFor('/insights/small-space-ideas/'))->toBe(PageType::Insight)
        ->and(LegacyUrlMap::typeFor('/projects/courtyard-house/'))->toBe(PageType::Project)
        ->and(LegacyUrlMap::typeFor('/privacy/'))->toBe(PageType::Legal)
        ->and(LegacyUrlMap::typeFor('/contact/'))->toBe(PageType::Contact)
        ->and(LegacyUrlMap::typeFor('/estimator/'))->toBe(PageType::Estimator)
        ->and(LegacyUrlMap::typeFor('/book-a-visit/'))->toBe(PageType::Booking);
});

it('keeps the home page and knows which page has no old file behind it', function () {
    expect(LegacyUrlMap::sourceFor('/'))->toBe('index.html')
        ->and(LegacyUrlMap::sourceFor('/interior-design/'))->toBe('interior-design/index.html')
        // The old site had no furniture page: it is written from the closest old page and then rewritten.
        ->and(LegacyUrlMap::sourceFor('/furniture/'))->toBeNull()
        ->and(LegacyUrlMap::isRedirect('/wardrobe-design/'))->toBeTrue()
        ->and(LegacyUrlMap::targetFor('/wardrobe-design/'))->toBe('/furniture/')
        ->and(LegacyUrlMap::isRedirect('/furniture/'))->toBeFalse();
});
