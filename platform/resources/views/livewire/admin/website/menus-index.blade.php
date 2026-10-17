<div>
    <x-admin.page-header title="Menus" description="The links in the header and the footer of every page. Add a service to the menu here and it appears on the whole website at once."
                         :breadcrumbs="['Website' => null, 'Menus' => null]" />

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
                        <th class="hidden px-5 py-3 font-medium md:table-cell">Opens in</th>
                        <th class="px-5 py-3"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($items as $item)
                        <tr wire:key="nav-{{ $item->id }}" class="hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                            <td class="px-5 py-3 font-medium text-gray-800 dark:text-white/90">{{ $item->label }}</td>
                            <td class="px-5 py-3 font-mono text-theme-xs text-gray-600 dark:text-gray-400">{{ $item->url }}</td>
                            <td class="hidden px-5 py-3 text-theme-xs text-gray-500 md:table-cell">{{ $item->target === '_blank' ? 'New tab' : 'Same tab' }}</td>
                            <td class="px-5 py-3">
                                <div class="flex items-center justify-end gap-2">
                                    <button type="button" wire:click="move({{ $item->id }}, -1)" class="wx-mini-btn" aria-label="Move up" @if ($loop->first) disabled @endif>↑</button>
                                    <button type="button" wire:click="move({{ $item->id }}, 1)" class="wx-mini-btn" aria-label="Move down" @if ($loop->last) disabled @endif>↓</button>
                                    <button type="button" wire:click="remove({{ $item->id }})" class="text-theme-sm text-error-600 hover:underline">Remove</button>
                                </div>
                            </td>
                        </tr>
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
