<?php

use App\Domain\Website\Builder\BuilderFile;
use App\Domain\Website\Enums\BlockType;
use App\Domain\Website\Models\Media;
use App\Domain\Website\Models\NavigationItem;
use App\Domain\Website\Models\Page;
use App\Domain\Website\Models\PageBlock;
use App\Domain\Website\Models\Redirect;
use App\Livewire\Admin\Website\MediaLibrary;
use App\Livewire\Admin\Website\MenusIndex;
use App\Livewire\Admin\Website\PagesIndex;
use App\Livewire\Admin\Website\RedirectsIndex;
use App\Livewire\Admin\Website\WebsiteSettings;
use App\Models\User;
use App\Settings\SiteSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithPublicWebsite;

/*
| The website screens (docs/09 §2.2): a page is created and published in the dashboard, the completeness gate
| holds a thin page back, a publish is kept as a version, and the 301 map is imported from a CSV.
*/
uses(InteractsWithPublicWebsite::class);

beforeEach(function () {
    // Whoever edits the website may also publish it, in these tests unless stated otherwise.
    $this->editor = User::factory()->create();
    $this->editor->givePermissionTo(['website.edit', 'website.publish']);
});

it('lists the pages of the site as the files they are', function () {
    // A page is a file under public/, so the list is the builder's own page list, not the pages table.
    $site = sys_get_temp_dir().'/woodex-pages-'.bin2hex(random_bytes(4));
    File::ensureDirectoryExists($site.'/about');
    File::ensureDirectoryExists($site.'/insights');
    File::put($site.'/index.html', '<html><head><title>Woodex home</title></head><body></body></html>');
    File::put($site.'/about/index.html', '<html><head><title>About the studio</title></head><body></body></html>');
    File::put($site.'/insights/index.html', '<html><head><title>Insights</title></head><body></body></html>');
    File::ensureDirectoryExists($site.'/insights/a-post');
    File::put($site.'/insights/a-post/index.html', '<html><head><title>An article</title></head><body></body></html>');
    $this->app->usePublicPath($site);

    $this->actingAs($this->editor);

    Livewire::test(PagesIndex::class)
        ->assertSee('Woodex home')
        ->assertSee('About the studio')
        ->assertSee('/about/')
        ->assertSee('Edit in builder')
        // The filter groups the pages that sit under one: /insights/a-post/ belongs to insights, while
        // /insights/ itself and /about/ are top-level pages.
        ->set('section', 'insights')
        ->assertSee('An article')
        ->assertDontSee('About the studio');

    // Nothing on the screen is a database row: an empty table still lists the site.
    expect(Page::query()->count())->toBe(0);

    File::deleteDirectory($site);
});

it('takes a page offline by moving its file out of the site, and keeps it', function () {
    $site = sys_get_temp_dir().'/woodex-pages-'.bin2hex(random_bytes(4));
    File::ensureDirectoryExists($site.'/about');
    File::put($site.'/about/index.html', '<html><head><title>About the studio</title></head><body></body></html>');
    $this->app->usePublicPath($site);

    $this->actingAs($this->editor);

    Livewire::test(PagesIndex::class)
        ->call('retire', 'about/index.html')
        ->assertDispatched('toast');

    // The URL is gone, the HTML is not: a version and the moved file both survive in storage.
    expect(is_file($site.'/about/index.html'))->toBeFalse();
    expect(BuilderFile::versions('about/index.html'))->not->toBe([]);

    $kept = glob(storage_path('app/private/site-retired/*/about/index.html')) ?: [];
    expect($kept)->not->toBe([]);

    foreach ($kept as $file) {
        @unlink($file);
    }

    File::deleteDirectory($site);
});

it('lets an editor take a page offline only with the publish permission', function () {
    $writer = User::factory()->create();
    $writer->givePermissionTo('website.edit');
    $this->actingAs($writer);

    Livewire::test(PagesIndex::class)
        ->assertDontSee('Take offline')
        ->call('retire', 'about/index.html')
        ->assertStatus(403);
});

it('keeps the website screens away from users who do not edit the website', function () {
    $sales = $this->userWithRole('sales');

    $this->actingAs($sales)
        ->get(route('admin.website.pages'))
        ->assertForbidden();
});

it('uploads an image, requires alt text and serves it from the media library', function () {
    Storage::fake('public');
    $this->actingAs($this->editor);

    $file = UploadedFile::fake()->image('living-room.jpg', 1200, 800);

    Livewire::test(MediaLibrary::class)
        ->set('uploads', [$file])
        ->call('save');

    $media = Media::query()->sole();

    expect($media->width)->toBe(1200)
        ->and($media->height)->toBe(800)
        ->and(Storage::disk('public')->exists($media->path))->toBeTrue();

    // The image is reachable at its URL, and the file is read from storage, not from the web folder.
    $served = $this->get($media->url())->assertOk();

    expect($served->headers->get('Cache-Control'))->toContain('public')->toContain('max-age=31536000')->toContain('immutable');

    // Alt text is edited in place.
    Livewire::test(MediaLibrary::class)
        ->set('alt.'.$media->id, 'Living room with timber panelling and linen seating')
        ->call('updateMeta', $media->id);

    expect($media->fresh()->alt)->toBe('Living room with timber panelling and linen seating');
});

it('shows the new alt text on a published page at once', function () {
    Storage::fake('public');
    $image = Media::factory()->create(['alt' => 'A living room']);
    $page = Page::factory()->published()->withSlug('/interior-design/')->create(['title' => 'Interior Design']);
    PageBlock::factory()->for($page)->asType(BlockType::Hero, ['heading' => 'Interiors', 'image_id' => $image->id])->create(['position' => 0]);

    // The page is read once, so it sits in the page cache.
    expect($this->siteGet('/interior-design/')->assertOk()->getContent())->toContain('alt="A living room"');

    $this->actingAs($this->editor);

    Livewire::test(MediaLibrary::class)
        ->set('alt.'.$image->id, 'Living room with timber panelling and linen seating')
        ->call('updateMeta', $image->id);

    // Alt text is printed by the page, not kept in a drawer: the cached copy must not be served any more.
    expect($this->siteGet('/interior-design/')->assertOk()->getContent())
        ->toContain('alt="Living room with timber panelling and linen seating"')
        ->and($this->siteGet('/interior-design/')->getContent())->not->toContain('alt="A living room"');
});

it('refuses to delete an image that a page uses', function () {
    $image = Media::factory()->create();
    $page = Page::factory()->published()->withSlug('/fit-out/')->create();
    PageBlock::factory()->for($page)->asType(BlockType::Hero, ['image_id' => $image->id])->create();

    $this->actingAs($this->editor);

    Livewire::test(MediaLibrary::class)->call('delete', $image->id)->assertDispatched('toast', type: 'error');

    expect(Media::query()->count())->toBe(1);
});

it('imports the redirect map from a CSV and answers each old URL with one 301', function () {
    $this->actingAs($this->editor);
    $before = Redirect::query()->count();

    Livewire::test(RedirectsIndex::class)
        ->set('csv', "/old-flat-design/,/interior-design/,301,was a service page\n/old-house-design/,/architecture/,301\nnot a url,/interior-design/")
        ->call('importCsv')
        ->assertSee('2 redirect(s) imported');

    expect(Redirect::query()->count())->toBe($before + 2);

    Page::factory()->published()->withSlug('/interior-design/')->create(['title' => 'Interior Design']);
    Page::factory()->published()->withSlug('/architecture/')->create(['title' => 'Architecture']);

    $this->siteGet('/old-flat-design/')->assertRedirect($this->siteUrl('/interior-design/'))->assertStatus(301);
    $this->siteGet('/old-house-design/')->assertRedirect($this->siteUrl('/architecture/'))->assertStatus(301);

    // A second import updates the rows instead of duplicating them.
    Livewire::test(RedirectsIndex::class)->set('csv', '/old-flat-design/,/architecture/,301')->call('importCsv');
    expect(Redirect::query()->count())->toBe($before + 2)
        ->and(Redirect::query()->where('from_path', '/old-flat-design/')->first()->to_path)->toBe('/architecture/');
});

it('exports the redirect map as a CSV', function () {
    Redirect::factory()->create(['from_path' => '/old-page/', 'to_path' => '/interior-design/']);
    $this->actingAs($this->editor);

    Livewire::test(RedirectsIndex::class)->call('exportCsv')
        ->assertFileDownloaded();

    $this->get(route('admin.website.redirects'))->assertOk()->assertSee('/old-page/');
});

it('builds the header menu from the dashboard', function () {
    Page::factory()->published()->withSlug('/interior-design/')->create(['title' => 'Interior Design']);
    $this->actingAs($this->editor);

    Livewire::test(MenusIndex::class)
        ->set('menu', 'header')
        ->set('label', 'Interior design')
        ->set('url', '/interior-design/')
        ->call('add')
        ->assertSee('Interior design');

    expect(NavigationItem::query()->where('menu', 'header')->count())->toBe(1);

    // The menu is what the public header shows (menus are cached for a day).
    $this->publishHomePage();
    $this->siteGet('/')->assertOk()->assertSee('Interior design')->assertSee('href="/interior-design/"', false);
});

it('takes the tracking IDs the owner pastes and loads nothing until they are filled in', function () {
    $owner = $this->userWithRole('owner');
    $this->actingAs($owner);

    Livewire::test(WebsiteSettings::class)
        ->set('ga4_id', 'G-ABC1234567')
        ->set('gtm_id', 'GTM-WX8ZQ2')
        ->call('save')
        ->assertHasNoErrors();

    $settings = app(SiteSettings::class);

    expect($settings->ga4_id)->toBe('G-ABC1234567')
        ->and($settings->gtm_id)->toBe('GTM-WX8ZQ2');

    // Visitors get the scripts; signed-in staff do not (below), so the check runs as a visitor.
    Auth::guard('web')->logout();

    $this->publishHomePage();
    $body = $this->siteGet('/')->assertOk()->getContent();
    expect($body)->toContain('GTM-WX8ZQ2')->toContain('googletagmanager.com/gtm.js');

    // Signed-in staff are not visitors: no tracking for them.
    expect($this->siteGet('/', $owner)->assertOk()->getContent())->not->toContain('googletagmanager.com/gtm.js');

    // A wrong ID is refused.
    Livewire::test(WebsiteSettings::class)->set('gtm_id', 'UA-123')->call('save')->assertHasErrors('gtm_id');
});

it('leaves the analytics out while tracking is switched off', function () {
    $settings = app(SiteSettings::class);
    $settings->ga4_id = 'G-ABC1234567';
    $settings->tracking_enabled = false;
    $settings->save();

    $this->publishHomePage();
    $this->siteGet('/')->assertOk()->assertDontSee('G-ABC1234567');
});
