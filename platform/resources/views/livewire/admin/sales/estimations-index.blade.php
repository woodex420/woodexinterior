<div>
    <x-admin.page-header title="Estimations" description="Estimates for every service line — the same server-side pricing engine the website estimator uses, frozen into a versioned document. One click turns an estimate into a quotation draft; the estimate itself never reaches a client."
                         :breadcrumbs="['Sales' => null, 'Estimations' => null]">
        <x-slot:actions>
            @can('estimates.manage')
                <a href="{{ route('admin.estimations.create') }}"><x-admin.button icon="plus">New estimate</x-admin.button></a>
            @endcan
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.card title="Estimates">
        <div class="mb-4 flex flex-wrap gap-2">
            <select wire:model.live="status" class="wx-input max-w-48">
                <option value="">All statuses</option>
                @foreach ($statuses as $choice)
                    <option value="{{ $choice->value }}">{{ ucfirst($choice->value) }}</option>
                @endforeach
            </select>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-theme-sm divide-y divide-gray-100 dark:divide-gray-800">
                <thead>
                    <tr>
                        <th class="px-3 py-2.5 font-medium text-gray-500 dark:text-gray-400">Estimate</th>
                        <th class="px-3 py-2.5 font-medium text-gray-500 dark:text-gray-400">Service</th>
                        <th class="px-3 py-2.5 font-medium text-gray-500 dark:text-gray-400">Client</th>
                        <th class="px-3 py-2.5 font-medium text-gray-500 dark:text-gray-400 text-right">Range</th>
                        <th class="px-3 py-2.5 font-medium text-gray-500 dark:text-gray-400 text-right">Total</th>
                        <th class="px-3 py-2.5 font-medium text-gray-500 dark:text-gray-400">Status</th>
                        <th class="px-3 py-2.5 font-medium text-gray-500 dark:text-gray-400">Quote</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($estimates as $estimate)
                        <tr wire:key="estimate-{{ $estimate->id }}">
                            <td class="px-3 py-2.5">
                                <a href="{{ route('admin.estimations.show', $estimate) }}" class="font-medium text-brand-600 hover:underline dark:text-brand-400">{{ $estimate->number }}</a>
                                <p class="text-gray-500 dark:text-gray-400">{{ $estimate->title }}</p>
                            </td>
                            <td class="px-3 py-2.5">{{ $estimate->service_key }}</td>
                            <td class="px-3 py-2.5">{{ $estimate->contact?->name ?? '—' }}</td>
                            <td class="px-3 py-2.5 text-right">
                                {{ \App\Support\Money::ofMinor((int) $estimate->computed['range_low_minor'] ?? 0)->format() }} –
                                {{ \App\Support\Money::ofMinor((int) $estimate->computed['range_high_minor'] ?? 0)->format() }}
                            </td>
                            <td class="px-3 py-2.5 text-right font-medium">{{ \App\Support\Money::ofMinor((int) $estimate->computed['total_minor'] ?? 0)->format() }}</td>
                            <td class="px-3 py-2.5">
                                @if ($estimate->status === App\Domain\Sales\Enums\EstimateStatus::Draft)
                                    <x-admin.badge color="warning">Draft</x-admin.badge>
                                @elseif ($estimate->status === App\Domain\Sales\Enums\EstimateStatus::Reviewed)
                                    <x-admin.badge color="info">Reviewed</x-admin.badge>
                                @else
                                    <x-admin.badge color="success">Converted</x-admin.badge>
                                @endif
                            </td>
                            <td class="px-3 py-2.5">
                                @if ($estimate->quote !== null)
                                    <a href="{{ route('admin.quotes.show', $estimate->quote) }}" class="text-brand-600 hover:underline dark:text-brand-400">{{ $estimate->quote->number }}</a>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-3 py-8 text-center text-gray-500 dark:text-gray-400">No estimates yet — start one for a website enquiry or a phone call.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-admin.card>
</div>
