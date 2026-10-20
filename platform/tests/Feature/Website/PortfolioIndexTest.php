<?php

declare(strict_types=1);

use App\Domain\Crm\Enums\BusinessLine;
use App\Domain\Crm\Models\Contact;
use App\Domain\Projects\Models\Project;
use App\Domain\Website\Enums\PageStatus;
use App\Domain\Website\Enums\PageType;
use App\Domain\Website\Models\Page;
use App\Livewire\Admin\Website\PortfolioIndex;
use Livewire\Livewire;

/*
| Portfolio is a view over the project pages, plus the two things a page list cannot say: whether a
| study is linked to a real project, and whether it is on the /projects/ index (whose card list is
| hand-curated because Frontend v1 is final).
*/

beforeEach(function () {
    $this->owner = $this->userWithRole('owner');
    $this->actingAs($this->owner);

    $this->listing = Page::query()->create([
        'type' => PageType::Listing,
        'slug' => '/projects/',
        'title' => 'Projects',
        'status' => PageStatus::Published,
    ]);

    $this->listing->blocks()->create([
        'type' => 'services',
        'block_key' => 'bproj0001',
        'data' => ['heading' => '', 'items' => [
            ['number' => '1', 'name' => 'A cafe that holds a crowd', 'copy' => 'Study · 2026', 'url' => '/projects/cafe/'],
        ]],
        'visible' => true,
        'position' => 0,
    ]);

    $this->study = Page::query()->create([
        'type' => PageType::Project,
        'slug' => '/projects/cafe/',
        'title' => 'A cafe that holds a crowd',
        'excerpt' => 'Counter flow and a seating mix.',
        'status' => PageStatus::Published,
    ]);

    Page::query()->create([
        'type' => PageType::Service,
        'slug' => '/interior-design/',
        'title' => 'Interior design service',
        'status' => PageStatus::Published,
    ]);
});

function projectFor(string $code, ?int $caseStudyPageId = null, bool $consent = true): Project
{
    return Project::query()->create([
        'code' => $code,
        'name' => "Job {$code}",
        'contact_id' => Contact::factory()->create()->id,
        'business_line' => BusinessLine::Interior,
        'city' => 'Lahore',
        'case_study_page_id' => $caseStudyPageId,
        'case_study_consent' => $consent,
    ]);
}

function portfolioIndexItems(Page $listing): array
{
    return $listing->blocks()->where('type', 'services')->firstOrFail()->refresh()->data['items'];
}

it('lists studies and nothing else, with the project they came from', function () {
    projectFor('PRJ-1001', $this->study->id);

    Livewire::test(PortfolioIndex::class)
        ->assertSee('A cafe that holds a crowd')
        ->assertSee('/projects/cafe/')
        ->assertSee('PRJ-1001')
        ->assertDontSee('Interior design service');
});

it('creates a draft with its URL, parent, SEO fields and two starter sections', function () {
    Livewire::test(PortfolioIndex::class)
        ->set('showNew', true)
        ->set('newTitle', 'A clinic that calms')
        ->set('newExcerpt', 'One-way patient flow in a tight floor plate.')
        ->call('createStudy')
        ->assertHasNoErrors();

    $page = Page::query()->where('slug', '/projects/a-clinic-that-calms/')->firstOrFail();

    expect($page->type)->toBe(PageType::Project)
        ->and($page->status)->toBe(PageStatus::Draft)
        ->and($page->parent_id)->toBe($this->listing->id)
        ->and($page->seo['title'])->toBe('A clinic that calms')
        ->and($page->blocks()->orderBy('position')->pluck('type')->map->value->all())->toBe(['hero', 'text']);
});

it('links a real project when the study is created', function () {
    $project = projectFor('PRJ-1002');

    Livewire::test(PortfolioIndex::class)
        ->set('newTitle', 'A clinic that calms')
        ->set('newProjectId', (string) $project->id)
        ->call('createStudy')
        ->assertHasNoErrors();

    $page = Page::query()->where('slug', '/projects/a-clinic-that-calms/')->firstOrFail();

    expect($project->refresh()->case_study_page_id)->toBe($page->id);
});

it('refuses a project that already has a case study', function () {
    $project = projectFor('PRJ-1003', $this->study->id);

    Livewire::test(PortfolioIndex::class)
        ->set('newTitle', 'A second study of the same job')
        ->set('newProjectId', (string) $project->id)
        ->call('createStudy')
        ->assertHasErrors('newProjectId');

    expect(Page::query()->where('slug', '/projects/a-second-study-of-the-same-job/')->exists())->toBeFalse();
});

it('links and unlinks a project after the fact', function () {
    $project = projectFor('PRJ-1004');

    Livewire::test(PortfolioIndex::class)
        ->call('openLink', $this->study->id)
        ->set('linkProjectId', (string) $project->id)
        ->call('linkProject')
        ->assertHasNoErrors();

    expect($project->refresh()->case_study_page_id)->toBe($this->study->id);

    Livewire::test(PortfolioIndex::class)
        ->call('openLink', $this->study->id)
        ->set('linkProjectId', '')
        ->call('linkProject')
        ->assertHasNoErrors();

    expect($project->refresh()->case_study_page_id)->toBeNull();
});

it('duplicates a study as an unlisted draft', function () {
    Livewire::test(PortfolioIndex::class)->call('duplicate', $this->study->id);

    $copy = Page::query()->where('slug', '/projects/cafe-copy/')->firstOrFail();

    expect($copy->status)->toBe(PageStatus::Draft)
        ->and($copy->title)->toBe('A cafe that holds a crowd (copy)')
        ->and(portfolioIndexItems($this->listing))->toHaveCount(1);
});

it('puts a published study on the index and takes it off again', function () {
    $study = Page::query()->create([
        'type' => PageType::Project,
        'slug' => '/projects/office/',
        'title' => 'An office floor',
        'status' => PageStatus::Published,
    ]);

    Livewire::test(PortfolioIndex::class)->call('toggleIndex', $study->id);

    expect(portfolioIndexItems($this->listing))->toHaveCount(2)
        ->and(collect(portfolioIndexItems($this->listing))->pluck('url'))->toContain('/projects/office/');

    Livewire::test(PortfolioIndex::class)->call('toggleIndex', $study->id);

    expect(portfolioIndexItems($this->listing))->toHaveCount(1);
});

it('refuses to put a draft on the index', function () {
    $draft = Page::query()->create([
        'type' => PageType::Project,
        'slug' => '/projects/unfinished/',
        'title' => 'Unfinished study',
        'status' => PageStatus::Draft,
    ]);

    Livewire::test(PortfolioIndex::class)->call('toggleIndex', $draft->id);

    expect(portfolioIndexItems($this->listing))->toHaveCount(1);
});

it('takes the study off the site and off the index when unpublished', function () {
    Livewire::test(PortfolioIndex::class)->call('unpublish', $this->study->id);

    expect($this->study->refresh()->status)->toBe(PageStatus::Draft)
        ->and(portfolioIndexItems($this->listing))->toBeEmpty();
});

it('keeps people without website.edit out of the screen', function () {
    $this->actingAs($this->userWithRole('sales'));

    $this->get(route('admin.website.portfolio'))->assertForbidden();
});
