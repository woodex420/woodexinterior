<?php

declare(strict_types=1);

use App\Domain\Website\Models\ContentEntry;
use App\Domain\Website\Models\Page;
use App\Domain\Website\Models\PageTemplate;
use App\Domain\Website\Models\StudioResource;
use App\Domain\Website\Studio\StudioResources;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function (): void {
    $this->owner = $this->userWithRole('owner');
    $this->actingAs($this->owner);
    $this->site = storage_path('framework/testing/resource-api-'.bin2hex(random_bytes(8)));
    mkdir($this->site, 0700, true);
    file_put_contents($this->site.'/index.html', '<html><main>Unchanged public sentinel</main></html>');
    $this->app->usePublicPath($this->site);
});

afterEach(function (): void {
    (new Filesystem)->deleteDirectory($this->site);
});

function resourceData(string $kind = 'template'): array
{
    return $kind === 'template' ? ['name' => 'Synthetic layout', 'hero' => 'navy', 'toc' => true, 'meta' => false, 'facts' => true,
        'sections' => array_map(fn ($key): array => ['k' => $key, 'on' => $key !== 'quote'], ['summary', 'body', 'faqs', 'related', 'quote'])]
        : ['name' => 'Synthetic FAQ group', 'items' => [['q' => 'Synthetic question?', 'a' => 'Synthetic **answer**.']]];
}

function resourceApi(array $payload)
{
    return test()->postJson(route('admin.website.studio.api'), $payload);
}

function resourceCreate(string $kind = 'template', string $scope = 'post', array $extra = []): array
{
    return resourceApi($extra + ['action' => 'cms_resource_save', 'kind' => $kind, 'scope' => $scope, 'data' => resourceData($kind)])
        ->assertJsonPath('ok', true)->json('item');
}

function resourceWrite(string $action, array $item, array $extra = [])
{
    return resourceApi(['action' => 'cms_resource_'.$action] + $extra + $item);
}

it('isolates saves and publishes only explicitly saved resource snapshots with immutable service history', function (string $kind, string $scope): void {
    $pages = Page::query()->get()->map->getAttributes()->all();
    $oldTemplates = PageTemplate::query()->get()->map->getAttributes()->all();
    $oldEntries = ContentEntry::query()->get()->map->getAttributes()->all();
    $this->actingAs($this->userWithRole('editor'));
    $draft = resourceCreate($kind, $scope);
    expect($draft['revision'])->toBe(1)->and($draft['has_draft'])->toBeTrue()->and($draft['published'])->toBeNull()
        ->and(DB::table('website_studio_resource_versions')->count())->toBe(0);
    expect(fn () => StudioResources::published($draft['id'], $kind, $scope))->toThrow(RuntimeException::class);
    resourceWrite('publish', $draft)->assertForbidden();
    $this->actingAs($this->owner);
    $public = resourceWrite('publish', $draft, ['data' => ['name' => 'UNSAVED CLIENT DATA'], 'published' => ['name' => 'FORGED']])
        ->assertJsonPath('ok', true)->json('item');
    $snapshot = StudioResources::published($draft['id'], $kind, $scope);
    $history = DB::table('website_studio_resource_versions')->first();
    expect($snapshot['data'])->toBe($draft['data'])->and($snapshot['hash'])->toBe($draft['draft_hash'])
        ->and($public['has_draft'])->toBeFalse()->and($public['revision'])->toBe(2)
        ->and($public['token'])->not->toBe($draft['token'])
        ->and(json_decode($history->payload, true))->toBe($draft['data']);
    $edited = $draft['data'];
    $edited['name'] = 'PRIVATE resource name';
    $pending = resourceWrite('save', $public, ['data' => $edited])->assertJsonPath('ok', true)->json('item');
    expect(StudioResources::published($draft['id'], $kind, $scope))->toBe($snapshot)
        ->and(resourceApi(['action' => 'cms_resource_list', 'kind' => $kind, 'scope' => $scope, 'view' => 'published'])->json('items.0.name'))->toBe($draft['data']['name'])
        ->and(resourceApi(['action' => 'cms_resource_list', 'kind' => $kind, 'scope' => $scope])->json('items.0.name'))->toBe('PRIVATE resource name');
    $next = resourceWrite('publish', $pending)->assertJsonPath('ok', true)->json('item');
    expect($next['published']['name'])->toBe('PRIVATE resource name')
        ->and(DB::table('website_studio_resource_versions')->count())->toBe(2)
        ->and(DB::table('website_studio_resource_versions')->where('id', $history->id)->first())->toEqual($history)
        ->and(Page::query()->get()->map->getAttributes()->all())->toBe($pages)
        ->and(PageTemplate::query()->get()->map->getAttributes()->all())->toBe($oldTemplates)
        ->and(ContentEntry::query()->get()->map->getAttributes()->all())->toBe($oldEntries)
        ->and(file_get_contents($this->site.'/index.html'))->toBe('<html><main>Unchanged public sentinel</main></html>');
})->with([['template', 'post'], ['template', 'study'], ['faq_group', 'shared']]);

it('discards private changes without altering published data and retains a conflict tombstone', function (bool $published): void {
    $item = resourceCreate();
    if ($published) {
        $item = resourceWrite('publish', $item)->assertJsonPath('ok', true)->json('item');
        $item = resourceWrite('save', $item, ['data' => ['name' => 'Private'] + resourceData()])->assertJsonPath('ok', true)->json('item');
    }
    $public = $item['published'];
    $discarded = resourceWrite('discard', $item)->assertJsonPath('ok', true)->json('item');
    expect($discarded['data'])->toBe($public)->and($discarded['has_draft'])->toBeFalse()
        ->and($discarded['revision'])->toBe($item['revision'] + 1)
        ->and(StudioResource::query()->whereKey($item['id'])->exists())->toBeTrue();
    resourceWrite('save', $item)->assertJsonPath('ok', false);
    resourceWrite('publish', $discarded)->assertJsonPath('ok', false);
    $resaved = resourceWrite('save', $discarded, ['data' => resourceData()])->assertJsonPath('ok', true)->json('item');
    expect($resaved['revision'])->toBe($discarded['revision'] + 1)->and($resaved['has_draft'])->toBeTrue();
})->with([false, true]);

it('rejects every stale resource mutation without changing data or history', function (string $action, string $field): void {
    $old = resourceCreate();
    $item = resourceWrite('save', $old)->assertJsonPath('ok', true)->json('item');
    $request = $item;
    $request[$field] = $old[$field];
    $before = StudioResource::findOrFail($item['id'])->getAttributes();
    resourceWrite($action, $request)->assertJsonPath('ok', false);
    expect(StudioResource::findOrFail($item['id'])->getAttributes())->toBe($before)
        ->and(DB::table('website_studio_resource_versions')->count())->toBe(0);
})->with(['save', 'discard', 'publish'])->with(['revision', 'token']);

it('requires both opened resource revision fields for every existing-row write', function (string $action, string $missing): void {
    $item = resourceCreate();
    unset($item[$missing]);
    resourceWrite($action, $item)->assertJsonPath('ok', false);
    expect(StudioResource::query()->firstOrFail()->revision)->toBe(1);
})->with(['save', 'discard', 'publish'])->with(['revision', 'token']);

it('binds publication to the reviewed saved draft hash and makes retry safe', function (): void {
    $item = resourceCreate();
    resourceWrite('publish', $item, ['draft_hash' => str_repeat('0', 64)])->assertJsonPath('ok', false);
    $request = $item;
    unset($request['draft_hash']);
    resourceWrite('publish', $request)->assertJsonPath('ok', false);
    resourceWrite('publish', $item)->assertJsonPath('ok', true);
    resourceWrite('publish', $item)->assertJsonPath('ok', false);
    expect(DB::table('website_studio_resource_versions')->count())->toBe(1);
});

it('rolls back resource promotion if publication history cannot be appended', function (): void {
    $item = resourceCreate();
    $before = StudioResource::findOrFail($item['id'])->getAttributes();
    DB::table('website_studio_resource_versions')->insert(['resource_id' => $item['id'], 'revision' => $item['revision'] + 1,
        'payload' => '{}', 'payload_hash' => str_repeat('0', 64), 'created_at' => now()]);
    resourceWrite('publish', $item)->assertJsonPath('ok', false);
    expect(StudioResource::findOrFail($item['id'])->getAttributes())->toBe($before)
        ->and(DB::table('website_studio_resource_versions')->count())->toBe(1);
});

it('prevents type and scope confusion on reads and writes', function (string $action): void {
    $item = resourceCreate();
    resourceWrite($action, $item, ['scope' => 'study'])->assertJsonPath('ok', false);
    resourceWrite($action, $item, ['kind' => 'faq_group', 'scope' => 'shared'])->assertJsonPath('ok', false);
    expect(StudioResource::findOrFail($item['id'])->revision)->toBe(1);
})->with(['get', 'save', 'discard', 'publish']);

it('keeps resource kinds and context explicit', function (string $kind, string $scope): void {
    resourceApi(['action' => 'cms_resource_save', 'kind' => $kind, 'scope' => $scope, 'data' => resourceData()])->assertJsonPath('ok', false);
    expect(StudioResource::query()->count())->toBe(0);
})->with([['template', 'shared'], ['faq_group', 'post'], ['faq_group', 'study'], ['code', 'post'], ['template', 'city']]);

it('bounds lists and isolates published summaries from unpublished resources and private names', function (): void {
    $first = resourceCreate();
    resourceWrite('publish', $first)->assertJsonPath('ok', true);
    for ($i = 0; $i < 26; $i++) {
        resourceCreate('template', 'post', ['data' => ['name' => 'Private '.$i] + resourceData()]);
    }
    resourceCreate('template', 'study');
    resourceCreate('faq_group', 'shared');
    $payload = ['action' => 'cms_resource_list', 'kind' => 'template', 'scope' => 'post'];
    $page = resourceApi($payload)->assertJsonPath('ok', true)->json();
    expect($page['total'])->toBe(27)->and($page['per_page'])->toBe(25)->and($page['items'])->toHaveCount(25)
        ->and($page['items'][0])->not->toHaveKeys(['data', 'token', 'draft_hash']);
    expect(resourceApi(['page' => 2] + $payload)->json('items'))->toHaveCount(2)
        ->and(resourceApi(['view' => 'published'] + $payload)->json('items'))->toHaveCount(1);
    resourceApi(['page' => 0] + $payload)->assertJsonPath('ok', false);
});

it('enforces HTTP and domain resource authority and no-store reads', function (): void {
    $item = resourceCreate();
    resourceWrite('get', $item)->assertHeader('Cache-Control', 'no-store, private')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    $viewer = $this->userWithRole('accountant');
    $this->actingAs($viewer);
    foreach (['get', 'save', 'discard', 'publish', 'list'] as $action) {
        resourceWrite($action, $item)->assertForbidden();
    }
    expect(fn () => (new StudioResources($viewer))->get($item))->toThrow(HttpException::class);
    $this->actingAs($this->userWithRole('editor'));
    expect(fn () => (new StudioResources(auth()->user()))->publish($item))->toThrow(HttpException::class);
    auth()->forgetGuards();
    resourceWrite('get', $item)->assertUnauthorized();
});

it('canonicalizes object key order but retains template section and FAQ item order', function (): void {
    $item = resourceCreate();
    $data = array_reverse(resourceData(), true);
    $data['sections'] = array_map(fn ($s) => array_reverse($s, true), $data['sections']);
    $same = resourceWrite('save', $item, ['data' => $data])->assertJsonPath('ok', true)->json('item');
    expect($same['draft_hash'])->toBe($item['draft_hash']);
    $data['sections'] = array_reverse($data['sections']);
    $changed = resourceWrite('save', $same, ['data' => $data])->assertJsonPath('ok', true)->json('item');
    expect($changed['draft_hash'])->not->toBe($same['draft_hash']);
});

it('rejects malformed or unbounded template and FAQ definitions without creating resources', function (string $kind, array $data): void {
    resourceApi(['action' => 'cms_resource_save', 'kind' => $kind, 'scope' => $kind === 'template' ? 'post' : 'shared', 'data' => $data])->assertJsonPath('ok', false);
    expect(StudioResource::query()->count())->toBe(0);
})->with([
    'unknown template fields' => ['template', ['raw_html' => '<script>unsafe()</script>'] + resourceData()],
    'implicit default forbidden' => ['template', ['default' => true] + resourceData()],
    'unknown hero' => ['template', ['hero' => 'code'] + resourceData()],
    'missing sections' => ['template', ['sections' => []] + resourceData()],
    'duplicate sections' => ['template', ['sections' => array_fill(0, 5, ['k' => 'body', 'on' => true])] + resourceData()],
    'unknown section' => ['template', ['sections' => array_fill(0, 5, ['k' => 'scripts', 'on' => true])] + resourceData()],
    'string switch' => ['template', ['toc' => 'false'] + resourceData()],
    'integer switch' => ['template', ['facts' => 1] + resourceData()],
    'nested name' => ['template', ['name' => ['nested']] + resourceData()],
    'oversized name' => ['template', ['name' => str_repeat('a', 121)] + resourceData()],
    'FAQ missing answer' => ['faq_group', ['items' => [['q' => 'Question?']]] + resourceData('faq_group')],
    'FAQ blank answer' => ['faq_group', ['items' => [['q' => 'Question?', 'a' => '   ']]] + resourceData('faq_group')],
    'FAQ nested answer' => ['faq_group', ['items' => [['q' => 'Question?', 'a' => ['nested']]]] + resourceData('faq_group')],
    'FAQ unknown keys' => ['faq_group', ['items' => [['q' => 'Question?', 'a' => 'Answer.', 'pages' => ['/']]]] + resourceData('faq_group')],
    'FAQ too many items' => ['faq_group', ['items' => array_fill(0, 101, ['q' => 'Q?', 'a' => 'A.'])] + resourceData('faq_group')],
    'FAQ too long answer' => ['faq_group', ['items' => [['q' => 'Q?', 'a' => str_repeat('a', 5001)]]] + resourceData('faq_group')],
    'FAQ page fanout forbidden' => ['faq_group', ['pages' => ['/']] + resourceData('faq_group')],
]);

it('does not enable legacy adapters or article publication just because resource APIs exist', function (): void {
    $item = resourceCreate();
    resourceWrite('publish', $item)->assertJsonPath('ok', true);
    resourceApi(['action' => 'cms_tpl_list'])->assertJsonPath('ok', false);
    resourceApi(['action' => 'cms_list', 'type' => 'faq'])->assertJsonPath('ok', false);
});

it('ignores forged public and identity columns on resource saves', function (): void {
    $item = resourceCreate('template', 'post', ['published_payload' => ['name' => 'FORGED'], 'published_hash' => str_repeat('a', 64),
        'published_revision' => 999, 'updated_by' => 999]);
    $row = StudioResource::findOrFail($item['id']);
    expect($row->published_payload)->toBeNull()->and($row->published_revision)->toBeNull()
        ->and($row->published_hash)->toBeNull()->and($row->updated_by)->toBe($this->owner->id);
    $this->actingAs($this->userWithRole('editor'));
    $saved = resourceWrite('save', $item, ['published' => resourceData(), 'published_payload' => resourceData(), 'published_revision' => 999])
        ->assertJsonPath('ok', true)->json('item');
    expect($saved['published'])->toBeNull()->and($saved['published_revision'])->toBeNull();
});

it('requires edit authority even when publish permission exists', function (): void {
    $item = resourceCreate();
    $user = $this->userWithRole('accountant');
    $user->givePermissionTo('website.publish');
    $this->actingAs($user);
    resourceWrite('publish', $item)->assertForbidden();
    expect(fn () => (new StudioResources($user))->publish($item))->toThrow(HttpException::class);
});

it('returns private no-store errors without leaking resource data', function (): void {
    $item = resourceCreate('faq_group', 'shared');
    $response = resourceWrite('get', $item, ['id' => 999999])->assertJsonPath('ok', false)
        ->assertHeader('Cache-Control', 'no-store, private')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    expect($response->json())->not->toHaveKeys(['item', 'published', 'token']);
});

it('does not turn a stale save into a newly created resource', function (): void {
    $item = resourceCreate();
    resourceWrite('save', $item, ['id' => 0])->assertJsonPath('ok', false);
    expect(StudioResource::query()->count())->toBe(1);
});

it('treats empty FAQ groups as explicit saved content rather than missing state', function (): void {
    $item = resourceCreate('faq_group', 'shared', ['data' => ['name' => 'Empty synthetic group', 'items' => []]]);
    $published = resourceWrite('publish', $item)->assertJsonPath('ok', true)->json('item');
    expect($published['published']['items'])->toBe([])->and($published['has_draft'])->toBeFalse()
        ->and(StudioResources::published($item['id'], 'faq_group', 'shared')['data']['items'])->toBe([]);
});
