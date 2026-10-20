<?php

declare(strict_types=1);

use App\Domain\Website\Enums\PageStatus;
use App\Domain\Website\Enums\PageType;
use App\Domain\Website\Models\ContentEntry;
use App\Domain\Website\Models\Media;
use App\Domain\Website\Models\Page;

/*
| The ported v2.1 admin talks to Woodex OS through one seam: `{action}` in, `{ok, ...}` out. These tests
| hold the two promises that seam makes — the OS session and the OS permission decide who may call it, and
| an action that has not been ported says so instead of pretending.
*/

beforeEach(function () {
    $this->owner = $this->userWithRole('owner');
    $this->actingAs($this->owner);
});

it('opens a ported screen inside the OS session', function () {
    $this->get(route('admin.website.logos'))
        ->assertOk()
        ->assertSee('Client logos')
        ->assertSee('wx21-strip', false)
        ->assertSee('/assets/admin-v21/admin-logos.js', false);
});

it('does not open a screen that is not switched on', function () {
    $this->get('/admin/website/studio/media')->assertNotFound();
});

it('keeps people without website.edit out of the screen and the api', function () {
    $this->actingAs($this->userWithRole('sales'));

    $this->get(route('admin.website.logos'))->assertForbidden();
    $this->postJson(route('admin.website.studio.api'), ['action' => 'status'])->assertForbidden();
});

it('answers status and me from the OS session, never from a token header', function () {
    $this->postJson(route('admin.website.studio.api'), ['action' => 'status'])
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('user.email', $this->owner->email);

    $this->postJson(route('admin.website.studio.api'), ['action' => 'me'], ['X-WX-ADM' => 'forged'])
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('user.name', $this->owner->name);
});

it('says so when an action has not been ported, instead of pretending', function () {
    $this->postJson(route('admin.website.studio.api'), ['action' => 'media_list'])
        ->assertOk()
        ->assertJsonPath('ok', false)
        ->assertJsonPath('error', fn (string $error): bool => str_contains($error, 'not ported yet'));
});

it('reads the client logos from the home page section', function () {
    $home = Page::query()->create([
        'type' => PageType::Home,
        'slug' => '/',
        'title' => 'Home',
        'status' => PageStatus::Published,
    ]);

    $home->blocks()->create([
        'type' => 'clients',
        'block_key' => 'bcl00001',
        'data' => ['heading' => 'Trusted by', 'layout' => 'slider', 'speed' => 45, 'grey' => false, 'library_ids' => [], 'logos' => []],
        'visible' => true,
        'position' => 0,
    ]);

    $this->postJson(route('admin.website.studio.api'), ['action' => 'logos_get'])
        ->assertOk()
        ->assertJsonPath('data.kicker', 'Trusted by')
        ->assertJsonPath('data.mode', 'slider')
        ->assertJsonPath('data.speed', 45)
        ->assertJsonPath('data.grey', false)
        ->assertJsonPath('data.show', false);
});

it('writes the display settings back to the section the site renders', function () {
    $home = Page::query()->create([
        'type' => PageType::Home,
        'slug' => '/',
        'title' => 'Home',
        'status' => PageStatus::Published,
    ]);

    $block = $home->blocks()->create([
        'type' => 'clients',
        'block_key' => 'bcl00002',
        'data' => ['heading' => 'Trusted by', 'library_ids' => [], 'logos' => []],
        'visible' => true,
        'position' => 0,
    ]);

    $this->postJson(route('admin.website.studio.api'), [
        'action' => 'logos_save',
        'data' => [
            'show' => true,
            'kicker' => 'Our clients',
            'title' => 'Brands we work with',
            'mode' => 'slider',
            'speed' => 25,
            'grey' => false,
            'items' => [],
        ],
    ])->assertOk()->assertJsonPath('ok', true);

    $data = $block->refresh()->data;

    expect($data['heading'])->toBe('Our clients')
        ->and($data['heading_strong'])->toBe('Brands we work with')
        ->and($data['layout'])->toBe('slider')
        ->and($data['speed'])->toBe(25)
        ->and($data['grey'])->toBeFalse();
});

it('creates a library entry for every logo it saves', function () {
    $home = Page::query()->create([
        'type' => PageType::Home,
        'slug' => '/',
        'title' => 'Home',
        'status' => PageStatus::Published,
    ]);

    $block = $home->blocks()->create([
        'type' => 'clients',
        'block_key' => 'bcl00003',
        'data' => ['heading' => 'Trusted by', 'library_ids' => [], 'logos' => []],
        'visible' => true,
        'position' => 0,
    ]);

    $media = Media::query()->create([
        'disk' => 'public',
        'path' => 'website/2026/10/brand.png',
        'alt' => 'Brand',
        'title' => 'Brand',
        'folder' => 'website/2026/10',
        'uploaded_by' => $this->owner->id,
    ]);

    $this->postJson(route('admin.website.studio.api'), [
        'action' => 'logos_save',
        'data' => [
            'show' => true,
            'kicker' => 'Trusted by',
            'title' => '',
            'mode' => 'grid',
            'speed' => 30,
            'grey' => true,
            'items' => [['name' => 'Brand', 'logo' => '/media/'.$media->id.'/brand.png?v=1', 'url' => 'https://example.com']],
        ],
    ])->assertOk()->assertJsonPath('ok', true);

    $entry = ContentEntry::query()->where('kind', ContentEntry::LOGO)->firstOrFail();

    expect($entry->data['media_id'])->toBe($media->id)
        ->and($entry->data['name'])->toBe('Brand')
        ->and($entry->data['url'])->toBe('https://example.com')
        ->and($block->refresh()->data['library_ids'])->toBe([$entry->id]);
});

it('refuses an upload that is not an image', function () {
    $this->postJson(route('admin.website.studio.api'), [
        'action' => 'media_upload',
        'name' => 'notes.txt',
        'data' => base64_encode('not an image at all'),
    ])->assertOk()->assertJsonPath('ok', false);
});

it('creates the client row before the enquiry form, and keeps every section in order', function () {
    $home = Page::query()->create([
        'type' => PageType::Home,
        'slug' => '/',
        'title' => 'Home',
        'status' => PageStatus::Published,
    ]);

    foreach (['hero', 'process', 'faq'] as $position => $type) {
        $home->blocks()->create(['type' => $type, 'block_key' => 'b'.$position, 'data' => [], 'visible' => true, 'position' => $position]);
    }

    $home->blocks()->create(['type' => 'form', 'block_key' => 'bform', 'data' => [], 'visible' => true, 'position' => 3]);
    $home->blocks()->create(['type' => 'text', 'block_key' => 'btext', 'data' => [], 'visible' => true, 'position' => 4]);

    $this->postJson(route('admin.website.studio.api'), [
        'action' => 'logos_save',
        'data' => ['show' => true, 'kicker' => 'Trusted by', 'title' => '', 'mode' => 'grid', 'speed' => 30, 'grey' => true, 'items' => []],
    ])->assertOk()->assertJsonPath('ok', true);

    $order = $home->blocks()->orderBy('position')->pluck('type')->map->value->all();

    expect($order)->toBe(['hero', 'process', 'faq', 'clients', 'form', 'text'])
        ->and($home->blocks()->orderBy('position')->pluck('position')->all())->toBe([0, 1, 2, 3, 4, 5]);
});
