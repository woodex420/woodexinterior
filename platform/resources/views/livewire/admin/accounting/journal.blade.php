<div>
    <x-admin.page-header title="Journal"
                         description="Every posting in one place. Posted entries are never edited: a correction is a reversal, and both stay visible."
                         :breadcrumbs="['Accounting' => null, 'Journal' => null]">
        <x-slot:actions>
            @can('accounting.manage')
                <x-admin.button icon="plus" wire:click="$set('showEntry', true)">Manual entry</x-admin.button>
            @endcan
        </x-slot:actions>
    </x-admin.page-header>

    <div class="mb-4 grid grid-cols-1 gap-3 md:grid-cols-4">
        <div class="relative md:col-span-2">
            <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-gray-400" />
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search number, memo or document" class="wx-input pl-10" aria-label="Search the journal">
        </div>
        <select wire:model.live="status" class="wx-input" aria-label="Status">
            <option value="">All entries</option>
            <option value="posted">Posted</option>
            <option value="reversed">Reversed</option>
        </select>
    </div>

    <x-admin.card :padding="false">
        <div class="overflow-x-auto" wire:loading.class="opacity-60">
            <table class="min-w-full text-left text-theme-sm">
                <thead class="border-b border-gray-100 text-theme-xs tracking-wide text-gray-500 uppercase dark:border-gray-800">
                    <tr>
                        <th class="px-5 py-3 font-medium">Entry</th>
                        <th class="hidden px-5 py-3 font-medium md:table-cell">Source</th>
                        <th class="px-5 py-3 text-right font-medium">Amount</th>
                        <th class="px-5 py-3"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($entries as $entry)
                        <tr wire:key="entry-{{ $entry->id }}" class="hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                            <td class="px-5 py-3">
                                <span class="block font-mono text-theme-xs text-gray-500">{{ $entry->number }}</span>
                                <span class="block text-gray-800 dark:text-white/90">{{ $entry->memo }}</span>
                                <span class="block text-theme-xs text-gray-500">{{ $entry->date->format('j M Y') }} · {{ $entry->lines_count }} lines
                                    @if ($entry->status->value === 'reversed') · <span class="text-error-600">reversed</span>@endif
                                </span>
                            </td>
                            <td class="hidden px-5 py-3 text-gray-600 md:table-cell dark:text-gray-400">
                                {{ $entry->source_type ? ucfirst($entry->source_type).' #'.$entry->source_id : 'Manual' }}
                                @if ($entry->source_event) <span class="block text-theme-xs text-gray-400">{{ $entry->source_event }}</span>@endif
                            </td>
                            <td class="px-5 py-3 text-right tabular-nums font-medium text-gray-800 dark:text-white/90">{{ $format($entry->debits()) }}</td>
                            <td class="px-5 py-3 text-right whitespace-nowrap">
                                <x-admin.button size="sm" variant="ghost" wire:click="view({{ $entry->id }})">View</x-admin.button>
                                @can('accounting.manage')
                                    @if ($entry->status->value === 'posted')
                                        <x-admin.button size="sm" variant="ghost" wire:click="confirmReverse({{ $entry->id }})">Reverse</x-admin.button>
                                    @endif
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4"><x-admin.empty icon="activity" title="No entries yet" description="Invoices and verified payments post here automatically; opening balances go in as a manual entry." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $entries->links('components.admin.pagination') }}
    </x-admin.card>

    @if ($showView && $viewing)
        <x-admin.modal name="showView" :title="'Journal entry '.$viewing->number" width="max-w-3xl">
            <div class="mb-3 flex flex-wrap gap-x-6 gap-y-1 text-theme-xs text-gray-500">
                <span>{{ $viewing->date->format('j M Y') }}</span>
                <span>{{ $viewing->period->name }}</span>
                <span>{{ $viewing->status->label() }}</span>
                <span>{{ $viewing->postedBy?->name ?? 'System' }}</span>
            </div>
            <p class="mb-4 text-theme-sm text-gray-700 dark:text-gray-300">{{ $viewing->memo }}</p>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-theme-sm">
                    <thead class="border-b border-gray-100 text-theme-xs tracking-wide text-gray-500 uppercase dark:border-gray-800">
                        <tr>
                            <th class="py-2 pr-3 font-medium">Account</th>
                            <th class="py-2 pr-3 text-right font-medium">Debit</th>
                            <th class="py-2 text-right font-medium">Credit</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($viewing->lines as $line)
                            <tr>
                                <td class="py-2 pr-3 text-gray-700 dark:text-gray-300">
                                    <span class="font-mono text-theme-xs text-gray-400">{{ $line->account->code }}</span>
                                    {{ $line->account->name }}
                                    @if ($line->memo)<span class="block text-theme-xs text-gray-400">{{ $line->memo }}</span>@endif
                                </td>
                                <td class="py-2 pr-3 text-right tabular-nums">{{ $line->debit_minor > 0 ? $format((int) $line->debit_minor) : '—' }}</td>
                                <td class="py-2 text-right tabular-nums">{{ $line->credit_minor > 0 ? $format((int) $line->credit_minor) : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-slot:footer>
                <x-admin.button variant="secondary" wire:click="$set('showView', false)">Close</x-admin.button>
            </x-slot:footer>
        </x-admin.modal>
    @endif

    @if ($showEntry)
        <x-admin.modal name="showEntry" title="Manual journal entry" description="Opening balances and accountant adjustments. Debits must equal credits." submit="post" width="max-w-3xl">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <x-admin.field label="Date" for="entryDate">
                    <input id="entryDate" type="date" wire:model="entryDate" class="wx-input">
                </x-admin.field>
                <x-admin.field label="Memo" for="entryMemo" class="sm:col-span-2">
                    <input id="entryMemo" wire:model="entryMemo" class="wx-input" placeholder="Opening balances at 1 January 2025">
                </x-admin.field>
            </div>

            <div class="mt-4 space-y-2">
                @foreach ($rows as $index => $row)
                    <div class="grid grid-cols-12 gap-2" wire:key="row-{{ $index }}">
                        <div class="col-span-12 sm:col-span-5">
                            <select wire:model="rows.{{ $index }}.account_id" class="wx-input" aria-label="Account">
                                <option value="">Account…</option>
                                @foreach ($accounts as $account)
                                    <option value="{{ $account->id }}">{{ $account->code }} · {{ $account->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-span-4 sm:col-span-2">
                            <input wire:model="rows.{{ $index }}.debit" class="wx-input" inputmode="decimal" placeholder="Debit" aria-label="Debit">
                        </div>
                        <div class="col-span-4 sm:col-span-2">
                            <input wire:model="rows.{{ $index }}.credit" class="wx-input" inputmode="decimal" placeholder="Credit" aria-label="Credit">
                        </div>
                        <div class="col-span-4 sm:col-span-2">
                            <input wire:model="rows.{{ $index }}.memo" class="wx-input" placeholder="Line memo" aria-label="Line memo">
                        </div>
                        <div class="col-span-12 sm:col-span-1">
                            <x-admin.button size="sm" variant="ghost" wire:click="removeRow({{ $index }})" aria-label="Remove line">×</x-admin.button>
                        </div>
                    </div>
                @endforeach
            </div>

            <x-admin.button size="sm" variant="secondary" class="mt-3" wire:click="addRow">Add line</x-admin.button>

            <x-slot:footer>
                <x-admin.button variant="secondary" wire:click="$set('showEntry', false)">Cancel</x-admin.button>
                <x-admin.button type="submit">Post entry</x-admin.button>
            </x-slot:footer>
        </x-admin.modal>
    @endif

    @if ($showReverse)
        <x-admin.modal name="showReverse" title="Reverse this entry" description="A mirror entry is posted today; the original stays in the journal." submit="reverse">
            <x-admin.field label="Reason (optional)" for="reversalReason">
                <input id="reversalReason" wire:model="reversalReason" class="wx-input" placeholder="Wrong client on the advance invoice">
            </x-admin.field>
            <x-slot:footer>
                <x-admin.button variant="secondary" wire:click="$set('showReverse', false)">Cancel</x-admin.button>
                <x-admin.button type="submit" variant="danger">Post reversal</x-admin.button>
            </x-slot:footer>
        </x-admin.modal>
    @endif
</div>
