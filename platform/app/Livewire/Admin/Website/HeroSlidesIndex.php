<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Website;

use App\Domain\Website\Actions\SavePage;
use App\Domain\Website\Enums\BlockType;
use App\Domain\Website\Enums\PageStatus;
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
        $this->slides = $this->sliderBlock($home)?->data['slides'] ?? [];
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
     * Save the slides onto the home page's Slider block — draft or published. The same SavePage the
     * builder uses, so the snapshot, the versions and the cache all behave identically.
     */
    public function save(bool $publish = false): void
    {
        abort_unless($this->user()->can('website.edit'), 403);

        if ($publish) {
            abort_unless($this->user()->can('website.publish'), 403);
        }

        $home = $this->home() ?? abort(404, 'The home page is missing.');
        $block = $this->sliderBlock($home);

        // The slides replace the block's data (a Slider block holds nothing but slides).
        // blocks arrive already ordered by position (the relation's own order)
        $blocks = $home->blocks->values()->map(fn (PageBlock $pageBlock): array => [
            'id' => $pageBlock->id,
            'key' => 'b'.$pageBlock->id,
            'type' => $pageBlock->type->value,
            'visible' => $pageBlock->visible,
            'data' => $pageBlock->id === $block?->id ? ['slides' => $this->slides] : $pageBlock->data,
        ])->all();

        // No slider yet: the hero becomes the page's first section, where a hero belongs.
        if ($block === null) {
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
        $this->slides = $this->sliderBlock($saved)?->data['slides'] ?? $this->slides;

        $this->toast($publish
            ? 'The hero slides are live on '.$saved->path()
            : 'Hero slides saved as a draft — publish when the home page is ready.');
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
}
