<div>
    <x-admin.page-header title="Leads" description="Every enquiry from every channel, one list. Search by name, phone in any format, or reference."
                         :breadcrumbs="['CRM' => null, 'Leads' => null]">
        <x-slot:actions>
            <x-admin.button variant="secondary" icon="kanban" :href="route('admin.pipeline')" wire:navigate>Board view</x-admin.button>
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

    <x-admin.card :padding="false">
        <div class="overflow-x-auto" wire:loading.class="opacity-60">
            <table class="min-w-full text-left text-theme-sm">
                <thead class="border-b border-gray-100 text-theme-xs tracking-wide text-gray-500 uppercase dark:border-gray-800">
                    <tr>
                        <th class="px-5 py-3 font-medium">Lead</th>
                        <th class="px-5 py-3 font-medium">Contact</th>
                        <th class="px-5 py-3 font-medium">Line · Service</th>
                        <th class="px-5 py-3 font-medium">Source</th>
                        <th class="px-5 py-3 font-medium">Stage</th>
                        <th class="px-5 py-3 font-medium">Owner</th>
                        <th class="px-5 py-3 font-medium">Next action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($leads as $lead)
                        <tr wire:key="lead-{{ $lead->id }}" class="hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                            <td class="px-5 py-3 whitespace-nowrap">
                                <a href="{{ route('admin.leads.show', $lead) }}" wire:navigate class="font-medium text-navy-800 hover:underline dark:text-white/90">{{ $lead->ref }}</a>
                                <span class="block text-theme-xs text-gray-400">{{ $lead->created_at?->timezone('Asia/Karachi')->format('j M, g:i a') }}</span>
                            </td>
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
                            <td class="px-5 py-3">
                                {{ $lead->business_line->label() }}
                                @if ($lead->service)
                                    <span class="block text-theme-xs text-gray-500">{{ \Illuminate\Support\Str::headline($lead->service) }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 whitespace-nowrap text-gray-600 dark:text-gray-400">{{ $lead->source->label() }}</td>
                            <td class="px-5 py-3">
                                <x-admin.badge :color="$lead->stage->badgeColor()">{{ $lead->stage->name }}</x-admin.badge>
                            </td>
                            <td class="px-5 py-3 whitespace-nowrap text-gray-600 dark:text-gray-400">{{ $lead->owner?->name ?? 'Unassigned' }}</td>
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
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
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
