<?php

declare(strict_types=1);

use App\Domain\Website\Builder\BuilderFile;
use App\Domain\Website\Builder\ListingFile;
use App\Domain\Website\Models\Page;
use App\Domain\Website\Models\StudioDraft;
use Illuminate\Filesystem\Filesystem;

beforeEach(function (): void {
    $this->owner = $this->userWithRole('owner');
    $this->actingAs($this->owner);
    $this->site = storage_path('framework/testing/listing-files-'.bin2hex(random_bytes(8)));
    foreach (['insights', 'projects'] as $folder) {
        mkdir($this->site.'/'.$folder.'/shell', 0700, true);
        file_put_contents($this->site.'/'.$folder.'/shell/index.html', '<!doctype html><html><head><title>Shell</title></head><body><header>Chrome</header><main><h1>Shell</h1></main><footer>Footer</footer></body></html>');
        file_put_contents($this->site.'/'.$folder.'/index.html', lfShell(lfOther()));
    }
    $this->app->usePublicPath($this->site);
});

afterEach(function (): void {
    (new Filesystem)->deleteDirectory($this->site);
});

function lfOther(): string
{
    return '<a data-curated="keep" class="hx-card" href="/elsewhere/"><figure><img src="/assets/keep.webp" alt="Keep" width="10" height="10"></figure><h3>Curated &amp; exact</h3><p>Do not regenerate</p></a>';
}

function lfShell(string $cards): string
{
    return '<!doctype html><html><head><title>Curated listing</title><link rel="canonical" href="https://woodex.com.pk/insights/"></head><body><header>Exact header</header><main><h1>Original heading</h1><section><div class="hx-cards">'.$cards.'</div></section><aside>Unrelated <a href="/insights/subject/">link</a></aside></main><footer>Exact footer</footer><script>/* unchanged */</script></body></html>';
}

function lfApi(array $payload)
{
    return test()->postJson(route('admin.website.studio.api'), $payload);
}

function lfSave(string $type = 'post', array $changes = []): array
{
    $payload = ['type' => $type, 'title' => 'Public subject', 'slug' => 'subject', 'data' => ['hero' => ['src' => '/assets/hero.webp', 'alt' => 'Hero'], 'dek' => 'Public excerpt', 'blocks' => [['t' => 'p', 'text' => 'Body']], 'card' => ['text' => 'Card copy', 'tag' => 'Card label', 'src' => '/assets/card.webp', 'alt' => 'Card alt']]];

    return lfApi(['action' => 'cms_save'] + array_replace($payload, $changes))->assertJsonPath('ok', true)->json('item');
}

function lfPublish(array $item)
{
    $preview = lfApi(['action' => 'cms_preview'] + $item)->assertJsonPath('ok', true)->json();

    return lfApi(['action' => 'cms_published', 'preview_hash' => $preview['html_hash'], 'dependency_hash' => $preview['dependency_hash']] + $item);
}

it('adds updates and withdraws exactly one real card only on explicit publication', function (string $type, string $folder): void {
    $index = $this->site.'/'.$folder.'/index.html';
    $original = file_get_contents($index);
    $item = lfSave($type);
    expect(file_get_contents($index))->toBe($original);
    $preview = lfApi(['action' => 'cms_preview'] + $item)->assertJsonPath('ok', true)->json();
    expect(file_get_contents($index))->toBe($original);
    $item = lfApi(['action' => 'cms_published', 'preview_hash' => $preview['html_hash'], 'dependency_hash' => $preview['dependency_hash']] + $item)->assertJsonPath('ok', true)->json('item');
    $published = file_get_contents($index);
    expect($published)->toContain(lfOther(), 'Card copy', 'Card label', 'Card alt', 'href="/'.$folder.'/subject/"')
        ->and(strpos($published, '/elsewhere/'))->toBeLessThan(strpos($published, '/'.$folder.'/subject/'))
        ->and(file_get_contents($this->site.'/'.$folder.'/subject/index.html'))->toBe($preview['html']);
    $item = lfSave($type, array_replace($item, ['title' => 'PRIVATE TITLE', 'data' => array_replace($item['data'], ['card' => ['text' => 'PRIVATE COPY', 'tag' => 'PRIVATE TAG']])]));
    lfApi(['action' => 'cms_preview'] + $item)->assertJsonPath('ok', true);
    expect(file_get_contents($index))->toBe($published)->not->toContain('PRIVATE');
    $item = lfPublish($item)->assertJsonPath('ok', true)->json('item');
    $updated = file_get_contents($index);
    expect($updated)->toContain('PRIVATE TITLE', 'PRIVATE COPY', 'PRIVATE TAG', lfOther())->not->toContain('Public subject', 'Card copy')
        ->and(substr_count($updated, 'href="/'.$folder.'/subject/"'))->toBe($type === 'post' ? 2 : 1);
    // Repeated explicit publication is byte-idempotent and retains the card slot.
    $item = lfPublish($item)->assertJsonPath('ok', true)->json('item');
    expect(file_get_contents($index))->toBe($updated);
    $item = lfApi(['action' => 'cms_status', 'status' => 'draft'] + $item)->assertJsonPath('ok', true)->json('item');
    $withdrawn = file_get_contents($index);
    expect($withdrawn)->toContain(lfOther(), '<aside>Unrelated <a href="/insights/subject/">link</a></aside>')->not->toContain('PRIVATE TITLE', 'PRIVATE COPY')
        ->and(is_file($this->site.'/'.$folder.'/subject/index.html'))->toBeFalse();
    // Repeated withdrawal is also safe and does not change unrelated content.
    lfApi(['action' => 'cms_status', 'status' => 'draft'] + $item)->assertJsonPath('ok', true);
    expect(file_get_contents($index))->toBe($withdrawn);
    expect(BuilderFile::versions($folder.'/index.html'))->not->toBeEmpty();
})->with(['Blog' => ['post', 'insights'], 'Portfolio' => ['study', 'projects']]);

it('replaces a target in its existing position and removes duplicate target cards only', function (): void {
    $first = '<a href="/insights/subject" class="hx-card"><h3>Old title</h3></a>';
    $last = '<a class="hx-card" href="/insights/subject/"><h3>Duplicate</h3></a>';
    $file = $this->site.'/insights/index.html';
    file_put_contents($file, lfShell($first.lfOther().$last));
    $prepared = ListingFile::prepare('post', '/insights/subject/', 'New title', ['card' => ['text' => 'New copy']]);
    expect(file_get_contents($file))->toContain('Old title', 'Duplicate');
    $prepared->write($this->owner);
    $actual = file_get_contents($file);
    expect($actual)->toContain(lfOther(), 'New title')->not->toContain('Old title', 'Duplicate')
        ->and(strpos($actual, 'New title'))->toBeLessThan(strpos($actual, '/elsewhere/'));
    $card = preg_match('~<a class="hx-card".*?</a>~s', $actual, $m) ? $m[0] : '';
    expect($card)->not->toBe('')->and(preg_replace('~<head>.*?</head>~s', '[MANAGED HEAD]', $actual))->toBe(preg_replace('~<head>.*?</head>~s', '[MANAGED HEAD]', lfShell($card.lfOther())));
});

it('escapes all listing text and uses safe image and metadata fallback', function (): void {
    $result = ListingFile::prepare('post', '/insights/subject/', '<script>Title</script>', ['hero' => ['src' => '/assets/hero.webp'], 'dek' => '<img onerror=bad>', 'meta' => [['k' => 'Read time', 'v' => '6 min read']]]);
    expect($result->after)->toContain('&lt;script&gt;Title&lt;/script&gt;', '&lt;img onerror=bad&gt;', 'src="/assets/hero.webp"', '6 min read', 'loading="lazy"', 'width="1920"', 'alt="&lt;script&gt;Title&lt;/script&gt;"')
        ->not->toContain('<script>Title', '<img onerror=bad>');
});

it('can render a card without fabricating a missing image', function (): void {
    $result = ListingFile::prepare('study', '/projects/subject/', 'No image', []);
    expect($result->after)->toContain('<figure class="hx-card-media--empty"><span class="hx-date">Study</span></figure>', 'View the study')->not->toContain('src=""');
});

it('refuses unsafe and malformed card input before any public writes', function (array $card): void {
    $item = lfSave(changes: ['data' => ['blocks' => [], 'card' => $card]]);
    $index = file_get_contents($this->site.'/insights/index.html');
    $result = lfPublish($item)->assertJsonPath('ok', false);
    expect($result->json('error'))->not->toBeEmpty()
        ->and(file_get_contents($this->site.'/insights/index.html'))->toBe($index)
        ->and(is_file($this->site.'/insights/subject/index.html'))->toBeFalse()
        ->and(Page::findOrFail($item['id'])->status->value)->toBe('draft')
        ->and(StudioDraft::where('page_id', $item['id'])->exists())->toBeTrue();
})->with([
    'script' => [['src' => 'javascript:alert(1)']],
    'protocol relative' => [['src' => '//evil.test/a.webp']],
    'encoded traversal' => [['src' => '/assets/%2e%2e/secret']],
    'attribute break' => [['src' => '/assets/x" onerror="bad']],
    'nested text' => [['text' => ['bad']]],
]);

it('fails closed on absent ambiguous or unsupported index files before publishing', function (string $shape): void {
    $item = lfSave();
    $file = $this->site.'/insights/index.html';
    match ($shape) {
        'missing' => unlink($file),
        'no cards' => file_put_contents($file, '<html><main>No cards</main></html>'),
        'duplicate containers' => file_put_contents($file, lfShell('').'<div class="hx-cards"></div>'),
        'nested layout' => file_put_contents($file, lfShell('<div><a class="hx-card" href="/x/">X</a></div>')),
        'unknown element' => file_put_contents($file, lfShell('<p>Unsupported</p>')),
        'non-card link' => file_put_contents($file, lfShell('<a href="/x/">Not a card</a>')),
        'wrong container' => file_put_contents($file, str_replace('div', 'section', lfShell(''))),
    };
    $before = is_file($file) ? file_get_contents($file) : null;
    lfPublish($item)->assertJsonPath('ok', false);
    expect(is_file($this->site.'/insights/subject/index.html'))->toBeFalse()
        ->and(Page::findOrFail($item['id'])->status->value)->toBe('draft')
        ->and(is_file($file) ? file_get_contents($file) : null)->toBe($before);
})->with(['missing', 'no cards', 'duplicate containers', 'nested layout', 'unknown element', 'non-card link', 'wrong container']);

it('refuses a stale prepared listing instead of losing an outside edit', function (): void {
    $prepared = ListingFile::prepare('post', '/insights/subject/', 'Subject', []);
    $file = $this->site.'/insights/index.html';
    $outside = lfShell(lfOther()).'<!-- external edit -->';
    file_put_contents($file, $outside);
    expect(fn () => $prepared->write($this->owner))->toThrow(RuntimeException::class, 'changed during publication')
        ->and(file_get_contents($file))->toBe($outside);
});

it('rejects listing symlinks even when their target stays inside public', function (): void {
    $file = $this->site.'/insights/index.html';
    rename($file, $file.'.kept');
    symlink($file.'.kept', $file);
    expect(fn () => ListingFile::prepare('post', '/insights/subject/', 'Subject', []))->toThrow(RuntimeException::class, 'missing or unsafe');
});

it('denies editor publication and withdrawal without changing either listing', function (): void {
    $item = lfSave();
    $files = array_map(fn ($folder) => file_get_contents($this->site.'/'.$folder.'/index.html'), ['insights', 'projects']);
    $this->actingAs($this->userWithRole('editor'));
    lfApi(['action' => 'cms_preview'] + $item)->assertJsonPath('ok', true);
    lfApi(['action' => 'cms_published'] + $item)->assertForbidden();
    lfApi(['action' => 'cms_status', 'status' => 'draft'] + $item)->assertForbidden();
    expect(array_map(fn ($folder) => file_get_contents($this->site.'/'.$folder.'/index.html'), ['insights', 'projects']))->toBe($files);
});

it('keeps unrelated listing changes made after article Preview', function (): void {
    $item = lfSave();
    $preview = lfApi(['action' => 'cms_preview'] + $item)->assertJsonPath('ok', true)->json();
    $file = $this->site.'/insights/index.html';
    file_put_contents($file, lfShell(lfOther()).'<!-- new independent curation -->');
    lfApi(['action' => 'cms_published', 'preview_hash' => $preview['html_hash'], 'dependency_hash' => $preview['dependency_hash']] + $item)->assertJsonPath('ok', true);
    expect(file_get_contents($file))->toContain('<!-- new independent curation -->', lfOther(), 'Public subject');
});

it('preflights withdrawal before retiring the live article', function (): void {
    $item = lfPublish(lfSave())->assertJsonPath('ok', true)->json('item');
    file_put_contents($this->site.'/insights/index.html', '<main>Changed layout</main>');
    lfApi(['action' => 'cms_status', 'status' => 'draft'] + $item)->assertJsonPath('ok', false);
    expect(is_file($this->site.'/insights/subject/index.html'))->toBeTrue()
        ->and(Page::findOrFail($item['id'])->status->value)->toBe('published');
});

it('preserves actual shipped listing chrome scripts and every unrelated curated card', function (string $type, string $folder, string $slug): void {
    $original = (string) file_get_contents(base_path('public/'.$folder.'/index.html'));
    file_put_contents($this->site.'/'.$folder.'/index.html', $original);
    $result = ListingFile::prepare($type, '/'.$folder.'/'.$slug.'/', 'Synthetic replacement', ['card' => ['text' => 'Changed copy']]);
    $pattern = '~<a\b[^>]*href="/'.$folder.'/'.$slug.'/"[^>]*>.*?</a>~s';
    $mask = static fn (string $h): string => preg_replace('~<head>.*?</head>~s', '[MANAGED HEAD]', preg_replace($pattern, '[TARGET]', str_replace('Six anonymous studies', 'Illustrative design studies', $h)));
    expect($mask($result->after))->toBe($mask($original));
})->with(['Blog' => ['post', 'insights', 'interior-design-cost-pakistan'], 'Portfolio' => ['study', 'projects', 'cafe-corner']]);

it('rejects unsupported type and cross-context article paths without touching an index', function (string $type, string $slug): void {
    expect(fn () => ListingFile::prepare($type, $slug, 'No write', []))->toThrow(RuntimeException::class);
})->with(['wrong folder' => ['post', '/projects/subject/'], 'traversal' => ['post', '/insights/../subject/'], 'unsupported type' => ['city', '/lahore/subject/']]);
