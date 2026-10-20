<?php

use App\Domain\Website\Enums\BlockType;
use App\Domain\Website\Models\ContentEntry;
use App\Domain\Website\Models\Media;
use App\Domain\Website\Models\Page;
use App\Domain\Website\Models\PageBlock;
use App\Domain\Website\Services\ContentLibrary;
use App\Livewire\Admin\Website\ContentLibraryIndex;
use App\Models\User;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithPublicWebsite;

/*
| V2-3 F6 (docs/14 §5a): the shared content library. FAQs, testimonials and client logos are
| written once in Website → Content; a page's FAQ, Reviews or Clients section picks entries and
| renders them in the library's order — in front of the section's own rows. Library FAQs feed the
| FAQPage rich results exactly like the page's own.
*/

uses(InteractsWithPublicWebsite::class);

beforeEach(function () {
    $this->editor = User::factory()->create();
    $this->editor->givePermissionTo('website.edit');

    ContentEntry::query()->delete();
    ContentLibrary::flush();
});

it('manages the three kinds from the dashboard, in the library\'s order', function () {
    Livewire::actingAs($this->editor)
        ->test(ContentLibraryIndex::class)
        ->set('kind', 'faq')
        ->set('question', 'Do you work outside Lahore?')
        ->set('answer', 'Yes, across Pakistan.')
        ->call('add')
        ->assertHasNoErrors()
        ->set('kind', 'testimonial')
        ->set('text', 'They finished on the date they promised.')
        ->set('name', 'A client')
        ->set('when', 'March 2026')
        ->call('add')
        ->assertHasNoErrors()
        ->assertSee('They finished on the date they promised.');

    expect(ContentEntry::query()->count())->toBe(2);

    $faq = ContentEntry::query()->ofKind('faq')->first();

    // The order is the studio's: entries swap with their neighbour inside their kind.
    Livewire::actingAs($this->editor)
        ->test(ContentLibraryIndex::class)
        ->set('kind', 'faq')
        ->set('question', 'Second question?')
        ->set('answer', 'Second answer.')
        ->call('add')
        ->call('move', $faq->id, 1);

    expect($faq->fresh()->position)->toBe(2);

    // A form that is not ready adds nothing.
    Livewire::actingAs($this->editor)
        ->test(ContentLibraryIndex::class)
        ->set('kind', 'faq')
        ->set('question', 'An answerless question?')
        ->set('answer', '')
        ->call('add')
        ->assertHasErrors('answer');

    expect(ContentEntry::query()->ofKind('faq')->count())->toBe(2);
});

it('shows library FAQs and reviews on a page, in the library\'s order before the section\'s own', function () {
    $first = ContentEntry::query()->create(['kind' => 'faq', 'data' => ['question' => 'Library question one', 'answer' => 'Library answer one'], 'position' => 1]);
    ContentEntry::query()->create(['kind' => 'faq', 'data' => ['question' => 'Library question two', 'answer' => 'Library answer two'], 'position' => 2]);
    $testimonial = ContentEntry::query()->create(['kind' => 'testimonial', 'data' => ['text' => 'Library review text', 'name' => 'Library client', 'when' => 'June 2026', 'source' => 'Google'], 'position' => 1]);

    $page = Page::factory()->published()->withSlug('/library-page/')->create(['title' => 'Library page']);
    PageBlock::factory()->for($page)->asType(BlockType::Faq, [
        'items' => [['question' => 'Own question', 'answer' => 'Own answer']],
        'library_ids' => [$second = $first->id + 1, $first->id],
    ])->create(['position' => 0]);
    PageBlock::factory()->for($page)->asType(BlockType::Reviews, [
        'items' => [],
        'library_ids' => [$first->id, $testimonial->id], // the kind decides: a FAQ id cannot leak a review in.
    ])->create(['position' => 1]);

    $body = $this->siteGet('/library-page/')->assertOk()->getContent();

    // Both library FAQs, in library order (one, two), before the section's own question.
    $one = strpos($body, 'Library question one');
    $two = strpos($body, 'Library question two');
    $own = strpos($body, 'Own question');
    expect($one)->not->toBeFalse()
        ->and($two)->not->toBeFalse()
        ->and($own)->not->toBeFalse()
        ->and($one)->toBeLessThan($two)
        ->and($two)->toBeLessThan($own)
        // The testimonial renders; the wrong-kind id does not invent one.
        ->and($body)->toContain('Library review text')
        ->and($body)->toContain('Library client');

    // The rich results carry the library's answers too.
    expect($body)->toContain('Library answer one');
});

it('shows library logos with their photos loaded', function () {
    $logo = Media::factory()->create();
    $other = Media::factory()->create();
    ContentEntry::query()->create(['kind' => 'logo', 'data' => ['media_id' => $logo->id, 'alt' => 'DHA Lahore'], 'position' => 1]);

    $page = Page::factory()->published()->withSlug('/logos-page/')->create(['title' => 'Logos page']);
    PageBlock::factory()->for($page)->asType(BlockType::Clients, [
        'logos' => [['id' => $other->id, 'alt' => 'Own logo']],
        'library_ids' => [ContentEntry::query()->ofKind('logo')->first()->id],
    ])->create(['position' => 0]);

    $body = $this->siteGet('/logos-page/')->assertOk()->getContent();

    $libraryAt = strpos($body, 'alt="DHA Lahore"');
    $ownAt = strpos($body, 'alt="Own logo"');
    expect($libraryAt)->not->toBeFalse()
        ->and($ownAt)->not->toBeFalse()
        ->and($libraryAt)->toBeLessThan($ownAt)
        // The library logo's photo URL really renders — the media map resolved it.
        ->and($body)->toContain($logo->url());
});
