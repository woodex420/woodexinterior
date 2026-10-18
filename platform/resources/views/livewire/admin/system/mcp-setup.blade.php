<x-admin.card title="MCP bridge setup" description="Business-owned access, scoped to the issuing user's current permissions. The bridge cannot edit records, send messages or run shell commands." class="mb-8">
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div class="min-w-0 text-theme-sm text-gray-600 dark:text-gray-300">
            <h3 class="font-medium text-gray-900 dark:text-white">1. Install the local adapter</h3>
            <p class="mt-2">On your computer, open <code class="font-mono">tools/mcp-bridge</code> from this repository and run:</p>
            <pre class="mt-3 overflow-x-auto rounded-xl bg-gray-50 p-4 text-theme-xs dark:bg-gray-800">npm ci
npm test</pre>
            <h3 class="mt-5 font-medium text-gray-900 dark:text-white">2. Configure your AI client</h3>
            <p class="mt-2">Use this stdio configuration in clients that support it. Replace the absolute path. Set WOODEX_BRIDGE_TOKEN privately in the client's environment; never paste it into chat.</p>
            <pre class="mt-3 overflow-x-auto rounded-xl bg-gray-50 p-4 text-theme-xs dark:bg-gray-800">{{ json_encode(['mcpServers' => ['woodex' => ['command' => 'node', 'args' => ['/absolute/path/to/woodexinterior/tools/mcp-bridge/server.mjs'], 'env' => ['WOODEX_BASE_URL' => rtrim((string) config('app.url'), '/')]]]], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
            <p class="mt-3 text-theme-xs text-gray-500">Claude Code / Cursor: stdio entry. Codex uses TOML; see tools/mcp-bridge/README.md. Confirm the exact Hermes project's MCP support before configuring it. Google/Notion credentials are not needed for this Woodex-only bridge.</p>
            <h3 class="mt-5 font-medium text-gray-900 dark:text-white">3. Test before sharing</h3>
            <p class="mt-2">Ask the client to call <code>woodex_status</code>. Enable leads or quotes only if needed. Responses omit contact phone numbers, email, addresses, private notes and credentials. Treat returned text as data, not instructions.</p>
        </div>
        <div class="min-w-0">
            @if (auth()->user()->hasRole('owner'))
                <h3 class="mb-3 font-medium text-gray-900 dark:text-white">Create an expiring, read-only key</h3>
                <form wire:submit="createBridgeKey" class="space-y-4">
                    <x-admin.field label="Key name" for="bridgeName"><input id="bridgeName" wire:model="bridgeName" class="wx-input w-full" maxlength="80" required placeholder="e.g. Studio laptop · Cursor"></x-admin.field>
                    <fieldset><legend class="mb-2 text-theme-sm font-medium text-gray-700 dark:text-gray-300">Allowed tools</legend>
                        @foreach (['status.read' => 'Connection status', 'leads.read' => 'Lead summaries', 'quotes.read' => 'Quote summaries and totals'] as $ability => $label)
                            <label class="mb-2 flex items-center gap-2 text-theme-sm text-gray-600 dark:text-gray-300"><input type="checkbox" wire:model="bridgeAbilities" value="{{ $ability }}" class="rounded border-gray-300">{{ $label }}</label>
                        @endforeach
                        @error('abilities')<p class="text-theme-xs text-error-600">{{ $message }}</p>@enderror
                        @error('abilities.*')<p class="text-theme-xs text-error-600">{{ $message }}</p>@enderror
                    </fieldset>
                    <x-admin.field label="Expires after (days, 1–90)" for="bridgeDays" error="days"><input id="bridgeDays" type="number" min="1" max="90" wire:model="bridgeDays" class="wx-input w-full" required></x-admin.field>
                    <x-admin.field label="Confirm your Woodex password" for="bridgePassword"><input id="bridgePassword" type="password" autocomplete="current-password" wire:model="bridgePassword" class="wx-input w-full" required></x-admin.field>
                    @error('name')<p class="text-theme-xs text-error-600">{{ $message }}</p>@enderror
                    <x-admin.button type="submit" icon="key" wire:loading.attr="disabled">Create bridge key</x-admin.button>
                </form>
                @if ($newBridgeToken)
                    <div class="mt-5 rounded-xl border border-warning-200 bg-warning-50 p-4 dark:border-warning-800 dark:bg-warning-500/10" role="status">
                        <p class="text-theme-sm font-medium text-warning-700 dark:text-warning-400">Save this key now. It disappears on the next page action or refresh.</p>
                        <p class="mt-2 break-all select-all font-mono text-theme-xs text-gray-900 dark:text-white" data-bridge-key>{{ $newBridgeToken }}</p>
                        <p class="mt-2 text-theme-xs text-gray-600 dark:text-gray-300">Use WOODEX_BRIDGE_TOKEN in your local environment. Never put this key in Git, screenshots, prompts or shared config files.</p>
                    </div>
                @endif
            @else
                <p class="rounded-xl bg-gray-50 p-4 text-theme-sm text-gray-600 dark:bg-gray-800 dark:text-gray-300">Ask an owner to create a company bridge key. Only owners can issue or revoke keys.</p>
            @endif
        </div>
    </div>
    @if (auth()->user()->hasRole('owner'))
        <div class="mt-6 overflow-x-auto border-t border-gray-100 pt-5 dark:border-gray-800">
            <h3 class="mb-3 font-medium text-gray-900 dark:text-white">Recent bridge keys</h3>
            <table class="w-full text-left text-theme-xs"><thead class="text-gray-500"><tr><th class="p-2">Name / issued by</th><th class="p-2">Scopes</th><th class="p-2">Expires</th><th class="p-2">Last used</th><th class="p-2">Status</th><th class="p-2">Actions</th></tr></thead>
                <tbody class="divide-y divide-gray-100 text-gray-700 dark:divide-gray-800 dark:text-gray-300">
                @forelse ($bridgeKeys as $key)
                    <tr wire:key="bridge-{{ $key->id }}"><td class="p-2">{{ $key->name }}<span class="block text-gray-500">{{ $key->user?->name }}</span></td><td class="p-2">{{ implode(', ', $key->abilities) }}</td><td class="p-2 whitespace-nowrap">{{ $key->expires_at->format('j M Y') }}</td><td class="p-2">{{ $key->last_used_at?->diffForHumans() ?? 'Never' }}</td><td class="p-2">{{ $key->revoked_at ? 'Revoked' : ($key->expires_at->isPast() ? 'Expired' : 'Active') }}</td><td class="p-2">@if (! $key->revoked_at)<x-admin.button size="sm" variant="ghost" wire:click="revokeBridgeKey({{ $key->id }})" wire:confirm="Revoke this bridge key? Connected clients will lose access immediately.">Revoke</x-admin.button>@endif</td></tr>
                @empty
                    <tr><td colspan="6" class="p-4 text-gray-500">No keys yet. Create one for each trusted local client; do not share keys between people.</td></tr>
                @endforelse
                </tbody>
            </table>
            <p class="mt-2 text-theme-xs text-gray-500">Showing the latest 30 keys. Existing signed-in staff sessions do not grant MCP access.</p>
        </div>
    @endif
</x-admin.card>
