<?php

use App\Domain\Website\Enums\BlockType;
use App\Domain\Website\Enums\PageType;
use App\Domain\Website\Models\Media;
use App\Domain\Website\Models\NavigationItem;
use App\Domain\Website\Models\Page;
use App\Domain\Website\Models\PageBlock;
use App\Domain\Website\Services\Menus;
use Tests\Concerns\InteractsWithPublicWebsite;

/*
| The public site renders everything the editor can build (docs/09 §4) and everything the dashboard can change
| (docs/09 §2.1): every section of the library, the menus from Website → Menus, and the built-in links when nobody
| has filled them in yet. A section that breaks would otherwise only show on the pages that happen to use it.
*/
uses(InteractsWithPublicWebsite::class);

/**
 * One section of each kind: the content the editor stores plus a piece of copy that only that section prints, so a
 * test can tell "the section drew itself" apart from "the page drew its chrome".
 *
 * @return array{data: array<string, mixed>, marker: string}
 */
function sectionSample(BlockType $type, int $imageId, int $pageId): array
{
    return match ($type) {
        BlockType::Hero => ['marker' => 'Every room, designed around you', 'data' => ['eyebrow' => 'Design & build', 'heading' => 'Every room, designed around you', 'text' => 'One team from brief to handover.', 'image_id' => $imageId, 'primary_label' => 'Get a consultation', 'primary_url' => '#enquiry', 'facts' => [['label' => 'Since', 'value' => '2016']]]],
        BlockType::Intro => ['marker' => 'The studio, in short', 'data' => ['eyebrow' => 'In short', 'heading' => 'The studio, in short', 'body' => 'Design, drawings and site supervision for homes and workplaces.', 'image_id' => $imageId, 'facts' => [['label' => 'Studio', 'value' => 'Lahore']]]],
        BlockType::Text => ['marker' => 'Method and materials', 'data' => ['heading' => 'Method and materials', 'body' => 'We measure, draw and then build.']],
        BlockType::Services => ['marker' => 'What we deliver', 'data' => ['heading' => 'What we deliver', 'items' => [['number' => '01', 'name' => 'Space planning', 'copy' => 'Layouts that fit how you use the room.', 'url' => '/interior-design/'], ['number' => '02', 'name' => 'Drawings', 'copy' => 'Elevations and details.', 'url' => '']]]],
        BlockType::Process => ['marker' => 'How the work runs', 'data' => ['heading' => 'How the work runs', 'stages' => [['number' => '01', 'name' => 'Brief', 'copy' => 'We listen first.'], ['number' => '02', 'name' => 'Design', 'copy' => 'Drawings and 3D views.']]]],
        BlockType::Gallery => ['marker' => 'Recent work', 'data' => ['heading' => 'Recent work', 'images' => [['id' => $imageId, 'caption' => 'A living room in Model Town', 'alt' => 'Living room with timber joinery']]]],
        BlockType::Faq => ['marker' => 'Common questions', 'data' => ['heading' => 'Common questions', 'items' => [['question' => 'Do you work outside Lahore?', 'answer' => 'Yes, across Pakistan.']]]],
        BlockType::Related => ['marker' => 'A related page', 'data' => ['heading' => 'Where to next', 'page_ids' => [$pageId]]],
        BlockType::Form => ['marker' => 'Tell us about the space', 'data' => ['heading' => 'Tell us about the space', 'text' => 'Share the location and the size.', 'show_contact' => true]],
        BlockType::Reviews => ['marker' => 'What our clients say', 'data' => ['heading' => 'What our clients say', 'items' => [['text' => 'They finished on the date they promised.', 'name' => 'A client', 'when' => 'March 2026', 'source' => 'Google']]]],
        BlockType::Specs => ['marker' => 'Materials and finishes', 'data' => ['heading' => 'Materials and finishes', 'rows' => [['label' => 'Boards', 'value' => 'Moisture-resistant MDF']]]],
        BlockType::PriceTable => ['marker' => 'Indicative investment', 'data' => ['heading' => 'Indicative investment', 'rows' => [['label' => 'Design only', 'value' => 'From PKR 90,000']], 'note' => 'A site visit turns this into a quote.']],
        BlockType::Author => ['marker' => 'Imtiaz Ahmad', 'data' => ['name' => 'Imtiaz Ahmad', 'role' => 'Principal', 'bio' => 'Founded Woodex Interior in 2016.', 'image_id' => $imageId]],
        BlockType::Map => ['marker' => 'Studio location', 'data' => ['heading' => 'Studio location', 'embed_url' => 'https://www.openstreetmap.org/export/embed.html']],
        BlockType::Clients => ['marker' => 'Trusted by', 'data' => ['heading' => 'Trusted by', 'logos' => [['id' => $imageId, 'alt' => 'Client logo']]]],
    };
}

/**
 * A published page of one section, on its own URL.
 */
function pageWithSections(string $slug, array $sections): Page
{
    $page = Page::factory()->published()->withSlug($slug)->create(['title' => 'Rendering '.$slug]);

    foreach (array_values($sections) as $position => $section) {
        PageBlock::factory()->for($page)->asType($section['type'], $section['data'])->create(['position' => $position]);
    }

    return $page;
}

it('renders every section of the library', function () {
    $image = Media::factory()->create();
    $related = Page::factory()->published()->withSlug('/a-related-page/')->create(['title' => 'A related page']);

    foreach (BlockType::cases() as $type) {
        $sample = sectionSample($type, $image->id, $related->id);
        $slug = '/section-'.$type->value.'/';

        pageWithSections($slug, [['type' => $type, 'data' => $sample['data']]]);

        $body = $this->siteGet($slug)->assertOk()->getContent();

        // The section printed its own copy, and nothing threw while doing it.
        expect($body, $type->value.' did not render')->toContain($sample['marker'])
            ->and($body)->not->toContain('ErrorException')
            ->and($body)->not->toContain('Undefined variable');
    }
});

it('renders a page that uses every section at once', function () {
    $image = Media::factory()->create();
    $related = Page::factory()->published()->withSlug('/a-related-page/')->create(['title' => 'A related page']);

    $sections = [];
    foreach (BlockType::cases() as $type) {
        $sample = sectionSample($type, $image->id, $related->id);
        $sections[] = ['type' => $type, 'data' => $sample['data']];
    }

    pageWithSections('/every-section/', $sections);

    $body = $this->siteGet('/every-section/')->assertOk()->getContent();

    foreach (BlockType::cases() as $type) {
        $sample = sectionSample($type, $image->id, $related->id);

        expect($body, $type->value.' is missing from the page')->toContain($sample['marker']);
    }
});

it('shows the menus from the dashboard in the header and the footer', function () {
    NavigationItem::factory()->create(['menu' => NavigationItem::HEADER, 'label' => 'Our services', 'url' => '/services/', 'position' => 0]);
    NavigationItem::factory()->create(['menu' => NavigationItem::FOOTER, 'label' => 'Privacy notice', 'url' => '/privacy/', 'position' => 0]);
    Menus::flush();

    pageWithSections('/interior-design/', [['type' => BlockType::Text, 'data' => sectionSample(BlockType::Text, 0, 0)['data']]]);

    $body = $this->siteGet('/interior-design/')->assertOk()->getContent();

    expect($body)->toContain('Our services')->toContain('href="/services/"')
        ->and($body)->toContain('Privacy notice')->toContain('href="/privacy/"');
});

it('falls back to the built-in links until someone fills the menus in', function () {
    Menus::flush();

    pageWithSections('/fit-out/', [['type' => BlockType::Text, 'data' => sectionSample(BlockType::Text, 0, 0)['data']]]);

    $body = $this->siteGet('/fit-out/')->assertOk()->getContent();

    // Real URLs, not scroll anchors: an empty menu must still leave every main page one click away.
    foreach (['/services/', '/insights/', '/projects/', '/about/', '/contact/', '/book-a-visit/', '/estimator/'] as $url) {
        expect($body, $url.' is missing from the navigation')->toContain('href="'.$url.'"');
    }

    expect($body)->toContain('Start your project');
});

it('lists the cities we work in the footer so no area page is an orphan', function () {
    Menus::flush();

    $city = Page::factory()->published()->create(['type' => PageType::City, 'slug' => '/lahore/', 'title' => 'Interior design in Lahore']);

    pageWithSections('/fit-out/', [['type' => BlockType::Text, 'data' => sectionSample(BlockType::Text, 0, 0)['data']]]);

    $body = $this->siteGet('/fit-out/')->assertOk()->getContent();

    expect($body)->toContain('Where we work')->toContain('href="/lahore/"');

    expect(Menus::cityLinks())->toHaveCount(1)
        ->and(Menus::cityLinks()[0]['url'])->toBe('/lahore/');
});
