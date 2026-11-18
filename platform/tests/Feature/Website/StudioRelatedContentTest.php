<?php

declare(strict_types=1);

use App\Domain\Website\Enums\PageStatus;
use App\Domain\Website\Enums\PageType;
use App\Domain\Website\Models\Page;
use App\Domain\Website\Models\StudioDraft;
use Illuminate\Filesystem\Filesystem;

beforeEach(function (): void {
    $this->actingAs($this->userWithRole('owner'));
    $this->site = storage_path('framework/testing/related-preview-'.bin2hex(random_bytes(8)));
    foreach (['insights', 'projects'] as $folder) {
        mkdir($this->site.'/'.$folder.'/template', 0700, true);
        file_put_contents($this->site.'/'.$folder.'/template/index.html', '<!doctype html><html><head><title>Shell</title><link rel="canonical" href="https://woodex.com.pk/'.$folder.'/template/"></head><body><header>Unchanged synthetic chrome</header><main>Shell</main><footer>Unchanged footer</footer></body></html>');
    }
    foreach (['insights', 'projects'] as $folder) {
        file_put_contents($this->site.'/'.$folder.'/index.html', '<!doctype html><html><head><title>Synthetic listing</title></head><body><main><h1>Listing</h1><div class="hx-cards"></div></main></body></html>');
    }
    $this->app->usePublicPath($this->site);
});

afterEach(function (): void {
    (new Filesystem)->deleteDirectory($this->site);
});

function relatedApi(array $payload)
{
    return test()->postJson(route('admin.website.studio.api'), $payload);
}

function relatedSave(array $data, string $type = 'post'): array
{
    return relatedApi(['action' => 'cms_save', 'title' => 'Synthetic parent', 'slug' => 'related-parent', 'type' => $type, 'data' => $data])->assertJsonPath('ok', true)->json('item');
}

function relatedSource(string $slug, string $type = 'post', array $data = []): Page
{
    $folder = $type === 'post' ? 'insights' : 'projects';
    $page = Page::query()->create(['type' => $type === 'post' ? PageType::Insight : PageType::Project,
        'slug' => '/'.$folder.'/'.$slug.'/', 'title' => 'Public '.$slug, 'status' => PageStatus::Published,
        'published_at' => now()->subDay(), 'excerpt' => 'Public excerpt '.$slug]);
    $page->blocks()->create(['block_key' => 'bstu'.$page->id, 'type' => 'text', 'position' => 1, 'data' => ['studio' => $data]]);
    $dir = test()->site.'/'.$folder.'/'.$slug;
    mkdir($dir, 0700, true);
    file_put_contents($dir.'/index.html', '<html><main>Public source '.$slug.'</main></html>');

    return $page;
}

it('uses selected order and only published card data through Preview and exact first publish and republish', function (string $type): void {
    $first = relatedSource('alpha', $type, ['card' => ['src' => '/assets/alpha.webp', 'alt' => 'Public "alpha"', 'text' => 'Public alpha card']]);
    $second = relatedSource('beta', $type, ['hero' => ['src' => '/assets/beta.webp'], 'dek' => 'Public beta standfirst']);
    relatedSource('not-selected', $type);
    $source = relatedApi(['action' => 'cms_get', 'id' => $first->id, 'type' => $type])->json('item');
    relatedApi(['action' => 'cms_save', 'title' => 'PRIVATE source title', 'data' => ['card' => ['src' => '/assets/private.webp', 'text' => 'PRIVATE card']]] + $source)->assertJsonPath('ok', true);
    $item = relatedSave(['related' => ['beta', 'alpha'], 'relatedTitle' => 'Chosen <reading> $1'], $type);
    $folder = $type === 'post' ? 'insights' : 'projects';
    $path = $this->site.'/'.$folder.'/related-parent/index.html';
    for ($round = 0; $round < 2; $round++) {
        $parent = Page::query()->findOrFail($item['id']);
        $before = $parent->getAttributes();
        $blocks = $parent->blocks()->get()->toArray();
        $draft = StudioDraft::query()->where('page_id', $parent->id)->firstOrFail()->getAttributes();
        $public = is_file($path) ? file_get_contents($path) : null;
        $this->actingAs($this->userWithRole('editor'));
        $preview = relatedApi(['action' => 'cms_preview'] + $item)->assertJsonPath('ok', true)->json();
        expect($preview['html'])->toContain('Chosen &lt;reading&gt; $1', '/'.$folder.'/beta/', '/'.$folder.'/alpha/', '/assets/alpha.webp',
            'Public &quot;alpha&quot;', 'Public alpha card', '/assets/beta.webp', 'Public beta standfirst', 'width="1920" height="1280"')
            ->not->toContain('PRIVATE', '/not-selected/')
            ->and(strpos($preview['html'], '/'.$folder.'/beta/'))->toBeLessThan(strpos($preview['html'], '/'.$folder.'/alpha/'))
            ->and($parent->fresh()->getAttributes())->toBe($before)
            ->and($parent->blocks()->get()->toArray())->toBe($blocks)
            ->and(StudioDraft::query()->where('page_id', $parent->id)->firstOrFail()->getAttributes())->toBe($draft)
            ->and(is_file($path) ? file_get_contents($path) : null)->toBe($public)
            ->and(strlen($preview['dependency_hash']))->toBe(64);
        relatedApi(['action' => 'cms_published', 'preview_hash' => $preview['html_hash'], 'dependency_hash' => $preview['dependency_hash']] + $item)->assertForbidden();
        $this->actingAs($this->userWithRole('owner'));
        $item = relatedApi(['action' => 'cms_published', 'preview_hash' => $preview['html_hash'], 'dependency_hash' => $preview['dependency_hash']] + $item)->assertJsonPath('ok', true)->json('item');
        expect(file_get_contents($path))->toBe($preview['html']);
        $item = relatedApi(['action' => 'cms_save'] + $item)->assertJsonPath('ok', true)->json('item');
    }
})->with(['post', 'study']);

it('refuses invalid selections without applying or deleting private drafts', function (array $selected): void {
    relatedSource('alpha');
    relatedSource('other-kind', 'study');
    $item = relatedSave(['related' => $selected]);
    $before = Page::query()->findOrFail($item['id'])->getAttributes();
    relatedApi(['action' => 'cms_preview'] + $item)->assertJsonPath('ok', false);
    relatedApi(['action' => 'cms_published'] + $item)->assertJsonPath('ok', false);
    expect(Page::query()->findOrFail($item['id'])->getAttributes())->toBe($before)
        ->and(StudioDraft::query()->where('page_id', $item['id'])->exists())->toBeTrue()
        ->and(is_file($this->site.'/insights/related-parent/index.html'))->toBeFalse();
})->with([
    'missing' => [['missing']], 'wrong type' => [['other-kind']], 'self' => [['related-parent']],
    'duplicate' => [['alpha', 'alpha']], 'more than three' => [['alpha', 'beta', 'gamma', 'delta']],
    'path traversal' => [['../alpha']], 'full URL' => [['https://example.invalid']], 'integer' => [[123]],
    'nested' => [[['slug' => 'alpha']]], 'non-list' => [['key' => 'alpha']],
]);

it('refuses selected sources that are no longer safely public', function (string $change): void {
    $page = relatedSource('alpha');
    $item = relatedSave(['related' => ['alpha']]);
    $preview = relatedApi(['action' => 'cms_preview'] + $item)->assertJsonPath('ok', true)->json();
    $file = $this->site.'/insights/alpha/index.html';
    match ($change) {
        'withdrawn' => $page->update(['status' => PageStatus::Draft]),
        'deleted' => $page->delete(),
        'future' => $page->update(['published_at' => now()->addDay()]),
        'unlisted' => $page->blocks()->first()->update(['data' => ['studio' => ['visibility' => 'unlisted']]]),
        'missing file' => unlink($file),
        'unsafe image' => $page->blocks()->first()->update(['data' => ['studio' => ['card' => ['src' => 'javascript:alert(1)']]]]),
        'malformed card' => $page->blocks()->first()->update(['data' => ['studio' => ['card' => 'invalid']]]),
    };
    relatedApi(['action' => 'cms_preview'] + $item)->assertJsonPath('ok', false);
    relatedApi(['action' => 'cms_published', 'preview_hash' => $preview['html_hash'], 'dependency_hash' => $preview['dependency_hash']] + $item)->assertJsonPath('ok', false);
    expect(is_file($this->site.'/insights/related-parent/index.html'))->toBeFalse();
})->with(['withdrawn', 'deleted', 'future', 'unlisted', 'missing file', 'unsafe image', 'malformed card']);

it('binds related public files even when changing their bytes does not change the card HTML', function (): void {
    relatedSource('alpha');
    $item = relatedSave(['related' => ['alpha']]);
    $old = relatedApi(['action' => 'cms_preview'] + $item)->assertJsonPath('ok', true)->json();
    file_put_contents($this->site.'/insights/alpha/index.html', '<html><main>Outside public edit, same card</main></html>');
    $new = relatedApi(['action' => 'cms_preview'] + $item)->assertJsonPath('ok', true)->json();
    expect($new['html_hash'])->toBe($old['html_hash'])->and($new['dependency_hash'])->not->toBe($old['dependency_hash']);
    relatedApi(['action' => 'cms_published', 'preview_hash' => $old['html_hash'], 'dependency_hash' => $old['dependency_hash']] + $item)->assertJsonPath('ok', false);
    expect(is_file($this->site.'/insights/related-parent/index.html'))->toBeFalse();
    relatedApi(['action' => 'cms_published', 'preview_hash' => $new['html_hash'], 'dependency_hash' => $new['dependency_hash']] + $item)->assertJsonPath('ok', true);
});

it('rejects related row or published card changes since Preview', function (string $change): void {
    $page = relatedSource('alpha');
    $item = relatedSave(['related' => ['alpha']]);
    $old = relatedApi(['action' => 'cms_preview'] + $item)->json();
    $change === 'title' ? $page->update(['title' => 'Changed public title']) : $page->blocks()->first()->update(['data' => ['studio' => ['card' => ['text' => 'Changed public card']]]]);
    relatedApi(['action' => 'cms_published', 'preview_hash' => $old['html_hash'], 'dependency_hash' => $old['dependency_hash']] + $item)->assertJsonPath('ok', false);
    expect(StudioDraft::query()->where('page_id', $item['id'])->exists())->toBeTrue();
})->with(['title', 'card']);

it('requires both Preview hashes for explicit selections', function (string $missing): void {
    relatedSource('alpha');
    $item = relatedSave(['related' => ['alpha']]);
    $preview = relatedApi(['action' => 'cms_preview'] + $item)->json();
    $request = ['action' => 'cms_published', 'preview_hash' => $preview['html_hash'], 'dependency_hash' => $preview['dependency_hash']] + $item;
    unset($request[$missing]);
    relatedApi($request)->assertJsonPath('ok', false);
    expect(is_file($this->site.'/insights/related-parent/index.html'))->toBeFalse();
})->with(['preview_hash', 'dependency_hash']);

it('keeps private source edits out of dependency hashes', function (): void {
    $page = relatedSource('alpha');
    $item = relatedSave(['related' => ['alpha']]);
    $old = relatedApi(['action' => 'cms_preview'] + $item)->json();
    $source = relatedApi(['action' => 'cms_get', 'id' => $page->id, 'type' => 'post'])->json('item');
    relatedApi(['action' => 'cms_save', 'title' => 'Private title', 'data' => ['card' => ['text' => 'Private text']]] + $source)->assertJsonPath('ok', true);
    $new = relatedApi(['action' => 'cms_preview'] + $item)->json();
    expect($new['html_hash'])->toBe($old['html_hash'])->and($new['dependency_hash'])->toBe($old['dependency_hash']);
    relatedApi(['action' => 'cms_published', 'preview_hash' => $old['html_hash'], 'dependency_hash' => $old['dependency_hash']] + $item)->assertJsonPath('ok', true);
});

it('preserves empty-selection automatic fallback without exposing unlisted or draft items', function (): void {
    relatedSource('alpha');
    relatedSource('unlisted', 'post', ['visibility' => 'unlisted']);
    relatedSource('draft')->update(['status' => PageStatus::Draft]);
    $item = relatedSave(['related' => []]);
    $html = relatedApi(['action' => 'cms_preview'] + $item)->assertJsonPath('ok', true)->json('html');
    expect($html)->toContain('/insights/alpha/')->not->toContain('/insights/unlisted/', '/insights/draft/');
});

it('renders exactly three chosen cards in the requested order', function (): void {
    foreach (['alpha', 'beta', 'gamma'] as $slug) {
        relatedSource($slug);
    }
    $item = relatedSave(['related' => ['gamma', 'alpha', 'beta']]);
    $html = relatedApi(['action' => 'cms_preview'] + $item)->assertJsonPath('ok', true)->json('html');
    expect(substr_count($html, 'class="dx-card"'))->toBe(3)
        ->and(strpos($html, '/gamma/'))->toBeLessThan(strpos($html, '/alpha/'))
        ->and(strpos($html, '/alpha/'))->toBeLessThan(strpos($html, '/beta/'));
});

it('refuses related public-file symlinks that escape the site root', function (): void {
    relatedSource('alpha');
    $item = relatedSave(['related' => ['alpha']]);
    $path = $this->site.'/insights/alpha/index.html';
    $outside = storage_path('framework/testing/related-outside-'.bin2hex(random_bytes(8)).'.html');
    file_put_contents($outside, '<html><main>Outside private file</main></html>');
    unlink($path);
    symlink($outside, $path);
    try {
        $result = relatedApi(['action' => 'cms_preview'] + $item)->assertJsonPath('ok', false)->json();
        expect($result)->not->toHaveKey('html');
        relatedApi(['action' => 'cms_published'] + $item)->assertJsonPath('ok', false);
        expect(is_file($this->site.'/insights/related-parent/index.html'))->toBeFalse();
    } finally {
        unlink($outside);
    }
});
