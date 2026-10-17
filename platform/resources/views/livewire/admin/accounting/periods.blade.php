<div>
    <x-admin.page-header title="Accounting periods"
                         description="A closed month cannot take new postings. Corrections become reversals in the next open month, so signed books never change."
                         :breadcrumbs="['Accounting' => null, 'Periods' => null]" />

    <x-admin.card :padding="false">
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-theme-sm">
                <thead class="border-b border-gray-100 text-theme-xs tracking-wide text-gray-500 uppercase dark:border-gray-800">
                    <tr>
                        <th class="px-5 py-3 font-medium">Period</th>
                        <th class="px-5 py-3 text-right font-medium">Entries</th>
                        <th class="px-5 py-3 text-right font-medium">Debits</th>
                        <th class="px-5 py-3 font-medium">Status</th>
                        <th class="px-5 py-3"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($periods as $row)
                        @php($period = $row['period'])
                        <tr wire:key="period-{{ $period->id }}">
                            <td class="px-5 py-3">
                                <span class="block text-gray-800 dark:text-white/90">{{ $period->name }}</span>
                                <span class="block text-theme-xs text-gray-500">{{ $period->starts_on->format('j M') }} – {{ $period->ends_on->format('j M Y') }}</span>
                            </td>
                            <td class="px-5 py-3 text-right tabular-nums text-gray-600 dark:text-gray-400">{{ $period->entries_count }}</td>
                            <td class="px-5 py-3 text-right tabular-nums text-gray-800 dark:text-white/90">{{ $format($row['debit']) }}</td>
                            <td class="px-5 py-3">
                                <x-admin.badge :color="$period->status->color()">{{ $period->status->label() }}</x-admin.badge>
                            </td>
                            <td class="px-5 py-3 text-right">
                                @can('accounting.close')
                                    @if ($period->isOpen())
                                        <x-admin.button size="sm" variant="secondary" wire:click="close({{ $period->id }})" wire:confirm="Close {{ $period->name }}? No posting can land there afterwards.">Close month</x-admin.button>
                                    @else
                                        <x-admin.button size="sm" variant="ghost" wire:click="reopen({{ $period->id }})" wire:confirm="Reopen {{ $period->name }}? Only do this before the month is filed.">Reopen</x-admin.button>
                                    @endif
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-admin.card>
</div>
