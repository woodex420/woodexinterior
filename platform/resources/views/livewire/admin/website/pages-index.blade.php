@use('App\Domain\Website\Enums\PageStatus')
@use('App\Domain\Website\Models\Page')

<div>
    <x-admin.page-header title="Pages" description="Every page of the public website. Content lives in the database, so a page is changed here rather than in code."
                         :breadcrumbs="['Website' => null, 'Pages' => null]">
        <x-slot:actions>
            {{-- The visual builder is a full document of its own: a normal link, not wire:navigate. --}}
            <x-admin.button icon="panel" :href="route('admin.website.builder')">Page builder</x-admin.button>
            <x-admin.button icon="plus" :href="route('admin.website.pages.create')" wire:navigate>New page</x-admin.button>
        </x-slot:actions>
    </x-admin.page-header>

    {{-- V2-3 F5: start a page from a template — the layout's sections land on a fresh draft. --}}
    {{-- 50 layouts now (19 Oct): the eight the studio wrote by hand plus the ones its own pages
         supply, so the picker filters by page type instead of asking someone to scroll. --}}
    <div x-data="{ open: false, type: '' }" class="mb-4">
        <button type="button" @click="open = !open" :aria-expanded="open.toString()"
                class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-theme-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                aria-controls="template-picker">
            {{ count($templates) }} page templates — start from one
            <x-icon name="chevron-down" class="size-3.5" />
        </button>

        <div id="template-picker" x-cloak x-show="open" @click.outside="open = false"
             class="mt-2 rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
            <div class="mb-3 flex flex-wrap items-center gap-1.5">
                <button type="button" @click="type = ''" :class="type === '' ? 'bg-navy-900 text-white dark:bg-white dark:text-navy-900' : 'bg-gray-100 text-gray-600 dark:bg-white/5 dark:text-gray-300'"
                        class="rounded-full px-2.5 py-1 text-theme-xs font-medium">All {{ count($templates) }}</button>
                @foreach ($templates->groupBy(fn ($template) => $template->page_type->value) as $value => $group)
                    <button type="button" @click="type = '{{ $value }}'" :class="type === '{{ $value }}' ? 'bg-navy-900 text-white dark:bg-white dark:text-navy-900' : 'bg-gray-100 text-gray-600 dark:bg-white/5 dark:text-gray-300'"
                            class="rounded-full px-2.5 py-1 text-theme-xs font-medium">{{ $group->first()->page_type->label() }} {{ count($group) }}</button>
                @endforeach
            </div>

            <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($templates as $template)
                <div class="rounded-xl border border-gray-200 p-3 dark:border-gray-800"
                     x-show="type === '' || type === '{{ $template->page_type->value }}'">
                    <div class="flex items-start justify-between gap-2">
                        <p class="text-theme-sm font-medium text-gray-800 dark:text-white/90">{{ $template->name }}</p>
                        @if ($template->is_default)
                            <span class="shrink-0 rounded-full bg-brand-50 px-2 py-0.5 text-[10px] font-medium text-brand-700 dark:bg-brand-500/10 dark:text-brand-300">Default</span>
                        @endif
                    </div>
                    <p class="mt-0.5 text-theme-xs text-gray-500">{{ $template->page_type->label() }} · {{ count($template->sections) }} sections</p>
                    <p class="mt-1.5 text-theme-xs text-gray-500">{{ $template->description }}</p>
                    <p class="mt-2 text-[11px] leading-relaxed text-gray-400">
                        @foreach (array_slice($template->sections, 0, 6) as $section)
                            <span class="rounded bg-gray-100 px-1.5 py-0.5 dark:bg-white/5">{{ \App\Domain\Website\Enums\BlockType::from($section['type'])->label() }}</span>
                        @endforeach
                        @if (count($template->sections) > 6)<span class="text-gray-400">+{{ count($template->sections) - 6 }}</span>@endif
                    </p>
                    <button type="button" wire:click="createFromTemplate({{ $template->id }})"
                            class="mt-3 text-theme-sm font-medium text-brand-600 hover:underline">Start this page →</button>
                </div>
            @endforeach
            </div>
        </div>
    </div>

    <div class="mb-4 grid grid-cols-1 gap-3 md:grid-cols-4">
        <div class="relative md:col-span-2">
            <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-gray-400" />
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search title or URL" class="wx-input pl-10" aria-label="Search pages">
        </div>
        <select wire:model.live="type" class="wx-input" aria-label="Type">
            <option value="">All types</option>
            @foreach ($types as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
        <select wire:model.live="status" class="wx-input" aria-label="Status">
            <option value="">Any status</option>
            @foreach ($statuses as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="mb-4 grid grid-cols-2 gap-3 md:grid-cols-4">
        <x-admin.stat label="Pages" :value="$counts['pages']" />
        <x-admin.stat label="Published" :value="$counts['published']" />
        <x-admin.stat label="Legacy redirects" :value="$counts['redirects']" />
        <x-admin.stat label="Images" :value="$counts['media']" />
    </div>

    <x-admin.card :padding="false">
        <div class="overflow-x-auto" wire:loading.class="opacity-60">
            <table class="min-w-full text-left text-theme-sm">
                <thead class="border-b border-gray-100 text-theme-xs tracking-wide text-gray-500 uppercase dark:border-gray-800">
                    <tr>
                        <th class="px-5 py-3 font-medium">Page</th>
                        <th class="hidden px-5 py-3 font-medium md:table-cell">Type</th>
                        <th class="px-5 py-3 font-medium">Status</th>
                        <th class="px-5 py-3 font-medium">Check</th>
                        <th class="hidden px-5 py-3 font-medium lg:table-cell">Sections</th>
                        <th class="hidden px-5 py-3 font-medium lg:table-cell">Changed</th>
                        <th class="px-5 py-3"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($pages as $page)
                        <tr wire:key="page-{{ $page->id }}" class="hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                            <td class="px-5 py-3">
                                <a href="{{ route('admin.website.pages.edit', $page) }}" wire:navigate class="block font-medium text-gray-800 hover:underline dark:text-white/90">{{ $page->title }}</a>
                                <span class="block text-theme-xs text-gray-500">{{ $page->slug }}</span>
                            </td>
                            <td class="hidden px-5 py-3 text-gray-600 md:table-cell dark:text-gray-400">{{ $page->type->label() }}</td>
                            <td class="px-5 py-3">
                                @php $status = PageStatus::from($page->status->value); @endphp
                                <x-admin.badge :color="match ($status) { PageStatus::Published => 'success', PageStatus::Review => 'warning', default => 'gray' }">
                                    {{ $status->label() }}
                                </x-admin.badge>
                                @if ($page->template === 'canvas')
                                    <x-admin.badge color="warning">canvas</x-admin.badge>
                                @endif
                                @if ($page->template === 'html')
                                    <x-admin.badge color="info">page builder</x-admin.badge>
                                @endif
                                @if ($status === PageStatus::Published && $page->published_at?->isFuture())
                                    <span class="block text-theme-xs text-gray-500">from {{ $page->published_at->format('d M Y') }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-3">
                                @php $check = $checks[$page->id] ?? null; @endphp
                                @if ($check === null || $check['count'] === 0)
                                    <span class="inline-flex items-center gap-1 text-theme-xs text-success-600 dark:text-success-500" title="Nothing to fix">
                                        <x-icon name="check-circle" class="size-3.5" /> Clear
                                    </span>
                                @else
                                    <a href="{{ route('admin.website.pages.edit', $page) }}#page-check" wire:navigate
                                       class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-theme-xs font-medium hover:underline {{ $check['bad'] > 0 ? 'bg-error-50 text-error-700 dark:bg-error-500/10 dark:text-error-500' : 'bg-warning-50 text-warning-700 dark:bg-warning-500/10 dark:text-warning-500' }}"
                                       title="{{ $check['first'] }}">
                                        <x-icon name="alert" class="size-3" />
                                        {{ $check['count'] }} {{ \Illuminate\Support\Str::plural('item', $check['count']) }}
                                    </a>
                                @endif
                            </td>
                            <td class="hidden px-5 py-3 text-gray-600 lg:table-cell dark:text-gray-400">{{ $page->blocks_count }}</td>
                            <td class="hidden px-5 py-3 text-gray-500 lg:table-cell">{{ $page->updated_at?->diffForHumans() }}</td>
                            <td class="px-5 py-3">
                                <div class="flex items-center justify-end gap-2">
                                    @if ($page->template === 'html')
                                        <a href="{{ route('admin.website.builder', $page) }}" class="font-medium text-theme-sm text-brand-600 hover:underline">Page builder</a>
                                    @endif
                                    @if ($page->status->value === PageStatus::Published->value)
                                        <a href="{{ $page->canonical() }}" target="_blank" rel="noopener" class="text-theme-sm text-brand-600 hover:underline">View</a>
                                        @can('website.publish')
                                            <button type="button" wire:click="unpublish({{ $page->id }})" class="text-theme-sm text-gray-500 hover:underline">Take offline</button>
                                        @endcan
                                    @else
                                        <a href="{{ $page->canonical() }}?preview=1" target="_blank" rel="noopener" class="text-theme-sm text-brand-600 hover:underline">Preview</a>
                                    @endif
                                    <button type="button" wire:click="duplicate({{ $page->id }})" class="text-theme-sm text-gray-500 hover:underline">Duplicate</button>
                                    @can('website.publish')
                                        <button type="button" wire:click="delete({{ $page->id }})" wire:confirm="Delete “{{ $page->title }}”? It can be restored from the recycle bin of the database." class="text-theme-sm text-error-600 hover:underline">Delete</button>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <x-admin.empty title="No pages yet" description="Create the first page, or import the 143 URLs of the previous website with the importer." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-admin.card>
</div>
