<?php

declare(strict_types=1);

use App\Domain\Website\Enums\PageStatus;
use App\Domain\Website\Enums\PageType;
use App\Domain\Website\Models\Page;
use App\Domain\Website\Services\SiteRouter;
use Illuminate\Http\Request;

/*
| The home page. Woodex OS 1 (docs/audit/16.19 step 4) made `/` a page file like every other address: the
| CMS-rendered home was exported to public/index.html, which the web server answers before Laravel. The CMS
| row still answers a request that reaches the app — the fallback for a deploy where the file is missing —
| and this file keeps both halves honest.
*/

it('serves the site root from a page file', function () {
    expect(public_path('index.html'))->toBeFile();

    $html = (string) file_get_contents(public_path('index.html'));

    expect(str_starts_with(ltrim($html), '<!DOCTYPE html>'))->toBeTrue()
        ->and(stripos($html, '</html>'))->not->toBeFalse();
});

it('still keeps the other page groups on their static files', function () {
    expect(public_path('about/index.html'))->toBeFile();
});

it('returns the stored document for the root, unwrapped', function () {
    $html = '<!DOCTYPE html><html lang="en"><head><title>Home</title></head><body><main><h1>Cut over</h1></main></body></html>';

    Page::factory()->create([
        'slug' => '/',
        'type' => PageType::Home,
        'template' => 'html',
        'canvas_html' => $html,
        'status' => PageStatus::Published,
        'published_at' => now()->subDay(),
    ]);

    $request = Request::create('http://localhost/', 'GET');
    $this->app->instance('request', $request);

    $response = $this->app->make(SiteRouter::class)->respond($request);

    expect($response)->not->toBeNull()
        ->and($response->getStatusCode())->toBe(200)
        ->and($response->headers->get('Content-Type'))->toContain('text/html')
        ->and($response->getContent())->toBe($html);
});
