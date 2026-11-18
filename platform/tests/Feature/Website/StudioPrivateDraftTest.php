<?php

declare(strict_types=1);

use App\Domain\Website\Enums\PageStatus;
use App\Domain\Website\Enums\PageType;
use App\Domain\Website\Models\Page;
use App\Domain\Website\Models\StudioDraft;
use App\Domain\Website\Studio\StudioContent;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function (): void {
    $this->owner = $this->userWithRole('owner');
    $this->actingAs($this->owner);
    $this->site = storage_path('framework/testing/private-draft-'.bin2hex(random_bytes(8)));
    foreach (['insights', 'projects'] as $folder) {
        mkdir($this->site.'/'.$folder.'/fixture', 0700, true);
        file_put_contents($this->site.'/'.$folder.'/fixture/index.html', '<!doctype html><html><head><title>Public baseline</title><link rel="canonical" href="https://woodex.com.pk/'.$folder.'/fixture/"></head><body><header>Original header</header><main>Original public content</main><footer>Original footer</footer></body></html>');
    }
    foreach (['insights', 'projects'] as $folder) {
        file_put_contents($this->site.'/'.$folder.'/index.html', '<!doctype html><html><head><title>Synthetic listing</title></head><body><main><h1>Listing</h1><div class="hx-cards"></div></main></body></html>');
    }
    $this->app->usePublicPath($this->site);
});

afterEach(function (): void {
    (new Filesystem)->deleteDirectory($this->site);
});

function privateDraftApi(array $payload)
{
    return test()->postJson(route('admin.website.studio.api'), $payload);
}

function privateDraftOpen(int $id, string $type = 'post'): array
{
    return privateDraftApi(['action' => 'cms_get', 'id' => $id, 'type' => $type])->assertJsonPath('ok', true)->json('item');
}

function privateDraftWrite(array $item, array $changes = []): array
{
    return array_replace(array_intersect_key($item, array_flip(['id', 'type', 'title', 'slug', 'data', 'seo', 'revision', 'token', 'base_hash'])), ['action' => 'cms_save'], $changes);
}

function privateDraftPage(string $type = 'post'): Page
{
    $page = Page::query()->create(['type' => $type === 'post' ? PageType::Insight : PageType::Project,
        'title' => 'Public baseline', 'slug' => '/'.($type === 'post' ? 'insights' : 'projects').'/fixture/',
        'status' => PageStatus::Published, 'seo' => ['description' => 'Original description']]);
    $page->blocks()->create(['type' => 'text', 'block_key' => 'bstubaseline', 'position' => 0, 'visible' => true,
        'data' => ['studio' => ['blocks' => [['t' => 'p', 'text' => 'Original public content']]]]]);

    return $page;
}

it('keeps saves by an editor private across files public rows blocks versions and listings', function (string $type): void {
    $page = privateDraftPage($type);
    $before = $page->fresh()->getAttributes();
    $blocks = $page->blocks()->get()->toArray();
    $folder = $type === 'post' ? 'insights' : 'projects';
    $file = $this->site.'/'.$folder.'/fixture/index.html';
    $bytes = file_get_contents($file);
    $this->actingAs($this->userWithRole('editor'));
    $item = privateDraftOpen($page->id, $type);
    $saved = privateDraftApi(privateDraftWrite($item, ['title' => 'Private title', 'seo' => ['desc' => 'Private description'],
        'data' => ['blocks' => [['t' => 'p', 'text' => 'Private replacement']]]]))->assertJsonPath('ok', true)->json('item');
    expect($page->fresh()->getAttributes())->toBe($before)
        ->and($page->blocks()->get()->toArray())->toBe($blocks)
        ->and($page->versions()->count())->toBe(0)
        ->and(file_get_contents($file))->toBe($bytes)
        ->and($saved['title'])->toBe('Private title')
        ->and($saved['seo']['desc'])->toBe('Private description')
        ->and($saved['has_draft'])->toBeTrue()
        ->and($saved['can_publish'])->toBeFalse();
    privateDraftApi(['action' => 'cms_published'] + $saved)->assertForbidden();
    privateDraftApi(['action' => 'cms_status', 'status' => 'draft'] + $saved)->assertForbidden();
})->with(['post', 'study']);

it('round trips both description keys without falling back to the standfirst', function (string $key): void {
    $item = privateDraftOpen(privateDraftPage()->id);
    $saved = privateDraftApi(privateDraftWrite($item, ['seo' => [$key => 'Exact search text'], 'data' => ['dek' => 'Different standfirst']]))->assertJsonPath('ok', true)->json('item');
    $again = privateDraftOpen($item['id']);
    expect($again['seo']['description'])->toBe('Exact search text')->and($again['seo']['desc'])->toBe('Exact search text');
    privateDraftApi(['action' => 'cms_published'] + $saved)->assertJsonPath('ok', true);
    expect(Page::query()->findOrFail($item['id'])->seo['description'])->toBe('Exact search text');
})->with(['desc', 'description']);

it('refuses stale saves publish withdrawal deletion and discard without changing private state', function (string $action): void {
    $page = privateDraftPage();
    $old = privateDraftOpen($page->id);
    $current = privateDraftApi(privateDraftWrite($old, ['title' => 'First writer']))->assertJsonPath('ok', true)->json('item');
    privateDraftApi(privateDraftWrite($old, ['action' => $action, 'status' => 'draft']))->assertJsonPath('ok', false);
    expect(privateDraftOpen($page->id)['token'])->toBe($current['token'])
        ->and(StudioDraft::query()->where('page_id', $page->id)->firstOrFail()->payload['title'])->toBe('First writer');
})->with(['cms_save', 'cms_published', 'cms_status', 'cms_delete', 'cms_discard']);

it('rejects missing and incorrect revision fields', function (string $field): void {
    $item = privateDraftOpen(privateDraftPage()->id);
    $payload = privateDraftWrite($item);
    unset($payload[$field]);
    privateDraftApi($payload)->assertJsonPath('ok', false);
    expect(StudioDraft::query()->count())->toBe(0);
})->with(['revision', 'token', 'base_hash']);

it('detects an outside public file edit before saving or publishing', function (string $action): void {
    $item = privateDraftOpen(privateDraftPage()->id);
    file_put_contents($this->site.'/insights/fixture/index.html', 'Outside writer');
    privateDraftApi(privateDraftWrite($item, ['action' => $action]))->assertJsonPath('ok', false);
    expect(file_get_contents($this->site.'/insights/fixture/index.html'))->toBe('Outside writer');
})->with(['cms_save', 'cms_published']);

it('detects outside database changes and permits explicit discard without overwriting them', function (): void {
    $page = privateDraftPage();
    $item = privateDraftOpen($page->id);
    $saved = privateDraftApi(privateDraftWrite($item, ['title' => 'Private replacement']))->assertJsonPath('ok', true)->json('item');
    $page->forceFill(['title' => 'Outside title'])->save();
    privateDraftApi(privateDraftWrite($saved))->assertJsonPath('ok', false);
    $discarded = privateDraftApi(['action' => 'cms_discard'] + $saved)->assertJsonPath('ok', true)->json('item');
    expect($discarded['title'])->toBe('Outside title')->and($discarded['has_draft'])->toBeFalse();
    privateDraftApi(privateDraftWrite($saved))->assertJsonPath('ok', false);
});

it('discards pending changes without touching the public page and prevents ABA tokens', function (): void {
    $page = privateDraftPage();
    $opened = privateDraftOpen($page->id);
    $bytes = file_get_contents($this->site.'/insights/fixture/index.html');
    $saved = privateDraftApi(privateDraftWrite($opened, ['title' => 'Pending']))->json('item');
    $discarded = privateDraftApi(['action' => 'cms_discard'] + $saved)->assertJsonPath('ok', true)->json('item');
    expect($discarded['title'])->toBe('Public baseline')->and($discarded['revision'])->toBeGreaterThan($saved['revision'])
        ->and(file_get_contents($this->site.'/insights/fixture/index.html'))->toBe($bytes);
    privateDraftApi(privateDraftWrite($opened))->assertJsonPath('ok', false);
    privateDraftApi(privateDraftWrite($discarded, ['title' => 'Next draft']))->assertJsonPath('ok', true);
});

it('keeps new drafts out of public blocks until explicit publish', function (): void {
    $saved = privateDraftApi(['action' => 'cms_save', 'type' => 'post', 'title' => 'Synthetic new article', 'slug' => 'synthetic-new',
        'data' => ['dek' => 'Private only', 'blocks' => [['t' => 'p', 'text' => 'Synthetic text']]], 'seo' => ['desc' => 'Private meta']])->assertJsonPath('ok', true)->json('item');
    $page = Page::query()->findOrFail($saved['id']);
    expect($page->status)->toBe(PageStatus::Draft)->and($page->blocks()->count())->toBe(0)->and($page->excerpt)->toBeNull()
        ->and(is_file($this->site.'/insights/synthetic-new/index.html'))->toBeFalse();
    privateDraftApi(['action' => 'cms_published'] + $saved)->assertJsonPath('ok', true);
    expect($page->refresh()->status)->toBe(PageStatus::Published)->and($page->blocks()->count())->toBe(1)
        ->and(file_get_contents($this->site.'/insights/synthetic-new/index.html'))->toContain('Synthetic text');
});

it('rejects live renames and collisions with unmanaged public files', function (): void {
    $item = privateDraftOpen(privateDraftPage()->id);
    privateDraftApi(privateDraftWrite($item, ['slug' => 'renamed']))->assertJsonPath('ok', false);
    privateDraftApi(['action' => 'cms_save', 'type' => 'post', 'title' => 'Collision', 'slug' => 'fixture'])->assertJsonPath('ok', false);
    expect(is_file($this->site.'/insights/fixture/index.html'))->toBeTrue();
});

it('refuses scheduling and malformed statuses rather than pretending to queue publication', function (): void {
    $item = privateDraftOpen(privateDraftPage()->id);
    privateDraftApi(privateDraftWrite($item, ['status' => 'scheduled']))->assertJsonPath('ok', false);
    privateDraftApi(['action' => 'cms_status', 'status' => 'nonsense'] + $item)->assertJsonPath('ok', false);
    expect(StudioDraft::query()->count())->toBe(0);
});

it('requires edit permission even when a domain method is called directly', function (): void {
    $user = $this->userWithRole('sales');
    expect(fn () => (new StudioContent($user))->save(['title' => 'Denied', 'slug' => 'denied']))
        ->toThrow(HttpException::class);
});

it('serves private authenticated responses with no-store and noindex', function (): void {
    $page = privateDraftPage();
    privateDraftApi(['action' => 'cms_get', 'id' => $page->id])->assertHeader('Cache-Control', 'no-store, private')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    auth()->logout();
    privateDraftApi(['action' => 'cms_get', 'id' => $page->id])->assertUnauthorized();
});

it('refuses a concurrent Studio writer while holding its nonblocking lock then releases cleanly', function (): void {
    $item = privateDraftOpen(privateDraftPage()->id);
    $handle = fopen(storage_path('app/studio-private/publisher.lock'), 'c');
    expect(flock($handle, LOCK_EX | LOCK_NB))->toBeTrue();
    try {
        privateDraftApi(privateDraftWrite($item))->assertJsonPath('ok', false);
        expect(StudioDraft::query()->count())->toBe(0);
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
    privateDraftApi(privateDraftWrite($item))->assertJsonPath('ok', true);
});

it('preserves private state and rolls back the database when publication fails before the file write', function (): void {
    $item = privateDraftApi(['action' => 'cms_save', 'type' => 'post', 'title' => 'Blocked synthetic page', 'slug' => 'blocked-synthetic',
        'data' => ['blocks' => [['t' => 'p', 'text' => 'Private text']]]])->assertJsonPath('ok', true)->json('item');
    file_put_contents($this->site.'/insights/blocked-synthetic', 'Not a directory');
    privateDraftApi(['action' => 'cms_published'] + $item)->assertJsonPath('ok', false);
    $page = Page::query()->findOrFail($item['id']);
    expect($page->status)->toBe(PageStatus::Draft)->and($page->blocks()->count())->toBe(0)
        ->and($page->versions()->count())->toBe(0)
        ->and(StudioDraft::query()->where('page_id', $page->id)->firstOrFail()->token)->toBe($item['token']);
});

it('does not overwrite an outside file created at a pending draft rename target', function (): void {
    $page = Page::query()->create(['type' => PageType::Insight, 'title' => 'Unpublished identity', 'slug' => '/insights/old-draft/', 'status' => PageStatus::Draft]);
    $item = privateDraftOpen($page->id);
    $saved = privateDraftApi(privateDraftWrite($item, ['slug' => 'new-draft']))->assertJsonPath('ok', true)->json('item');
    mkdir($this->site.'/insights/new-draft', 0700, true);
    file_put_contents($this->site.'/insights/new-draft/index.html', 'Outside new page');
    privateDraftApi(['action' => 'cms_published'] + $saved)->assertJsonPath('ok', false);
    expect(file_get_contents($this->site.'/insights/new-draft/index.html'))->toBe('Outside new page')
        ->and($page->refresh()->slug)->toBe('/insights/old-draft/');
});

it('reserves pending addresses between private drafts and releases a reservation on discard', function (): void {
    $page = Page::query()->create(['type' => PageType::Insight, 'title' => 'Unpublished identity', 'slug' => '/insights/old-draft/', 'status' => PageStatus::Draft]);
    $saved = privateDraftApi(privateDraftWrite(privateDraftOpen($page->id), ['slug' => 'reserved-draft']))->assertJsonPath('ok', true)->json('item');
    $create = ['action' => 'cms_save', 'type' => 'post', 'slug' => 'reserved-draft', 'title' => 'Another draft'];
    privateDraftApi($create)->assertJsonPath('ok', false);
    privateDraftApi(['action' => 'cms_discard'] + $saved)->assertJsonPath('ok', true);
    privateDraftApi($create)->assertJsonPath('ok', true);
});
