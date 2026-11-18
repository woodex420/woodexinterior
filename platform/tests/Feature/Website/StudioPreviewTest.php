<?php

declare(strict_types=1);

use App\Domain\Website\Builder\ArticleFile;
use App\Domain\Website\Enums\PageStatus;
use App\Domain\Website\Models\Page;
use App\Domain\Website\Models\StudioDraft;
use App\Domain\Website\Studio\StudioContent;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function (): void {
    $this->owner = $this->userWithRole('owner');
    $this->actingAs($this->owner);
    $this->site = storage_path('framework/testing/server-preview-'.bin2hex(random_bytes(8)));
    foreach (['insights', 'projects'] as $folder) {
        mkdir($this->site.'/'.$folder.'/template', 0700, true);
        file_put_contents($this->site.'/'.$folder.'/template/index.html', '<!doctype html><html lang="en"><head><title>Template</title><meta name="description" content="Template description"><link rel="canonical" href="https://woodex.com.pk/'.$folder.'/template/"><style>.fixture{color:green}</style></head><body><header id="site-header">Design header</header><main id="main-content"><h1>Old public content</h1></main><footer>Design footer</footer></body></html>');
    }
    foreach (['insights', 'projects'] as $folder) {
        file_put_contents($this->site.'/'.$folder.'/index.html', '<!doctype html><html><head><title>Synthetic listing</title></head><body><main><h1>Listing</h1><div class="hx-cards"></div></main></body></html>');
    }
    $this->app->usePublicPath($this->site);
});

afterEach(function (): void {
    (new Filesystem)->deleteDirectory($this->site);
});

function serverPreviewApi(array $payload)
{
    return test()->postJson(route('admin.website.studio.api'), $payload);
}

function serverPreviewSave(string $type = 'post', array $changes = []): array
{
    return serverPreviewApi(array_replace(['action' => 'cms_save', 'type' => $type, 'title' => 'Synthetic preview title', 'slug' => 'synthetic-preview',
        'seo' => ['desc' => 'Exact synthetic description'],
        'data' => ['hero' => ['src' => '/assets/img/fixture.webp', 'alt' => 'Synthetic artwork'],
            'dek' => 'Synthetic standfirst', 'blocks' => [['t' => 'h', 'text' => 'Synthetic section'], ['t' => 'p', 'text' => '**Saved** private text.'],
                ['t' => 'list', 'items' => ['First', 'Second'], 'ol' => true]], 'faqs' => [['q' => 'Synthetic question?', 'a' => 'Synthetic answer.']]]], $changes))
        ->assertJsonPath('ok', true)->json('item');
}

it('renders private saved state without any public or database mutation for an editor', function (string $type): void {
    $this->actingAs($this->userWithRole('editor'));
    $item = serverPreviewSave($type);
    $page = Page::query()->findOrFail($item['id']);
    $attributes = $page->getAttributes();
    $draft = StudioDraft::query()->firstOrFail()->getAttributes();
    $folder = $type === 'post' ? 'insights' : 'projects';
    $shell = file_get_contents($this->site.'/'.$folder.'/template/index.html');
    $response = serverPreviewApi(['action' => 'cms_preview'] + $item)->assertJsonPath('ok', true)
        ->assertHeader('Cache-Control', 'no-store, private')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    $html = $response->json('html');
    expect($html)->toContain('Synthetic preview title', '<strong>Saved</strong> private text.', '<ol>', 'Exact synthetic description', 'Synthetic artwork', 'Design header', 'Design footer', '.fixture{color:green}', 'Synthetic question?')
        ->and($html)->toContain('href="https://woodex.com.pk/'.$folder.'/synthetic-preview/"')
        ->and($response->json('html_hash'))->toBe(hash('sha256', $html))
        ->and($page->fresh()->getAttributes())->toBe($attributes)
        ->and($page->blocks()->count())->toBe(0)
        ->and($page->versions()->count())->toBe(0)
        ->and(StudioDraft::query()->firstOrFail()->getAttributes())->toBe($draft)
        ->and(is_file($this->site.'/'.$folder.'/synthetic-preview/index.html'))->toBeFalse()
        ->and(file_get_contents($this->site.'/'.$folder.'/template/index.html'))->toBe($shell);
    serverPreviewApi(['action' => 'cms_published'] + $item)->assertForbidden();
})->with(['post', 'study']);

it('publishes exactly the returned server preview bytes on first publish and republish', function (string $type): void {
    $item = serverPreviewSave($type);
    $folder = $type === 'post' ? 'insights' : 'projects';
    $file = $this->site.'/'.$folder.'/synthetic-preview/index.html';
    for ($round = 0; $round < 2; $round++) {
        $preview = serverPreviewApi(['action' => 'cms_preview'] + $item)->assertJsonPath('ok', true)->json();
        $item = serverPreviewApi(['action' => 'cms_published', 'preview_hash' => $preview['html_hash']] + $item)->assertJsonPath('ok', true)->json('item');
        expect(file_get_contents($file))->toBe($preview['html']);
        $item = serverPreviewApi(['action' => 'cms_save', 'title' => 'Synthetic revision '.$round] + $item)->assertJsonPath('ok', true)->json('item');
    }
})->with(['post', 'study']);

it('renders published pending edits without replacing the live row blocks or file', function (): void {
    $item = serverPreviewSave();
    $live = serverPreviewApi(['action' => 'cms_published'] + $item)->assertJsonPath('ok', true)->json('item');
    $page = Page::query()->findOrFail($item['id']);
    $attrs = $page->getAttributes();
    $blocks = $page->blocks()->get()->toArray();
    $file = $this->site.'/insights/synthetic-preview/index.html';
    $old = file_get_contents($file);
    $pending = serverPreviewApi(['action' => 'cms_save', 'title' => 'Private replacement', 'seo' => ['desc' => 'Private SEO replacement']] + $live)->assertJsonPath('ok', true)->json('item');
    $preview = serverPreviewApi(['action' => 'cms_preview'] + $pending)->assertJsonPath('ok', true)->json('html');
    expect($preview)->toContain('Private replacement', 'Private SEO replacement')
        ->and(file_get_contents($file))->toBe($old)
        ->and($page->fresh()->getAttributes())->toBe($attrs)
        ->and($page->blocks()->get()->toArray())->toBe($blocks);
});

it('ignores unsaved payload or client HTML in a preview request', function (): void {
    $item = serverPreviewSave();
    serverPreviewApi(['action' => 'cms_preview', 'title' => 'Not saved', 'html' => '<script>unsafe()</script>',
        'data' => ['blocks' => [['t' => 'p', 'text' => 'Not saved either']]]] + $item)->assertJsonPath('ok', true)
        ->assertJson(fn ($json) => $json->where('ok', true)->where('html', fn ($html) => ! str_contains($html, 'Not saved') && ! str_contains($html, 'unsafe()'))->etc());
});

it('refuses stale preview revisions and never includes private output in errors', function (string $field): void {
    $old = serverPreviewSave();
    $item = serverPreviewApi(['action' => 'cms_save', 'title' => 'Another writer'] + $old)->assertJsonPath('ok', true)->json('item');
    $request = ['action' => 'cms_preview'] + $item;
    $request[$field] = $old[$field];
    if ($field === 'base_hash') {
        $request[$field] = str_repeat('0', 64);
    }
    $response = serverPreviewApi($request)->assertJsonPath('ok', false)->assertHeader('Cache-Control', 'no-store, private');
    expect($response->json())->not->toHaveKey('html');
})->with(['revision', 'token', 'base_hash']);

it('requires every opened revision field for preview', function (string $field): void {
    $item = serverPreviewSave();
    unset($item[$field]);
    serverPreviewApi(['action' => 'cms_preview'] + $item)->assertJsonPath('ok', false);
})->with(['revision', 'token', 'base_hash']);

it('refuses changed public state and wrong content types during preview', function (): void {
    $item = serverPreviewSave();
    serverPreviewApi(['action' => 'cms_preview', 'type' => 'study'] + $item)->assertJsonPath('ok', false);
    Page::query()->findOrFail($item['id'])->forceFill(['title' => 'Outside writer'])->save();
    serverPreviewApi(['action' => 'cms_preview'] + $item)->assertJsonPath('ok', false);
});

it('requires authentication and edit authority without requiring publish authority', function (): void {
    $item = serverPreviewSave();
    $this->actingAs($this->userWithRole('sales'));
    serverPreviewApi(['action' => 'cms_preview'] + $item)->assertForbidden();
    expect(fn () => (new StudioContent(auth()->user()))->preview($item['id'], 'post', $item))->toThrow(HttpException::class);
    auth()->logout();
    serverPreviewApi(['action' => 'cms_preview'] + $item)->assertUnauthorized();
});

it('refuses unsupported sections without losing drafts or changing public state', function (array $data): void {
    $item = serverPreviewSave('post', ['data' => $data]);
    $page = Page::query()->findOrFail($item['id']);
    $before = $page->getAttributes();
    serverPreviewApi(['action' => 'cms_preview'] + $item)->assertJsonPath('ok', false);
    serverPreviewApi(['action' => 'cms_published'] + $item)->assertJsonPath('ok', false);
    expect($page->fresh()->getAttributes())->toBe($before)->and($page->blocks()->count())->toBe(0)
        ->and(StudioDraft::query()->where('page_id', $page->id)->firstOrFail()->payload['data'])->toBe($data)
        ->and(is_file($this->site.'/insights/synthetic-preview/index.html'))->toBeFalse();
})->with([
    'unknown block' => [['blocks' => [['t' => 'unknown', 'text' => 'Keep me']]]],
    'custom template' => [['tpl' => 123]],
    'linked FAQ group' => [['faqGroup' => 123]],
    'selected related' => [['related' => ['chosen-slug']]],
    'unknown visibility' => [['visibility' => 'secret']],
]);

it('refuses unsafe addresses before public projection or output', function (array $data): void {
    $item = serverPreviewSave('post', ['data' => $data]);
    serverPreviewApi(['action' => 'cms_preview'] + $item)->assertJsonPath('ok', false);
    serverPreviewApi(['action' => 'cms_published'] + $item)->assertJsonPath('ok', false);
    expect(Page::query()->findOrFail($item['id'])->status)->toBe(PageStatus::Draft)
        ->and(is_file($this->site.'/insights/synthetic-preview/index.html'))->toBeFalse();
})->with([
    'javascript CTA' => [['blocks' => [['t' => 'cta', 'href' => 'javascript:alert(1)']]]],
    'data hero' => [['hero' => ['src' => 'data:text/html,unsafe']]],
    'protocol relative image' => [['hero' => ['src' => '//example.invalid/image.webp']]],
    'traversal image' => [['hero' => ['src' => '/%2e%2e/.env']]],
    'backslash image' => [['hero' => ['src' => '/assets\\image.webp']]],
]);

it('detects fallback template changes when the caller publishes the preview hash', function (): void {
    $item = serverPreviewSave();
    $preview = serverPreviewApi(['action' => 'cms_preview'] + $item)->assertJsonPath('ok', true)->json();
    $shell = $this->site.'/insights/template/index.html';
    file_put_contents($shell, str_replace('Design header', 'Outside header change', file_get_contents($shell)));
    serverPreviewApi(['action' => 'cms_published', 'preview_hash' => $preview['html_hash']] + $item)->assertJsonPath('ok', false);
    expect(is_file($this->site.'/insights/synthetic-preview/index.html'))->toBeFalse()
        ->and(StudioDraft::query()->where('page_id', $item['id'])->exists())->toBeTrue();
});

it('fails closed for missing and nonstandard shells without applying public state', function (bool $remove): void {
    $item = serverPreviewSave();
    $file = $this->site.'/insights/template/index.html';
    $remove ? unlink($file) : file_put_contents($file, '<html><head></head><body>No main section</body></html>');
    serverPreviewApi(['action' => 'cms_preview'] + $item)->assertJsonPath('ok', false);
    serverPreviewApi(['action' => 'cms_published'] + $item)->assertJsonPath('ok', false);
    expect(Page::query()->findOrFail($item['id'])->blocks()->count())->toBe(0);
})->with([true, false]);

it('refuses malformed slugs and public symlinks escaping the site during rendering', function (): void {
    expect(ArticleFile::pathForSlug('/insights/../secret/', 'post'))->toBeNull()
        ->and(ArticleFile::pathForSlug('/projects/wrong-kind/', 'post'))->toBeNull();
    $item = serverPreviewSave();
    $outside = storage_path('framework/testing/outside-'.bin2hex(random_bytes(6)));
    mkdir($outside, 0700, true);
    file_put_contents($outside.'/index.html', '<html><head></head><body><main>Private outside content</main></body></html>');
    symlink($outside, $this->site.'/insights/synthetic-preview');
    try {
        serverPreviewApi(['action' => 'cms_preview'] + $item)->assertJsonPath('ok', false)->assertJsonMissing(['html' => 'Private outside content']);
    } finally {
        unlink($this->site.'/insights/synthetic-preview');
        (new Filesystem)->deleteDirectory($outside);
    }
});

it('loads the local analyzer before the editor and keeps the frame sandboxed', function (): void {
    $view = file_get_contents(resource_path('views/admin/website/studio.blade.php'));
    expect(strpos($view, 'seo-analyzer.js'))->toBeLessThan(strpos($view, 'admin-content.js'));
    $js = file_get_contents(base_path('public/assets/admin-v21/admin-content.js'));
    expect($js)->toContain('f.setAttribute("sandbox", "")', 'script-src \'none\'', 'connect-src \'none\'', 'form-action \'none\'');
});
