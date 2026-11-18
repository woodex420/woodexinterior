<?php

declare(strict_types=1);

use App\Domain\Website\Enums\PageStatus;
use App\Domain\Website\Models\Page;
use App\Domain\Website\Models\StudioDraft;
use Illuminate\Filesystem\Filesystem;

beforeEach(function (): void {
    $this->actingAs($this->userWithRole('owner'));
    $this->site = storage_path('framework/testing/rich-preview-'.bin2hex(random_bytes(8)));
    foreach (['insights', 'projects'] as $folder) {
        mkdir($this->site.'/'.$folder.'/template', 0700, true);
        file_put_contents($this->site.'/'.$folder.'/template/index.html', '<!doctype html><html><head><title>Shell</title><link rel="canonical" href="https://woodex.com.pk/'.$folder.'/template/"></head><body><header>Unchanged chrome</header><main>Shell content</main><footer>Unchanged footer</footer></body></html>');
    }
    foreach (['insights', 'projects'] as $folder) {
        file_put_contents($this->site.'/'.$folder.'/index.html', '<!doctype html><html><head><title>Synthetic listing</title></head><body><main><h1>Listing</h1><div class="hx-cards"></div></main></body></html>');
    }
    $this->app->usePublicPath($this->site);
});

afterEach(function (): void {
    (new Filesystem)->deleteDirectory($this->site);
});

function richApi(array $payload)
{
    return test()->postJson(route('admin.website.studio.api'), $payload);
}

function richSave(array $data, string $type = 'post'): array
{
    return richApi(['action' => 'cms_save', 'type' => $type, 'title' => 'Synthetic $1 title', 'slug' => 'rich-fixture', 'data' => $data])
        ->assertJsonPath('ok', true)->json('item');
}

it('keeps rich draft content private and publishes byte-identical previews on first publish and republish', function (string $type): void {
    $data = ['blocks' => [
        ['t' => 'h', 'text' => 'Rich $1 content'],
        ['t' => 'gallery', 'caption' => 'Gallery <em>caption</em> $1', 'imgs' => [
            ['src' => '/assets/first.webp', 'alt' => 'First "photo"'], ['src' => '/assets/second.webp']]],
        ['t' => 'ba', 'before' => '/assets/before.webp', 'after' => '/assets/after.webp', 'caption' => 'Synthetic room'],
        ['t' => 'faq', 'text' => "Q: Synthetic question?\nA: A **bold** answer with [contact](/contact/).\nContinued answer.\n\nSecond question?\nSecond answer."],
        ['t' => 'p', 'text' => 'After rich blocks $0 $1 \\1 <script>not code</script>'],
    ], 'ctaTitle' => 'Discuss this <study> $1'];
    if ($type === 'study') {
        $data += ['factsTitle' => 'Synthetic <facts>', 'factsNote' => 'Illustration only $1', 'facts' => [['k' => 'Scope', 'v' => 'Synthetic scope'], ['k' => '', 'v' => ''], ['k' => 'Materials', 'v' => '<wood> & stone']]];
    }
    $item = richSave($data, $type);
    $data = $item['data']; // Normal HTTP middleware converts empty strings to null.
    $page = Page::query()->findOrFail($item['id']);
    $folder = $type === 'post' ? 'insights' : 'projects';
    $path = $this->site.'/'.$folder.'/rich-fixture/index.html';
    $shell = file_get_contents($this->site.'/'.$folder.'/template/index.html');
    for ($round = 0; $round < 2; $round++) {
        $attributes = $page->fresh()->getAttributes();
        $blocks = $page->blocks()->get()->toArray();
        $draft = StudioDraft::query()->where('page_id', $page->id)->firstOrFail()->getAttributes();
        $public = is_file($path) ? file_get_contents($path) : null;
        $this->actingAs($this->userWithRole('editor'));
        $preview = richApi(['action' => 'cms_preview'] + $item)->assertJsonPath('ok', true)->json();
        $html = $preview['html'];
        expect($html)->toContain('Synthetic $1 title', 'Rich $1 content', 'class="wx-gal"', 'First &quot;photo&quot;',
            'Gallery &lt;em&gt;caption&lt;/em&gt; $1', 'class="wx-ba"', 'class="wx-ba-b"', 'Synthetic room before', 'Synthetic room after',
            'type="range"', 'class="wx-faq"', '<summary>Synthetic question?</summary>', 'A <strong>bold</strong> answer',
            '<a href="/contact/">contact</a>', 'Continued answer.', '<summary>Second question?</summary>',
            'After rich blocks $0 $1 \\1 &lt;script&gt;not code&lt;/script&gt;', '<h2>Discuss this &lt;study&gt; $1</h2>', 'Unchanged chrome', 'Unchanged footer')
            ->and($preview['html_hash'])->toBe(hash('sha256', $html))
            ->and($page->fresh()->getAttributes())->toBe($attributes)
            ->and($page->blocks()->get()->toArray())->toBe($blocks)
            ->and(StudioDraft::query()->where('page_id', $page->id)->firstOrFail()->getAttributes())->toBe($draft)
            ->and(is_file($path) ? file_get_contents($path) : null)->toBe($public);
        if ($type === 'study') {
            expect($html)->toContain('class="dx-side"', 'Synthetic &lt;facts&gt;', '<strong>Scope</strong> Synthetic scope', '&lt;wood&gt; &amp; stone', 'Illustration only $1');
        } else {
            expect($html)->not->toContain('class="dx-side"');
        }
        richApi(['action' => 'cms_published'] + $item)->assertForbidden();
        $this->actingAs($this->userWithRole('owner'));
        $item = richApi(['action' => 'cms_published', 'preview_hash' => $preview['html_hash']] + $item)->assertJsonPath('ok', true)->json('item');
        expect(file_get_contents($path))->toBe($html)
            ->and(file_get_contents($this->site.'/'.$folder.'/template/index.html'))->toBe($shell)
            ->and(richApi(['action' => 'cms_get', 'id' => $item['id'], 'type' => $type])->json('item.data'))->toBe($data);
        $item = richApi(['action' => 'cms_save', 'title' => 'Synthetic $1 title'] + $item)->assertJsonPath('ok', true)->json('item');
    }
})->with(['post', 'study']);

it('renders blank rich blocks and blank facts without phantom sections', function (string $type): void {
    $item = richSave(['blocks' => [['t' => 'gallery', 'imgs' => []], ['t' => 'ba'], ['t' => 'faq', 'text' => '']],
        'facts' => [['k' => '', 'v' => '']], 'ctaTitle' => ''], $type);
    $html = richApi(['action' => 'cms_preview'] + $item)->assertJsonPath('ok', true)->json('html');
    expect($html)->not->toContain('class="wx-gal"', 'class="wx-ba"', 'class="wx-faq"', 'class="dx-side"')
        ->and($html)->toContain('Ready to start your project?');
})->with(['post', 'study']);

it('renders a note-only Portfolio sidebar and default image alt text without inventing facts', function (): void {
    $item = richSave(['facts' => [], 'factsNote' => 'Synthetic note only', 'blocks' => [
        ['t' => 'gallery', 'imgs' => [['src' => '/assets/a.webp'], ['src' => '/assets/b.webp']]],
        ['t' => 'ba', 'before' => '/assets/a.webp', 'after' => '/assets/b.webp'],
    ]], 'study');
    $html = richApi(['action' => 'cms_preview'] + $item)->assertJsonPath('ok', true)->json('html');
    expect($html)->toContain('<h2>Study facts</h2>', 'Synthetic note only', 'alt="Project photo 1"', 'alt="Project photo 2"', 'alt="Project before"', 'alt="Project after"')
        ->not->toContain('<ul class="dx-arrow-list">');
});

it('refuses unsafe or malformed rich content without losing the private draft or modifying public state', function (array $data): void {
    $item = richSave($data, 'study');
    $page = Page::query()->findOrFail($item['id']);
    $attributes = $page->getAttributes();
    $draft = StudioDraft::query()->where('page_id', $page->id)->firstOrFail()->getAttributes();
    richApi(['action' => 'cms_preview'] + $item)->assertJsonPath('ok', false);
    richApi(['action' => 'cms_published'] + $item)->assertJsonPath('ok', false);
    expect($page->fresh()->getAttributes())->toBe($attributes)
        ->and($page->status)->toBe(PageStatus::Draft)
        ->and($page->blocks()->count())->toBe(0)
        ->and(StudioDraft::query()->where('page_id', $page->id)->firstOrFail()->getAttributes())->toBe($draft)
        ->and(is_file($this->site.'/projects/rich-fixture/index.html'))->toBeFalse();
})->with([
    'gallery script URL' => [['blocks' => [['t' => 'gallery', 'imgs' => [['src' => 'javascript:alert(1)']]]]]],
    'gallery external protocol relative' => [['blocks' => [['t' => 'gallery', 'imgs' => [['src' => '//example.invalid/a']]]]]],
    'gallery encoded traversal' => [['blocks' => [['t' => 'gallery', 'imgs' => [['src' => '/%2e%2e/.env']]]]]],
    'gallery encoded backslash' => [['blocks' => [['t' => 'gallery', 'imgs' => [['src' => '/%5cexample.invalid/a']]]]]],
    'gallery encoded newline' => [['blocks' => [['t' => 'gallery', 'imgs' => [['src' => '/assets/a%0a.webp']]]]]],
    'gallery malformed images' => [['blocks' => [['t' => 'gallery', 'imgs' => 'not a list']]]],
    'gallery malformed image' => [['blocks' => [['t' => 'gallery', 'imgs' => ['not an image']]]]],
    'gallery nested alt' => [['blocks' => [['t' => 'gallery', 'imgs' => [['src' => '/assets/a.webp', 'alt' => ['nested']]]]]]],
    'gallery nested src' => [['blocks' => [['t' => 'gallery', 'imgs' => [['src' => ['nested']]]]]]],
    'before data URL' => [['blocks' => [['t' => 'ba', 'before' => 'data:image/svg+xml,unsafe', 'after' => '/assets/a.webp']]]],
    'after unsafe URL' => [['blocks' => [['t' => 'ba', 'before' => '/assets/a.webp', 'after' => 'file:///etc/passwd']]]],
    'half comparison' => [['blocks' => [['t' => 'ba', 'before' => '/assets/a.webp']]]],
    'comparison nested caption' => [['blocks' => [['t' => 'ba', 'caption' => ['nested']]]]],
    'FAQ incomplete' => [['blocks' => [['t' => 'faq', 'text' => "Good?\nAnswer.\n\nUnanswered?"]]]],
    'FAQ empty question' => [['blocks' => [['t' => 'faq', 'text' => "Q:\nA: Answer."]]]],
    'FAQ empty answer' => [['blocks' => [['t' => 'faq', 'text' => "Question?\nA:"]]]],
    'FAQ protocol relative markdown' => [['blocks' => [['t' => 'faq', 'text' => "Question?\n[unsafe](//example.invalid/a)"]]]],
    'FAQ encoded backslash markdown' => [['blocks' => [['t' => 'faq', 'text' => "Question?\n[unsafe](/%5cexample.invalid/a)"]]]],
    'FAQ nested text' => [['blocks' => [['t' => 'faq', 'text' => ['nested']]]]],
    'facts malformed list' => [['facts' => 'not a list']],
    'facts malformed entry' => [['facts' => ['not a fact']]],
    'facts nested value' => [['facts' => [['k' => 'Scope', 'v' => ['nested']]]]],
    'facts nested heading' => [['factsTitle' => ['nested']]],
    'facts nested note' => [['factsNote' => ['nested']]],
    'CTA nested heading' => [['ctaTitle' => ['nested']]],
]);

it('does not silently discard Portfolio facts on Blog posts', function (): void {
    $item = richSave(['facts' => [['k' => 'Scope', 'v' => 'Synthetic scope']]]);
    richApi(['action' => 'cms_preview'] + $item)->assertJsonPath('ok', false);
    richApi(['action' => 'cms_published'] + $item)->assertJsonPath('ok', false);
});

it('uses only local contained files for image dimension inspection', function (): void {
    mkdir($this->site.'/assets', 0700, true);
    // A one-pixel GIF is a dimension fixture, not uploaded or production data.
    $gif = base64_decode('R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==');
    file_put_contents($this->site.'/assets/local.gif', $gif);
    $outside = storage_path('framework/testing/image-'.bin2hex(random_bytes(8)).'.gif');
    file_put_contents($outside, $gif);
    symlink($outside, $this->site.'/assets/outside.gif');
    try {
        $item = richSave(['blocks' => [['t' => 'gallery', 'imgs' => [['src' => '/assets/local.gif'], ['src' => '/assets/outside.gif'], ['src' => 'https://example.invalid/remote.gif']]]]]);
        $html = richApi(['action' => 'cms_preview'] + $item)->assertJsonPath('ok', true)->json('html');
        expect($html)->toContain('src="/assets/local.gif" alt="Project photo 1" width="1" height="1"',
            'src="/assets/outside.gif" alt="Project photo 2" width="1920" height="1280"',
            'src="https://example.invalid/remote.gif" alt="Project photo 3" width="1920" height="1280"');
    } finally {
        unlink($outside);
    }
});

it('keeps FAQ question markup inert and accepts CRLF and Q-dot A-dot notation', function (): void {
    $item = richSave(['blocks' => [['t' => 'faq', 'text' => "Q. <img src=x onerror=alert(1)>?\r\nA. **Plain** & safe [contact](/contact/?q=hello%20world).\r\n\r\nAnother?\r\n<script>not code</script>"]]]);
    $html = richApi(['action' => 'cms_preview'] + $item)->assertJsonPath('ok', true)->json('html');
    expect($html)->toContain('<summary>&lt;img src=x onerror=alert(1)&gt;?</summary>', '<strong>Plain</strong> &amp; safe',
        'href="/contact/?q=hello%20world"', '&lt;script&gt;not code&lt;/script&gt;')->not->toContain('<img src=x', '<script>not code');
});
