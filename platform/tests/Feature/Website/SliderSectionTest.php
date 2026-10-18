<?php

use App\Console\Commands\SeedHomeSlider;
use App\Domain\Website\Enums\BlockType;
use App\Domain\Website\Enums\PageType;
use App\Domain\Website\Models\Media;
use App\Domain\Website\Models\Page;
use App\Domain\Website\Models\PageBlock;
use Illuminate\Support\Facades\Artisan;
use Tests\Concerns\InteractsWithPublicWebsite;

/*
| V2-3 (docs/14): the v26 hero slider, as a reusable section. Alpine rotates the slides, autoplay
| pauses on hover and for prefers-reduced-motion, the first photo is the LCP element, and the page
| keeps exactly one H1. The seeder gives the home page the three v26 slides, guarded against
| double runs and missing photos.
*/

uses(InteractsWithPublicWebsite::class);

function sliderMedia(): array
{
    $one = Media::factory()->create(['path' => 'website/imported/2026/10/img-c349a92a4ae0.webp']);
    $two = Media::factory()->create(['path' => 'website/imported/2026/10/img-1d6ad6c77d0f.webp']);
    $three = Media::factory()->create(['path' => 'website/imported/2026/10/img-dce06249b137.webp']);

    return [$one->id, $two->id, $three->id];
}

it('renders the slider section with controls, lazy photos and one H1', function () {
    [$one, $two] = sliderMedia();

    $page = Page::factory()->published()->withSlug('/slider-page/')->create(['title' => 'Slider page']);
    PageBlock::factory()->for($page)->asType(BlockType::Slider, [
        'slides' => [
            ['image_id' => $one, 'kicker' => 'Woodex Interior · Lahore', 'heading' => 'Spaces shaped by purpose', 'text' => 'One accountable team.', 'display' => 'INTERIORS', 'primary_label' => 'Start your project', 'primary_url' => '/contact/', 'secondary_label' => 'Explore services', 'secondary_url' => '/services/'],
            ['image_id' => $two, 'kicker' => 'Commercial spaces', 'heading' => 'Workplaces that work', 'text' => 'Ready for day one.', 'display' => '', 'primary_label' => '', 'primary_url' => '', 'secondary_label' => '', 'secondary_url' => ''],
        ],
    ])->create(['position' => 0]);

    $body = $this->siteGet('/slider-page/')->assertOk()->getContent();

    // The carousel: slides, dots, arrows, the count, and the display word of the v26 design.
    expect($body)->toContain('aria-roledescription="carousel"')
        ->and($body)->toContain('Spaces shaped by purpose')
        ->and($body)->toContain('Workplaces that work')
        ->and($body)->toContain('INTERIORS')
        ->and($body)->toContain('aria-label="Previous slide"')
        ->and($body)->toContain('aria-label="Next slide"')
        ->and($body)->toContain('aria-label="Go to slide 2"')
        ->and($body)->toContain('prefers-reduced-motion')
        ->and($body)->toContain('Start your project');

    // The first photo is the LCP element; the second must not steal its priority.
    expect(substr_count($body, 'fetchpriority="high"'))->toBeGreaterThanOrEqual(1)
        ->and($body)->toContain('loading="lazy"');

    // One H1 on the page: the first slide carries it, the second is an H2.
    preg_match_all('/<h1[^>]*>/', $body, $h1);
    expect($h1[0])->toHaveCount(1)
        ->and($body)->toContain('<h2 class="mt-4 max-w-3xl');
});

it('skips the slider markup entirely when no slide has a photo', function () {
    $page = Page::factory()->published()->withSlug('/empty-slider/')->create(['title' => 'Empty slider']);
    PageBlock::factory()->for($page)->asType(BlockType::Slider, ['slides' => [['image_id' => null, 'heading' => 'No photo']]])->create(['position' => 0]);

    $body = $this->siteGet('/empty-slider/')->assertOk()->getContent();

    expect($body)->not->toContain('aria-roledescription="carousel"')
        // The page falls back to its own title as the H1.
        ->and($body)->toContain('<h1');
});

it('gives the home page the v26 slider, once, and skips slides without photos', function () {
    sliderMedia();

    $home = Page::factory()->published()->asType(PageType::Home)->withSlug('/')->create(['title' => 'Woodex Interior']);
    PageBlock::factory()->for($home)->asType(BlockType::Text, ['heading' => 'Welcome', 'body' => 'Body'])->create(['position' => 0]);

    Artisan::call(SeedHomeSlider::class);
    Artisan::call(SeedHomeSlider::class); // a second run must not add a second slider

    $blocks = $home->fresh(['blocks'])->blocks->values();

    expect($blocks)->toHaveCount(2)
        ->and($blocks[0]->type)->toBe(BlockType::Slider)
        ->and($blocks[0]->position)->toBe(0)
        ->and(count($blocks[0]->data['slides']))->toBe(3)
        ->and($blocks[0]->data['slides'][0]['display'])->toBe('INTERIORS')
        ->and($blocks[0]->data['slides'][2]['display'])->toBe('ARCHITECTURE')
        // What was there moved down exactly one row, under the slider.
        ->and($blocks[1]->type)->toBe(BlockType::Text)
        ->and($blocks[1]->position)->toBe(1);

    // A home without the v26 photos gets no half-empty carousel: the command skips cleanly.
    Media::query()->where('path', 'like', '%img-%')->delete();
    PageBlock::query()->where('page_id', $home->id)->delete();
    Artisan::call(SeedHomeSlider::class);

    expect($home->fresh(['blocks'])->blocks)->toBeEmpty();
});
