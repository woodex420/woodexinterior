<div>
    <x-admin.page-header title="Automations"
                         description="What runs by itself, whether it is on, and when it last ran. Pausing is always safe — nothing queues up behind it. A toggle takes effect within a minute."
                         :breadcrumbs="['Automation' => null, 'Automations' => null]">
    </x-admin.page-header>

    <x-admin.card title="Business automations" description="The studio's flows. Switch one off and the scheduler skips it — no messages are sent, no records change." :padding="false">
        <div class="divide-y divide-gray-100 dark:divide-gray-800">
            @foreach ($automations as $automation)
                @if (! $automation['toggleable'])
                    @continue
                @endif
                <div class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between" wire:key="auto-{{ \Illuminate\Support\Str::slug($automation['key']) }}">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-medium text-gray-900 dark:text-white/90">{{ $automation['name'] }}</span>
                            <x-admin.badge :color="$automation['enabled'] ? 'success' : 'warning'">{{ $automation['enabled'] ? 'On' : 'Paused' }}</x-admin.badge>
                            <span class="text-theme-xs text-gray-500">{{ $automation['cadence'] }}</span>
                        </div>
                        <p class="mt-1 text-theme-sm text-gray-600 dark:text-gray-400">{{ $automation['description'] }}</p>
                        <p class="mt-1 text-theme-xs text-gray-500">
                            @if ($automation['last_run'] !== null)
                                Last ran {{ $automation['last_run']->diffForHumans() }}@if ($automation['last_runtime_ms']) · {{ $automation['last_runtime_ms'] }} ms @endif
                            @else
                                Not recorded yet
                            @endif
                            @if ($automation['toggled_at'] !== null)
                                · {{ $automation['enabled'] ? 'resumed' : 'paused' }} {{ $automation['toggled_at']->diffForHumans() }}
                            @endif
                        </p>
                    </div>
                    <div class="shrink-0">
                        @if ($canToggle)
                            <x-admin.button size="sm" :variant="$automation['enabled'] ? 'secondary' : 'primary'" wire:click="toggle('{{ $automation['key'] }}')" wire:loading.attr="disabled">
                                {{ $automation['enabled'] ? 'Pause' : 'Resume' }}
                            </x-admin.button>
                        @else
                            <x-admin.button size="sm" variant="secondary" disabled>
                                {{ $automation['enabled'] ? 'Pause' : 'Resume' }}
                            </x-admin.button>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </x-admin.card>

    <div class="mt-6">
        <x-admin.card title="Platform jobs" description="Keep-the-lights-on work: shown for honesty, not for switching off." :padding="false">
            <div class="divide-y divide-gray-100 dark:divide-gray-800">
                @foreach ($automations as $automation)
                    @if ($automation['toggleable'])
                        @continue
                    @endif
                    <div class="flex flex-col gap-1 px-5 py-3 sm:flex-row sm:items-center sm:justify-between" wire:key="job-{{ \Illuminate\Support\Str::slug($automation['key']) }}">
                        <div>
                            <span class="text-theme-sm font-medium text-gray-800 dark:text-white/90">{{ $automation['name'] }}</span>
                            <span class="ml-2 text-theme-xs text-gray-500">{{ $automation['cadence'] }}</span>
                            <p class="mt-0.5 text-theme-xs text-gray-500">{{ $automation['description'] }}</p>
                        </div>
                        <span class="text-theme-xs text-gray-500">
                            @if ($automation['last_run'] !== null)
                                {{ $automation['last_run']->diffForHumans() }}@if ($automation['last_runtime_ms']) · {{ $automation['last_runtime_ms'] }} ms @endif
                            @else
                                not recorded yet
                            @endif
                        </span>
                    </div>
                @endforeach
            </div>
        </x-admin.card>
    </div>

    <p class="mt-4 text-theme-xs text-gray-500">
        Run history comes from the scheduler itself and is kept for a fortnight. System → Health shows the same per-task history alongside cron, queue and backups.
    </p>
</div>
