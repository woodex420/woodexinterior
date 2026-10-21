<div>
    <x-admin.page-header title="Integrations" description="Your tools, your workspace. Manage company connections and give AI clients a controlled view of Woodex."
                         :breadcrumbs="['System' => null, 'Integrations' => null]">
        <x-slot:actions><x-admin.button icon="link" onclick="document.getElementById('real-connectors')?.scrollIntoView({behavior: 'smooth'})">Connect an app</x-admin.button></x-slot:actions>
    </x-admin.page-header>
    <x-admin.google-notice />
    @error('password')<p class="mb-4 text-theme-sm text-error-600" role="alert">{{ $message }}</p>@enderror

    <div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-gray-200 bg-white px-4 py-3 dark:border-gray-800 dark:bg-gray-900">
        <p class="flex items-center gap-2 text-theme-sm text-gray-600 dark:text-gray-300"><x-icon name="building" class="size-4" /> Company workspace <span class="text-gray-300">/</span> Owner-managed connections</p>
        <span class="flex items-center gap-2 text-theme-xs text-gray-500"><x-icon name="shield" class="size-4" /> No shared credentials in AI prompts</span>
    </div>

    @php
        $appLook = ['connected' => ['success', 'Connected'], 'check' => ['warning', 'Needs check'], 'setup' => ['warning', 'Needs setup'], 'error' => ['error', 'Error'], 'off' => ['gray', 'Not connected']];
    @endphp

    {{-- The v2.1 "Connected apps" strip: five apps, four states, each one a measurement rather than a saved flag. --}}
    <section class="mb-6" aria-labelledby="connected-apps">
        <div class="mb-3 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 id="connected-apps" class="text-theme-xl font-medium text-gray-900 dark:text-white">Connected apps</h2>
                <p class="mt-1 text-theme-xs text-gray-500">Green means a real request succeeded in the last 15 minutes. Amber means configured but unproven — press Check. Nothing on this row is a saved flag.</p>
            </div>
            <x-admin.button variant="secondary" size="sm" icon="refresh" wire:click="checkAllConnectors" wire:loading.attr="disabled">Check all now</x-admin.button>
        </div>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
            @foreach ($connectedApps as $app)
                <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                    <div class="flex items-center justify-between gap-2">
                        <span class="flex size-9 items-center justify-center rounded-lg bg-gray-50 text-gray-700 dark:bg-white/5 dark:text-gray-200"><x-icon :name="$app['icon']" class="size-4" /></span>
                        <x-admin.badge :color="$appLook[$app['state']][0]">{{ $appLook[$app['state']][1] }}</x-admin.badge>
                    </div>
                    <p class="mt-3 text-theme-sm font-medium text-gray-900 dark:text-white">{{ $app['name'] }}</p>
                    <p class="mt-1 truncate text-theme-xs text-gray-500" title="{{ $app['hint'] }}">{{ $app['hint'] }}</p>
                </div>
            @endforeach
        </div>
    </section>

    <section class="mb-8" aria-labelledby="real-connectors">
        <div class="mb-3">
            <h2 id="real-connectors" class="text-theme-xl font-medium text-gray-900 dark:text-white">Connectors you can authorise now</h2>
            <p class="mt-1 text-theme-xs text-gray-500">A token or a Google consent screen — no roadmap cards. The provider is asked before anything is stored, and again every time you press Check.</p>
        </div>
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            @foreach ($connectors as $connector)
                @php
                    $connectorState = $connector['status'] === 'error' ? 'error' : ($connector['status'] === 'connected' ? 'connected' : 'off');
                @endphp
                <div class="flex flex-col rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900" wire:key="connector-{{ $connector['key'] }}">
                    <div class="flex items-start justify-between gap-3">
                        <span class="flex size-11 items-center justify-center rounded-xl border border-gray-200 bg-gray-50 text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"><x-icon :name="$connector['icon']" class="size-5" /></span>
                        <x-admin.badge :color="$appLook[$connectorState][0]">{{ $appLook[$connectorState][1] }}</x-admin.badge>
                    </div>
                    <h3 class="mt-4 text-theme-lg font-medium text-gray-900 dark:text-white">{{ $connector['name'] }}</h3>
                    <p class="mt-1 flex-1 text-theme-sm text-gray-500 dark:text-gray-400">{{ $connector['detail'] }}</p>

                    @if ($connector['account'])
                        <p class="mt-3 text-theme-xs text-gray-600 dark:text-gray-300"><span class="font-medium">{{ $connector['account'] }}</span>
                            @if ($connector['last_checked'])<span class="text-gray-400"> · checked {{ $connector['last_checked']->diffForHumans() }}</span>@endif
                        </p>
                    @elseif ($connector['missing'])
                        <p class="mt-3 rounded-lg bg-warning-50 px-3 py-2 text-theme-xs text-warning-700 dark:bg-warning-500/10 dark:text-warning-400">{{ $connector['missing'] }}</p>
                    @else
                        <p class="mt-3 text-theme-xs text-gray-400">{{ $connector['mode'] === 'oauth' ? 'Google consent, then a real token check.' : 'Paste a credential; it is proved before it is saved.' }}</p>
                    @endif

                    @if ($connector['last_error'])
                        <p class="mt-2 text-theme-xs text-error-600" role="alert">{{ $connector['last_error'] }}</p>
                    @endif

                    @if ($connector['scopes'])
                        <div class="mt-3 flex flex-wrap gap-1">
                            @foreach (array_slice($connector['scopes'], 0, 4) as $scope)
                                <span class="rounded bg-gray-100 px-1.5 py-0.5 text-[11px] text-gray-600 dark:bg-white/5 dark:text-gray-300">{{ \Illuminate\Support\Str::afterLast($scope, '/') }}</span>
                            @endforeach
                            @if (count($connector['scopes']) > 4)<span class="text-[11px] text-gray-400">+{{ count($connector['scopes']) - 4 }}</span>@endif
                        </div>
                    @endif

                    <div class="mt-4 flex flex-wrap gap-2 border-t border-gray-100 pt-4 dark:border-gray-800">
                        @if ($connector['missing'] === null)
                            <x-admin.button size="sm" icon="link" wire:click="openConnector('{{ $connector['key'] }}')">{{ $connector['status'] ? 'Reconnect' : 'Connect' }}</x-admin.button>
                        @endif
                        @if ($connector['status'])
                            <x-admin.button variant="secondary" size="sm" icon="refresh" wire:click="checkConnector('{{ $connector['key'] }}')" wire:loading.attr="disabled">Check now</x-admin.button>
                            <x-admin.button variant="ghost" size="sm" wire:click="disconnectConnector('{{ $connector['key'] }}')" wire:confirm="Disconnect {{ $connector['name'] }}? The stored credential is deleted.">Disconnect</x-admin.button>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    <section id="mcp-bridge" class="mb-8 overflow-hidden rounded-2xl border border-navy-800 bg-navy-900 text-white">
        <div class="flex flex-col gap-6 p-6 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex max-w-2xl items-start gap-4">
                <span class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-white/10"><x-icon name="command" class="size-6" /></span>
                <div><p class="mb-2 text-theme-xs font-medium tracking-widest text-navy-200 uppercase">AI & agents · remote MCP</p>
                    <h2 class="font-heading text-2xl">One URL and a key. Any MCP client.</h2>
                    <p class="mt-2 text-theme-sm leading-6 text-navy-100">Claude, Cursor, Codex and any MCP client connect straight to this site over HTTPS — no local adapter. Read pages, site health, leads and quotes with an expiring key and no write access.</p>
                    <div class="mt-4 flex flex-wrap gap-x-5 gap-y-2 text-theme-xs text-navy-200"><span>✓ Remote endpoint</span><span>✓ Revocable keys</span><span>✓ Read-only</span></div>
                </div>
            </div>
            <button type="button" wire:click="openMcpSetup" class="inline-flex h-10 shrink-0 items-center justify-center gap-2 rounded-lg bg-white px-4 text-theme-sm font-medium text-navy-900 hover:bg-navy-50"><x-icon name="key" class="size-4" /> Set up MCP bridge</button>
        </div>
        <div class="border-t border-white/10 px-6 py-3 text-theme-xs text-navy-200">Endpoint <code class="font-mono text-navy-100">{{ url('/api/mcp') }}</code> · Bearer key · JSON-RPC over HTTPS. The optional stdio adapter still works for clients that need it.</div>
    </section>

    @if ($showMcpSetup)
        @include('livewire.admin.system.mcp-setup')
    @endif

    <div class="mb-5 flex flex-col justify-between gap-4 lg:flex-row lg:items-center">
        <div><h2 class="text-theme-xl font-medium text-gray-900 dark:text-white">Other connections</h2><p class="mt-1 text-theme-xs text-gray-500">Channels that already work, the Google identity link, and services that still need an app registration. “Configured” is not a delivery check.</p></div>
        <div class="relative w-full lg:w-72"><label class="sr-only" for="app-search">Search integrations</label><input id="app-search" type="search" wire:model.live.debounce.300ms="appSearch" class="wx-input w-full" placeholder="Search apps and services…"></div>
    </div>
    <nav class="mb-6 flex flex-wrap gap-2" aria-label="Integration categories">
        @foreach (['all' => 'All apps', 'workspace' => 'Workspace', 'communication' => 'Communication', 'social' => 'Social media', 'system' => 'System'] as $key => $label)
            <button type="button" wire:click="$set('appCategory', '{{ $key }}')" aria-pressed="{{ $appCategory === $key ? 'true' : 'false' }}" @class(['rounded-lg px-4 py-2 text-theme-sm font-medium transition', 'bg-navy-900 text-white dark:bg-white dark:text-navy-900' => $appCategory === $key, 'border border-gray-200 bg-white text-gray-600 hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300' => $appCategory !== $key])>{{ $label }}</button>
        @endforeach
    </nav>

    <div class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($cards as $card)
            <x-admin.card wire:key="app-{{ $card['key'] }}" :padding="false" class="flex flex-col">
                <div class="flex h-full flex-col p-5">
                    <div class="mb-4 flex items-start justify-between gap-3">
                        <span @class(['flex size-12 items-center justify-center rounded-xl border', 'border-navy-100 bg-navy-50 text-navy-700 dark:border-navy-700 dark:bg-navy-800 dark:text-navy-100' => $card['phase'] === null, 'border-gray-200 bg-gray-50 text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300' => $card['phase'] !== null])><x-icon :name="$card['icon']" class="size-6" /></span>
                        @if ($card['phase'])
                            <x-admin.badge color="gray" :dot="false">Planned</x-admin.badge>
                        @elseif ($card['key'] === 'google_account')
                            <x-admin.badge :color="$googleConnection?->status === 'active' ? 'success' : 'warning'">{{ $googleConnection?->status === 'active' ? 'Connected · identity only' : ($googleConnection ? 'Action required' : ($googleConfigured ? 'Not connected' : 'Setup required')) }}</x-admin.badge>
                        @elseif ($card['configured'])
                            <x-admin.badge color="success">Configured</x-admin.badge>
                        @else
                            <x-admin.badge color="warning">Needs setup</x-admin.badge>
                        @endif
                    </div>
                    <h3 class="text-theme-xl font-medium text-gray-900 dark:text-white">{{ $card['name'] }}</h3>
                    <p class="mt-2 flex-1 text-theme-sm leading-6 text-gray-500 dark:text-gray-400">{{ $card['detail'] }}</p>
                    @if ($card['phase'] === null)
                        <dl class="mt-5 grid grid-cols-2 gap-3 border-t border-gray-100 pt-4 text-theme-xs dark:border-gray-800">
                            <div><dt class="text-gray-500">Last success</dt><dd class="mt-1 text-gray-700 dark:text-gray-300">{{ $card['last_ok']?->diffForHumans() ?? 'No activity' }}</dd></div>
                            <div><dt class="text-gray-500">Last error</dt><dd class="mt-1 text-gray-700 dark:text-gray-300">{{ $card['last_error']?->diffForHumans() ?? 'None recorded' }}</dd></div>
                        </dl>
                    @else
                        <p class="mt-4 rounded-lg bg-gray-50 px-3 py-2 text-theme-xs leading-5 text-gray-500 dark:bg-white/5 dark:text-gray-400">{{ $card['requirements'] ?? 'This service needs an app registration that is not in place yet.' }}</p>
                    @endif
                    @if ($card['phase'] === null)
                    <div class="mt-5 flex flex-wrap gap-2">
                        @if ($card['key'] === 'google_account')
                            <x-admin.button size="sm" icon="link" wire:click="openGoogleSetup">{{ $googleConnection ? 'Manage connection' : 'Connect Google' }}</x-admin.button>
                            <x-admin.button variant="ghost" size="sm" :href="route('admin.profile.security')" wire:navigate>Set up staff sign-in</x-admin.button>
                        @elseif ($card['key'] === 'telegram')
                            <x-admin.button size="sm" icon="link" wire:click="setTelegramWebhook" wire:loading.attr="disabled">Connect webhook</x-admin.button>
                            <x-admin.button variant="secondary" size="sm" wire:click="checkTelegramConnection" wire:loading.attr="disabled">Check live connection</x-admin.button>
                            <x-admin.button variant="secondary" size="sm" icon="send" wire:click="testTelegram">Send me a test</x-admin.button>
                            <x-admin.button variant="ghost" size="sm" :href="route('admin.profile.security')" wire:navigate>Link your account</x-admin.button>
                            @if (auth()->user()->hasRole('owner'))
                                <x-admin.button variant="ghost" size="sm" wire:click="checkTelegramConfiguration">Check server configuration</x-admin.button>
                            @endif
                        @elseif ($card['key'] === 'whatsapp')
                            <x-admin.button variant="secondary" size="sm" icon="settings" :href="route('admin.settings', ['tab' => 'whatsapp'])" wire:navigate>Set up</x-admin.button>
                            <x-admin.button variant="secondary" size="sm" icon="send" wire:click="testWhatsApp" wire:loading.attr="disabled">Test connection</x-admin.button>
                            <x-admin.button variant="secondary" size="sm" icon="message" :href="route('admin.whatsapp.templates')" wire:navigate>Templates</x-admin.button>
                        @elseif ($card['key'] === 'mail')
                            <x-admin.button variant="secondary" size="sm" icon="mail" wire:click="testMail">Send me a test email</x-admin.button>
                        @elseif ($card['key'] === 'legacy')
                            <x-admin.button variant="secondary" size="sm" icon="database" wire:click="testLegacy">Test connection</x-admin.button>
                            @can('system.import')<x-admin.button variant="ghost" size="sm" :href="route('admin.data-import')" wire:navigate>Data import</x-admin.button>@endcan
                        @else
                            <x-admin.button variant="secondary" size="sm" :href="route('admin.system.health')" wire:navigate>System health</x-admin.button>
                        @endif
                    </div>
                    @endif
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
                    @include('livewire.admin.system.telegram-diagnostics')
                </div>
            </x-admin.card>
        @empty
            <div class="rounded-xl border border-dashed border-gray-300 p-8 text-theme-sm text-gray-500 md:col-span-2 xl:col-span-3">No integrations match your search. Try another app name or category.</div>
        @endforelse
    </div>

    @include('livewire.admin.system.google-setup')
    @include('livewire.admin.system.integration-log')

    @if ($showConnectorModal && $connectorForm)
        <x-admin.modal name="showConnectorModal" :title="'Connect '.$connectorForm['name']" description="The provider is asked first; nothing is stored until it answers.">
            <form wire:submit="connectConnector" class="space-y-4">
                @foreach ($connectorForm['fields'] as $field)
                    <x-admin.field :label="$field['label']" :for="'connector-'.$field['name']">
                        <input id="connector-{{ $field['name'] }}" type="{{ $field['type'] }}" wire:model="connectorInput.{{ $field['name'] }}"
                               placeholder="{{ $field['placeholder'] }}" @if ($field['type'] !== 'password') autocomplete="off" @else autocomplete="new-password" @endif
                               class="wx-input w-full" @if ($field['required']) required @endif>
                    </x-admin.field>
                @endforeach
                @error('connectorInput')<p class="text-theme-xs text-error-600" role="alert">{{ $message }}</p>@enderror
                <p class="text-theme-xs text-gray-500">Credentials are encrypted with the application key and never shown again. Disconnect deletes the stored copy.</p>
                <div class="flex justify-end gap-3 pt-2">
                    <x-admin.button type="button" variant="secondary" wire:click="$set('showConnectorModal', false)">Cancel</x-admin.button>
                    <x-admin.button type="submit" icon="link" wire:loading.attr="disabled">Test and connect</x-admin.button>
                </div>
            </form>
        </x-admin.modal>
    @endif
</div>
