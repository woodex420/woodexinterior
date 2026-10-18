<?php

use App\Domain\Website\Enums\BlockType;
use App\Domain\Website\Models\Media;
use App\Domain\Website\Models\Page;
use App\Domain\Website\Models\PageBlock;
use App\Domain\Website\Models\PageTemplate;
use App\Domain\Website\Models\TeamMember;
use App\Domain\Website\Services\ContentLibrary;
use App\Livewire\Admin\Website\PageEditor;
use App\Livewire\Admin\Website\TeamMembersIndex;
use App\Models\User;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithPublicWebsite;

/*
| V2-3 F7 (docs/14 §5a): the team manager. Members with a photo, a role and a bio are managed once
| under Website → Team; a page's Team section picks them and shows them in the studio's order, in
| front of anyone written on the page itself. An empty team section renders nothing.
*/

uses(InteractsWithPublicWebsite::class);

beforeEach(function () {
    $this->editor = User::factory()->create();
    $this->editor->givePermissionTo('website.edit');

    TeamMember::query()->delete();
    ContentLibrary::flush();
});

it('manages members from the dashboard, in the studio\'s order', function () {
    Livewire::actingAs($this->editor)
        ->test(TeamMembersIndex::class)
        ->set('name', 'The founder')
        ->set('role', 'Founder & lead designer')
        ->set('bio', 'Fifteen years of interiors across Lahore.')
        ->call('add')
        ->assertHasNoErrors()
        ->assertSee('The founder')
        ->assertSee('Founder & lead designer');

    // A member without a role is refused — a card saying nothing helps nobody.
    Livewire::actingAs($this->editor)
        ->test(TeamMembersIndex::class)
        ->set('name', 'Nameless role')
        ->set('role', '')
        ->call('add')
        ->assertHasErrors('role');

    expect(TeamMember::query()->count())->toBe(1);

    // A second member lands under the first; moving the first down swaps them.
    Livewire::actingAs($this->editor)
        ->test(TeamMembersIndex::class)
        ->set('name', 'The joiner')
        ->set('role', 'Project manager')
        ->call('add');

    $first = TeamMember::query()->where('name', 'The founder')->first();

    Livewire::actingAs($this->editor)
        ->test(TeamMembersIndex::class)
        ->call('move', $first->id, 1);

    expect($first->fresh()->position)->toBe(2)
        ->and(TeamMember::query()->ordered()->pluck('name')->all())->toBe(['The joiner', 'The founder']);
});

it('shows the team on a page — members with photos first, then someone written on the page', function () {
    $photo = Media::factory()->create(['path' => 'website/media/founder.webp']);

    $founder = TeamMember::query()->create(['name' => 'The founder', 'role' => 'Founder', 'bio' => 'Fifteen years.', 'media_id' => $photo->id, 'position' => 1]);
    $joiner = TeamMember::query()->create(['name' => 'The joiner', 'role' => 'Project manager', 'bio' => null, 'media_id' => null, 'position' => 2]);

    $page = Page::factory()->published()->withSlug('/studio-page/')->create(['title' => 'The studio']);
    PageBlock::factory()->for($page)->asType(BlockType::Team, [
        'heading' => 'The people',
        'library_ids' => [$joiner->id, $founder->id], // picked out of order — the library's order wins
        'items' => [['id' => null, 'name' => 'A guest collaborator', 'role' => 'Lahore', 'bio' => '']],
    ])->create(['position' => 0]);

    $body = $this->siteGet('/studio-page/')->assertOk()->getContent();

    // The studio's order: founder first, then the joiner, then the page's own guest.
    $founderAt = strpos($body, 'The founder');
    $joinerAt = strpos($body, 'The joiner');
    $guestAt = strpos($body, 'A guest collaborator');
    expect($founderAt)->not->toBeFalse()
        ->and($joinerAt)->not->toBeFalse()
        ->and($guestAt)->not->toBeFalse()
        ->and($founderAt)->toBeLessThan($joinerAt)
        ->and($joinerAt)->toBeLessThan($guestAt)
        // The founder's photo really renders — the media map resolved the library pick.
        ->and($body)->toContain($photo->url())
        ->and($body)->toContain('Fifteen years.')
        ->and($body)->toContain('The people');
});

it('renders nothing for an empty team section', function () {
    $page = Page::factory()->published()->withSlug('/empty-team/')->create(['title' => 'Nobody here']);
    PageBlock::factory()->for($page)->asType(BlockType::Team, ['heading' => 'The people', 'items' => [], 'library_ids' => []])->create(['position' => 0]);

    $body = $this->siteGet('/empty-team/')->assertOk()->getContent();

    expect($body)->not->toContain('The people');
});

it('offers the team to the section in the page editor, and the About template carries it', function () {
    TeamMember::query()->create(['name' => 'The founder', 'role' => 'Founder', 'bio' => null, 'media_id' => null, 'position' => 1]);

    $page = Page::factory()->withSlug('/editor-team/')->create(['title' => 'Editor team']);
    PageBlock::factory()->for($page)->asType(BlockType::Team, ['items' => []])->create(['position' => 0]);

    Livewire::actingAs($this->editor)
        ->test(PageEditor::class, ['page' => $page])
        ->assertSee("From the studio's team (Website → Team)")
        ->assertSee('The founder');

    $about = PageTemplate::query()->where('name', 'About the studio')->first();
    expect($about)->not->toBeNull()
        ->and(collect($about->sections)->pluck('type'))->toContain(BlockType::Team->value);
});
