<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Website;

use App\Domain\Website\Models\NavigationItem;
use App\Domain\Website\Models\Page;
use App\Domain\Website\Services\Menus;
use App\Livewire\Concerns\InteractsWithAdmin;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The header and footer menus (docs/09 §2.1). Menus are content, not code: adding a service to the menu is one
 * line here, and the change is live on every page at once.
 *
 * V2-3: menus are a tree now. A link can sit at the top level, under another link, or one level deeper —
 * a top item with children opens a dropdown, and its mega switch (V2-3, owner answer of 18 Oct) turns that
 * dropdown into the wide numbered panel the v26 design shows.
 */
#[Layout('layouts::admin')]
#[Title('Menus')]
class MenusIndex extends Component
{
    use InteractsWithAdmin;

    public const array MENUS = ['header' => 'Header', 'footer' => 'Footer'];

    public string $menu = 'header';

    public string $label = '';

    public string $url = '/';

    public string $target = '_self';

    /** 'top', or the id of the link this one sits under. */
    public string $parent = 'top';

    public function mount(): void
    {
        abort_unless($this->user()->can('website.edit'), 403);
    }

    public function updatedMenu(): void
    {
        $this->parent = 'top';
    }

    public function add(): void
    {
        abort_unless($this->user()->can('website.edit'), 403);

        $this->validate([
            'label' => ['required', 'string', 'max:120'],
            'url' => ['required', 'string', 'max:500'],
            'target' => ['required', 'in:_self,_blank'],
            'parent' => ['nullable', 'string'],
        ]);

        $parentId = $this->parentItemId();
        $parent = $parentId === null ? null : NavigationItem::query()->where('menu', $this->menu)->findOrFail($parentId);

        // Three levels are enough for any navigation: a top link, its dropdown links, their links.
        if ($parent !== null && $parent->parent_id !== null) {
            $this->toast('Menus go three levels deep at most — put this link one level up.', 'error');

            return;
        }

        NavigationItem::query()->create([
            'menu' => $this->menu,
            'label' => trim($this->label),
            'url' => trim($this->url),
            'target' => $this->target,
            'parent_id' => $parent?->id,
            'position' => $this->nextPosition($parent?->id),
        ]);

        $this->label = '';
        $this->url = '/';
        Menus::flush();
        $this->toast('Menu item added.');
    }

    public function remove(int $itemId): void
    {
        abort_unless($this->user()->can('website.edit'), 403);

        // Children go with their parent (the database cascades), like a folder being deleted.
        NavigationItem::query()->findOrFail($itemId)->delete();
        Menus::flush();
        $this->toast('Menu item removed, with anything under it.');
    }

    /**
     * Moves a link up or down inside its own level only — a child never jumps out of its parent.
     */
    public function move(int $itemId, int $direction): void
    {
        abort_unless($this->user()->can('website.edit'), 403);

        $item = NavigationItem::query()->findOrFail($itemId);
        $sibling = NavigationItem::query()
            ->where('menu', $item->menu)
            ->where('parent_id', $item->parent_id)
            ->where('position', $item->position + $direction)
            ->first();

        if ($sibling === null) {
            return;
        }

        [$item->position, $sibling->position] = [$sibling->position, $item->position];
        $item->save();
        $sibling->save();

        Menus::flush();
    }

    /**
     * The mega switch (V2-3): a top link with children opens either a plain dropdown or the wide
     * numbered panel. Only meaningful on top-level links, so that is where it is allowed.
     */
    public function toggleMega(int $itemId): void
    {
        abort_unless($this->user()->can('website.edit'), 403);

        $item = NavigationItem::query()->findOrFail($itemId);

        if ($item->parent_id !== null) {
            $this->toast('Only a top-level link can open a mega panel.', 'error');

            return;
        }

        $item->forceFill(['is_mega' => ! $item->is_mega])->save();
        Menus::flush();
        $this->toast($item->is_mega ? '“'.$item->label.'” now opens a mega panel.' : '“'.$item->label.'” now opens a plain dropdown.');
    }

    public function render(): View
    {
        $items = NavigationItem::query()
            ->where('menu', $this->menu)
            ->orderBy('position')
            ->get();

        return view('livewire.admin.website.menus-index', [
            'items' => $items,
            'menus' => self::MENUS,
            'pages' => Page::query()->published()->orderBy('slug')->get(['id', 'slug', 'title']),
            'parentOptions' => $this->parentOptions($items),
        ]);
    }

    /**
     * Where the "add a link" form can place a new link: the top level, any top link (dropdown), and any
     * second-level link (the columns of a mega panel).
     *
     * @param  \Illuminate\Support\Collection<int, NavigationItem>  $items
     * @return list<array{id: string, label: string}>
     */
    private function parentOptions($items): array
    {
        $options = [['id' => 'top', 'label' => 'Top level']];

        foreach ($items as $item) {
            if ($item->parent_id === null) {
                $options[] = ['id' => (string) $item->id, 'label' => $item->label];
            } else {
                $parent = $items->firstWhere('id', $item->parent_id);
                $parentLabel = $parent === null ? '?' : $parent->label;
                $options[] = ['id' => (string) $item->id, 'label' => $parentLabel.' → '.$item->label];
            }
        }

        return $options;
    }

    /** The chosen parent's id, or null for the top level. */
    private function parentItemId(): ?int
    {
        return $this->parent === 'top' || $this->parent === '' ? null : (int) $this->parent;
    }

    private function nextPosition(?int $parentId): int
    {
        return (int) NavigationItem::query()
            ->where('menu', $this->menu)
            ->where('parent_id', $parentId)
            ->max('position') + 1;
    }
}
