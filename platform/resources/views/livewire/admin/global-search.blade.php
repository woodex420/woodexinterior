@use('App\Support\Phone')
<div x-data="{ open: false }"
     x-on:keydown.window.ctrl.k.prevent="open = true; $nextTick(() => $refs.term.focus())"
     x-on:keydown.window.meta.k.prevent="open = true; $nextTick(() => $refs.term.focus())"
     x-on:open-search.window="open = true; $nextTick(() => $refs.term.focus())"
     class="max-w-md flex-1">
    <button type="button" x-on:click="open = true; $nextTick(() => $refs.term.focus())"
            class="flex h-10 w-full items-center gap-2.5 rounded-lg border border-gray-200 bg-gray-50 px-3.5 text-left text-theme-sm text-gray-400 transition hover:border-gray-300 hover:bg-white dark:border-gray-800 dark:bg-white/[0.03] dark:hover:border-gray-700"
            aria-label="Search (Ctrl K)">
        <x-icon name="search" class="size-4" />
        <span class="hidden flex-1 truncate md:block">Search name, phone or L-2026-…</span>
        <kbd class="ml-auto hidden rounded border border-gray-200 bg-white px-1.5 py-0.5 font-sans text-[11px] text-gray-500 md:inline dark:border-gray-700 dark:bg-gray-800">Ctrl K</kbd>
    </button>

    @teleport('body')
        <div x-show="open" x-cloak x-transition.opacity class="fixed inset-0 z-[10000] flex items-start justify-center bg-gray-900/50 p-4 pt-[10vh] backdrop-blur-[2px]"
             x-on:keydown.escape.window="open = false" x-on:click.self="open = false" role="dialog" aria-modal="true" aria-label="Search">
            <div class="w-full max-w-xl overflow-hidden rounded-2xl bg-white shadow-theme-xl dark:bg-gray-900" x-trap.noscroll="open">
                <div class="flex items-center gap-3 border-b border-gray-100 px-4 dark:border-gray-800">
                    <x-icon name="search" class="size-5 text-gray-400" />
                    <input x-ref="term" type="search" wire:model.live.debounce.250ms="term" placeholder="Search leads, contacts and companies…"
                           class="h-14 flex-1 border-0 bg-transparent text-theme-sm text-gray-800 placeholder:text-gray-400 focus:ring-0 focus:outline-none dark:text-white"
                           x-on:keydown.down.prevent="$focus.within($refs.results).first()" autocomplete="off" aria-label="Search">
                    <span wire:loading wire:target="term" class="size-4 animate-spin rounded-full border-2 border-gray-300 border-t-navy-700"></span>
                    <kbd class="rounded border border-gray-200 px-1.5 py-0.5 text-[11px] text-gray-400 dark:border-gray-700">Esc</kbd>
                </div>

                <div x-ref="results" class="max-h-[60vh] overflow-y-auto p-2"
                     x-on:keydown.down.prevent="$focus.wrap().next()" x-on:keydown.up.prevent="$focus.wrap().previous()">
                    @if (! $searched)
                        <p class="px-3 pt-2 pb-1 text-theme-xs font-medium tracking-wide text-gray-400 uppercase">Go to</p>
                        @foreach (array_filter([
                            ['My Day', 'sun', Route::has('admin.my-day') ? route('admin.my-day') : null],
                            ['Leads', 'users', route('admin.leads.index')],
                            ['Pipeline', 'kanban', route('admin.pipeline')],
                            ['Contacts', 'contact', Route::has('admin.contacts.index') ? route('admin.contacts.index') : null],
                            ['Tasks', 'tasks', Route::has('admin.tasks.index') ? route('admin.tasks.index') : null],
                            ['Site visits', 'calendar', Route::has('admin.visits.index') ? route('admin.visits.index') : null],
                        ], fn ($item) => $item[2] !== null) as [$label, $icon, $url])
                            <a href="{{ $url }}" wire:navigate x-on:click="open = false" class="flex items-center gap-3 rounded-lg px-3 py-2 text-theme-sm text-gray-700 hover:bg-gray-50 focus:bg-gray-100 focus:outline-none dark:text-gray-300 dark:hover:bg-white/5 dark:focus:bg-white/10">
                                <x-icon :name="$icon" class="size-4 text-gray-400" /> {{ $label }}
                            </a>
                        @endforeach
                    @else
                        @if ($leads->isNotEmpty())
                            <p class="px-3 pt-2 pb-1 text-theme-xs font-medium tracking-wide text-gray-400 uppercase">Leads</p>
                            @foreach ($leads as $lead)
                                <a href="{{ route('admin.leads.show', $lead) }}" wire:navigate x-on:click="open = false" wire:key="sl-{{ $lead->id }}"
                                   class="flex items-center gap-3 rounded-lg px-3 py-2 hover:bg-gray-50 focus:bg-gray-100 focus:outline-none dark:hover:bg-white/5 dark:focus:bg-white/10">
                                    <span class="flex size-8 items-center justify-center rounded-lg bg-navy-50 text-navy-700 dark:bg-white/10 dark:text-white"><x-icon name="users" class="size-4" /></span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-theme-sm font-medium text-gray-800 dark:text-white/90">{{ $lead->contact->name }}</span>
                                        <span class="block truncate text-theme-xs text-gray-500">{{ $lead->ref }} · {{ $lead->business_line->label() }}</span>
                                    </span>
                                    <x-admin.badge :color="$lead->stage->badgeColor()">{{ $lead->stage->name }}</x-admin.badge>
                                </a>
                            @endforeach
                        @endif
                        @if ($contacts->isNotEmpty())
                            <p class="px-3 pt-3 pb-1 text-theme-xs font-medium tracking-wide text-gray-400 uppercase">Contacts</p>
                            @foreach ($contacts as $contact)
                                <a href="{{ route('admin.contacts.show', $contact) }}" wire:navigate x-on:click="open = false" wire:key="sc-{{ $contact->id }}"
                                   class="flex items-center gap-3 rounded-lg px-3 py-2 hover:bg-gray-50 focus:bg-gray-100 focus:outline-none dark:hover:bg-white/5 dark:focus:bg-white/10">
                                    <span class="flex size-8 items-center justify-center rounded-lg bg-sand-100 text-wood-700 dark:bg-wood-500/10 dark:text-wood-300"><x-icon name="contact" class="size-4" /></span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-theme-sm font-medium text-gray-800 dark:text-white/90">{{ $contact->name }}</span>
                                        <span class="block truncate text-theme-xs text-gray-500">{{ Phone::display($contact->phone) ?: $contact->email }} · {{ $contact->leads_count }} {{ \Illuminate\Support\Str::plural('enquiry', $contact->leads_count) }}</span>
                                    </span>
                                    @if ($contact->is_client)<x-admin.badge color="success" :dot="false">Client</x-admin.badge>@endif
                                </a>
                            @endforeach
                        @endif
                        @if ($companies->isNotEmpty())
                            <p class="px-3 pt-3 pb-1 text-theme-xs font-medium tracking-wide text-gray-400 uppercase">Companies</p>
                            @foreach ($companies as $company)
                                <a href="{{ route('admin.companies.show', $company) }}" wire:navigate x-on:click="open = false" wire:key="sco-{{ $company->id }}"
                                   class="flex items-center gap-3 rounded-lg px-3 py-2 hover:bg-gray-50 focus:bg-gray-100 focus:outline-none dark:hover:bg-white/5 dark:focus:bg-white/10">
                                    <span class="flex size-8 items-center justify-center rounded-lg bg-navy-50 text-navy-700 dark:bg-white/10 dark:text-white"><x-icon name="building" class="size-4" /></span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-theme-sm font-medium text-gray-800 dark:text-white/90">{{ $company->name }}</span>
                                        <span class="block truncate text-theme-xs text-gray-500">{{ $company->typeLabel() }} · {{ $company->contacts_count }} {{ \Illuminate\Support\Str::plural('person', $company->contacts_count) }}</span>
                                    </span>
                                </a>
                            @endforeach
                        @endif
                        @if ($leads->isEmpty() && $contacts->isEmpty() && $companies->isEmpty())
                            <div class="px-3 py-10 text-center">
                                <p class="text-theme-sm text-gray-600 dark:text-gray-400">Nothing found for “{{ $term }}”.</p>
                                <p class="mt-1 text-theme-xs text-gray-400">Try a phone number in any format, part of a name, or a lead reference.</p>
                            </div>
                        @endif
                    @endif
                </div>
                <div class="flex items-center gap-4 border-t border-gray-100 px-4 py-2.5 text-[11px] text-gray-400 dark:border-gray-800">
                    <span><kbd class="font-sans">↑</kbd> <kbd class="font-sans">↓</kbd> to move</span>
                    <span><kbd class="font-sans">Enter</kbd> to open</span>
                </div>
            </div>
        </div>
    @endteleport
</div>
