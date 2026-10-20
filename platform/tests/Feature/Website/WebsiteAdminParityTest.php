<?php

use App\Domain\Website\Enums\BlockType;
use App\Domain\Website\Models\Page;
use App\Domain\Website\Models\Redirect;
use App\Domain\Website\Models\SavedSection;
use App\Livewire\Admin\Website\HeroSlidesIndex;
use App\Livewire\Admin\Website\SectionLibraryIndex;
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
    expect($labels('Pages & builder'))->toBe([
        'All pages', 'Page builder', 'Section library', 'Header & footer', 'Hero slides', 'Redirects',
    ])->and($labels('Content'))->toBe([
        'Blog', 'Portfolio', 'Service pages', 'City pages', 'Page templates', 'Forms', 'Estimator',
        'FAQs', 'Testimonials', 'Client logos', 'Team',
    ])->and($labels(null))->toBe([
        'Media', 'SEO', 'SEO agent', 'Speed', 'Site health', 'Theme', 'Website Settings',
    ]);

    // The six screens that are not ported yet are listed disabled, so the section matches the target
    // without pretending they work.
    $planned = collect($groups->firstWhere('title', 'Content')['items'])
        ->whereNull('route')
        ->pluck('phase')
        ->unique()
        ->all();

    expect($planned)->toBe(['soon']);
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

it('shows the section library standalone, renames and retires saved sections', function () {
    SavedSection::query()->create([
        'name' => 'Lahore gallery, tuned',
        'type' => BlockType::Gallery,
        'data' => ['heading' => 'Lahore', 'images' => []],
        'created_by' => $this->editor->id,
    ]);

    Livewire::actingAs($this->editor)
        ->test(SectionLibraryIndex::class)
        ->assertSee('Lahore gallery, tuned')
        ->assertSee(BlockType::Gallery->label())
        ->call('rename', 1)
        ->set('renameName', 'Lahore gallery, final')
        ->call('saveRename')
        ->assertDontSee('Lahore gallery, tuned');

    expect(SavedSection::query()->first()->name)->toBe('Lahore gallery, final');

    Livewire::actingAs($this->editor)
        ->test(SectionLibraryIndex::class)
        ->call('delete', 1);

    expect(SavedSection::query()->count())->toBe(0);
});

it('manages the home page hero slides on their own screen', function () {
    $home = $this->publishHomePage();
    $home->blocks()->create([
        'type' => BlockType::Slider,
        'block_key' => 'bhero01',
        'position' => 0,
        'visible' => true,
        'data' => ['slides' => [
            ['image_id' => null, 'kicker' => 'Lahore', 'heading' => 'Spaces', 'text' => '', 'display' => '', 'primary_label' => '', 'primary_url' => '', 'secondary_label' => '', 'secondary_url' => ''],
            ['image_id' => null, 'kicker' => 'Since 2016', 'heading' => 'Purpose', 'text' => '', 'display' => '', 'primary_label' => '', 'primary_url' => '', 'secondary_label' => '', 'secondary_url' => ''],
        ]],
    ]);

    $component = Livewire::actingAs($this->editor)
        ->test(HeroSlidesIndex::class)
        ->assertSet('homeId', $home->id)
        ->assertSee('Slide 1')
        ->assertSee('Slide 2')
        ->call('addSlide')
        ->assertSet('slides', fn (array $slides): bool => count($slides) === 3)
        ->call('removeSlide', 2)
        ->call('moveSlide', 1, -1)
        ->call('save', false);

    $block = $home->fresh(['blocks'])->blocks->first(fn ($block) => $block->type === BlockType::Slider);
    expect($block->data['slides'][0]['kicker'])->toBe('Since 2016') // the move held through the save
        ->and($home->fresh()->blocks->count())->toBe(1);

    // Publishing is its own permission.
    $plainEditor = User::factory()->create();
    $plainEditor->givePermissionTo(['website.edit']);

    Livewire::actingAs($plainEditor)
        ->test(HeroSlidesIndex::class)
        ->call('save', true)
        ->assertStatus(403);
});

it('creates the hero slider when the home page has none yet', function () {
    $home = $this->publishHomePage();

    Livewire::actingAs($this->editor)
        ->test(HeroSlidesIndex::class)
        ->assertSee('No hero slider yet')
        ->call('addSlide')
        ->set('slides.0.heading', 'Spaces shaped by purpose')
        ->call('save', true);

    $block = $home->fresh(['blocks'])->blocks->firstWhere('type', BlockType::Slider->value);
    expect($block)->not->toBeNull()
        ->and($block->data['slides'][0]['heading'])->toBe('Spaces shaped by purpose');
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

it('keeps the four screens behind the website permission', function () {
    $intruder = User::factory()->create(); // no website.edit

    foreach ([
        '/admin/website/sections',
        '/admin/website/heroes',
        '/admin/website/seo-overview',
        '/admin/website/health',
    ] as $path) {
        $this->actingAs($intruder)->get($path)->assertForbidden();
        $this->actingAs($this->editor)->get($path)->assertOk();
    }
});
