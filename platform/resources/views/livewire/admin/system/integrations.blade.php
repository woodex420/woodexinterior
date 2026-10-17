<div>
    <x-admin.page-header title="Integrations" description="Your tools, your workspace. Manage company connections and give AI clients a controlled view of Woodex."
                         :breadcrumbs="['System' => null, 'Integrations' => null]">
        <x-slot:actions><x-admin.button icon="link" wire:click="openGoogleSetup">Connect an app</x-admin.button></x-slot:actions>
    </x-admin.page-header>
    <x-admin.google-notice />
    @error('password')<p class="mb-4 text-theme-sm text-error-600" role="alert">{{ $message }}</p>@enderror

    <div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-gray-200 bg-white px-4 py-3 dark:border-gray-800 dark:bg-gray-900">
        <p class="flex items-center gap-2 text-theme-sm text-gray-600 dark:text-gray-300"><x-icon name="building" class="size-4" /> Company workspace <span class="text-gray-300">/</span> Owner-managed connections</p>
        <span class="flex items-center gap-2 text-theme-xs text-gray-500"><x-icon name="shield" class="size-4" /> No shared credentials in AI prompts</span>
    </div>

    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-admin.card><p class="text-theme-xs text-gray-500">Available integrations</p><p class="mt-1 font-heading text-title-sm text-gray-900 dark:text-white">{{ $availableCount + 1 }} <span class="font-sans text-theme-xs font-normal text-gray-500">including the MCP bridge</span></p></x-admin.card>
        <x-admin.card><p class="text-theme-xs text-gray-500">Active bridge keys</p><p class="mt-1 font-heading text-title-sm text-gray-900 dark:text-white">{{ $activeBridgeCount }} <span class="font-sans text-theme-xs font-normal text-gray-500">read-only access</span></p></x-admin.card>
        <x-admin.card><p class="text-theme-xs text-gray-500">On the roadmap</p><p class="mt-1 font-heading text-title-sm text-gray-900 dark:text-white">{{ $plannedCount }} <span class="font-sans text-theme-xs font-normal text-gray-500">not connected yet</span></p></x-admin.card>
    </div>

    <section id="mcp-bridge" class="mb-8 overflow-hidden rounded-2xl border border-navy-800 bg-navy-900 text-white">
        <div class="flex flex-col gap-6 p-6 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex max-w-2xl items-start gap-4">
                <span class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-white/10"><x-icon name="command" class="size-6" /></span>
                <div><p class="mb-2 text-theme-xs font-medium tracking-widest text-navy-200 uppercase">AI & local tools</p>
                    <h2 class="font-heading text-2xl">One bridge. Your choice of AI client.</h2>
                    <p class="mt-2 text-theme-sm leading-6 text-navy-100">Connect Claude Code, Codex or Cursor through a local MCP adapter. Read Woodex status, leads and quotes—with expiring keys and no write access.</p>
                    <div class="mt-4 flex flex-wrap gap-x-5 gap-y-2 text-theme-xs text-navy-200"><span>✓ Read-only tools</span><span>✓ Revocable keys</span><span>✓ No server daemon</span></div>
                </div>
            </div>
            <button type="button" wire:click="openMcpSetup" class="inline-flex h-10 shrink-0 items-center justify-center gap-2 rounded-lg bg-white px-4 text-theme-sm font-medium text-navy-900 hover:bg-navy-50"><x-icon name="key" class="size-4" /> Set up MCP bridge</button>
        </div>
        <div class="border-t border-white/10 px-6 py-3 text-theme-xs text-navy-200">Runs locally on Node.js 22+ → authenticated HTTPS requests → Woodex on Hostinger. Hermes support depends on the exact client's stdio MCP capability.</div>
    </section>

    @if ($showMcpSetup)
        @include('livewire.admin.system.mcp-setup')
    @endif

    <div class="mb-5 flex flex-col justify-between gap-4 lg:flex-row lg:items-center">
        <div><h2 class="text-theme-xl font-medium text-gray-900 dark:text-white">Explore integrations</h2><p class="mt-1 text-theme-xs text-gray-500">Available tools and upcoming connections. “Configured” is not a delivery check.</p></div>
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
                        <p class="mt-4 text-theme-xs text-gray-400">Connection flow is not implemented in this release.</p>
                    @endif
                    <div class="mt-5 flex flex-wrap gap-2">
                        @if ($card['phase'])
                            <x-admin.button variant="secondary" size="sm" wire:click="openIntegrationDetails('{{ $card['key'] }}')">View requirements</x-admin.button>
                        @elseif ($card['key'] === 'google_account')
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
                        @elseif ($card['key'] === 'mail')
                            <x-admin.button variant="secondary" size="sm" icon="mail" wire:click="testMail">Send me a test email</x-admin.button>
                        @elseif ($card['key'] === 'legacy')
                            <x-admin.button variant="secondary" size="sm" icon="database" wire:click="testLegacy">Test connection</x-admin.button>
                            @can('system.import')<x-admin.button variant="ghost" size="sm" :href="route('admin.data-import')" wire:navigate>Data import</x-admin.button>@endcan
                        @else
                            <x-admin.button variant="secondary" size="sm" :href="route('admin.system.health')" wire:navigate>System health</x-admin.button>
                        @endif
                    </div>
                    @include('livewire.admin.system.telegram-diagnostics')
                </div>
            </x-admin.card>
        @empty
            <div class="rounded-xl border border-dashed border-gray-300 p-8 text-theme-sm text-gray-500 md:col-span-2 xl:col-span-3">No integrations match your search. Try another app name or category.</div>
        @endforelse
    </div>

    @include('livewire.admin.system.google-setup')
    @include('livewire.admin.system.integration-log')

    @if ($showIntegrationDetails && $details)
        <x-admin.modal name="showIntegrationDetails" :title="$details['name']" description="Roadmap · Not connected">
            <p class="text-theme-sm leading-6 text-gray-600 dark:text-gray-300">{{ $details['requirements'] }}</p>
            <p class="mt-4 text-theme-xs text-gray-500">Company connections will require owner consent, encrypted token storage, least-privilege scopes and audited disconnect/revocation. This page does not request credentials for planned apps.</p>
            <x-slot:footer><x-admin.button variant="secondary" :href="$details['docs']" target="_blank" rel="noopener noreferrer" icon="external">Provider documentation</x-admin.button></x-slot:footer>
        </x-admin.modal>
    @endif
</div>
