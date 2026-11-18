<?php

declare(strict_types=1);

use App\Domain\Website\Builder\ArticleFile;
use App\Domain\Website\Models\Page;
use App\Domain\Website\Models\StudioDraft;
use App\Domain\Website\Models\StudioResource;
use Illuminate\Filesystem\Filesystem;

beforeEach(function (): void {
    $this->owner = $this->userWithRole('owner');
    $this->actingAs($this->owner);
    $this->site = storage_path('framework/testing/resource-render-'.bin2hex(random_bytes(8)));
    foreach (['insights', 'projects'] as $folder) {
        mkdir($this->site.'/'.$folder.'/shell', 0700, true);
        file_put_contents($this->site.'/'.$folder.'/shell/index.html', '<!doctype html><html><head><title>Shell</title><link rel="canonical" href="https://woodex.com.pk/'.$folder.'/shell/"></head><body><header>Unchanged synthetic chrome</header><main>Shell</main><footer>Unchanged footer</footer></body></html>');
    }
    foreach (['insights', 'projects'] as $folder) {
        file_put_contents($this->site.'/'.$folder.'/index.html', '<!doctype html><html><head><title>Synthetic listing</title></head><body><main><h1>Listing</h1><div class="hx-cards"></div></main></body></html>');
    }
    $this->app->usePublicPath($this->site);
});

afterEach(function (): void {
    (new Filesystem)->deleteDirectory($this->site);
});

function rrApi(array $input)
{
    return test()->postJson(route('admin.website.studio.api'), $input);
}

function rrTemplate(array $changes = []): array
{
    return array_replace(['name' => 'Synthetic resource template', 'description' => '', 'hero' => 'navy', 'toc' => true, 'meta' => true, 'facts' => true,
        'sections' => array_map(fn (string $key): array => ['k' => $key, 'on' => true], ['summary', 'quote', 'body', 'related', 'faqs'])], $changes);
}

function rrResource(string $kind = 'template', string $scope = 'post', array $changes = [], bool $publish = true): array
{
    $item = rrApi(['action' => 'cms_resource_save', 'kind' => $kind, 'scope' => $scope,
        'data' => $kind === 'template' ? rrTemplate($changes) : array_replace(['name' => 'Synthetic FAQ resource', 'description' => '', 'items' => [['q' => 'Published group question?', 'a' => 'Published **group** answer.']]], $changes)])
        ->assertJsonPath('ok', true)->json('item');

    return $publish ? rrApi(['action' => 'cms_resource_publish'] + $item)->assertJsonPath('ok', true)->json('item') : $item;
}

function rrSave(string $type = 'post', array $changes = []): array
{
    $data = ['hero' => ['src' => '/assets/synthetic.webp', 'alt' => 'Synthetic hero'], 'meta' => [['k' => 'Public metadata', 'v' => 'Synthetic value']],
        'blocks' => [['t' => 'h', 'text' => 'First body heading'], ['t' => 'p', 'text' => 'BODY-CONTENT'], ['t' => 'h', 'text' => 'Second heading']],
        'summary' => ['SUMMARY-CONTENT'], 'quote' => 'QUOTE-CONTENT', 'faqs' => [['q' => 'Local question?', 'a' => 'Local answer.']]];
    if ($type === 'study') {
        $data['facts'] = [['k' => 'Fact label', 'v' => 'FACT-CONTENT']];
    }

    return rrApi(['action' => 'cms_save', 'type' => $type, 'title' => 'Synthetic resource parent', 'slug' => 'resource-parent', 'data' => array_replace($data, $changes)])
        ->assertJsonPath('ok', true)->json('item');
}

function rrPreview(array $item): array
{
    return rrApi(['action' => 'cms_preview'] + $item)->assertJsonPath('ok', true)->json();
}

function rrPublish(array $item, array $preview)
{
    return rrApi(['action' => 'cms_published', 'preview_hash' => $preview['html_hash'], 'dependency_hash' => $preview['dependency_hash']] + $item);
}

function rrRelatedSource(string $type = 'post'): void
{
    $folder = $type === 'post' ? 'insights' : 'projects';
    $page = Page::create(['type' => $type === 'post' ? 'insight' : 'project', 'title' => 'RELATED-MARKER', 'slug' => '/'.$folder.'/related-source/', 'status' => 'published']);
    $page->blocks()->create(['type' => 'text', 'block_key' => 'bsturesourcerelated', 'position' => 0, 'data' => ['studio' => ['card' => ['text' => 'Published related card']]]]);
    mkdir(test()->site.'/'.$folder.'/related-source', 0700, true);
    file_put_contents(test()->site.'/'.$folder.'/related-source/index.html', '<html><main>Named synthetic related source</main></html>');
}

it('renders context-scoped published hero modes with exact Preview Publish parity', function (string $type, string $mode): void {
    $template = rrResource(scope: $type, changes: ['hero' => $mode]);
    $faq = rrResource('faq_group', 'shared');
    $item = rrSave($type, ['templateResource' => $template['id'], 'faqResource' => $faq['id']]);
    $preview = rrPreview($item);
    $html = $preview['html'];
    expect($html)->toContain('dx-hero--'.$mode, 'BODY-CONTENT', 'Published group question?', '<strong>group</strong>', 'Unchanged synthetic chrome', 'Unchanged footer')
        ->and($html)->not->toContain('Local question?');
    if ($mode === 'media') {
        expect($html)->toContain('class="dx-hero-media"', 'fetchpriority="high"')->and($html)->not->toContain('class="dx-hero-bg"');
    } elseif ($mode === 'navy') {
        expect($html)->not->toContain('class="dx-hero-bg"', 'class="dx-hero-media"');
    } else {
        expect($html)->toContain('class="dx-hero-bg"')->and($html)->not->toContain('class="dx-hero-media"');
    }
    $folder = $type === 'post' ? 'insights' : 'projects';
    expect(is_file($this->site.'/'.$folder.'/resource-parent/index.html'))->toBeFalse();
    rrPublish($item, $preview)->assertJsonPath('ok', true);
    expect(file_get_contents($this->site.'/'.$folder.'/resource-parent/index.html'))->toBe($html)
        ->and(ArticleFile::payload(Page::findOrFail($item['id']))['faqs'][0]['q'])->toBe('Local question?');
})->with([['post', 'image'], ['post', 'navy'], ['post', 'media'], ['study', 'image'], ['study', 'navy'], ['study', 'media']]);

it('preserves interleaved published order and creates valid body groups', function (string $type): void {
    $template = rrResource(scope: $type);
    $preview = rrPreview(rrSave($type, ['templateResource' => $template['id']]));
    $html = substr($preview['html'], strpos($preview['html'], '<main '));
    expect(strpos($html, 'SUMMARY-CONTENT'))->toBeLessThan(strpos($html, 'QUOTE-CONTENT'))
        ->and(strpos($html, 'QUOTE-CONTENT'))->toBeLessThan(strpos($html, 'BODY-CONTENT'))
        ->and(strpos($html, 'BODY-CONTENT'))->toBeLessThan(strpos($html, 'Local question?'))
        ->and(substr_count($html, '<main '))->toBe(1)->and(substr_count($html, '</main>'))->toBe(1);
    if ($type === 'study') {
        expect(substr_count($html, 'FACT-CONTENT'))->toBe(1)->and($html)->toContain('dx-body-grid--single');
    }
})->with(['post', 'study']);

it('honors each template section switch without removing saved content', function (string $key): void {
    rrRelatedSource();
    $sections = rrTemplate()['sections'];
    foreach ($sections as &$section) {
        $section['on'] = $section['k'] !== $key;
    }
    unset($section);
    $template = rrResource(changes: ['sections' => $sections]);
    $item = rrSave(changes: ['templateResource' => $template['id']]);
    $html = rrPreview($item)['html'];
    $marker = ['body' => 'BODY-CONTENT', 'summary' => 'SUMMARY-CONTENT', 'faqs' => 'Local question?', 'quote' => 'QUOTE-CONTENT', 'related' => 'RELATED-MARKER'][$key];
    expect($html)->not->toContain($marker);
    expect(StudioDraft::where('page_id', $item['id'])->firstOrFail()->payload['data'])->toHaveKeys(['blocks', 'summary', 'faqs', 'quote']);
    if ($key === 'body') {
        expect($html)->not->toContain('class="dx-toc"');
    }
})->with(['body', 'summary', 'faqs', 'quote', 'related']);

it('honors metadata facts and contents switches in Portfolio templates', function (bool $toc, bool $meta, bool $facts): void {
    $template = rrResource(scope: 'study', changes: compact('toc', 'meta', 'facts'));
    $html = rrPreview(rrSave('study', ['templateResource' => $template['id']]))['html'];
    expect(str_contains($html, 'class="dx-toc"'))->toBe($toc)
        ->and(str_contains($html, 'Public metadata'))->toBe($meta)
        ->and(str_contains($html, 'FACT-CONTENT'))->toBe($facts);
})->with([[false, false, false], [true, false, false], [false, true, false], [false, false, true], [true, true, true]]);

it('requires both preview hashes for explicit resource selection', function (string $key, array $hashes): void {
    $resource = $key === 'templateResource' ? rrResource() : rrResource('faq_group', 'shared');
    $item = rrSave(changes: [$key => $resource['id']]);
    $preview = rrPreview($item);
    $payload = ['action' => 'cms_published'] + $item;
    foreach ($hashes as $hash) {
        $payload[$hash] = $preview[$hash === 'preview_hash' ? 'html_hash' : $hash];
    }
    rrApi($payload)->assertJsonPath('ok', false)->assertJsonPath('error', fn (string $message): bool => str_contains($message, 'Preview'));
    expect(is_file($this->site.'/insights/resource-parent/index.html'))->toBeFalse();
})->with([['templateResource', []], ['templateResource', ['preview_hash']], ['templateResource', ['dependency_hash']], ['faqResource', []], ['faqResource', ['preview_hash']], ['faqResource', ['dependency_hash']]]);

it('isolates private resource saves and discard from rendered bytes and dependency hashes', function (string $kind): void {
    $resource = rrResource($kind, $kind === 'template' ? 'post' : 'shared');
    $key = $kind === 'template' ? 'templateResource' : 'faqResource';
    $item = rrSave(changes: [$key => $resource['id']]);
    $before = rrPreview($item);
    $pending = rrApi(['action' => 'cms_resource_save', 'data' => array_replace($resource['data'], ['name' => 'PRIVATE NAME', 'description' => 'PRIVATE DESCRIPTION'])] + $resource)->assertJsonPath('ok', true)->json('item');
    $after = rrPreview($item);
    expect($after['html'])->toBe($before['html'])->and($after['dependency_hash'])->toBe($before['dependency_hash']);
    rrApi(['action' => 'cms_resource_discard'] + $pending)->assertJsonPath('ok', true);
    expect(rrPreview($item)['dependency_hash'])->toBe($before['dependency_hash']);
    rrPublish($item, $before)->assertJsonPath('ok', true);
})->with(['template', 'faq_group']);

it('blocks resource publication drift even with identical rendered HTML and never fans out live pages', function (string $kind): void {
    $resource = rrResource($kind, $kind === 'template' ? 'post' : 'shared');
    $key = $kind === 'template' ? 'templateResource' : 'faqResource';
    $item = rrSave(changes: [$key => $resource['id']]);
    $before = rrPreview($item);
    rrPublish($item, $before)->assertJsonPath('ok', true);
    $file = $this->site.'/insights/resource-parent/index.html';
    $pages = Page::query()->get()->map->getAttributes()->all();
    $pending = rrApi(['action' => 'cms_resource_save', 'data' => array_replace($resource['data'], ['description' => 'Public description change, no visible page change'])] + $resource)->json('item');
    rrApi(['action' => 'cms_resource_publish'] + $pending)->assertJsonPath('ok', true);
    expect(file_get_contents($file))->toBe($before['html'])->and(Page::query()->get()->map->getAttributes()->all())->toBe($pages);
    $item = rrApi(['action' => 'cms_get', 'id' => $item['id'], 'type' => 'post'])->json('item');
    $after = rrPreview($item);
    expect($after['html_hash'])->toBe($before['html_hash'])->and($after['dependency_hash'])->not->toBe($before['dependency_hash']);
    rrPublish($item, $before)->assertJsonPath('ok', false);
    expect(file_get_contents($file))->toBe($before['html']);
    rrPublish($item, $after)->assertJsonPath('ok', true);
})->with(['template', 'faq_group']);

it('refuses wrong-context unpublished missing or forged resource references without public changes', function (string $case): void {
    $ref = match ($case) {
        'wrong scope' => rrResource(scope: 'study')['id'],
        'wrong kind' => rrResource('faq_group', 'shared')['id'],
        'private only' => rrResource(publish: false)['id'],
        'missing' => 999999,
        'negative' => -1,
        'string' => '1',
        'object' => ['id' => 1],
        'boolean' => true,
        'float' => 1.5,
    };
    $item = rrSave(changes: ['templateResource' => $ref]);
    rrApi(['action' => 'cms_preview'] + $item)->assertJsonPath('ok', false);
    rrApi(['action' => 'cms_published'] + $item)->assertJsonPath('ok', false);
    expect(StudioDraft::where('page_id', $item['id'])->exists())->toBeTrue()
        ->and(is_file($this->site.'/insights/resource-parent/index.html'))->toBeFalse();
})->with(['wrong scope', 'wrong kind', 'private only', 'missing', 'negative', 'string', 'object', 'boolean', 'float']);

it('refuses invalid FAQ references rather than falling back to saved local questions', function (mixed $ref): void {
    $item = rrSave(changes: ['faqResource' => $ref]);
    rrApi(['action' => 'cms_preview'] + $item)->assertJsonPath('ok', false);
    rrApi(['action' => 'cms_published'] + $item)->assertJsonPath('ok', false);
})->with([999999, -1, '2', [['id' => 2]], true]);

it('treats a published empty FAQ group as an explicit replacement and supports explicit unlink', function (string $type): void {
    $faq = rrResource('faq_group', 'shared', ['items' => []]);
    $item = rrSave($type, ['faqResource' => $faq['id']]);
    expect(rrPreview($item)['html'])->not->toContain('Local question?', 'Asked often');
    $data = $item['data'];
    $data['faqResource'] = 0;
    $item = rrApi(['action' => 'cms_save', 'data' => $data] + $item)->assertJsonPath('ok', true)->json('item');
    expect(rrPreview($item)['html'])->toContain('Local question?');
})->with(['post', 'study']);

it('refuses corrupted published payload or hash and preserves saved article state', function (string $corruption): void {
    $resource = rrResource();
    $row = StudioResource::findOrFail($resource['id']);
    match ($corruption) {
        'hash' => $row->update(['published_hash' => str_repeat('0', 64)]),
        'schema' => $row->update(['published_payload' => ['hero' => 'javascript:bad', 'name' => 'Bad']]),
    };
    $item = rrSave(changes: ['templateResource' => $resource['id']]);
    rrApi(['action' => 'cms_preview'] + $item)->assertJsonPath('ok', false);
    expect(StudioDraft::where('page_id', $item['id'])->exists())->toBeTrue();
})->with(['hash', 'schema']);

it('escapes hostile published FAQ text without copying private resource fields', function (): void {
    $faq = rrResource('faq_group', 'shared', ['items' => [['q' => '<script>bad</script>', 'a' => '<img onerror=bad src=x> $1']]]);
    $html = rrPreview(rrSave(changes: ['faqResource' => $faq['id']]))['html'];
    expect($html)->toContain('&lt;script&gt;bad&lt;/script&gt;', '&lt;img onerror=bad src=x&gt; $1')
        ->and($html)->not->toContain('<script>bad', '<img onerror=bad');
});

it('ignores unpublished resources unless explicitly selected and retains legacy reference refusals', function (): void {
    $item = rrSave();
    $before = rrPreview($item);
    rrResource();
    rrResource('faq_group', 'shared');
    $after = rrPreview($item);
    expect($after['html'])->toBe($before['html'])->and($after['dependency_hash'])->toBe($before['dependency_hash']);
    foreach (['tpl', 'faqGroup'] as $key) {
        $data = $item['data'];
        $data[$key] = 1;
        $item = rrApi(['action' => 'cms_save', 'data' => $data] + $item)->json('item');
        rrApi(['action' => 'cms_preview'] + $item)->assertJsonPath('ok', false);
    }
});

it('honors complete global ordering including related cards before body and quote', function (string $type): void {
    rrRelatedSource($type);
    $order = ['faqs', 'related', 'summary', 'body', 'quote'];
    $template = rrResource(scope: $type, changes: ['sections' => array_map(fn ($k): array => ['k' => $k, 'on' => true], $order)]);
    $html = rrPreview(rrSave($type, ['templateResource' => $template['id']]))['html'];
    $markers = ['Local question?', 'RELATED-MARKER', 'SUMMARY-CONTENT', 'BODY-CONTENT', 'QUOTE-CONTENT'];
    foreach (array_slice($markers, 1) as $i => $marker) {
        expect(strpos($html, $markers[$i]))->toBeLessThan(strpos($html, $marker));
    }
})->with(['post', 'study']);

it('binds same-payload resource republication revisions even when published hashes stay equal', function (): void {
    $resource = rrResource();
    $item = rrSave(changes: ['templateResource' => $resource['id']]);
    $before = rrPreview($item);
    $pending = rrApi(['action' => 'cms_resource_save'] + $resource)->json('item');
    $republished = rrApi(['action' => 'cms_resource_publish'] + $pending)->assertJsonPath('ok', true)->json('item');
    expect($republished['published_hash'])->toBe($resource['published_hash']);
    $after = rrPreview($item);
    expect($after['html_hash'])->toBe($before['html_hash'])->and($after['dependency_hash'])->not->toBe($before['dependency_hash']);
    rrPublish($item, $before)->assertJsonPath('ok', false);
});

it('refuses a resource with its published snapshot cleared after Preview instead of using a default', function (string $key): void {
    $resource = $key === 'templateResource' ? rrResource() : rrResource('faq_group', 'shared');
    $item = rrSave(changes: [$key => $resource['id']]);
    $before = rrPreview($item);
    // Simulate an outside writer; the service has no withdrawal/deletion action and history FKs restrict deletion.
    StudioResource::findOrFail($resource['id'])->update(['published_payload' => null]);
    rrPublish($item, $before)->assertJsonPath('ok', false);
    rrApi(['action' => 'cms_preview'] + $item)->assertJsonPath('ok', false);
    expect(is_file($this->site.'/insights/resource-parent/index.html'))->toBeFalse();
})->with(['templateResource', 'faqResource']);

it('still binds an explicitly linked FAQ resource when the template hides FAQs', function (): void {
    $sections = array_map(fn ($s): array => ['k' => $s['k'], 'on' => $s['k'] !== 'faqs'], rrTemplate()['sections']);
    $template = rrResource(changes: ['sections' => $sections]);
    $faq = rrResource('faq_group', 'shared');
    $item = rrSave(changes: ['templateResource' => $template['id'], 'faqResource' => $faq['id']]);
    $before = rrPreview($item);
    expect($before['html'])->not->toContain('Published group question?');
    $pending = rrApi(['action' => 'cms_resource_save', 'data' => array_replace($faq['data'], ['description' => 'Updated hidden dependency'])] + $faq)->json('item');
    rrApi(['action' => 'cms_resource_publish'] + $pending)->assertJsonPath('ok', true);
    rrPublish($item, $before)->assertJsonPath('ok', false);
    expect(rrPreview($item)['html_hash'])->toBe($before['html_hash']);
});
