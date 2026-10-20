<?php

use App\Domain\Website\Models\ContentEntry;
use App\Domain\Website\Models\Page;
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
