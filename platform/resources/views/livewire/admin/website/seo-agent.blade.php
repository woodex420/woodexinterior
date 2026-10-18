<div>
    <x-admin.page-header title="SEO agent" description="A deterministic sweep of every page — titles, descriptions, slugs, alt texts and the site's own links. Every finding is a rule you can re-check by hand; fixes are one click. AI proposals join when a model key exists."
                         :breadcrumbs="['Website' => null, 'SEO agent' => null]" />

    <div class="mb-4 flex flex-wrap items-center gap-2">
        @foreach (['pages' => 'Page audit', 'site' => 'Website health'] as $value => $label)
            <button type="button" wire:click="$set('tab', '{{ $value }}')"
                    @class(['wx-mini-btn' => true, 'border-brand-500 text-brand-700 dark:text-brand-300' => $tab === $value])>{{ $label }}</button>
        @endforeach

        <div class="ml-auto flex flex-wrap items-center gap-2 text-theme-xs text-gray-500">
            <span class="rounded-full bg-soft px-3 py-1">{{ $audit['counts']['pages'] }} page(s) with findings</span>
            <span class="rounded-full bg-soft px-3 py-1">{{ $audit['counts']['issues'] }} issue(s)</span>
            <span class="rounded-full bg-brand-50 px-3 py-1 font-medium text-brand-700 dark:bg-brand-500/10 dark:text-brand-300">{{ $audit['counts']['fixable'] }} fixable in one click</span>
        </div>
    </div>

    @if (! $aiReady)
        <x-admin.card class="mb-4" title="AI proposals are asleep">
            <p class="text-theme-sm text-gray-600 dark:text-gray-300">The audit above needs no key and always runs. The studio's model key — set once on the <a href="{{ route('admin.settings') }}" class="font-medium text-brand-700 underline dark:text-brand-300">Settings screen</a> — also wakes the SEO agent's writer: it drafts a title and a meta description per page, and nothing lands until you click Apply.</p>
        </x-admin.card>
    @endif

    @if ($tab === 'pages')
        <div class="space-y-3">
            @forelse ($audit['pages'] as $finding)
                <x-admin.card :padding="false">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-3 dark:border-gray-800">
                        <div class="min-w-0">
                            <p class="truncate text-theme-sm font-medium text-gray-800 dark:text-white/90">
                                {{ $finding['title'] }}
                                @if ($finding['draft'])<span class="ml-2 rounded-full bg-gray-100 px-2 py-0.5 text-theme-xs text-gray-500 dark:bg-white/5">draft</span>@endif
                            </p>
                            <p class="truncate text-theme-xs text-gray-400">{{ $finding['slug'] }}</p>
                        </div>
                        @if ($aiReady)
                            <button type="button" wire:click="propose({{ $finding['id'] }})" wire:loading.attr="disabled" class="wx-mini-btn">Draft title &amp; description with AI</button>
                        @endif
                    </div>

                    @if ($proposalPageId === $finding['id'] && $proposal !== [])
                        <div class="border-b border-gray-100 bg-brand-50/50 px-5 py-4 dark:border-gray-800 dark:bg-brand-500/5">
                            <p class="text-theme-xs font-medium text-brand-700 dark:text-brand-300">Proposal — read it, then apply or walk away.</p>
                            <p class="mt-2 text-theme-sm text-gray-800 dark:text-white/90">{{ $proposal['title'] }}</p>
                            <p class="mt-1 text-theme-sm text-gray-600 dark:text-gray-300">{{ $proposal['description'] }}</p>
                            <div class="mt-3 flex gap-2">
                                <x-admin.button type="button" wire:click="applyProposal" icon="check">Apply to the page</x-admin.button>
                                <button type="button" wire:click="$set('proposal', []), $set('proposalPageId', null)" class="wx-mini-btn">Discard</button>
                            </div>
                        </div>
                    @endif

                    <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($finding['issues'] as $issue)
                            <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
                                <p class="text-theme-sm text-gray-600 dark:text-gray-300">{{ $issue['message'] }}</p>
                                @if ($issue['fix'] === 'description')
                                    <button type="button" wire:click="fixDescription({{ $finding['id'] }})" class="wx-mini-btn border-brand-500 text-brand-700 dark:text-brand-300">Describe from the page</button>
                                @elseif ($issue['fix'] === 'slug')
                                    <button type="button" wire:click="fixSlug({{ $finding['id'] }})" wire:confirm="Tidy the slug? The old address will redirect to the new one." class="wx-mini-btn border-brand-500 text-brand-700 dark:text-brand-300">Tidy the slug</button>
                                @elseif ($issue['fix'] === 'alt')
                                    <button type="button" wire:click="fixAlt({{ $finding['id'] }})" class="wx-mini-btn border-brand-500 text-brand-700 dark:text-brand-300">Fill alt texts</button>
                                @else
                                    <span class="text-theme-xs text-gray-400">advice</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </x-admin.card>
            @empty
                <x-admin.card>
                    <x-admin.empty icon="search" title="Nothing to fix" description="Every page passed every rule — titles, descriptions, slugs, alt texts. The sweep runs again the moment you open this screen." />
                </x-admin.card>
            @endforelse
        </div>
    @else
        <div class="space-y-3">
            @forelse ($audit['site'] as $issue)
                <x-admin.card>
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <p class="text-theme-sm text-gray-600 dark:text-gray-300">{{ $issue['message'] }}</p>
                        @if ($issue['fix'] === 'redirect')
                            <button type="button" wire:click="fixRedirect({{ $issue['ids'][0] }})" wire:confirm="Remove this redirect? Visitors following it will get the 404 page." class="wx-mini-btn border-brand-500 text-brand-700 dark:text-brand-300">Remove the oldest</button>
                        @else
                            <span class="text-theme-xs text-gray-400">advice</span>
                        @endif
                    </div>
                    @if ($issue['fix'] === 'redirect' && count($issue['ids']) > 1)
                        <div class="mt-2 flex flex-wrap gap-2">
                            @foreach (array_slice($issue['ids'], 1) as $redirectId)
                                <button type="button" wire:click="fixRedirect({{ $redirectId }})" wire:confirm="Remove this redirect? Visitors following it will get the 404 page." class="wx-mini-btn">✕ #{{ $redirectId }}</button>
                            @endforeach
                        </div>
                    @endif
                </x-admin.card>
            @empty
                <x-admin.card>
                    <x-admin.empty icon="search" title="The site is healthy" description="No broken redirects, no dead links, no orphan pages — this check is the F10 health sweep." />
                </x-admin.card>
            @endforelse
        </div>
    @endif
</div>
