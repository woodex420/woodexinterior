<div>
    <x-admin.page-header title="SEO" description="Every page's search listing in one place: the title and the description Google shows, their lengths against the cut-offs (title ~65, description ~165), and the robots rule. Fix the two listing fields right here; focus keyword and OG image live in each page's editor."
                         :breadcrumbs="['Website' => null, 'SEO' => null]">
        <x-slot:actions>
            <x-admin.button variant="secondary" icon="sparkles" href="{{ route('admin.website.seo') }}">SEO agent</x-admin.button>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="mb-4 grid grid-cols-2 gap-3 md:grid-cols-4">
        <x-admin.stat label="Pages listed" :value="$filter === 'all' ? $counts['all'] : ($filter === 'published' ? $counts['published'] : $counts['all'] - $counts['published'])" icon="layout" />
        <x-admin.stat label="Published" :value="$counts['published']" icon="globe" tone="wood" />
        <x-admin.stat label="Drafts" :value="$counts['all'] - $counts['published']" icon="pencil" />
        <x-admin.stat label="Missing a description" :value="$rows->where('meta_description_len', 0)->count()" icon="alert" color="warning" />
    </div>

    <x-admin.card title="The listings" description="Green = complete and inside the cut-offs. Amber = too short or from the excerpt. Red = missing.">
        <div class="mb-4 flex flex-wrap gap-2">
            <x-admin.button variant="{{ $filter === 'published' ? 'primary' : 'secondary' }}" wire:click="setFilter('published')">Published</x-admin.button>
            <x-admin.button variant="{{ $filter === 'draft' ? 'primary' : 'secondary' }}" wire:click="setFilter('draft')">Drafts</x-admin.button>
            <x-admin.button variant="{{ $filter === 'all' ? 'primary' : 'secondary' }}" wire:click="setFilter('all')">All</x-admin.button>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-theme-sm">
                <thead>
                    <tr class="border-b border-gray-200 text-left text-theme-xs uppercase tracking-wider text-gray-400 dark:border-gray-800">
                        <th class="py-2 pr-3">Page</th>
                        <th class="py-2 pr-3">Search title</th>
                        <th class="py-2 pr-3">Description</th>
                        <th class="py-2 pr-3">Robots</th>
                        <th class="py-2 pr-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($rows as $row)
                        <tr>
                            <td class="py-2.5 pr-3">
                                <a href="{{ route('admin.website.pages.edit', $row['id']) }}" wire:navigate class="font-medium text-gray-900 hover:underline dark:text-white">{{ $row['title'] }}</a>
                                <p class="text-theme-xs text-gray-400">{{ $row['slug'] }} · {{ $row['status'] }}</p>
                            </td>
                            <td class="py-2.5 pr-3">
                                <div class="flex items-center gap-2">
                                    @if ($row['meta_title_len'] > 65)
                                        <x-admin.badge color="warning">{{ $row['meta_title_len'] }} chars — cut off</x-admin.badge>
                                    @elseif ($row['meta_title_len'] < 20)
                                        <x-admin.badge color="warning">{{ $row['meta_title_len'] }} chars — thin</x-admin.badge>
                                    @else
                                        <x-admin.badge color="success">{{ $row['meta_title_len'] }}</x-admin.badge>
                                    @endif
                                </div>
                                <p class="mt-1 max-w-sm truncate text-theme-xs text-gray-500" title="{{ $row['meta_title'] }}">{{ $row['meta_title'] }}</p>
                                @if ($row['meta_title_source'] !== 'set')
                                    <p class="text-theme-xs text-gray-400">not set — {{ $row['meta_title_source'] }}</p>
                                @endif
                            </td>
                            <td class="py-2.5 pr-3">
                                @if ($row['meta_description_len'] === 0)
                                    <x-admin.badge color="error">missing</x-admin.badge>
                                @elseif ($row['meta_description_len'] > 165)
                                    <x-admin.badge color="warning">{{ $row['meta_description_len'] }} chars — cut off</x-admin.badge>
                                @elseif ($row['meta_description_len'] < 50)
                                    <x-admin.badge color="warning">{{ $row['meta_description_len'] }} chars — thin</x-admin.badge>
                                @else
                                    <x-admin.badge color="success">{{ $row['meta_description_len'] }}</x-admin.badge>
                                @endif
                                <p class="mt-1 max-w-md truncate text-theme-xs text-gray-500" title="{{ $row['meta_description'] }}">{{ $row['meta_description'] ?: '—' }}</p>
                                @if ($row['meta_description_source'] !== 'set' && $row['meta_description_source'] !== 'missing')
                                    <p class="text-theme-xs text-gray-400">not set — {{ $row['meta_description_source'] }}</p>
                                @endif
                            </td>
                            <td class="py-2.5 pr-3">
                                @if ($row['robots'] !== '')
                                    <x-admin.badge color="warning">{{ $row['robots'] }}</x-admin.badge>
                                @else
                                    <span class="text-theme-xs text-gray-400">index</span>
                                @endif
                            </td>
                            <td class="py-2.5 text-right">
                                <x-admin.button variant="secondary" icon="pencil" wire:click="edit({{ $row['id'] }})">Edit listing</x-admin.button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-admin.card>

    @if ($showEdit)
        <x-admin.modal name="showEdit" title="The search listing" description="Exactly what Google shows: the title as the blue link, the description as the grey text under it." submit="saveEdit" width="max-w-xl">
            <div class="space-y-4">
                <x-admin.field label="Search title" for="editTitle" hint="{{ strlen($editTitle) }} characters — Google cuts around 65.">
                    <input id="editTitle" type="text" wire:model="editTitle" class="wx-input" maxlength="90">
                </x-admin.field>
                <x-admin.field label="Search description" for="editDescription" hint="{{ strlen($editDescription) }} characters — Google cuts around 165.">
                    <textarea id="editDescription" rows="3" wire:model="editDescription" class="wx-input" maxlength="320"></textarea>
                </x-admin.field>
            </div>
        </x-admin.modal>
    @endif
</div>
