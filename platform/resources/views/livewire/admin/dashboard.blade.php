<div wire:poll.60s.visible>
    <x-admin.page-header :title="$greeting"
                         description="Live overview of enquiries, pipeline and cash. Updates every minute.">
        <x-slot:actions>
            <x-admin.button variant="secondary" icon="kanban" :href="route('admin.leads.index', ['tab' => 'pipeline'])" wire:navigate>Pipeline</x-admin.button>
            <x-admin.button icon="plus" :href="route('admin.leads.index')" wire:navigate>New lead</x-admin.button>
        </x-slot:actions>
    </x-admin.page-header>

    {{-- KPI cards (Preline "KPI Cards" pattern) --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-admin.stat label="New leads today" :value="$stats['new_today']" icon="user-plus" tone="navy" :hint="$stats['new_week'].' this week'" :href="route('admin.leads.index')" />
        <x-admin.stat label="Open pipeline" :value="$stats['open_value']" icon="trending-up" tone="wood" :hint="$stats['open_count'].' open leads'" :href="route('admin.leads.index', ['tab' => 'pipeline'])" />
        <x-admin.stat label="SLA breaches" :value="$stats['sla_breached']" icon="alert" :tone="$stats['sla_breached'] > 0 ? 'error' : 'success'" hint="Not contacted in time" />
        <x-admin.stat label="Collected this month" :value="$stats['collected']" icon="banknote" tone="success" :hint="$stats['quotes_sent'].' quotes sent · '.$stats['accepted_value'].' accepted'" />
    </div>

    <div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-3">
        <x-admin.card title="New leads, last 14 days" class="min-w-0 xl:col-span-2">
            <div wire:ignore data-chart='@json($chart)' class="-mx-2 min-h-[240px] min-w-0"></div>
        </x-admin.card>

        <x-admin.card title="Pipeline" description="Leads per stage">
            @php($max = max(1, (int) $funnel->max('leads_count')))
            <ul class="space-y-3">
                @foreach ($funnel as $stage)
                    <li>
                        <div class="mb-1 flex justify-between text-theme-sm">
                            <span class="text-gray-600 dark:text-gray-300">{{ $stage->name }}</span>
                            <span class="font-medium text-gray-800 dark:text-white/90">{{ $stage->leads_count }}</span>
                        </div>
                        <div class="h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-white/5">
                            <div @class([
                                'h-full rounded-full',
                                'bg-success-500' => $stage->type === \App\Domain\Crm\Enums\StageType::Won,
                                'bg-error-500' => $stage->type === \App\Domain\Crm\Enums\StageType::Lost,
                                'bg-navy-600' => $stage->type === \App\Domain\Crm\Enums\StageType::Open,
                            ]) style="width: {{ round($stage->leads_count / $max * 100) }}%"></div>
                        </div>
                    </li>
                @endforeach
            </ul>
        </x-admin.card>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-3">
        <x-admin.card title="Needs attention" description="Overdue follow-ups and SLA breaches" class="xl:col-span-2" :padding="false">
            @forelse ($attention as $lead)
                <div class="flex flex-wrap items-center gap-3 border-b border-gray-100 px-5 py-3 last:border-0 dark:border-gray-800">
                    <div class="min-w-0 flex-1">
                        <a href="{{ route('admin.leads.show', $lead) }}" wire:navigate class="block truncate font-medium text-gray-800 hover:underline dark:text-white/90">{{ $lead->contact->name }}
                            <span class="ml-1 text-theme-xs font-normal text-gray-400">{{ $lead->ref }}</span></a>
                        <p class="text-theme-xs text-gray-500">{{ $lead->business_line->label() }}{{ $lead->service ? ' · '.\Illuminate\Support\Str::headline($lead->service) : '' }} · {{ $lead->owner?->name ?? 'Unassigned' }}</p>
                    </div>
                    @if ($lead->isSlaBreached())
                        <x-admin.badge color="error">SLA breached</x-admin.badge>
                    @else
                        <x-admin.badge color="warning">Due {{ $lead->next_action_at?->diffForHumans() }}</x-admin.badge>
                    @endif
                    @if ($wa = \App\Support\Phone::whatsappUrl($lead->contact->whatsappNumber()))
                        <a href="{{ $wa }}" target="_blank" rel="noopener" class="flex size-8 items-center justify-center rounded-lg border border-gray-200 text-success-600 hover:bg-success-50 dark:border-gray-800" title="WhatsApp">
                            <x-icon name="whatsapp" class="size-4" />
                        </a>
                    @endif
                    @if ($lead->contact->phone)
                        <a href="tel:{{ $lead->contact->phone }}" class="flex size-8 items-center justify-center rounded-lg border border-gray-200 text-navy-700 hover:bg-navy-50 dark:border-gray-800 dark:text-gray-300" title="Call">
                            <x-icon name="phone" class="size-4" />
                        </a>
                    @endif
                </div>
            @empty
                <x-admin.empty icon="check-circle" title="Nothing overdue" description="Every lead has a follow-up on time. Nice work." />
            @endforelse
        </x-admin.card>

        <x-admin.card title="Go-live checklist" description="Turns green as setup completes">
            <ul class="space-y-3">
                @foreach ($checklist as $item)
                    <li class="flex items-start gap-3">
                        <span @class([
                            'mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full',
                            'bg-success-500 text-white' => $item['done'],
                            'border border-gray-300 text-transparent dark:border-gray-700' => ! $item['done'],
                        ])><x-icon name="check-circle" class="size-3.5" /></span>
                        <span>
                            <span class="block text-theme-sm text-gray-700 dark:text-gray-300">{{ $item['label'] }}</span>
                            @unless ($item['done'])
                                <span class="block text-theme-xs text-gray-400">{{ $item['hint'] }}</span>
                            @endunless
                        </span>
                    </li>
                @endforeach
            </ul>
        </x-admin.card>
    </div>

    <x-admin.card title="Recent activity" class="mt-6" :padding="false">
        @forelse ($recent as $activity)
            <div class="flex gap-3 border-b border-gray-100 px-5 py-3 last:border-0 dark:border-gray-800">
                <span class="mt-1 size-2 shrink-0 rounded-full bg-wood-500"></span>
                <div class="min-w-0">
                    <p class="text-theme-sm text-gray-700 dark:text-gray-300">
                        <span class="font-medium text-gray-900 dark:text-white">{{ $activity->contact?->name ?? 'System' }}</span>
                        · {{ \Illuminate\Support\Str::limit((string) $activity->body, 140) }}
                    </p>
                    <p class="text-theme-xs text-gray-400">{{ $activity->occurred_at?->diffForHumans() }}{{ $activity->user ? ' · '.$activity->user->name : '' }}</p>
                </div>
            </div>
        @empty
            <x-admin.empty icon="activity" title="No activity yet" description="New enquiries from the website, WhatsApp and the team will appear here." />
        @endforelse
    </x-admin.card>
</div>
