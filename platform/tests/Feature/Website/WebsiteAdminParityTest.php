<?php

use App\Domain\Website\Models\Page;
use App\Domain\Website\Models\Redirect;
use App\Livewire\Admin\Website\SeoOverviewIndex;
use App\Livewire\Admin\Website\SiteHealthIndex;
use App\Models\User;
use App\Support\AdminNavigation;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithPublicWebsite;

/*
| The old admin v2.1 pro's Website section, item for item (docs/14 §5a, 19 Oct): the four screens it
| had that Woodex OS lacked — Section library, Hero slides, SEO and Site health — plus the old names
| on the screens that already existed under different labels (Header & footer, Redirects, All pages,
| Page builder). One flat sidebar group carries everything the owner starred.
*/

uses(InteractsWithPublicWebsite::class);

beforeEach(function () {
    $this->editor = User::factory()->create();
    $this->editor->givePermissionTo(['website.edit', 'website.publish']);
});

it('groups the Website section the way it is worked on', function () {
    $owner = User::factory()->create();
    $owner->givePermissionTo([
        'dashboard.view', 'website.edit', 'website.publish', 'theme.manage', 'settings.manage',
        'leads.view', 'contacts.view', 'estimates.view', 'quotes.view', 'quotes.approve', 'invoices.view',
        'accounts.view', 'projects.view', 'inbox.view', 'reports.view', 'automation.manage',
        'integrations.manage', 'social.manage', 'users.manage', 'system.health', 'audit.view',
        'system.change_requests', 'leads.delete', 'system.import',
    ]);

    $groups = collect(AdminNavigation::for($owner));

    $labels = fn (?string $title): array => collect($groups->firstWhere('title', $title)['items'])
        ->pluck('label')
        ->all();

    // Build a page, fill it with content, then tune the whole site — the owner's three bands.
    // Section library and Page templates left the sidebar with the block editor (step 6): they fed a
    // screen that wrote rows, and the files never read them.
    expect($labels('Pages & builder'))->toBe([
        'All pages', 'Page builder', 'Header & footer', 'Redirects',
    ])->and($labels('Content'))->toBe([
        'Blog', 'Portfolio', 'Forms', 'Estimator', 'Team',
    ])->and($labels(null))->toBe([
        'Media', 'SEO', 'SEO agent', 'Speed', 'Site health', 'Theme', 'Website Settings',
    ]);

    // Every Content item has a route, so nothing is listed disabled. The four database-only screens
    // (Service pages, City pages, FAQs, Testimonials) were retired with the database-page model (§16.19).
    $planned = collect($groups->firstWhere('title', 'Content')['items'])
        ->whereNull('route')
        ->pluck('label')
        ->all();

    expect($planned)->toBe([]);
});

it('retires the Content library from the sidebar but keeps its records', function () {
    $owner = User::factory()->create();
    $owner->givePermissionTo(['website.edit']);

    $all = collect(AdminNavigation::for($owner))->flatMap(fn (array $group): array => $group['items'])->pluck('label');

    expect($all)->not->toContain('Content library');

    // The screen itself still answers: nothing was deleted, it simply is not a separate destination
    // any more — the pages and blocks it listed are edited by the screens above.
    expect(route('admin.website.content', absolute: false))->toBe('/admin/website/content');

    $this->actingAs($owner)->get('/admin/website/content')->assertOk();
});

it('shows every page\'s search listing on the SEO screen, and edits it in place', function () {
    $this->publishHomePage(['title' => 'Woodex Interior — design & build', 'description' => 'Interior design in Lahore.']);

    Livewire::actingAs($this->editor)
        ->test(SeoOverviewIndex::class)
        ->assertSee('Woodex Interior — design & build')
        ->call('setFilter', 'draft')
        ->call('setFilter', 'all')
        ->call('edit', 1)
        ->set('editTitle', 'Woodex Interior — Lahore studio')
        ->set('editDescription', 'Interior design, fit-out and architecture in Lahore since 2016.')
        ->call('saveEdit');

    $seo = Page::query()->where('slug', '/')->first()->seo;
    expect($seo['title'])->toBe('Woodex Interior — Lahore studio')
        ->and($seo['description'])->toBe('Interior design, fit-out and architecture in Lahore since 2016.');
});

it('reports the whole site\'s health, and catches a redirect to nowhere', function () {
    $this->publishHomePage();
    Redirect::query()->create(['from_path' => '/old-page/', 'to_path' => '/does-not-exist/', 'code' => 301, 'note' => null]);

    Livewire::actingAs($this->editor)
        ->test(SiteHealthIndex::class)
        ->assertSee('Internal links that lead nowhere')
        ->assertSee('Redirects pointing at no page')
        ->assertSee('/old-page/ → /does-not-exist/')
        ->assertSee('robots.txt is generated')
        ->assertSee('sitemap.xml is generated')
        ->assertSee('finding(s)');
});

it('keeps the standalone screens behind the website permission', function () {
    $intruder = User::factory()->create(); // no website.edit

    foreach ([
        '/admin/website/seo-overview',
        '/admin/website/health',
    ] as $path) {
        $this->actingAs($intruder)->get($path)->assertForbidden();
        $this->actingAs($this->editor)->get($path)->assertOk();
    }
});

it('retires the screens that only wrote rows the files never read', function (string $url) {
    $owner = User::factory()->create();
    $owner->givePermissionTo(['website.edit']);

    $all = collect(AdminNavigation::for($owner))->flatMap(fn (array $group): array => $group['items'])->pluck('label');

    expect($all)
        ->not->toContain('Section library')
        ->not->toContain('Page templates');

    $this->actingAs($owner)->get($url)->assertNotFound();
})->with([
    '/admin/website/sections',
    '/admin/website/templates',
]);

it('retires the four database-only screens instead of leaving a destination that cannot reach the site', function (string $url) {
    $owner = User::factory()->create();
    $owner->givePermissionTo(['website.edit']);

    $all = collect(AdminNavigation::for($owner))->flatMap(fn (array $group): array => $group['items'])->pluck('label');

    expect($all)
        ->not->toContain('Service pages')
        ->not->toContain('City pages')
        ->not->toContain('FAQs')
        ->not->toContain('Testimonials');

    // The URLs are gone with the screens — not hidden rows that still write a page nobody serves.
    $this->actingAs($owner)->get($url)->assertNotFound();
})->with([
    '/admin/website/services',
    '/admin/website/cities',
    '/admin/website/faqs',
    '/admin/website/testimonials',
]);

it('keeps the screens that still have work to do', function (string $url) {
    $owner = User::factory()->create();
    $owner->givePermissionTo(['website.edit']);

    $this->actingAs($owner)->get($url)->assertOk();
})->with([
    '/admin/website/forms',
    '/admin/website/estimator',
]);

it('retires the home screens now that / is a file', function (string $url) {
    $owner = User::factory()->create();
    $owner->givePermissionTo(['website.edit']);

    $all = collect(AdminNavigation::for($owner))->flatMap(fn (array $group): array => $group['items'])->pluck('label');

    // The home page is public/index.html (§16.19 step 4): a screen that writes the CMS home row can no
    // longer put a word on the live page, so it is not a destination.
    expect($all)
        ->not->toContain('Hero slides')
        ->not->toContain('Client logos');

    $this->actingAs($owner)->get($url)->assertNotFound();
})->with([
    '/admin/website/heroes',
    '/admin/website/logos',
]);
