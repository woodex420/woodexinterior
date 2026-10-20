<?php

declare(strict_types=1);

use App\Domain\Website\Builder\ArticleFile;
use App\Domain\Website\Enums\PageStatus;
use App\Domain\Website\Enums\PageType;
use App\Domain\Website\Models\Page;

/*
| Blog and Portfolio used to write rows only, and rows stopped being a page when the renderer was retired
| (docs/audit/20): a "published" article appeared in the sitemap and answered 404. These tests hold the
| rewrite — saving and publishing write the page file, taking it off the site moves the file away, and a
| renamed article does not keep answering at its old address.
*/

beforeEach(function () {
    $this->owner = $this->userWithRole('owner');
    $this->actingAs($this->owner);

    // Never write into the shipped pages: the site folder is a temporary tree with one shell per kind.
    $this->site = storage_path('framework/testing/site-'.uniqid());
    mkdir($this->site.'/insights/template', 0o775, true);
    mkdir($this->site.'/projects/template', 0o775, true);

    file_put_contents($this->site.'/insights/template/index.html', <<<'HTML'
<!doctype html><html lang="en"><head>
<title>Template insight | Woodex</title>
<meta name="description" content="Template description" />
<link rel="canonical" href="https://woodex.com.pk/insights/template/" />
<meta property="og:title" content="Template insight | Woodex" />
<meta property="og:description" content="Template description" />
<meta property="og:image" content="https://woodex.com.pk/assets/img/template.webp" />
<meta name="twitter:title" content="Template insight | Woodex" />
<meta name="twitter:description" content="Template description" />
<meta name="twitter:image" content="https://woodex.com.pk/assets/img/template.webp" />
</head><body><header id="site-header">Woodex</header>
<main id="main-content"><h1>Template</h1></main>
<footer id="site-footer">Footer</footer></body></html>
HTML);

    file_put_contents($this->site.'/projects/template/index.html', str_replace(
        ['insights/template/', 'Template insight'],
        ['projects/template/', 'Template study'],
        (string) file_get_contents($this->site.'/insights/template/index.html'),
    ));

    // The whole site — reads and writes — moves to the temporary tree for this test (same as CmsPageTest).
    $this->app->usePublicPath($this->site);
});

afterEach(function () {
    articleRemoveTree($this->site);

    foreach (['insights', 'projects'] as $folder) {
        foreach (glob(storage_path('app/studio-retired/'.$folder.'/*/*.html')) ?: [] as $kept) {
            @unlink($kept);
            @rmdir(dirname($kept));
        }
    }
});

/** @param array<string, mixed> $payload */
function articleApi(array $payload)
{
    return test()->postJson(route('admin.website.studio.api'), $payload);
}

it('does not write a file for a draft', function () {
    articleApi([
        'action' => 'cms_save',
        'type' => 'post',
        'title' => 'Still a draft',
        'slug' => 'still-a-draft',
        'data' => ['blocks' => [['t' => 'p', 'text' => 'Not live yet.']]],
        'seo' => [],
    ])->assertOk()->assertJsonPath('ok', true);

    expect(is_file($this->site.'/insights/still-a-draft/index.html'))->toBeFalse();
});

it('publishes an article into its page file, in the design shell', function () {
    articleApi([
        'action' => 'cms_save',
        'type' => 'post',
        'title' => 'Kitchen costs in Lahore',
        'slug' => 'kitchen-costs',
        'data' => [
            'kicker' => 'Cost guide',
            'dek' => 'What drives the price, room by room.',
            'blocks' => [
                ['t' => 'h', 'text' => 'The numbers'],
                ['t' => 'p', 'text' => 'Joinery is **most** of it.'],
                ['t' => 'list', 'items' => ['Carcasses', 'Worktops'], 'style' => 'ul'],
            ],
            'meta' => [['k' => 'Read time', 'v' => '6 min read']],
            'faqs' => [['q' => 'Is it fixed?', 'a' => 'The fee is agreed before work starts.']],
        ],
        'seo' => ['title' => 'Kitchen costs in Lahore | Woodex', 'description' => 'What a kitchen costs.'],
    ])->assertOk()->assertJsonPath('ok', true);

    $id = (int) Page::query()->where('slug', '/insights/kitchen-costs/')->value('id');

    articleApi(['action' => 'cms_published', 'id' => $id, 'type' => 'post'])->assertOk()->assertJsonPath('ok', true);

    $file = $this->site.'/insights/kitchen-costs/index.html';

    expect(is_file($file))->toBeTrue();

    $html = (string) file_get_contents($file);

    // The design's own chrome is kept; only the page's head values and main change.
    expect($html)->toContain('<header id="site-header">')
        ->and($html)->toContain('<footer id="site-footer">')
        ->and($html)->toContain('<title>Kitchen costs in Lahore | Woodex</title>')
        ->and($html)->toContain('<meta name="description" content="What a kitchen costs." />')
        ->and($html)->toContain('href="https://woodex.com.pk/insights/kitchen-costs/"')
        ->and($html)->not->toContain('insights/template/');

    // The article as the editor wrote it.
    expect($html)->toContain('<h1 id="dx-title">Kitchen costs in Lahore</h1>')
        ->and($html)->toContain('<p class="dx-kicker">Cost guide</p>')
        ->and($html)->toContain('What drives the price, room by room.')
        ->and($html)->toContain('<h2 id="s-1-the-numbers">The numbers</h2>')
        ->and($html)->toContain('Joinery is <strong>most</strong> of it.')
        ->and($html)->toContain('<li>Carcasses</li>')
        ->and($html)->toContain('Is it fixed?')
        ->and($html)->toContain('The fee is agreed before work starts.')
        ->and($html)->toContain('Read time');
});

it('writes the file when a published article is saved again', function () {
    $page = Page::query()->create([
        'type' => PageType::Insight,
        'slug' => '/insights/live-edit/',
        'title' => 'Live edit',
        'status' => PageStatus::Published,
    ]);

    articleApi([
        'action' => 'cms_save',
        'id' => $page->id,
        'type' => 'post',
        'title' => 'Live edit',
        'slug' => 'live-edit',
        'data' => ['blocks' => [['t' => 'p', 'text' => 'The wording the editor just changed.']]],
        'seo' => [],
    ])->assertOk()->assertJsonPath('ok', true);

    $file = $this->site.'/insights/live-edit/index.html';

    expect(is_file($file))->toBeTrue()
        ->and((string) file_get_contents($file))->toContain('The wording the editor just changed.');
});

it('takes the file away when the page is taken off the site, and keeps a copy', function () {
    $page = Page::query()->create([
        'type' => PageType::Insight,
        'slug' => '/insights/off-the-air/',
        'title' => 'Off the air',
        'status' => PageStatus::Draft,
    ]);

    articleApi(['action' => 'cms_published', 'id' => $page->id, 'type' => 'post'])->assertOk();
    expect(is_file($this->site.'/insights/off-the-air/index.html'))->toBeTrue();

    articleApi(['action' => 'cms_status', 'id' => $page->id, 'type' => 'post', 'status' => 'draft'])
        ->assertOk()
        ->assertJsonPath('item.status', 'draft');

    $kept = glob(storage_path('app/studio-retired/insights/off-the-air/*.html')) ?: [];

    expect(is_file($this->site.'/insights/off-the-air/index.html'))->toBeFalse()
        ->and($kept)->toHaveCount(1)
        ->and((string) file_get_contents($kept[0]))->toContain('Off the air');
});

it('stops answering at the old address when an article is renamed', function () {
    $page = Page::query()->create([
        'type' => PageType::Insight,
        'slug' => '/insights/old-address/',
        'title' => 'Old address',
        'status' => PageStatus::Published,
    ]);

    articleApi(['action' => 'cms_published', 'id' => $page->id, 'type' => 'post'])->assertOk();
    expect(is_file($this->site.'/insights/old-address/index.html'))->toBeTrue();

    articleApi([
        'action' => 'cms_save',
        'id' => $page->id,
        'type' => 'post',
        'title' => 'Old address',
        'slug' => 'new-address',
        'data' => ['blocks' => [['t' => 'p', 'text' => 'Moved.']]],
        'seo' => [],
    ])->assertOk()->assertJsonPath('ok', true);

    expect(is_file($this->site.'/insights/old-address/index.html'))->toBeFalse()
        ->and(is_file($this->site.'/insights/new-address/index.html'))->toBeTrue();
});

it('writes a study into /projects/', function () {
    articleApi([
        'action' => 'cms_save',
        'type' => 'study',
        'title' => 'A clinic that calms',
        'slug' => 'clinic',
        'data' => ['kicker' => 'Design study', 'blocks' => [['t' => 'p', 'text' => 'Quiet rooms.']]],
        'seo' => [],
    ])->assertOk();

    $id = (int) Page::query()->where('slug', '/projects/clinic/')->value('id');

    articleApi(['action' => 'cms_published', 'id' => $id, 'type' => 'study'])->assertOk();

    $file = $this->site.'/projects/clinic/index.html';

    expect(is_file($file))->toBeTrue()
        ->and((string) file_get_contents($file))->toContain('<h1 id="dx-title">A clinic that calms</h1>')
        ->and((string) file_get_contents($file))->toContain('Quiet rooms.')
        ->and(ArticleFile::pathFor(Page::query()->findOrFail($id), 'study'))->toBe('projects/clinic/index.html');
});

it('never advertises a published row that has no file', function () {
    Page::query()->create([
        'type' => PageType::Insight,
        'slug' => '/insights/ghost-row/',
        'title' => 'Ghost row',
        'status' => PageStatus::Published,
    ]);

    $this->get('/sitemap.xml')->assertOk()->assertDontSee('/insights/ghost-row/', false);
});

/** Remove a temporary tree. */
function articleRemoveTree(string $path): void
{
    if (! is_dir($path)) {
        return;
    }

    foreach (glob($path.'/*') ?: [] as $entry) {
        is_dir($entry) ? articleRemoveTree($entry) : @unlink($entry);
    }

    @rmdir($path);
}
