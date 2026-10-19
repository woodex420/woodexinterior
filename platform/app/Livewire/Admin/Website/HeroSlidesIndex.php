<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Website;

use App\Domain\Website\Actions\SavePage;
use App\Domain\Website\Enums\BlockType;
use App\Domain\Website\Enums\PageType;
use App\Domain\Website\Models\Media;
use App\Domain\Website\Models\Page;
use App\Domain\Website\Models\PageBlock;
use App\Domain\Website\Models\PageVersion;
use App\Livewire\Concerns\InteractsWithAdmin;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The hero slides screen (the old admin's own item): the home page's opening slider, managed on its
 * own — photo, words and buttons per slide, reordered, added, retired. It edits the home page's real
 * Slider block through the same SavePage action the builder uses (snapshot first, cache invalidated),
 * so there is exactly one hero in the system and the builder shows the same slides.
 */
#[Layout('layouts::admin')]
#[Title('Hero slides')]
class HeroSlidesIndex extends Component
{
    use InteractsWithAdmin;

    /** @var list<array<string, mixed>> the Slider block's slides, in order */
    public array $slides = [];

    public int $homeId = 0;

    public string $homeTitle = '';

    public bool $homePublished = false;

    public string $status = '';

    /**
     * True while the screen is showing the page's single Hero section instead of a slider: the
     * old importer and every template except "Home — v26" open with a Hero, and the old admin
     * called both of them the hero. Editing one slide writes that Hero back in place.
     */
    public bool $fromHero = false;

    /** A slide's blank shape — the Slider block's own fields (BlockType::Slider::schema()). */
    private const NEW_SLIDE = [
        'image_id' => null,
        'kicker' => '',
        'heading' => '',
        'text' => '',
        'display' => '',
        'primary_label' => '',
        'primary_url' => '',
        'secondary_label' => '',
        'secondary_url' => '',
    ];

    public function mount(): void
    {
        $home = $this->home();

        if ($home === null) {
            return; // no home page: the screen shows the honest empty state instead of a 404
        }

        $this->homeId = $home->id;
        $this->homeTitle = $home->title;
        $this->homePublished = $home->isPublished();
        $this->status = $home->status->value;

        $slider = $this->sliderBlock($home);

        if ($slider !== null) {
            $this->slides = array_values((array) ($slider->data['slides'] ?? []));

            return;
        }

        $hero = $this->heroBlock($home);

        if ($hero !== null) {
            // The page already has a hero; show it as the one slide it is instead of "0 slides".
            $this->fromHero = true;
            $this->slides = [$this->slideFromHero($hero->data)];
        }
    }

    public function addSlide(): void
    {
        $this->slides[] = self::NEW_SLIDE;
    }

    public function removeSlide(int $index): void
    {
        unset($this->slides[$index]);
        $this->slides = array_values($this->slides);
    }

    public function moveSlide(int $index, int $delta): void
    {
        $to = $index + $delta;

        if ($to < 0 || $to >= count($this->slides)) {
            return;
        }

        [$this->slides[$index], $this->slides[$to]] = [$this->slides[$to], $this->slides[$index]];
        $this->slides = array_values($this->slides);
    }

    /**
     * Save the slides onto the home page — draft or published. The same SavePage the builder uses,
     * so the snapshot, the versions and the cache all behave identically.
     *
     * A page whose opening is a single Hero section is edited in place: one slide writes that Hero
     * back, key figures and all. A second slide turns it into the slider this screen is named for —
     * the old Hero stays on the page, hidden, so nothing it held is lost.
     */
    public function save(bool $publish = false): void
    {
        abort_unless($this->user()->can('website.edit'), 403);

        if ($publish) {
            abort_unless($this->user()->can('website.publish'), 403);
        }

        $home = $this->home() ?? abort(404, 'The home page is missing.');

        $this->slides = array_values(array_map(
            static fn (mixed $slide): array => array_merge(self::NEW_SLIDE, is_array($slide) ? $slide : []),
            $this->slides,
        ));

        if ($this->slides === []) {
            $this->toast('Add a slide first — a hero with no slides shows the page title instead.', 'error');

            return;
        }

        $slider = $this->sliderBlock($home);
        $hero = $this->heroBlock($home);
        $single = $slider === null && $hero !== null && count($this->slides) === 1;

        // Blocks arrive already ordered by position (the relation's own order).
        $blocks = $home->blocks->values()->map(function (PageBlock $pageBlock) use ($slider, $hero, $single): array {
            $data = $pageBlock->data;

            if ($slider !== null && $pageBlock->id === $slider->id) {
                $data = ['slides' => $this->slides];
            } elseif ($single && $pageBlock->id === $hero?->id) {
                $data = $this->heroDataFromSlide($pageBlock->data, $this->slides[0]);
            }

            return [
                'id' => $pageBlock->id,
                'key' => $pageBlock->block_key ?? 'b'.$pageBlock->id,
                'type' => $pageBlock->type->value,
                'visible' => $pageBlock->visible,
                'data' => $data,
            ];
        })->all();

        if ($slider === null && ! $single) {
            // A slider (or the first of several slides) becomes the opening section, where a hero
            // belongs; a Hero it replaces keeps its content but stops showing.
            if ($hero !== null) {
                $blocks = array_map(static fn (array $block): array => $block['id'] === $hero->id
                    ? [...$block, 'visible' => false]
                    : $block, $blocks);
            }

            array_unshift($blocks, [
                'id' => null,
                'key' => 'b'.bin2hex(random_bytes(4)),
                'type' => BlockType::Slider->value,
                'visible' => true,
                'data' => ['slides' => $this->slides],
            ]);
        }

        $input = [
            'blocks' => $blocks,
            'type' => $home->type,
            'slug' => $home->slug,
            'title' => $home->title,
            'excerpt' => $home->excerpt,
            'seo' => array_merge((array) $home->seo, ['last_reviewed' => now()->toDateString()]),
            'schema_type' => $home->schema_type,
            'sort' => (int) $home->sort,
            'template' => $home->template,
            'canvas_html' => (string) $home->canvas_html,
        ];

        $saved = (new SavePage)($home, $input, $blocks, $publish, $this->user());

        $this->homePublished = $saved->isPublished();
        $this->status = $saved->status->value;

        $savedSlider = $this->sliderBlock($saved);

        if ($savedSlider !== null) {
            $this->fromHero = false;
            $this->slides = array_values((array) ($savedSlider->data['slides'] ?? $this->slides));
        } elseif (($savedHero = $this->heroBlock($saved)) !== null) {
            $this->fromHero = true;
            $this->slides = [$this->slideFromHero($savedHero->data)];
        }

        if ($publish) {
            $this->toast($single
                ? 'The hero is live on '.$saved->path()
                : 'The hero slides are live on '.$saved->path());
        } else {
            $this->toast($single
                ? 'Hero saved as a draft — publish when the home page is ready.'
                : 'Hero slides saved as a draft — publish when the home page is ready.');
        }
    }

    /**
     * The Hero section in the editor's own shape — so the screen shows the page's real opening
     * (image, words, buttons) instead of "0 slides" on every imported page.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function slideFromHero(array $data): array
    {
        return array_merge(self::NEW_SLIDE, [
            'image_id' => $data['image_id'] ?? null,
            'kicker' => (string) ($data['eyebrow'] ?? ''),
            'heading' => (string) ($data['heading'] ?? ''),
            'text' => (string) ($data['text'] ?? ''),
            'primary_label' => (string) ($data['primary_label'] ?? ''),
            'primary_url' => (string) ($data['primary_url'] ?? ''),
            'secondary_label' => (string) ($data['secondary_label'] ?? ''),
            'secondary_url' => (string) ($data['secondary_url'] ?? ''),
        ]);
    }

    /**
     * One slide written back onto a Hero section: the fields a hero and a slide share. The hero's
     * key figures are untouched because they are not part of a slide.
     *
     * @param  array<string, mixed>  $hero
     * @param  array<string, mixed>  $slide
     * @return array<string, mixed>
     */
    private function heroDataFromSlide(array $hero, array $slide): array
    {
        return array_merge($hero, [
            'eyebrow' => (string) ($slide['kicker'] ?? ''),
            'heading' => (string) ($slide['heading'] ?? ''),
            'text' => (string) ($slide['text'] ?? ''),
            'image_id' => $slide['image_id'] ?? null,
            'primary_label' => (string) ($slide['primary_label'] ?? ''),
            'primary_url' => (string) ($slide['primary_url'] ?? ''),
            'secondary_label' => (string) ($slide['secondary_label'] ?? ''),
            'secondary_url' => (string) ($slide['secondary_url'] ?? ''),
        ]);
    }

    public function render(): View
    {
        return view('livewire.admin.website.hero-slides-index', [
            'media' => Media::query()->orderByDesc('id')->get(['id', 'title', 'alt', 'path']),
            'versions' => PageVersion::query()->where('page_id', $this->homeId)->latest('id')->take(3)->with('user')->get(),
        ]);
    }

    /**
     * The home page — the one whose slug is the root, or the first page typed Home. The seeder ships
     * one; the empty state covers a studio that somehow deletes theirs.
     */
    private function home(): ?Page
    {
        return Page::query()->with('blocks')
            ->where('slug', '/')
            ->orWhere(fn ($query) => $query->where('type', PageType::Home->value))
            ->orderByRaw("case when slug = '/' then 0 else 1 end")
            ->first();
    }

    private function sliderBlock(Page $page): ?PageBlock
    {
        return $page->blocks->first(fn (PageBlock $block): bool => $block->type === BlockType::Slider);
    }

    /** The page's single Hero section — the opening every imported page and most templates use. */
    private function heroBlock(Page $page): ?PageBlock
    {
        return $page->blocks->first(fn (PageBlock $block): bool => $block->type === BlockType::Hero && $block->visible);
    }
}
