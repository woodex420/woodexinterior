<?php

use App\Domain\Website\Enums\BlockType;
use App\Domain\Website\Enums\PageStatus;
use App\Domain\Website\Models\Media;
use App\Domain\Website\Models\NavigationItem;
use App\Domain\Website\Models\Page;
use App\Domain\Website\Models\PageBlock;
use App\Domain\Website\Models\Redirect;
use App\Livewire\Admin\Website\MediaLibrary;
use App\Livewire\Admin\Website\MenusIndex;
use App\Livewire\Admin\Website\PageEditor;
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

it('lists the pages of the website', function () {
    Page::factory()->published()->withSlug('/interior-design/')->create(['title' => 'Interior Design']);
    Page::factory()->withSlug('/draft-page/')->create(['title' => 'Still a draft']);

    $this->actingAs($this->editor);

    Livewire::test(PagesIndex::class)
        ->assertSee('Interior Design')
        ->assertSee('/interior-design/')
        ->assertSee('Still a draft')
        ->call('unpublish', Page::query()->where('slug', '/interior-design/')->first()->id)
        ->assertDispatched('toast');

    expect(Page::query()->where('slug', '/interior-design/')->first()->status)->toBe(PageStatus::Draft);
});

it('keeps the website screens away from users who do not edit the website', function () {
    $sales = $this->userWithRole('sales');

    $this->actingAs($sales)
        ->get(route('admin.website.pages'))
        ->assertForbidden();
});

it('lets an editor change a page but not publish it', function () {
    $page = Page::factory()->withSlug('/interior-design/')->create(['title' => 'Interior Design']);

    $writer = User::factory()->create();
    $writer->givePermissionTo('website.edit');
    $this->actingAs($writer);

    // The screen opens, the draft is saved…
    Livewire::test(PageEditor::class, ['page' => $page])
        ->set('title', 'Interior Design, Lahore')
        ->call('save', false)
        ->assertDispatched('toast');

    expect($page->fresh()->title)->toBe('Interior Design, Lahore')
        ->and($page->fresh()->status)->toBe(PageStatus::Draft);

    // …but publishing needs the second permission.
    Livewire::test(PageEditor::class, ['page' => $page->fresh()])->call('save', true)->assertStatus(403);
});

it('creates a page, adds sections and publishes it', function () {
    $this->actingAs($this->editor);

    Livewire::test(PageEditor::class)
        ->set('title', 'Interior Design in Lahore')
        ->set('slug', '/interior-design/')
        ->set('excerpt', 'Design, drawings and site supervision for homes and workplaces in Lahore, from one team.')
        ->set('seoTitle', 'Interior Design in Lahore | Woodex Interior')
        ->set('seoDescription', 'Interior design in Lahore: concept, drawings, 3D views, approvals and site supervision for homes, offices and retail, delivered by one studio team.')
        ->call('addBlock', BlockType::Hero->value)
        ->call('addBlock', BlockType::Faq->value)
        ->call('save', true)
        ->assertRedirect(route('admin.website.pages.edit', Page::query()->sole()));

    $page = Page::query()->sole();

    expect($page->status)->toBe(PageStatus::Published)
        ->and($page->slug)->toBe('/interior-design/')
        ->and($page->blocks()->count())->toBe(2)
        ->and($page->versions()->count())->toBe(1);

    // The page is live at its URL.
    $this->siteGet('/interior-design/')->assertOk()->assertSee('Interior Design in Lahore');
});

it('holds back a page that is not ready to publish', function () {
    $this->actingAs($this->editor);

    Livewire::test(PageEditor::class)
        ->set('title', 'Fit-Out')
        ->set('slug', '/fit-out/')
        ->call('save', true)
        ->assertSet('issues.0', 'Add an SEO title (the blue link in search results).');

    expect(Page::query()->count())->toBe(0);
});

it('will not publish a page whose images have no alt text', function () {
    $image = Media::factory()->create(['alt' => '']);
    $this->actingAs($this->editor);

    $test = Livewire::test(PageEditor::class)
        ->set('title', 'Renovation')
        ->set('slug', '/renovation/')
        ->set('excerpt', 'Renovation of homes and offices in Lahore, carried out by one team from survey to handover.')
        ->set('seoTitle', 'Renovation in Lahore')
        ->set('seoDescription', 'Renovation in Lahore for homes, offices and shops: survey, drawings, approvals, execution and handover by one studio team.')
        ->call('addBlock', BlockType::Hero->value)
        ->set('blocks.0.data.image_id', (string) $image->id)
        ->call('addBlock', BlockType::Faq->value)
        ->call('save', true);

    expect($test->get('issues'))->toContain('Alt text is missing for the image '.basename($image->path).'.')
        ->and(Page::query()->count())->toBe(0);

    // Give the image a description and the page goes live.
    $image->update(['alt' => 'Renovated drawing room with lime plaster walls and oak flooring']);

    Livewire::test(PageEditor::class)
        ->set('title', 'Renovation')
        ->set('slug', '/renovation/')
        ->set('excerpt', 'Renovation of homes and offices in Lahore, carried out by one team from survey to handover.')
        ->set('seoTitle', 'Renovation in Lahore')
        ->set('seoDescription', 'Renovation in Lahore for homes, offices and shops: survey, drawings, approvals, execution and handover by one studio team.')
        ->call('addBlock', BlockType::Hero->value)
        ->set('blocks.0.data.image_id', (string) $image->id)
        ->call('addBlock', BlockType::Faq->value)
        ->call('save', true)
        ->assertRedirect(route('admin.website.pages.edit', Page::query()->sole()));
});

it('saves a draft with anything in it, and only publishing is checked', function () {
    $this->actingAs($this->editor);

    Livewire::test(PageEditor::class)
        ->set('title', 'Architecture')
        ->set('slug', '/architecture/')
        ->call('save', false)
        ->assertRedirect(route('admin.website.pages.edit', Page::query()->sole()));

    expect(Page::query()->sole()->status)->toBe(PageStatus::Draft);
});

it('edits the sections of a page, reorders them and hides one', function () {
    $page = Page::factory()->published()->withSlug('/fit-out/')->create(['title' => 'Fit-Out']);
    PageBlock::factory()->for($page)->asType(BlockType::Text, ['heading' => 'First'])->create(['position' => 0]);
    PageBlock::factory()->for($page)->asType(BlockType::Text, ['heading' => 'Second'])->create(['position' => 1]);

    $this->actingAs($this->editor);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->assertSet('blocks.0.data.heading', 'First')
        ->call('moveBlock', 0, 1)
        ->assertSet('blocks.0.data.heading', 'Second')
        ->call('toggleBlock', 1)
        ->call('addItem', 0, 'items')
        ->call('save', false);

    $blocks = $page->fresh()->blocks;

    expect($blocks[0]->data['heading'])->toBe('Second')
        ->and($blocks[1]->visible)->toBeFalse();
});

it('removes a section and keeps the rest', function () {
    $page = Page::factory()->published()->withSlug('/fit-out/')->create(['title' => 'Fit-Out']);
    PageBlock::factory()->for($page)->asType(BlockType::Text, ['heading' => 'Keep me'])->create(['position' => 0]);
    PageBlock::factory()->for($page)->asType(BlockType::Text, ['heading' => 'Delete me'])->create(['position' => 1]);

    $this->actingAs($this->editor);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->call('removeBlock', 1)
        ->call('save', false);

    expect($page->fresh()->blocks->pluck('data.heading')->all())->toBe(['Keep me']);
});

it('restores an earlier version of a page', function () {
    $page = Page::factory()->published()->withSlug('/furniture/')->create([
        'title' => 'Furniture',
        'excerpt' => 'Custom furniture made and fitted in Lahore.',
        'seo' => ['title' => 'Furniture', 'description' => 'Custom furniture in Lahore: wardrobes, kitchens, vanities and joinery, designed, made in our own workshop and fitted by our team.'],
    ]);
    PageBlock::factory()->for($page)->asType(BlockType::Text, ['heading' => 'Original copy'])->create(['position' => 0]);
    PageBlock::factory()->for($page)->asType(BlockType::Faq, ['items' => [['question' => 'How long?', 'answer' => 'Three to five weeks.']]])->create(['position' => 1]);

    $this->actingAs($this->editor);

    // Publish once so there is a version to go back to.
    Livewire::test(PageEditor::class, ['page' => $page])->call('save', true)->assertRedirect(route('admin.website.pages.edit', $page));

    $page->blocks()->first()->update(['data' => ['heading' => 'Rewritten copy', 'body' => '']]);
    $page->update(['title' => 'Furniture & joinery']);

    $version = $page->versions()->latest('id')->first();

    Livewire::test(PageEditor::class, ['page' => $page->fresh()])
        ->call('restore', $version->id)
        ->assertDispatched('toast');

    expect($page->fresh()->title)->toBe('Furniture')
        ->and($page->fresh()->blocks->first()->data['heading'])->toBe('Original copy');
});

it('duplicates a page with its sections', function () {
    $page = Page::factory()->published()->withSlug('/interior-design/')->create(['title' => 'Interior Design']);
    PageBlock::factory()->for($page)->asType(BlockType::Text, ['heading' => 'Shared copy'])->create(['position' => 0]);

    $this->actingAs($this->editor);

    Livewire::test(PagesIndex::class)->call('duplicate', $page->id)->assertRedirect(route('admin.website.pages.edit', 2));

    $copy = Page::query()->find(2);

    expect($copy->title)->toBe('Interior Design (copy)')
        ->and($copy->slug)->toBe('/interior-design-copy/')
        ->and($copy->status)->toBe(PageStatus::Draft)
        ->and($copy->blocks->first()->data['heading'])->toBe('Shared copy');
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

    Livewire::test(RedirectsIndex::class)
        // URLs of their own: the 54 redirects of the old site are seeded by a migration (LegacyUrlParityTest).
        ->set('csv', "/old-flat-design/,/interior-design/,301,was a service page\n/old-house-design/,/architecture/,301\nnot a url,/interior-design/")
        ->call('importCsv')
        ->assertSee('2 redirect(s) imported');

    $seeded = Redirect::query()->count() - 2;
    expect($seeded)->toBeGreaterThan(0);

    Page::factory()->published()->withSlug('/interior-design/')->create(['title' => 'Interior Design']);
    Page::factory()->published()->withSlug('/architecture/')->create(['title' => 'Architecture']);

    $this->siteGet('/old-flat-design/')->assertRedirect($this->siteUrl('/interior-design/'))->assertStatus(301);
    $this->siteGet('/old-house-design/')->assertRedirect($this->siteUrl('/architecture/'))->assertStatus(301);

    // A second import updates the rows instead of duplicating them.
    Livewire::test(RedirectsIndex::class)->set('csv', '/old-flat-design/,/architecture/,301')->call('importCsv');
    expect(Redirect::query()->count())->toBe($seeded + 2)
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
