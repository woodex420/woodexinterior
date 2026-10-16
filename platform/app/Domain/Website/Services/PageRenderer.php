<?php

declare(strict_types=1);

namespace App\Domain\Website\Services;

use App\Domain\Website\Enums\BlockType;
use App\Domain\Website\Models\Media;
use App\Domain\Website\Models\Page;
use App\Domain\Website\Models\PageBlock;
use App\Settings\BusinessSettings;
use App\Support\Phone;
use Illuminate\Support\Collection;

/**
 * Everything a public page needs to render (docs/09 §2.3). Blocks are read once, their images are loaded in one
 * query and handed to the sections as a map, so a page never runs one query per image.
 */
final class PageRenderer
{
    public function __construct(private readonly BusinessSettings $business) {}

    /**
     * @return array<string, mixed>
     */
    public function viewData(Page $page): array
    {
        $blocks = $page->blocks->where('visible', true)->values();
        $media = $this->mediaFor($blocks, $page->featured_image_id);

        $faqs = $blocks->where('type', BlockType::Faq)->flatMap(fn (PageBlock $block): array => $block->data['items'] ?? [])->values()->all();
        $services = $blocks->where('type', BlockType::Services)->flatMap(fn (PageBlock $block): array => $block->data['items'] ?? [])->values()->all();

        $seo = $page->seo ?? [];
        $description = (string) ($seo['description'] ?? $page->excerpt ?? '');
        $ogImage = isset($seo['og_image_id']) ? ($media[$seo['og_image_id']] ?? null) : null;
        $ogImage ??= $page->featuredImage;

        return [
            'page' => $page,
            'blocks' => $blocks,
            'media' => $media,
            'business' => $this->business,
            'phone' => Phone::display($this->business->phone),
            'phoneUrl' => Phone::telUrl($this->business->phone),
            'whatsapp' => Phone::whatsappUrl($this->business->whatsapp, 'Assalam o Alaikum, I would like to discuss a project with Woodex Interior.'),
            'services' => $services,
            'faqs' => $faqs,
            'canonical' => $page->canonical(),
            'ogImage' => $ogImage?->url() ?? Page::urlFor('/img/og-home.jpg'),
            'title' => (string) ($seo['title'] ?? $page->title),
            'description' => $description,
            'schemaType' => $page->schema_type ?? $page->type->schemaType(),
            'robots' => $seo['robots'] ?? null, // null: the head prints its default "index, follow"
            'modified' => ($page->updated_at ?? now())->toDateString(),
            'hasHero' => $blocks->contains(fn (PageBlock $block): bool => $block->type === BlockType::Hero),
        ];
    }

    /**
     * @param  Collection<int, PageBlock>  $blocks
     * @return array<int, Media> keyed by media id
     */
    private function mediaFor(Collection $blocks, ?int ...$extra): array
    {
        $ids = [];

        foreach ($blocks as $block) {
            foreach ((array) ($block->data ?? []) as $key => $value) {
                if (str_ends_with((string) $key, '_id') && is_numeric($value)) {
                    $ids[] = (int) $value;
                }

                if (in_array((string) $key, ['images', 'logos'], true) && is_array($value)) {
                    foreach ($value as $item) {
                        if (is_array($item) && isset($item['id'])) {
                            $ids[] = (int) $item['id'];
                        }
                    }
                }
            }
        }

        foreach ($extra as $id) {
            if ($id !== null) {
                $ids[] = $id;
            }
        }

        $ids = array_values(array_unique(array_filter($ids)));

        return $ids === [] ? [] : Media::query()->whereIn('id', $ids)->get()->keyBy('id')->all();
    }
}
