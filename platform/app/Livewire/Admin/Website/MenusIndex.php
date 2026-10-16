<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Website;

use App\Domain\Website\Models\NavigationItem;
use App\Domain\Website\Models\Page;
use App\Domain\Website\Services\Menus;
use App\Livewire\Concerns\InteractsWithAdmin;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The header and footer menus (docs/09 §2.1). Menus are content, not code: adding a service to the menu is one
 * line here, and the change is live on every page at once.
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

    public function mount(): void
    {
        abort_unless($this->user()->can('website.edit'), 403);
    }

    public function add(): void
    {
        abort_unless($this->user()->can('website.edit'), 403);

        $this->validate([
            'label' => ['required', 'string', 'max:120'],
            'url' => ['required', 'string', 'max:500'],
            'target' => ['required', 'in:_self,_blank'],
        ]);

        NavigationItem::query()->create([
            'menu' => $this->menu,
            'label' => trim($this->label),
            'url' => trim($this->url),
            'target' => $this->target,
            'position' => (int) NavigationItem::query()->where('menu', $this->menu)->max('position') + 1,
        ]);

        $this->label = '';
        $this->url = '/';
        Menus::flush();
        $this->toast('Menu item added.');
    }

    public function remove(int $itemId): void
    {
        abort_unless($this->user()->can('website.edit'), 403);

        NavigationItem::query()->findOrFail($itemId)->delete();
        Menus::flush();
        $this->toast('Menu item removed.');
    }

    public function move(int $itemId, int $direction): void
    {
        abort_unless($this->user()->can('website.edit'), 403);

        $item = NavigationItem::query()->findOrFail($itemId);
        $sibling = NavigationItem::query()
            ->where('menu', $item->menu)
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

    public function render(): View
    {
        /** @var Collection<int, NavigationItem> $items */
        $items = NavigationItem::query()->where('menu', $this->menu)->orderBy('position')->get();

        return view('livewire.admin.website.menus-index', [
            'items' => $items,
            'menus' => self::MENUS,
            'pages' => Page::query()->published()->orderBy('slug')->get(['id', 'slug', 'title']),
        ]);
    }
}
