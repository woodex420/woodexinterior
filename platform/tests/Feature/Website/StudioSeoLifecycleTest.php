<?php

declare(strict_types=1);

use App\Domain\Website\Builder\PublicationSeo;
use App\Domain\Website\Models\Page;
use App\Domain\Website\Models\StudioDraft;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->actingAs($this->userWithRole('owner'));
    $this->site = storage_path('framework/testing/seo-lifecycle-'.bin2hex(random_bytes(8)));
    foreach (['insights', 'projects'] as $folder) {
        mkdir($this->site.'/'.$folder.'/shell', 0700, true);
        $head = '<title>STALE TITLE</title><meta content="STALE DESCRIPTION" NAME="description"><meta name="robots" content="noindex"><meta content="none" name="Googlebot"><link href="https://woodex.com.pk/old/" rel="CANONICAL"><link rel="canonical" href="/duplicate/">'
            .'<meta property="og:image" content="https://old.invalid/image.webp"><meta name="twitter:image" content="https://old.invalid/twitter.webp"><link rel="stylesheet" href="/assets/site-p21.css">'
            .'<script type="application/ld+json">[{"@type":"Organization","name":"Keep studio"},{"@type":"Article","headline":"STALE ARTICLE"},{"@type":"FAQPage","mainEntity":[{"name":"STALE FAQ"}]}]</script><script>/* executable kept */</script>';
        file_put_contents($this->site.'/'.$folder.'/shell/index.html', '<!doctype html><html><head>'.$head.'</head><body><header>Keep chrome</header><main><h1>Old</h1></main><footer>Keep footer</footer></body></html>');
        file_put_contents($this->site.'/'.$folder.'/index.html', '<!doctype html><html><head>'.$head.'<script type="application/ld+json">{"@type":"CollectionPage","mainEntity":{"itemListElement":[{"url":"STALE-LIST"}]}}</script></head><body><header>Keep chrome</header><main><h1>Listing</h1><p>Six anonymous studies in homes, clinics, shops, offices and cafés, each thought through to a buildable plan.</p><div class="hx-cards"></div></main><footer>Keep footer</footer></body></html>');
    }
    $this->app->usePublicPath($this->site);
    Cache::flush();
    Http::fake();
});

afterEach(function (): void {
    Http::assertNothingSent();
    (new Filesystem)->deleteDirectory($this->site);
});

function slApi(array $input)
{
    return test()->postJson(route('admin.website.studio.api'), $input);
}

function slSave(string $type = 'post', array $changes = []): array
{
    return slApi(['action' => 'cms_save'] + array_replace(['type' => $type, 'title' => 'Own title', 'slug' => 'seo-subject', 'seo' => ['title' => 'Own SEO title', 'desc' => 'Own description', 'og' => '/assets/own-social.webp'],
        'data' => ['hero' => ['src' => '/assets/hero.webp', 'alt' => 'Hero'], 'blocks' => [['t' => 'p', 'text' => 'Own body']], 'faqs' => [['q' => 'Own question?', 'a' => 'Own **answer**.']]]], $changes))->assertJsonPath('ok', true)->json('item');
}

function slPublish(array $item): array
{
    $preview = slApi(['action' => 'cms_preview'] + $item)->assertJsonPath('ok', true)->json();
    $live = slApi(['action' => 'cms_published', 'preview_hash' => $preview['html_hash'], 'dependency_hash' => $preview['dependency_hash']] + $item)->assertJsonPath('ok', true)->json('item');
    $folder = $item['type'] === 'post' ? 'insights' : 'projects';
    expect(file_get_contents(test()->site.'/'.$folder.'/'.$live['slug'].'/index.html'))->toBe($preview['html']);

    return $live;
}

function slDom(string $html): DOMXPath
{
    $previous = libxml_use_internal_errors(true);
    $dom = new DOMDocument;
    $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    return new DOMXPath($dom);
}

function slSchemas(string $html): array
{
    preg_match_all('~<script type="application/ld\+json">(.*?)</script>~s', $html, $m);
    $nodes = [];
    foreach ($m[1] as $json) {
        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        foreach (array_is_list($data) ? $data : [$data] as $node) {
            $nodes[] = $node;
        }
    }

    return $nodes;
}

function slOfType(string $html, string $type): array
{
    return array_values(array_filter(slSchemas($html), fn ($n) => ($n['@type'] ?? '') === $type));
}

it('owns article metadata canonical social and visible FAQ schema instead of inheriting its shell', function (string $type, string $folder): void {
    $item = slSave($type);
    $preview = slApi(['action' => 'cms_preview'] + $item)->assertJsonPath('ok', true)->json();
    $html = $preview['html'];
    expect($html)->not->toContain('STALE TITLE', 'STALE DESCRIPTION', 'STALE ARTICLE', 'STALE FAQ', '/old/', '/duplicate/', 'old.invalid')
        ->and($html)->toContain('Keep chrome', 'Keep footer', '/* executable kept */', '/assets/site-p21.css', 'Keep studio', 'Own SEO title', 'https://woodex.com.pk/assets/own-social.webp');
    $dom = slDom($html);
    expect($dom->query('//link[@rel="canonical"]')->length)->toBe(1)
        ->and($dom->query('//link[@rel="canonical"]')->item(0)->getAttribute('href'))->toBe('https://woodex.com.pk/'.$folder.'/seo-subject/')
        ->and($dom->query('//meta[@name="robots"]')->item(0)->getAttribute('content'))->toBe('index,follow')
        ->and($dom->query('//meta[@name="Googlebot"]')->length)->toBe(0)
        ->and(slOfType($html, 'FAQPage')[0]['mainEntity'][0]['name'])->toBe('Own question?')
        ->and(slOfType($html, 'FAQPage')[0]['mainEntity'][0]['acceptedAnswer']['text'])->toBe('Own answer.');
    $node = slOfType($html, $type === 'post' ? 'Article' : 'CreativeWork')[0];
    expect($node['url'])->toBe('https://woodex.com.pk/'.$folder.'/seo-subject/')->and($node)->not->toHaveKeys(['datePublished', 'dateModified']);
    $item = slPublish($item);
    expect($item['seo']['og'])->toBe('/assets/own-social.webp');
    $after = slApi(['action' => 'cms_preview'] + $item)->assertJsonPath('ok', true)->json();
    expect($after['html'])->toBe($html);
})->with([['post', 'insights'], ['study', 'projects']]);

it('keeps public unlisted public and withdrawal changes explicit and sitemap-consistent', function (string $type, string $folder): void {
    $item = slPublish(slSave($type));
    $file = $this->site.'/'.$folder.'/seo-subject/index.html';
    $index = $this->site.'/'.$folder.'/index.html';
    $public = file_get_contents($file);
    $listing = file_get_contents($index);
    $map = $this->get('/sitemap.xml')->assertOk()->getContent();
    expect($map)->toContain('https://woodex.com.pk/'.$folder.'/seo-subject/');
    $item = slSave($type, array_replace($item, ['data' => array_replace($item['data'], ['visibility' => 'unlisted'])]));
    $preview = slApi(['action' => 'cms_preview'] + $item)->assertJsonPath('ok', true)->json();
    expect(PublicationSeo::noindex($preview['html']))->toBeTrue()
        ->and(file_get_contents($file))->toBe($public)->and(file_get_contents($index))->toBe($listing)
        ->and($this->get('/sitemap.xml')->getContent())->toBe($map)
        ->and(Page::findOrFail($item['id'])->seo['robots'])->toBe('index,follow');
    slApi(['action' => 'cms_published'] + $item)->assertJsonPath('ok', false);
    $item = slPublish($item);
    expect(is_file($file))->toBeTrue()->and(PublicationSeo::noindex(file_get_contents($file)))->toBeTrue()
        ->and(file_get_contents($index))->not->toContain('href="/'.$folder.'/seo-subject/"')
        ->and($this->get('/sitemap.xml')->getContent())->not->toContain('https://woodex.com.pk/'.$folder.'/seo-subject/')
        ->and(Page::findOrFail($item['id'])->seo['robots'])->toBe('noindex,follow');
    expect(slOfType(file_get_contents($index), 'CollectionPage')[0]['mainEntity']['numberOfItems'])->toBe(0);
    $item = slSave($type, array_replace($item, ['data' => array_replace($item['data'], ['visibility' => 'public'])]));
    slApi(['action' => 'cms_published'] + $item)->assertJsonPath('ok', false);
    $item = slPublish($item);
    expect(PublicationSeo::noindex(file_get_contents($file)))->toBeFalse()
        ->and(file_get_contents($index))->toContain('href="/'.$folder.'/seo-subject/"')
        ->and($this->get('/sitemap.xml')->getContent())->toContain('https://woodex.com.pk/'.$folder.'/seo-subject/');
    $collection = slOfType(file_get_contents($index), 'CollectionPage')[0];
    expect(slOfType(file_get_contents($index), 'FAQPage'))->toBe([])
        ->and($collection['mainEntity']['numberOfItems'])->toBe(1)
        ->and($collection['mainEntity']['itemListElement'][0]['url'])->toBe('https://woodex.com.pk/'.$folder.'/seo-subject/');
    slApi(['action' => 'cms_status', 'status' => 'draft'] + $item)->assertJsonPath('ok', true);
    expect(is_file($file))->toBeFalse()->and($this->get('/sitemap.xml')->getContent())->not->toContain('https://woodex.com.pk/'.$folder.'/seo-subject/');
})->with([['post', 'insights'], ['study', 'projects']]);

it('refreshes same-second same-size sitemap metadata changes without depending on SQL timestamps', function (): void {
    $file = $this->site.'/index.html';
    $off = '<html><head><meta content="noindex" name="robots"></head><body></body></html>';
    $on = str_replace('noindex', 'index  ', $off);
    $stamp = 1700000000;
    file_put_contents($file, $off);
    touch($file, $stamp);
    clearstatcache();
    expect($this->get('/sitemap.xml')->getContent())->not->toContain('<loc>https://woodex.com.pk/</loc>');
    file_put_contents($file, $on);
    touch($file, $stamp);
    clearstatcache();
    expect($this->get('/sitemap.xml')->getContent())->toContain('<loc>https://woodex.com.pk/</loc>');
    file_put_contents($file, $off);
    touch($file, $stamp);
    clearstatcache();
    expect($this->get('/sitemap.xml')->getContent())->not->toContain('<loc>https://woodex.com.pk/</loc>');
});

it('recognizes crawler directives regardless of attribute order quoting case and none', function (string $tag): void {
    expect(PublicationSeo::noindex('<html><head>'.$tag.'</head></html>'))->toBeTrue();
})->with(['<meta content="NOINDEX,follow" NAME="robots">', "<meta content='none' name='googlebot'>", '<meta name="bingbot" data-x="1" content="noindex">']);

it('does not misread noindex-like words as a crawler directive', function (): void {
    expect(PublicationSeo::noindex('<html><head><meta name="description" content="noindex"><meta name="robots" content="not-noindexing"></head></html>'))->toBeFalse();
});

it('escapes metadata and JSON-LD without introducing executable markup or stale images', function (): void {
    $item = slSave(changes: ['title' => 'Title </script><script>alert(1)</script>', 'seo' => ['title' => 'SEO " <b>', 'desc' => '</script><script>bad</script>'], 'data' => ['blocks' => []]]);
    $html = slApi(['action' => 'cms_preview'] + $item)->assertJsonPath('ok', true)->json('html');
    expect($html)->not->toContain('<script>alert(1)</script>', '<script>bad</script>', 'old.invalid', 'property="og:image"', 'name="twitter:image"')
        ->and($html)->toContain('&lt;script&gt;', '\\u003C/script\\u003E');
    expect(slOfType($html, 'Article')[0]['headline'])->toBe($item['title']);
});

it('rejects unsafe social images before publishing', function (string $image): void {
    $item = slSave(changes: ['seo' => ['og' => $image]]);
    slApi(['action' => 'cms_preview'] + $item)->assertJsonPath('ok', false);
    slApi(['action' => 'cms_published'] + $item)->assertJsonPath('ok', false);
    expect(is_file($this->site.'/insights/seo-subject/index.html'))->toBeFalse();
})->with(['javascript:bad', '//evil.test/a', '/assets/%2e%2e/a', '/assets/a" onerror="bad']);

it('rejects malformed shell schema without deleting public pages or drafts', function (): void {
    $file = $this->site.'/insights/shell/index.html';
    file_put_contents($file, str_replace('</head>', '<script type="application/ld+json">{broken}</script></head>', file_get_contents($file)));
    $item = slSave();
    slApi(['action' => 'cms_preview'] + $item)->assertJsonPath('ok', false);
    expect(StudioDraft::where('page_id', $item['id'])->exists())->toBeTrue();
});

it('keeps inline and linked FAQs aligned with actually rendered sections', function (): void {
    $template = slApi(['action' => 'cms_resource_save', 'kind' => 'template', 'scope' => 'post', 'data' => ['name' => 'Hidden FAQ layout', 'description' => '', 'hero' => 'navy', 'meta' => false, 'toc' => false, 'facts' => false, 'sections' => array_map(fn ($k) => ['k' => $k, 'on' => $k !== 'faqs'], ['body', 'summary', 'faqs', 'quote', 'related'])]])->assertJsonPath('ok', true)->json('item');
    $template = slApi(['action' => 'cms_resource_publish'] + $template)->assertJsonPath('ok', true)->json('item');
    $item = slSave(changes: ['data' => ['templateResource' => $template['id'], 'faqs' => [['q' => 'Hidden question?', 'a' => 'Hidden answer']], 'blocks' => [['t' => 'faq', 'text' => 'Inline question?\nInline answer.']]]]);
    // An actual newline is part of the editor FAQ block syntax.
    $item['data']['blocks'][0]['text'] = "Inline question?\nInline answer.";
    $item = slSave(changes: $item);
    $html = slApi(['action' => 'cms_preview'] + $item)->assertJsonPath('ok', true)->json('html');
    expect($html)->not->toContain('Hidden question?')->and(slOfType($html, 'FAQPage')[0]['mainEntity'][0]['name'])->toBe('Inline question?');
});

it('excludes unlisted sources from new related previews and refuses explicit hidden selections', function (): void {
    $source = slPublish(slSave(changes: ['slug' => 'hidden-source', 'data' => ['visibility' => 'unlisted', 'blocks' => []]]));
    $item = slSave();
    $html = slApi(['action' => 'cms_preview'] + $item)->assertJsonPath('ok', true)->json('html');
    expect($html)->not->toContain('href="/insights/hidden-source/"');
    $item = slSave(changes: array_replace($item, ['data' => ['related' => ['hidden-source']]]));
    slApi(['action' => 'cms_preview'] + $item)->assertJsonPath('ok', false);
});

it('keeps visibility changes private on discard and denies Editor promotion', function (): void {
    $item = slPublish(slSave());
    $before = file_get_contents($this->site.'/insights/seo-subject/index.html');
    $this->actingAs($this->userWithRole('editor'));
    $item = slSave(changes: array_replace($item, ['data' => array_replace($item['data'], ['visibility' => 'unlisted'])]));
    slApi(['action' => 'cms_preview'] + $item)->assertJsonPath('ok', true);
    slApi(['action' => 'cms_published'] + $item)->assertForbidden();
    slApi(['action' => 'cms_discard'] + $item)->assertJsonPath('ok', true);
    expect(file_get_contents($this->site.'/insights/seo-subject/index.html'))->toBe($before);
});

it('uses canonical robots sitemap URLs even when requested on an unrelated host', function (): void {
    $this->get('http://preview.invalid/robots.txt')->assertOk()->assertSee('Sitemap: https://woodex.com.pk/sitemap.xml', false);
});

it('uses linked published FAQs in schema while private resource edits remain invisible', function (): void {
    $resource = slApi(['action' => 'cms_resource_save', 'kind' => 'faq_group', 'scope' => 'shared', 'data' => ['name' => 'Schema FAQ group', 'description' => '', 'items' => [['q' => 'Published group question?', 'a' => 'Published group answer.']]]])->assertJsonPath('ok', true)->json('item');
    $resource = slApi(['action' => 'cms_resource_publish'] + $resource)->assertJsonPath('ok', true)->json('item');
    $item = slSave(changes: ['data' => ['faqResource' => $resource['id'], 'faqs' => [['q' => 'Local hidden question?', 'a' => 'Local retained answer']], 'blocks' => []]]);
    $before = slApi(['action' => 'cms_preview'] + $item)->assertJsonPath('ok', true)->json();
    expect(slOfType($before['html'], 'FAQPage')[0]['mainEntity'][0]['name'])->toBe('Published group question?')
        ->and($before['html'])->not->toContain('Local hidden question?');
    slApi(['action' => 'cms_resource_save', 'data' => array_replace($resource['data'], ['items' => [['q' => 'PRIVATE question?', 'a' => 'PRIVATE answer']]])] + $resource)->assertJsonPath('ok', true);
    $after = slApi(['action' => 'cms_preview'] + $item)->assertJsonPath('ok', true)->json();
    expect($after['html'])->toBe($before['html'])->and($after['dependency_hash'])->toBe($before['dependency_hash']);
});

it('replaces managed graph nodes without removing organization identity or executable assets', function (): void {
    $file = $this->site.'/insights/shell/index.html';
    file_put_contents($file, str_replace('</head>', '<script type="APPLICATION/LD+JSON; charset=utf-8">{"@context":"https://schema.org","@graph":[{"@type":"Organization","name":"Graph brand"},{"@type":["Article","CreativeWork"],"name":"STALE GRAPH"}]}</script></head>', file_get_contents($file)));
    $html = slApi(['action' => 'cms_preview'] + slSave())->assertJsonPath('ok', true)->json('html');
    expect($html)->toContain('Graph brand', '/* executable kept */')->not->toContain('STALE GRAPH');
});

it('rejects malformed listing schema before changing the article projection or file', function (): void {
    $item = slSave();
    $index = $this->site.'/insights/index.html';
    file_put_contents($index, str_replace('</head>', '<script type="application/ld+json">{bad}</script></head>', file_get_contents($index)));
    $before = file_get_contents($index);
    $preview = slApi(['action' => 'cms_preview'] + $item)->assertJsonPath('ok', true)->json();
    slApi(['action' => 'cms_published', 'preview_hash' => $preview['html_hash'], 'dependency_hash' => $preview['dependency_hash']] + $item)->assertJsonPath('ok', false);
    expect(file_get_contents($index))->toBe($before)->and(is_file($this->site.'/insights/seo-subject/index.html'))->toBeFalse()
        ->and(Page::findOrFail($item['id'])->status->value)->toBe('draft');
});

it('requires both Preview hashes for unlisted publication', function (string $field): void {
    $item = slSave(changes: ['data' => ['visibility' => 'unlisted', 'blocks' => []]]);
    $preview = slApi(['action' => 'cms_preview'] + $item)->assertJsonPath('ok', true)->json();
    $expected = ['preview_hash' => $preview['html_hash'], 'dependency_hash' => $preview['dependency_hash']];
    unset($expected[$field]);
    slApi(['action' => 'cms_published'] + $expected + $item)->assertJsonPath('ok', false);
})->with(['preview_hash', 'dependency_hash']);

it('rejects invalid public canonical configuration rather than using the request host', function (string $base): void {
    config(['woodex.public_site_url' => $base]);
    expect(fn () => PublicationSeo::absolute('/insights/'))->toThrow(RuntimeException::class);
})->with(['javascript:bad', 'relative.example', 'https://woodex.example/?query=bad', 'https://woodex.example/#fragment']);
