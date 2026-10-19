<?php

declare(strict_types=1);

use App\Domain\Website\Enums\PageStatus;
use App\Domain\Website\Enums\PageType;
use App\Domain\Website\Models\Page;
use App\Livewire\Admin\Website\BlogIndex;
use Livewire\Livewire;

/*
| The blog is a view over the insight pages, not a second content system: same editor, same SEO
| fields, same publish rules. These tests hold that line — a post is created as a page, and the
| screen only ever touches pages of type `insight`.
*/

beforeEach(function () {
    $this->owner = $this->userWithRole('owner');
    $this->actingAs($this->owner);

    $this->listing = Page::query()->create([
        'type' => PageType::Listing,
        'slug' => '/insights/',
        'title' => 'Insights',
        'status' => PageStatus::Published,
    ]);

    $this->post = Page::query()->create([
        'type' => PageType::Insight,
        'slug' => '/insights/kitchen-cost/',
        'title' => 'Kitchen cost in Lahore',
        'excerpt' => 'What drives the price of a kitchen.',
        'status' => PageStatus::Published,
    ]);

    $this->post->blocks()->create([
        'type' => 'text',
        'block_key' => 'b1234567',
        'data' => ['heading' => 'Cabinets', 'body' => 'Most of the cost is joinery.'],
        'visible' => true,
        'position' => 0,
    ]);

    Page::query()->create([
        'type' => PageType::Service,
        'slug' => '/interior-design/',
        'title' => 'Interior design service',
        'status' => PageStatus::Published,
    ]);
});

it('lists posts and nothing else', function () {
    Livewire::test(BlogIndex::class)
        ->assertSee('Kitchen cost in Lahore')
        ->assertSee('/insights/kitchen-cost/')
        ->assertDontSee('Interior design service')
        ->assertSee('Published');
});

it('filters drafts away from published posts', function () {
    Page::query()->create([
        'type' => PageType::Insight,
        'slug' => '/insights/unfinished/',
        'title' => 'Unfinished draft',
        'status' => PageStatus::Draft,
    ]);

    Livewire::test(BlogIndex::class)
        ->set('status', 'draft')
        ->assertSee('Unfinished draft')
        ->assertDontSee('Kitchen cost in Lahore');
});

it('creates a draft with its URL, parent, SEO fields and two starter sections', function () {
    Livewire::test(BlogIndex::class)
        ->set('showNew', true)
        ->set('newTitle', 'Bathroom renovation cost')
        ->set('newExcerpt', 'Where the money goes in a small bathroom.')
        ->call('createPost')
        ->assertHasNoErrors();

    $page = Page::query()->where('slug', '/insights/bathroom-renovation-cost/')->firstOrFail();

    expect($page->type)->toBe(PageType::Insight)
        ->and($page->status)->toBe(PageStatus::Draft)
        ->and($page->parent_id)->toBe($this->listing->id)
        ->and($page->excerpt)->toBe('Where the money goes in a small bathroom.')
        ->and($page->seo['title'])->toBe('Bathroom renovation cost')
        ->and($page->blocks()->orderBy('position')->pluck('type')->map->value->all())->toBe(['hero', 'text']);
});

it('refuses a URL that is already taken', function () {
    Livewire::test(BlogIndex::class)
        ->set('newTitle', 'Another kitchen post')
        ->set('newSlug', 'kitchen-cost')
        ->call('createPost')
        ->assertHasErrors('newSlug');

    expect(Page::query()->where('slug', '/insights/kitchen-cost/')->count())->toBe(1);
});

it('duplicates a post as a draft with its own URL', function () {
    Livewire::test(BlogIndex::class)->call('duplicate', $this->post->id);

    $copy = Page::query()->where('slug', '/insights/kitchen-cost-copy/')->firstOrFail();

    expect($copy->status)->toBe(PageStatus::Draft)
        ->and($copy->title)->toBe('Kitchen cost in Lahore (copy)')
        ->and($copy->blocks()->count())->toBe(1)
        ->and($this->post->refresh()->status)->toBe(PageStatus::Published);
});

it('takes a post down without deleting it', function () {
    Livewire::test(BlogIndex::class)->call('unpublish', $this->post->id);

    expect($this->post->refresh()->status)->toBe(PageStatus::Draft)
        ->and(Page::query()->whereKey($this->post->id)->exists())->toBeTrue();
});

it('gives a second duplicate its own URL', function () {
    Livewire::test(BlogIndex::class)
        ->call('duplicate', $this->post->id)
        ->call('duplicate', $this->post->id);

    expect(Page::query()->where('slug', '/insights/kitchen-cost-copy-2/')->exists())->toBeTrue();
});

it('keeps people without website.edit out of the screen', function () {
    $this->actingAs($this->userWithRole('sales'));

    $this->get(route('admin.website.blog'))->assertForbidden();
});
