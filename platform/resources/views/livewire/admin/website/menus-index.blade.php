<div x-on:focus-label.window="$el.querySelector('#label')?.scrollIntoView({ behavior: 'smooth', block: 'center' }); document.getElementById('label')?.focus()">
    <x-admin.page-header title="Header & footer" description="The links in the header and the footer of every page. A link can sit under another link: a top link with children opens a dropdown, and its mega switch widens it into the numbered panel. Add a service here and it appears on the whole website at once."
                         :breadcrumbs="['Website' => null, 'Header & footer' => null]" />

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <x-admin.card title="Add a link">
            <form wire:submit="add" class="space-y-3">
                <x-admin.field label="Menu" for="menu">
                    <select id="menu" wire:model.live="menu" class="wx-input">
                        @foreach ($menus as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </x-admin.field>
                <x-admin.field label="Text" for="label">
                    <input id="label" type="text" wire:model="label" class="wx-input" placeholder="Interior design">
                </x-admin.field>
                <x-admin.field label="Link" for="url" hint="Pick a page below, or type any address.">
                    <input id="url" type="text" wire:model="url" class="wx-input" placeholder="/interior-design/">
                    <select class="wx-input mt-2" onchange="document.getElementById('url').value = this.value; document.getElementById('label').value ||= this.options[this.selectedIndex].text">
                        <option value="">…or pick a published page</option>
                        @foreach ($pages as $page)
                            <option value="{{ $page->slug }}">{{ $page->title }} · {{ $page->slug }}</option>
                        @endforeach
                    </select>
                </x-admin.field>
                <x-admin.field label="Sits under" for="parent" hint="Top level for the bar itself; under a top link for its dropdown; under a dropdown link for a mega column.">
                    <select id="parent" wire:model="parent" class="wx-input">
                        @foreach ($parentOptions as $option)
                            <option value="{{ $option['id'] }}">{{ $option['label'] }}</option>
                        @endforeach
                    </select>
                </x-admin.field>
                <x-admin.field label="Opens in" for="target">
                    <select id="target" wire:model="target" class="wx-input">
                        <option value="_self">Same tab</option>
                        <option value="_blank">New tab</option>
                    </select>
                </x-admin.field>
                <x-admin.button type="submit" icon="plus">Add to the {{ \Illuminate\Support\Str::lower($menus[$menu] ?? 'menu') }}</x-admin.button>
            </form>
        </x-admin.card>

        <x-admin.card :padding="false" title="{{ $menus[$menu] ?? 'Menu' }} links" class="xl:col-span-2">
            <table class="min-w-full text-left text-theme-sm">
                <thead class="border-b border-gray-100 text-theme-xs tracking-wide text-gray-500 uppercase dark:border-gray-800">
                    <tr>
                        <th class="px-5 py-3 font-medium">Text</th>
                        <th class="px-5 py-3 font-medium">Link</th>
                        <th class="hidden px-5 py-3 font-medium md:table-cell">Opens as</th>
                        <th class="px-5 py-3"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @php
                        // Rows in reading order: top link, its children, their children — position is per level.
                        $rows = $items->whereNull('parent_id')->values();
                        $childrenOf = fn ($parentId) => $items->where('parent_id', $parentId)->values();
                    @endphp
                    @forelse ($rows as $item)
                        @include('livewire.admin.website.menus-row', ['item' => $item, 'level' => 0, 'childrenOf' => $childrenOf])
                    @empty
                        <tr>
                            <td colspan="4">
                                <x-admin.empty icon="menu" title="No links yet" description="Until a menu is filled in, the website shows its built-in links." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </x-admin.card>
    </div>
</div>
