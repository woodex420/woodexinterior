<div>
    <x-admin.page-header title="Leads" description="Every enquiry from every channel, one list. Search by name, phone in any format, or reference."
                         :breadcrumbs="['CRM' => null, 'Leads' => null]">
        <x-slot:actions>
            <x-admin.button variant="secondary" icon="kanban" :href="route('admin.leads.index', ['tab' => 'pipeline'])" wire:navigate>Board view</x-admin.button>
            @can('create', \App\Domain\Crm\Models\Lead::class)
                <x-admin.button icon="plus" wire:click="$set('showCreate', true)">New lead</x-admin.button>
            @endcan
        </x-slot:actions>
    </x-admin.page-header>

    {{-- Toolbar --}}
    <div class="mb-4 grid grid-cols-1 gap-3 md:grid-cols-6">
        <div class="relative md:col-span-2">
            <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-gray-400" />
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Name, 0322…, L-2026-…" class="wx-input pl-10" aria-label="Search leads">
        </div>
        <select wire:model.live="stage" class="wx-input" aria-label="Stage">
            <option value="">All stages</option>
            @foreach ($stages as $s)
                <option value="{{ $s->key }}">{{ $s->name }}</option>
            @endforeach
        </select>
        <select wire:model.live="line" class="wx-input" aria-label="Business line">
            <option value="">All lines</option>
            @foreach ($lines as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
        <select wire:model.live="source" class="wx-input" aria-label="Source">
            <option value="">All sources</option>
            @foreach ($sources as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
        <select wire:model.live="owner" class="wx-input" aria-label="Owner">
            <option value="">Everyone</option>
            <option value="me">My leads</option>
            <option value="unassigned">Unassigned</option>
        </select>
    </div>

    {{-- List tools (GLB-003): sort, page size, saved views, columns, export --}}
    <div class="mb-4 flex flex-wrap items-center gap-2">
        <select wire:model.live="sort" class="wx-input h-9 w-auto text-theme-sm" aria-label="Sort">
            @foreach (\App\Livewire\Admin\Crm\LeadsIndex::SORTS as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
        <select wire:model.live="perPage" class="wx-input h-9 w-auto text-theme-sm" aria-label="Rows per page">
            @foreach ([25, 50, 100] as $size)
                <option value="{{ $size }}">{{ $size }} per page</option>
            @endforeach
        </select>

        <div class="relative" x-data="{ open: false }" x-on:click.outside="open = false">
            <x-admin.button variant="secondary" size="sm" icon="bookmark" x-on:click="open = ! open">Views{{ $views->isNotEmpty() ? ' ('.$views->count().')' : '' }}</x-admin.button>
            <div x-cloak x-show="open" x-transition.opacity class="absolute left-0 z-40 mt-2 w-72 rounded-xl border border-gray-200 bg-white p-2 shadow-theme-lg dark:border-gray-800 dark:bg-gray-900">
                @forelse ($views as $view)
                    <div wire:key="view-{{ $view->id }}" class="flex items-center justify-between gap-2 rounded-lg px-2 py-1.5 hover:bg-gray-50 dark:hover:bg-white/5">
                        <button type="button" wire:click="applyView({{ $view->id }})" x-on:click="open = false" class="min-w-0 flex-1 truncate text-left text-theme-sm text-gray-700 dark:text-gray-300">
                            {{ $view->name }} @if ($view->is_default)<span class="text-theme-xs text-gray-400">· default</span>@endif
                        </button>
                        <button type="button" wire:click="deleteView({{ $view->id }})" wire:confirm="Delete the view “{{ $view->name }}”?" class="text-gray-400 hover:text-error-600" aria-label="Delete view"><x-icon name="x" class="size-3.5" /></button>
                    </div>
                @empty
                    <p class="px-2 py-2 text-theme-xs text-gray-500">Save the current filters, sort and columns as a view.</p>
                @endforelse
                <button type="button" wire:click="$set('showSaveView', true)" x-on:click="open = false" class="mt-1 flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-theme-sm font-medium text-navy-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-white/5"><x-icon name="plus" class="size-4" /> Save current view</button>
            </div>
        </div>

        <div class="relative" x-data="{ open: false }" x-on:click.outside="open = false">
            <x-admin.button variant="secondary" size="sm" icon="layout" x-on:click="open = ! open">Columns</x-admin.button>
            <div x-cloak x-show="open" x-transition.opacity class="absolute left-0 z-40 mt-2 w-56 rounded-xl border border-gray-200 bg-white p-2 shadow-theme-lg dark:border-gray-800 dark:bg-gray-900">
                @foreach (\App\Livewire\Admin\Crm\LeadsIndex::COLUMNS as $key => $label)
                    <label class="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1.5 text-theme-sm text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-white/5">
                        <input type="checkbox" wire:click="toggleColumn('{{ $key }}')" @checked(in_array($key, $columns, true)) class="rounded border-gray-300"> {{ $label }}
                    </label>
                @endforeach
            </div>
        </div>

        @if ($canExport)
            <x-admin.button variant="secondary" size="sm" icon="download" wire:click="export" class="ml-auto">Export CSV</x-admin.button>
        @endif
    </div>

    {{-- Bulk actions --}}
    @if ($selected !== [])
        <div class="sticky top-[80px] z-20 mb-3 flex flex-wrap items-center gap-2 rounded-xl border border-navy-200 bg-navy-50 px-4 py-2.5 text-theme-sm shadow-theme-xs dark:border-white/10 dark:bg-gray-900">
            <span class="font-medium text-navy-900 dark:text-white">{{ count($selected) }} selected</span>
            @if ($staff->isNotEmpty())
                <select x-data x-on:change="if ($event.target.value) { $wire.bulkAssign($event.target.value); $event.target.value = '' }" class="wx-input h-8 w-auto text-theme-xs" aria-label="Assign selected leads">
                    <option value="">Assign to…</option>
                    @foreach ($staff as $member)<option value="{{ $member->id }}">{{ $member->name }}</option>@endforeach
                </select>
            @endif
            <select x-data x-on:change="if ($event.target.value) { $wire.bulkMove($event.target.value); $event.target.value = '' }" class="wx-input h-8 w-auto text-theme-xs" aria-label="Move selected leads">
                <option value="">Move to…</option>
                @foreach ($stages->where('type', \App\Domain\Crm\Enums\StageType::Open) as $s)<option value="{{ $s->key }}">{{ $s->name }}</option>@endforeach
            </select>
            @if ($canDelete)
                <x-admin.button variant="secondary" size="sm" icon="trash" wire:click="bulkDelete" wire:confirm="Move {{ count($selected) }} lead(s) to the recycle bin?">Delete</x-admin.button>
            @endif
            <button type="button" wire:click="$set('selected', [])" class="ml-auto text-theme-xs text-gray-500 hover:text-gray-800">Clear selection</button>
        </div>
    @endif

    <x-admin.card :padding="false">
        <div class="overflow-x-auto" wire:loading.class="opacity-60">
            <table class="min-w-full text-left text-theme-sm">
                <thead class="border-b border-gray-100 text-theme-xs tracking-wide text-gray-500 uppercase dark:border-gray-800">
                    <tr>
                        <th class="w-10 py-3 pl-5">
                            <input type="checkbox" class="rounded border-gray-300" aria-label="Select all on this page"
                                   x-data x-on:change="$wire.set('selected', $event.target.checked ? @js($leads->pluck('id')->map(fn ($id) => (string) $id)->values()) : [])"
                                   @checked($leads->count() > 0 && count($selected) >= $leads->count())>
                        </th>
                        <th class="px-5 py-3 font-medium">Lead</th>
                        @if (in_array('contact', $columns, true))<th class="px-5 py-3 font-medium">Contact</th>@endif
                        @if (in_array('line', $columns, true))<th class="px-5 py-3 font-medium">Line · Service</th>@endif
                        @if (in_array('source', $columns, true))<th class="px-5 py-3 font-medium">Source</th>@endif
                        @if (in_array('stage', $columns, true))<th class="px-5 py-3 font-medium">Stage</th>@endif
                        @if (in_array('owner', $columns, true))<th class="px-5 py-3 font-medium">Owner</th>@endif
                        @if (in_array('score', $columns, true))<th class="px-5 py-3 text-right font-medium">Score</th>@endif
                        @if (in_array('next', $columns, true))<th class="px-5 py-3 font-medium">Next action</th>@endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($leads as $lead)
                        <tr wire:key="lead-{{ $lead->id }}" @class(['hover:bg-gray-50 dark:hover:bg-white/[0.02]', 'bg-navy-50/50 dark:bg-white/[0.03]' => in_array((string) $lead->id, array_map('strval', $selected), true)])>
                            <td class="py-3 pl-5"><input type="checkbox" wire:model.live="selected" value="{{ $lead->id }}" class="rounded border-gray-300" aria-label="Select {{ $lead->ref }}"></td>
                            <td class="px-5 py-3 whitespace-nowrap">
                                <a href="{{ route('admin.leads.show', $lead) }}" wire:navigate class="font-medium text-navy-800 hover:underline dark:text-white/90">{{ $lead->ref }}</a>
                                <span class="block text-theme-xs text-gray-400">{{ $lead->created_at?->timezone('Asia/Karachi')->format('j M, g:i a') }}</span>
                            </td>
                            @if (in_array('contact', $columns, true))
                                <td class="px-5 py-3">
                                    <a href="{{ route('admin.leads.show', $lead) }}" wire:navigate class="block font-medium text-gray-800 hover:underline dark:text-white/90">{{ $lead->contact->name }}</a>
                                    <span class="mt-0.5 flex items-center gap-2 text-theme-xs text-gray-500">
                                        {{ \App\Support\Phone::display($lead->contact->phone) ?: $lead->contact->email }}
                                        @if ($wa = \App\Support\Phone::whatsappUrl($lead->contact->whatsappNumber()))
                                            <a href="{{ $wa }}" target="_blank" rel="noopener" class="text-success-600 hover:text-success-700" title="WhatsApp"><x-icon name="whatsapp" class="size-3.5" /></a>
                                        @endif
                                        @if ($lead->contact->phone)
                                            <a href="tel:{{ $lead->contact->phone }}" class="text-navy-600 hover:text-navy-800 dark:text-gray-300" title="Call"><x-icon name="phone" class="size-3.5" /></a>
                                        @endif
                                    </span>
                                </td>
                            @endif
                            @if (in_array('line', $columns, true))
                                <td class="px-5 py-3">
                                    {{ $lead->business_line->label() }}
                                    @if ($lead->service)
                                        <span class="block text-theme-xs text-gray-500">{{ \Illuminate\Support\Str::headline($lead->service) }}</span>
                                    @endif
                                </td>
                            @endif
                            @if (in_array('source', $columns, true))
                                <td class="px-5 py-3 whitespace-nowrap text-gray-600 dark:text-gray-400">{{ $lead->source->label() }}</td>
                            @endif
                            @if (in_array('stage', $columns, true))
                                <td class="px-5 py-3"><x-admin.badge :color="$lead->stage->badgeColor()">{{ $lead->stage->name }}</x-admin.badge></td>
                            @endif
                            @if (in_array('owner', $columns, true))
                                <td class="px-5 py-3 whitespace-nowrap text-gray-600 dark:text-gray-400">{{ $lead->owner?->name ?? 'Unassigned' }}</td>
                            @endif
                            @if (in_array('score', $columns, true))
                                <td class="px-5 py-3 text-right tabular-nums">
                                    <span @class(['font-medium text-wood-700 dark:text-wood-300' => $lead->isHot(), 'text-gray-600 dark:text-gray-400' => ! $lead->isHot()])>{{ $lead->score }}</span>
                                </td>
                            @endif
                            @if (in_array('next', $columns, true))
                                <td class="px-5 py-3 whitespace-nowrap">
                                    @if ($lead->next_action_at)
                                        <span @class(['font-medium', 'text-error-600' => $lead->isOverdue(), 'text-gray-700 dark:text-gray-300' => ! $lead->isOverdue()])>
                                            {{ $lead->next_action_type?->label() ?? 'Follow up' }}
                                        </span>
                                        <span class="block text-theme-xs text-gray-400">{{ $lead->next_action_at->diffForHumans() }}</span>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ 2 + count($columns) }}">
                                @if ($hasFilters)
                                    <x-admin.empty icon="filter" title="No leads match these filters" description="Try another search or clear the filters.">
                                        <x-admin.button variant="secondary" wire:click="clearFilters">Clear filters</x-admin.button>
                                    </x-admin.empty>
                                @else
                                    <x-admin.empty icon="users" title="No leads yet" description="Website forms post to /api/public/leads. Enquiries from WhatsApp, social media and phone calls will appear here as each channel goes live." />
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $leads->links('components.admin.pagination') }}
    </x-admin.card>

    @if ($showSaveView)
        <x-admin.modal name="showSaveView" title="Save this view" description="Saves the filters, sort, page size and columns you see now." submit="saveView">
            <x-admin.field label="Name" for="viewName">
                <input id="viewName" type="text" wire:model="viewName" class="wx-input" placeholder="My hot project leads">
            </x-admin.field>
            <label class="mt-3 flex items-center gap-2 text-theme-sm text-gray-700 dark:text-gray-300"><input type="checkbox" wire:model="viewAsDefault" class="rounded border-gray-300"> Open Leads with this view</label>
            <x-slot:footer>
                <x-admin.button variant="secondary" wire:click="$set('showSaveView', false)">Cancel</x-admin.button>
                <x-admin.button type="submit" icon="bookmark">Save view</x-admin.button>
            </x-slot:footer>
        </x-admin.modal>
    @endif

    {{-- Quick add (CAP-008) --}}
    @if ($showCreate)
        <div class="fixed inset-0 z-[9999] flex items-end justify-center bg-gray-900/50 p-4 sm:items-center" role="dialog" aria-modal="true" aria-labelledby="new-lead-title"
             x-data x-on:keydown.escape.window="$wire.set('showCreate', false)">
            <form wire:submit="createLead" class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-theme-lg dark:bg-gray-900">
                <div class="mb-5 flex items-start justify-between">
                    <div>
                        <h2 id="new-lead-title" class="text-theme-xl font-medium text-gray-900 dark:text-white">New lead</h2>
                        <p class="text-theme-xs text-gray-500">Phone numbers are matched to existing contacts automatically.</p>
                    </div>
                    <button type="button" wire:click="$set('showCreate', false)" class="text-gray-400 hover:text-gray-600" aria-label="Close"><x-icon name="x" /></button>
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="wx-label" for="newName">Name</label>
                        <input id="newName" type="text" wire:model="newName" class="wx-input" autofocus>
                        @error('newName') <p class="mt-1 text-theme-xs text-error-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="wx-label" for="newPhone">Phone / WhatsApp</label>
                        <input id="newPhone" type="tel" wire:model="newPhone" class="wx-input" placeholder="0322 4000768">
                        @error('newPhone') <p class="mt-1 text-theme-xs text-error-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="wx-label" for="newEmail">Email</label>
                        <input id="newEmail" type="email" wire:model="newEmail" class="wx-input">
                        @error('newEmail') <p class="mt-1 text-theme-xs text-error-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="wx-label" for="newLine">Business line</label>
                        <select id="newLine" wire:model="newLine" class="wx-input">
                            @foreach ($lines as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="wx-label" for="newSource">Source</label>
                        <select id="newSource" wire:model="newSource" class="wx-input">
                            @foreach (\App\Domain\Crm\Enums\LeadSource::manualSources() as $option)
                                <option value="{{ $option->value }}">{{ $option->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="wx-label" for="newService">Service</label>
                        <input id="newService" type="text" wire:model="newService" class="wx-input" placeholder="e.g. renovation, kitchen-design">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="wx-label" for="newMessage">Notes</label>
                        <textarea id="newMessage" wire:model="newMessage" rows="3" class="wx-input h-auto py-2"></textarea>
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <x-admin.button variant="secondary" wire:click="$set('showCreate', false)">Cancel</x-admin.button>
                    <x-admin.button type="submit" icon="plus" wire:loading.attr="disabled" wire:target="createLead">Create lead</x-admin.button>
                </div>
            </form>
        </div>
    @endif
</div>
