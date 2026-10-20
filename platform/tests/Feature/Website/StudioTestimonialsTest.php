<?php

declare(strict_types=1);

use App\Domain\Website\Enums\BlockType;
use App\Domain\Website\Enums\PageStatus;
use App\Domain\Website\Enums\PageType;
use App\Domain\Website\Models\ContentEntry;
use App\Domain\Website\Models\Page;
use App\Support\AdminNavigation;

/*
| The Testimonials screen, ported from the v2.1 admin (docs/audit/16.17).
|
| One shared review library (content_entries, kind = testimonial); a page's Reviews section picks from it.
| The words are quoted verbatim, so the screen stores exactly what was typed and says which pages show it.
*/

beforeEach(function () {
    $this->owner = $this->userWithRole('owner');
    $this->actingAs($this->owner);
});

function reviewEntry(string $text, string $name, int $position = 0, string $when = '', string $source = ''): ContentEntry
{
    return ContentEntry::query()->create([
        'kind' => ContentEntry::TESTIMONIAL,
        'data' => ['text' => $text, 'name' => $name, 'when' => $when, 'source' => $source],
        'position' => $position,
    ]);
}

it('opens the testimonials screen inside the OS session', function () {
    $this->get(route('admin.website.testimonials'))
        ->assertOk()
        ->assertSee('Testimonials')
        ->assertSee('/assets/admin-v21/admin-testimonials.js', false);
});

it('keeps people without website.edit out', function () {
    $this->actingAs($this->userWithRole('sales'));

    $this->get(route('admin.website.testimonials'))->assertForbidden();
    $this->postJson(route('admin.website.studio.api'), ['action' => 'cms_testimonials'])->assertForbidden();
});

it('lists the reviews in library order, with the words as written', function () {
    reviewEntry('They finished two weeks early.', 'Bilal Ahmed', 1, 'March 2026', 'Google');
    reviewEntry('The kitchen is exactly what we asked for.', 'Ayesha Khan', 0, 'January 2026', 'Google');

    $items = $this->postJson(route('admin.website.studio.api'), ['action' => 'cms_testimonials'])
        ->assertOk()
        ->json('items');

    expect($items)->toHaveCount(2)
        ->and($items[0]['name'])->toBe('Ayesha Khan')
        ->and($items[0]['text'])->toBe('The kitchen is exactly what we asked for.')
        ->and($items[0]['when'])->toBe('January 2026')
        ->and($items[0]['source'])->toBe('Google')
        ->and($items[1]['name'])->toBe('Bilal Ahmed');
});

it('shows which pages quote a review', function () {
    $entry = reviewEntry('Excellent finish.', 'Sara Malik');

    $page = Page::query()->create([
        'title' => 'Office Fit-Out', 'slug' => '/office-fit-out/', 'type' => PageType::Service, 'status' => PageStatus::Published,
    ]);

    $page->blocks()->create([
        'type' => BlockType::Reviews, 'position' => 0, 'visible' => true,
        'data' => ['heading' => 'What our clients say', 'library_ids' => [$entry->id], 'items' => []],
    ]);

    $items = $this->postJson(route('admin.website.studio.api'), ['action' => 'cms_testimonials'])->json('items');

    expect($items[0]['used_on'])->toHaveCount(1)
        ->and($items[0]['used_on'][0]['title'])->toBe('Office Fit-Out')
        ->and($items[0]['used_on'][0]['url'])->toBe('/office-fit-out/');
});

it('adds a review and edits it in place', function () {
    $created = $this->postJson(route('admin.website.studio.api'), [
        'action' => 'cms_testimonial_save',
        'text' => 'Best decision we made.',
        'name' => 'Hamza Tariq',
        'when' => 'May 2026',
        'source' => 'Google',
    ])->assertOk()->json('item');

    $this->postJson(route('admin.website.studio.api'), [
        'action' => 'cms_testimonial_save',
        'id' => $created['id'],
        'text' => 'Best decision we made — twice.',
        'name' => 'Hamza Tariq',
        'when' => 'May 2026',
        'source' => 'Google',
    ])->assertOk()->assertJsonPath('item.text', 'Best decision we made — twice.');

    expect(ContentEntry::query()->ofKind(ContentEntry::TESTIMONIAL)->count())->toBe(1);
});

it('refuses a review with no words and one with no name', function () {
    $this->postJson(route('admin.website.studio.api'), [
        'action' => 'cms_testimonial_save', 'text' => '   ', 'name' => 'Ayesha',
    ])->assertOk()
        ->assertJsonPath('ok', false)
        ->assertJsonPath('error', 'Paste the client’s review — a review with no words cannot be shown.');

    $this->postJson(route('admin.website.studio.api'), [
        'action' => 'cms_testimonial_save', 'text' => 'Great work.', 'name' => '',
    ])->assertOk()
        ->assertJsonPath('ok', false)
        ->assertJsonPath('error', 'Write the client’s name — an unsigned review reads as invented.');

    expect(ContentEntry::query()->ofKind(ContentEntry::TESTIMONIAL)->count())->toBe(0);
});

it('saves a new order and deletes without touching the page that quoted it', function () {
    $a = reviewEntry('First review', 'A', 0);
    $b = reviewEntry('Second review', 'B', 1);

    $page = Page::query()->create([
        'title' => 'Home', 'slug' => '/', 'type' => PageType::Home, 'status' => PageStatus::Published,
    ]);
    $page->blocks()->create([
        'type' => BlockType::Reviews, 'position' => 0, 'visible' => true,
        'data' => ['heading' => 'Reviews', 'library_ids' => [$a->id], 'items' => []],
    ]);

    $this->postJson(route('admin.website.studio.api'), [
        'action' => 'cms_testimonial_order', 'ids' => [$b->id, $a->id],
    ])->assertOk()->assertJsonPath('note', 'Order saved.');

    $items = $this->postJson(route('admin.website.studio.api'), ['action' => 'cms_testimonials'])->json('items');
    expect($items[0]['name'])->toBe('B');

    $this->postJson(route('admin.website.studio.api'), ['action' => 'cms_testimonial_delete', 'id' => $a->id])
        ->assertOk()
        ->assertJsonPath('note', 'Deleted — 1 reviews section(s) were showing it and no longer do.');

    expect($page->blocks()->count())->toBe(1);
});

it('lists Testimonials as a live destination, with nothing left on “soon”', function () {
    $items = collect(AdminNavigation::for($this->owner))
        ->flatMap(fn (array $group): array => $group['items']);

    expect($items->firstWhere('label', 'Testimonials')['route'])->toBe('admin.website.testimonials')
        ->and($items->filter(fn (array $i): bool => ($i['phase'] ?? null) === 'soon'))->toHaveCount(0);
});
