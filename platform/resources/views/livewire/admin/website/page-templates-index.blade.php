<div>
    <x-admin.page-header title="Page templates"
                         description="Every layout a page can start from — the sections and their order, never the words. Editing a template never changes a page already made from it."
                         :breadcrumbs="['Website' => null, 'Page templates' => null]">
        <x-slot:actions>
            <x-admin.button variant="secondary" icon="layout" :href="route('admin.website.pages')">All pages</x-admin.button>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center">
        <div class="relative min-w-0 flex-1">
            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-gray-400">
                <x-icon name="search" class="size-4" />
            </span>
            <input type="search" wire:model.live.debounce.300ms="search" class="wx-input pl-9"
                   placeholder="Search a layout by name or description…" aria-label="Search templates">
        </div>
        <div class="shrink-0 sm:w-56">
            <select wire:model.live="type" class="wx-input" aria-label="Filter by page type">
                <option value="">All page types</option>
                @foreach ($types as $option)
                    <option value="{{ $option->value }}">{{ $option->label() }}</option>
                @endforeach
            </select>
        </div>
        <span class="shrink-0 whitespace-nowrap text-theme-sm text-gray-500">{{ $templates->count() }} of {{ $total }}</span>
    </div>

    @if ($templates->isEmpty())
        <x-admin.empty icon="layers" title="No layout matches"
                       description="Try another search, another page type, or save a page's layout as a template from the page editor.">
            <x-admin.button variant="secondary" wire:click="$set('search', ''); $set('type', '')">Clear filters</x-admin.button>
        </x-admin.empty>
    @else
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($templates as $template)
                <article wire:key="template-{{ $template->id }}"
                         class="flex flex-col rounded-xl border border-gray-200 bg-white p-5 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                    <div class="flex items-center justify-between gap-2">
                        <span class="rounded-full bg-gray-100 px-2.5 py-1 text-theme-xs font-medium text-gray-600 dark:bg-white/5 dark:text-gray-300">
                            {{ $template->page_type->label() }}
                        </span>
                        @if ($template->is_default)
                            <span class="rounded-full bg-wood-50 px-2.5 py-1 text-theme-xs font-medium text-wood-700 dark:bg-wood-500/10 dark:text-wood-300">Default</span>
                        @endif
                    </div>

                    <h3 class="mt-3 text-theme-sm font-semibold text-gray-900 dark:text-white">{{ $template->name }}</h3>

                    @if ($template->description)
                        <p class="mt-1 line-clamp-2 text-theme-xs text-gray-500 dark:text-gray-400">{{ $template->description }}</p>
                    @endif

                    <div class="mt-3 flex flex-wrap gap-1.5">
                        @foreach (array_slice($template->sections, 0, 5) as $section)
                            <span class="rounded-md bg-gray-50 px-1.5 py-0.5 text-[11px] text-gray-500 dark:bg-white/5 dark:text-gray-400">
                                {{ \App\Domain\Website\Enums\BlockType::tryFrom((string) $section['type'])?->label() ?? $section['type'] }}
                            </span>
                        @endforeach
                        @if (count($template->sections) > 5)
                            <span class="text-[11px] text-gray-400">+{{ count($template->sections) - 5 }} more</span>
                        @endif
                    </div>

                    <p class="mt-3 text-theme-xs text-gray-400">
                        {{ count($template->sections) }} {{ str('section')->plural(count($template->sections)) }}
                        · updated {{ $template->updated_at?->diffForHumans() }}
                    </p>

                    <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-gray-100 pt-4 dark:border-gray-800">
                        <x-admin.button size="sm" wire:click="createPage({{ $template->id }})">Create page</x-admin.button>
                        <x-admin.button size="sm" variant="secondary" wire:click="edit({{ $template->id }})">Edit</x-admin.button>
                        <x-admin.button size="sm" variant="ghost" wire:click="duplicate({{ $template->id }})" title="Duplicate this layout">Duplicate</x-admin.button>
                        @can('website.publish')
                            <x-admin.button size="sm" variant="ghost" class="ml-auto text-error-600 hover:text-error-700"
                                            wire:click="delete({{ $template->id }})"
                                            wire:confirm="Delete “{{ $template->name }}”? Pages already made from it are untouched."
                                            title="Delete this layout">Delete</x-admin.button>
                        @endcan
                    </div>
                </article>
            @endforeach
        </div>
    @endif

    @if ($showEdit)
        <div class="fixed inset-0 z-[9999] flex items-end justify-center bg-gray-900/50 p-4 sm:items-center" role="dialog" aria-modal="true" aria-labelledby="edit-template-title"
             x-data x-on:keydown.escape.window="$wire.cancelEdit()">
            <form wire:submit="save" class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-theme-lg dark:bg-gray-900">
                <div class="mb-5 flex items-start justify-between">
                    <div>
                        <h2 id="edit-template-title" class="text-theme-xl font-medium text-gray-900 dark:text-white">Edit layout</h2>
                        <p class="text-theme-xs text-gray-500">The sections themselves are edited on a page; this names the layout and sets where it is offered.</p>
                    </div>
                    <button type="button" wire:click="cancelEdit" class="text-gray-400 hover:text-gray-600" aria-label="Close"><x-icon name="x" /></button>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="wx-label" for="editName">Name</label>
                        <input id="editName" type="text" wire:model="editName" class="wx-input" autofocus>
                        @error('editName') <p class="mt-1 text-theme-xs text-error-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="wx-label" for="editType">Page type</label>
                        <select id="editType" wire:model="editType" class="wx-input">
                            @foreach (\App\Domain\Website\Enums\PageType::cases() as $case)
                                <option value="{{ $case->value }}">{{ $case->label() }}</option>
                            @endforeach
                        </select>
                        @error('editType') <p class="mt-1 text-theme-xs text-error-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label class="wx-label" for="editDescription">Description <span class="text-gray-400">(shown on the card)</span></label>
                        <textarea id="editDescription" rows="2" wire:model="editDescription" class="wx-input h-auto py-2"></textarea>
                        @error('editDescription') <p class="mt-1 text-theme-xs text-error-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2 flex items-start gap-2">
                        <input id="editDefault" type="checkbox" wire:model="editDefault" class="mt-0.5 rounded border-gray-300">
                        <label for="editDefault" class="text-theme-sm text-gray-600 dark:text-gray-300">
                            Default layout for this page type
                            <span class="block text-theme-xs text-gray-400">Only one layout per type can be the default; choosing this one clears the others.</span>
                        </label>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <x-admin.button variant="secondary" wire:click="cancelEdit">Cancel</x-admin.button>
                    <x-admin.button type="submit" wire:loading.attr="disabled" wire:target="save">Save layout</x-admin.button>
                </div>
            </form>
        </div>
    @endif
</div>
