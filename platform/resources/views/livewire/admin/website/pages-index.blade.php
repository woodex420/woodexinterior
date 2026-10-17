@use('App\Domain\Website\Enums\PageStatus')
@use('App\Domain\Website\Models\Page')

<div>
    <x-admin.page-header title="Pages" description="Every page of the public website. Content lives in the database, so a page is changed here rather than in code."
                         :breadcrumbs="['Website' => null, 'Pages' => null]">
        <x-slot:actions>
            <x-admin.button icon="plus" :href="route('admin.website.pages.create')" wire:navigate>New page</x-admin.button>
        </x-slot:actions>
    </x-admin.page-header>

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
                                @if ($status === PageStatus::Published && $page->published_at?->isFuture())
                                    <span class="block text-theme-xs text-gray-500">from {{ $page->published_at->format('d M Y') }}</span>
                                @endif
                            </td>
                            <td class="hidden px-5 py-3 text-gray-600 lg:table-cell dark:text-gray-400">{{ $page->blocks_count }}</td>
                            <td class="hidden px-5 py-3 text-gray-500 lg:table-cell">{{ $page->updated_at?->diffForHumans() }}</td>
                            <td class="px-5 py-3">
                                <div class="flex items-center justify-end gap-2">
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
                            <td colspan="6">
                                <x-admin.empty title="No pages yet" description="Create the first page, or import the 143 URLs of the previous website with the importer." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-admin.card>
</div>
