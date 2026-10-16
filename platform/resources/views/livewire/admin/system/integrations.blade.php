<div>
    <x-admin.page-header title="Integrations" description="Connection status for every outside service. Keys and passwords are set in the server .env and are never shown here."
                         :breadcrumbs="['System' => null, 'Integrations' => null]" />

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($cards as $card)
            <x-admin.card wire:key="card-{{ $card['key'] }}" @class(['opacity-70' => $card['phase'] !== null])>
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <span class="flex size-10 items-center justify-center rounded-xl bg-navy-50 text-navy-700 dark:bg-white/10 dark:text-white"><x-icon :name="$card['icon']" class="size-5" /></span>
                        <div>
                            <p class="font-medium text-gray-800 dark:text-white/90">{{ $card['name'] }}</p>
                            <p class="text-theme-xs text-gray-500">{{ $card['detail'] }}</p>
                        </div>
                    </div>
                    @if ($card['phase'])
                        <x-admin.badge color="gray">{{ $card['phase'] }}</x-admin.badge>
                    @elseif ($card['configured'])
                        <x-admin.badge color="success">{{ $card['key'] === 'telegram' ? 'Configured' : 'Connected' }}</x-admin.badge>
                    @else
                        <x-admin.badge color="warning">Not set up</x-admin.badge>
                    @endif
                </div>
                @if ($card['phase'] === null)
                    <dl class="mt-4 grid grid-cols-2 gap-2 text-theme-xs">
                        <div><dt class="text-gray-500">Last success</dt><dd class="text-gray-800 dark:text-white/90">{{ $card['last_ok']?->diffForHumans() ?? '—' }}</dd></div>
                        <div><dt class="text-gray-500">Last error</dt><dd @class(['text-error-600' => $card['last_error'], 'text-gray-800 dark:text-white/90' => ! $card['last_error']])>{{ $card['last_error']?->diffForHumans() ?? '—' }}</dd></div>
                    </dl>
                    <div class="mt-4 flex flex-wrap gap-2">
                        @if ($card['key'] === 'telegram')
                            <x-admin.button variant="secondary" size="sm" icon="send" wire:click="testTelegram">Send me a test</x-admin.button>
                            <x-admin.button variant="secondary" size="sm" icon="link" wire:click="setTelegramWebhook">Connect webhook</x-admin.button>
                            <x-admin.button variant="secondary" size="sm" wire:click="checkTelegramConnection" wire:loading.attr="disabled">Check live connection</x-admin.button>
                        @elseif ($card['key'] === 'mail')
                            <x-admin.button variant="secondary" size="sm" icon="mail" wire:click="testMail">Send me a test email</x-admin.button>
                        @elseif ($card['key'] === 'whatsapp')
                            <x-admin.button variant="secondary" size="sm" icon="settings" :href="route('admin.settings', ['tab' => 'whatsapp'])" wire:navigate>Set up</x-admin.button>
                            <x-admin.button variant="secondary" size="sm" icon="send" wire:click="testWhatsApp" wire:loading.attr="disabled">Test connection</x-admin.button>
                            <x-admin.button variant="secondary" size="sm" icon="message" :href="route('admin.whatsapp.templates')" wire:navigate>Templates</x-admin.button>
                        @elseif ($card['key'] === 'legacy')
                            <x-admin.button variant="secondary" size="sm" icon="database" wire:click="testLegacy">Test connection</x-admin.button>
                            @can('system.import')
                                <x-admin.button variant="secondary" size="sm" icon="upload" :href="route('admin.data-import')" wire:navigate>Data import</x-admin.button>
                            @endcan
                        @endif
                    </div>
                    @if ($card['key'] === 'whatsapp' && $whatsappStatus !== null)
                        <div class="mt-4 text-theme-xs text-gray-600 dark:text-gray-300" role="status">
                            <p class="@if ($whatsappStatus['ok']) text-success-600 @else text-error-600 @endif font-medium">{{ $whatsappStatus['ok'] ? 'Connected' : 'Not connected' }}</p>
                            @if ($whatsappStatus['phone'] || $whatsappStatus['name'])
                                <p>{{ $whatsappStatus['phone'] }}@if ($whatsappStatus['name']) · {{ $whatsappStatus['name'] }}@endif</p>
                            @endif
                            @if ($whatsappStatus['quality'])
                                <p>Quality rating: {{ \Illuminate\Support\Str::headline($whatsappStatus['quality']) }}</p>
                            @endif
                            @if ($whatsappStatus['error'])
                                <p class="text-error-600">{{ $whatsappStatus['error'] }}</p>
                            @endif
                            <p>Add a number in Settings → WhatsApp to send one real message and prove delivery.</p>
                        </div>
                    @endif
                    @if ($card['key'] === 'telegram' && $telegramStatus !== null)
                        <div class="mt-4 text-theme-xs text-gray-600 dark:text-gray-300" role="status">
                            <p>Telegram API: {{ $telegramStatus['api_ok'] ? 'reachable' : 'failed — check token and outbound HTTPS in the log below' }}</p>
                            <p>Bot: {{ $telegramStatus['bot_username'] ? '@'.$telegramStatus['bot_username'] : 'unavailable' }} · {{ $telegramStatus['bot_matches'] ? 'correct bot' : 'MISMATCH — check the token belongs to WoodexInteriorBot' }}</p>
                            <p>Webhook: {{ ! $telegramStatus['webhook_set'] ? 'not registered' : ($telegramStatus['webhook_matches'] ? 'matches this site' : 'MISMATCH — press Connect webhook on this site') }}</p>
                            <p>Pending updates: {{ $telegramStatus['pending_updates'] }}</p>
                            <p>Last delivery error: {{ $telegramStatus['last_error'] ?: 'none reported' }}{{ $telegramStatus['last_error_at'] ? ' (Unix time '.$telegramStatus['last_error_at'].')' : '' }}</p>
                            <p>Last received here: {{ $telegramStatus['last_received'] ?? 'none' }}</p>
                            <p>Last processed here: {{ $telegramStatus['last_processed'] ?? 'none' }}</p>
                            <p>Send /start to the bot, wait a few seconds, then check again. Registration alone does not prove delivery. A delivery error may be historical; compare its time with your latest message.</p>
                        </div>
                    @endif
                @endif
            </x-admin.card>
        @endforeach
    </div>

    <x-admin.card title="Recent outbound calls" description="Request metadata only: no secrets, no message text." :padding="false" class="mt-6">
        <div class="flex flex-wrap items-center gap-3 border-b border-gray-100 px-5 py-3 dark:border-gray-800">
            <select wire:model.live="logIntegration" class="wx-input h-9 w-auto text-theme-sm" aria-label="Integration">
                <option value="">All integrations</option>
                @foreach ($integrations as $name)<option value="{{ $name }}">{{ \Illuminate\Support\Str::headline($name) }}</option>@endforeach
            </select>
            <label class="flex items-center gap-2 text-theme-sm text-gray-700 dark:text-gray-300"><input type="checkbox" wire:model.live="failedOnly" class="rounded border-gray-300"> Failed only</label>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-theme-sm">
                <thead class="border-b border-gray-100 text-theme-xs tracking-wide text-gray-500 uppercase dark:border-gray-800">
                    <tr><th class="px-5 py-3 font-medium">When</th><th class="px-5 py-3 font-medium">Integration</th><th class="px-5 py-3 font-medium">Action</th><th class="px-5 py-3 font-medium">Result</th><th class="px-5 py-3 text-right font-medium">Time</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($logs as $log)
                        <tr wire:key="log-{{ $log->id }}">
                            <td class="px-5 py-2.5 whitespace-nowrap text-gray-600 dark:text-gray-400" title="{{ $log->created_at?->format('j M Y, g:i:s a') }}">{{ $log->created_at?->diffForHumans() }}</td>
                            <td class="px-5 py-2.5">{{ \Illuminate\Support\Str::headline($log->integration) }}</td>
                            <td class="px-5 py-2.5 font-mono text-theme-xs">{{ $log->action }}</td>
                            <td class="px-5 py-2.5">
                                @if ($log->ok)
                                    <x-admin.badge color="success">OK{{ $log->status_code ? ' · '.$log->status_code : '' }}</x-admin.badge>
                                @else
                                    <x-admin.badge color="error">Failed{{ $log->status_code ? ' · '.$log->status_code : '' }}</x-admin.badge>
                                    <span class="mt-0.5 block max-w-md truncate text-theme-xs text-gray-500">{{ $log->error ?? ($log->response['description'] ?? '') }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-2.5 text-right text-theme-xs text-gray-500 tabular-nums">{{ $log->duration_ms !== null ? $log->duration_ms.' ms' : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-8 text-center text-theme-sm text-gray-500">No outbound calls logged yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $logs->links('components.admin.pagination') }}
    </x-admin.card>
</div>
