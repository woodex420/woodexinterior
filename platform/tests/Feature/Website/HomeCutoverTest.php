<?php

declare(strict_types=1);

use App\Domain\Website\Enums\PageStatus;
use App\Domain\Website\Enums\PageType;
use App\Domain\Website\Models\Page;
use App\Domain\Website\Services\SiteRouter;
use Illuminate\Http\Request;

/*
| Phase 3, home page: `/` is served by the CMS. The v1 document is kept at resources/legacy/home-v1.html
| (outside the web root) so the cutover is reversible without git, and every other folder still answers
| with its own static index.html until its group is cut over.
*/

it('serves the site root from the CMS, not from a static file', function () {
    expect(public_path('index.html'))->not->toBeFile()
        ->and(resource_path('legacy/home-v1.html'))->toBeFile();
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
