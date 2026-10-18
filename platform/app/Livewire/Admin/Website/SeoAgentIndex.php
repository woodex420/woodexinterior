<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Website;

use App\Domain\Website\Models\Media;
use App\Domain\Website\Models\Page;
use App\Domain\Website\Models\PageBlock;
use App\Domain\Website\Models\Redirect;
use App\Domain\Website\Seo\ProposeSeoCopy;
use App\Domain\Website\Seo\SeoAudit;
use App\Domain\Website\Services\PageCache;
use App\Livewire\Concerns\InteractsWithAdmin;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The SEO agent's screen (V2-3 F8 + F10, docs/14 §5a). The audit is deterministic and always runs —
 * no key, no model, every finding a rule a person can re-check. One-click fixes mend what a rule
 * can mend; AI proposals appear only when the studio's model key exists (the BOT pattern) and are
 * applied by the owner's click, never by themselves.
 */
#[Layout('layouts::admin')]
#[Title('SEO agent')]
class SeoAgentIndex extends Component
{
    use InteractsWithAdmin;

    public string $tab = 'pages';

    /** The AI proposal currently on the table: ['title' => …, 'description' => …] for $proposalPageId, empty when none. */
    /** @var array{title?: string, description?: string} */
    public array $proposal = [];

    public ?int $proposalPageId = null;

    public function mount(): void
    {
        abort_unless($this->user()->can('website.edit'), 403);
    }

    public function fixDescription(int $pageId): void
    {
        $page = Page::query()->findOrFail($pageId);

        $text = trim((string) $page->excerpt);

        if ($text === '') {
            foreach ($page->blocks()->orderBy('position')->get() as $block) {
                $candidate = trim((string) ($block->data['text'] ?? $block->data['body'] ?? ''));

                if ($candidate !== '') {
                    $text = $candidate;
                    break;
                }
            }
        }

        if ($text === '') {
            $this->toast('Nothing on the page to describe it from — write a few lines first.');

            return;
        }

        $seo = (array) $page->seo;
        $seo['description'] = mb_substr($text, 0, 155);
        $page->seo = $seo;
        $page->save();

        PageCache::invalidate($page);
        $this->toast('Meta description taken from the page itself.');
    }

    public function fixSlug(int $pageId): void
    {
        $page = Page::query()->findOrFail($pageId);

        $clean = '/'.implode('/', array_map(
            static fn (string $part): string => Str::slug($part),
            array_filter(explode('/', trim((string) $page->slug, '/'))),
        )).'/';

        if ($clean === $page->slug) {
            return;
        }

        Redirect::query()->firstOrCreate(
            ['from_path' => $page->slug],
            ['to_path' => $clean],
        );

        $page->slug = $clean;
        $page->save();

        PageCache::invalidate($page);
        $this->toast('Slug tidied — the old address now redirects to it.');
    }

    public function fixAlt(int $pageId): void
    {
        $page = Page::query()->findOrFail($pageId);
        $media = Media::query()->get()->keyBy('id');
        $fixed = 0;

        foreach ($page->blocks()->orderBy('position')->get() as $block) {
            $data = (array) $block->data;
            $changed = false;

            foreach (['logos', 'items'] as $key) {
                foreach ((array) ($data[$key] ?? []) as $index => $item) {
                    if (is_array($item) && isset($item['id']) && trim((string) ($item['alt'] ?? '')) === '') {
                        $image = $media->get((int) $item['id']);

                        if ($image === null) {
                            continue;
                        }

                        $item['alt'] = $image->alt ?: $image->title ?: Str::of(basename((string) $image->path))->replace(['-', '_'], ' ')->title()->toString();
                        $data[$key][$index] = $item;
                        $fixed++;
                        $changed = true;
                    }
                }
            }

            if ($changed) {
                $block->data = $data;
                $block->save();
            }
        }

        PageCache::invalidate($page);
        $this->toast($fixed > 0 ? sprintf('%d alt text(s) filled from the media library.', $fixed) : 'No empty alt texts left on this page.');
    }

    public function fixRedirect(int $redirectId): void
    {
        Redirect::query()->findOrFail($redirectId)->delete();
        $this->toast('Redirect removed.');
    }

    /**
     * Ask the model (only when a key exists) for a title and description proposal.
     */
    public function propose(int $pageId, ProposeSeoCopy $proposer): void
    {
        $page = Page::query()->findOrFail($pageId);

        $content = collect($page->blocks()->orderBy('position')->get())
            ->map(static fn (PageBlock $block): string => trim((string) ($block->data['heading'] ?? '').' '.(string) ($block->data['text'] ?? $block->data['body'] ?? '')))
            ->filter()
            ->implode("\n");

        $proposal = $proposer->propose((string) $page->title, mb_substr($content, 0, 4000));

        if ($proposal === null) {
            $this->toast('The model could not be reached — check the key on the Chat Bot screen.');

            return;
        }

        $this->proposal = $proposal;
        $this->proposalPageId = $pageId;
    }

    public function applyProposal(): void
    {
        if ($this->proposalPageId === null || $this->proposal === []) {
            return;
        }

        $page = Page::query()->findOrFail($this->proposalPageId);

        $seo = (array) $page->seo;
        $seo['title'] = $this->proposal['title'];
        $seo['description'] = $this->proposal['description'];
        $page->seo = $seo;
        $page->save();

        PageCache::invalidate($page);
        $this->proposal = [];
        $this->proposalPageId = null;
        $this->toast('Proposal applied to the page.');
    }

    public function render(): View
    {
        $audit = (new SeoAudit)->handle();

        return view('livewire.admin.website.seo-agent', [
            'audit' => $audit,
            'aiReady' => app(ProposeSeoCopy::class)->isConfigured(),
        ]);
    }
}
