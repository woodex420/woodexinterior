<?php

declare(strict_types=1);

use App\Domain\Website\Enums\BlockType;
use App\Domain\Website\Enums\PageStatus;
use App\Domain\Website\Enums\PageType;
use App\Domain\Website\Models\ContentEntry;
use App\Domain\Website\Models\Page;
use App\Domain\Website\Services\ContentLibrary;
use App\Support\AdminNavigation;

/*
| The FAQs screen, ported from the v2.1 admin (docs/audit/16.16).
|
| v2.1 had named "FAQ groups"; the OS keeps one shared library (content_entries, kind = faq) that a page's
| FAQ section picks from. The screen is the same promise with one store: write the answer once, reuse it
| anywhere, and see where it is already used.
*/

beforeEach(function () {
    $this->owner = $this->userWithRole('owner');
    $this->actingAs($this->owner);
});

function faqEntry(string $question, string $answer, int $position = 0): ContentEntry
{
    return ContentEntry::query()->create([
        'kind' => ContentEntry::FAQ,
        'data' => ['question' => $question, 'answer' => $answer],
        'position' => $position,
    ]);
}

it('opens the faqs screen inside the OS session', function () {
    $this->get(route('admin.website.faqs'))
        ->assertOk()
        ->assertSee('FAQs')
        ->assertSee('/assets/admin-v21/admin-faqs.js', false);
});

it('keeps people without website.edit out', function () {
    $this->actingAs($this->userWithRole('sales'));

    $this->get(route('admin.website.faqs'))->assertForbidden();
    $this->postJson(route('admin.website.studio.api'), ['action' => 'cms_faqs'])->assertForbidden();
});

it('lists the answers in library order', function () {
    faqEntry('How long does a home interior take?', 'Ten to fourteen weeks.', 2);
    faqEntry('Do you work outside Lahore?', 'Yes, across Punjab.', 1);

    $items = $this->postJson(route('admin.website.studio.api'), ['action' => 'cms_faqs'])
        ->assertOk()
        ->json('items');

    expect($items)->toHaveCount(2)
        ->and($items[0]['question'])->toBe('Do you work outside Lahore?')
        ->and($items[1]['question'])->toBe('How long does a home interior take?')
        ->and($items[0]['used_on'])->toBe([]);
});

it('shows which pages already use an answer', function () {
    $entry = faqEntry('Do you give a written quote?', 'Yes, itemised, before work starts.');

    $page = Page::query()->create([
        'title' => 'Kitchens',
        'slug' => '/kitchens/',
        'type' => PageType::Service,
        'status' => PageStatus::Published,
    ]);

    $page->blocks()->create([
        'type' => BlockType::Faq,
        'position' => 0,
        'visible' => true,
        'data' => ['heading' => 'Questions', 'library_ids' => [$entry->id], 'items' => []],
    ]);

    $items = $this->postJson(route('admin.website.studio.api'), ['action' => 'cms_faqs'])->json('items');

    expect($items[0]['used_on'])->toHaveCount(1)
        ->and($items[0]['used_on'][0]['title'])->toBe('Kitchens')
        ->and($items[0]['used_on'][0]['url'])->toBe('/kitchens/');
});

it('adds an answer, then edits it in place', function () {
    $created = $this->postJson(route('admin.website.studio.api'), [
        'action' => 'cms_faq_save',
        'question' => 'Is the site visit free?',
        'answer' => 'Yes, in Lahore.',
    ])->assertOk()->json('item');

    expect(ContentEntry::query()->ofKind(ContentEntry::FAQ)->count())->toBe(1)
        ->and($created['question'])->toBe('Is the site visit free?');

    $this->postJson(route('admin.website.studio.api'), [
        'action' => 'cms_faq_save',
        'id' => $created['id'],
        'question' => 'Is the first site visit free?',
        'answer' => 'Yes, anywhere in Lahore.',
    ])->assertOk()->assertJsonPath('item.question', 'Is the first site visit free?');

    expect(ContentEntry::query()->ofKind(ContentEntry::FAQ)->count())->toBe(1)
        ->and(ContentEntry::query()->ofKind(ContentEntry::FAQ)->first()?->data['answer'])->toBe('Yes, anywhere in Lahore.');
});

it('refuses a question with no answer, and says why', function () {
    $this->postJson(route('admin.website.studio.api'), [
        'action' => 'cms_faq_save',
        'question' => 'Can you do kitchens?',
        'answer' => '   ',
    ])->assertOk()
        ->assertJsonPath('ok', false)
        ->assertJsonPath('error', 'Write the answer as well — a question on its own cannot be shown.');

    expect(ContentEntry::query()->ofKind(ContentEntry::FAQ)->count())->toBe(0);
});

it('saves a new order', function () {
    $a = faqEntry('First', 'Answer A', 0);
    $b = faqEntry('Second', 'Answer B', 1);

    $this->postJson(route('admin.website.studio.api'), [
        'action' => 'cms_faq_order',
        'ids' => [$b->id, $a->id],
    ])->assertOk()->assertJsonPath('note', 'Order saved.');

    $items = $this->postJson(route('admin.website.studio.api'), ['action' => 'cms_faqs'])->json('items');

    expect($items[0]['question'])->toBe('Second')
        ->and($items[1]['question'])->toBe('First');
});

it('deletes an answer and says how many pages were showing it', function () {
    $entry = faqEntry('What is the advance?', 'Fifty percent with the work order.');

    $page = Page::query()->create([
        'title' => 'Pricing',
        'slug' => '/pricing/',
        'type' => PageType::Service,
        'status' => PageStatus::Published,
    ]);

    $page->blocks()->create([
        'type' => BlockType::Faq,
        'position' => 0,
        'visible' => true,
        'data' => ['heading' => 'Questions', 'library_ids' => [$entry->id], 'items' => []],
    ]);

    $this->postJson(route('admin.website.studio.api'), ['action' => 'cms_faq_delete', 'id' => $entry->id])
        ->assertOk()
        ->assertJsonPath('note', 'Deleted — 1 page section(s) were showing it and no longer do.');

    expect(ContentEntry::query()->ofKind(ContentEntry::FAQ)->count())->toBe(0);

    // The page keeps its block: deleting an answer must not silently edit somebody's page.
    expect($page->blocks()->count())->toBe(1);
});

it('keeps the answers and testimonials apart, and flushes the cache the site reads', function () {
    faqEntry('Question', 'Answer');
    ContentEntry::query()->create([
        'kind' => ContentEntry::TESTIMONIAL,
        'data' => ['text' => 'Great work', 'name' => 'Ayesha'],
        'position' => 0,
    ]);

    // Warm the cache the renderer uses, then add one: the new answer must be visible immediately.
    expect(ContentLibrary::entries(ContentEntry::FAQ))->toHaveCount(1);

    $this->postJson(route('admin.website.studio.api'), [
        'action' => 'cms_faq_save',
        'question' => 'Second',
        'answer' => 'Another answer',
    ])->assertOk();

    expect(ContentLibrary::entries(ContentEntry::FAQ))->toHaveCount(2)
        ->and(ContentLibrary::entries(ContentEntry::TESTIMONIAL))->toHaveCount(1);
});

it('lists FAQs as a live destination in the sidebar', function () {
    $items = collect(AdminNavigation::for($this->owner))
        ->flatMap(fn (array $group): array => $group['items'])
        ->keyBy('label');

    expect($items['FAQs']['route'])->toBe('admin.website.faqs')
        // Every Website screen in the ported band is live now; nothing is left showing a "soon" badge.
        ->and($items['Testimonials']['route'])->toBe('admin.website.testimonials')
        ->and($items->filter(fn (array $i): bool => ($i['phase'] ?? null) === 'soon'))->toHaveCount(0);
});

it('re-renders the pages that draw from the library, and only those', function () {
    $entry = faqEntry('Do you charge for the first visit?', 'No.');

    $using = Page::query()->create(['title' => 'Home', 'slug' => '/', 'type' => PageType::Home, 'status' => PageStatus::Published]);
    $using->blocks()->create([
        'type' => BlockType::Faq, 'position' => 0, 'visible' => true,
        'data' => ['heading' => 'Questions', 'library_ids' => [$entry->id], 'items' => []],
    ]);

    $own = Page::query()->create(['title' => 'Careers', 'slug' => '/careers/', 'type' => PageType::Page, 'status' => PageStatus::Published]);
    $own->blocks()->create([
        'type' => BlockType::Faq, 'position' => 0, 'visible' => true,
        'data' => ['heading' => 'Questions', 'library_ids' => [], 'items' => [['question' => 'Own', 'answer' => 'Typed here']]],
    ]);

    $usingStamp = $using->fresh()?->updated_at;
    $ownStamp = $own->fresh()?->updated_at;

    $this->travel(5)->minutes();

    $this->postJson(route('admin.website.studio.api'), [
        'action' => 'cms_faq_save', 'id' => $entry->id, 'question' => 'Do you charge for the first visit?', 'answer' => 'No, never.',
    ])->assertOk();

    // A page whose section shows the answer renders again...
    expect($using->fresh()?->updated_at?->greaterThan($usingStamp))->toBeTrue()
        // ...and a page that only types its own questions keeps its timestamp.
        ->and($own->fresh()?->updated_at?->equalTo($ownStamp))->toBeTrue();
});
