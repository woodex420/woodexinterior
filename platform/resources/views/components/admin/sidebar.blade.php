@php
    $groups = \App\Support\AdminNavigation::for(auth()->user());
    $navUser = auth()->user();

    // P10.1: live badges — unread conversations and quotations waiting for approval. Passive counts
    // only (A0): nothing is sent anywhere from here.
    $badges = [];
    if ($navUser?->can('inbox.view')) {
        $badges['admin.inbox'] = \App\Domain\Conversations\Models\Conversation::query()->where('unread_count', '>', 0)->count();
    }
    if ($navUser?->can('quotes.approve')) {
        $badges['admin.approvals'] = \App\Domain\System\Models\Approval::query()
            ->where('approvable_type', 'quote')
            ->where('status', \App\Domain\System\Enums\ApprovalStatus::Pending)
            ->count();
    }
@endphp
<aside
    class="fixed inset-y-0 left-0 z-50 flex h-screen w-[280px] -translate-x-full flex-col border-r border-gray-200 bg-white px-4 transition-all duration-300 ease-in-out xl:w-(--wx-sidebar-w) xl:translate-x-0 dark:border-gray-800 dark:bg-gray-900"
    :class="$store.sidebar.mobileOpen && 'translate-x-0!'"
    aria-label="Main navigation">
    <div class="flex h-[72px] shrink-0 items-center px-2 text-navy-900 dark:text-white">
        <a href="{{ route('admin.dashboard') }}" wire:navigate class="flex items-center">
            <x-brand-logo subtitle="OPERATING SYSTEM" x-show="$store.sidebar.expanded || $store.sidebar.mobileOpen" />
            <x-brand-logo compact x-cloak x-show="!$store.sidebar.expanded && !$store.sidebar.mobileOpen" />
        </a>
    </div>

    <nav class="no-scrollbar flex-1 overflow-y-auto pb-8">
        @foreach ($groups as $group)
            @php($groupKey = \Illuminate\Support\Str::slug($group['title']))
            <div class="mt-5 first:mt-2">
                <button type="button"
                        x-show="$store.sidebar.expanded || $store.sidebar.mobileOpen"
                        @click="$store.sidebar.toggleGroup('{{ $groupKey }}')"
                        :aria-expanded="$store.sidebar.groupOpen('{{ $groupKey }}') ? 'true' : 'false'"
                        class="mb-2 flex w-full items-center justify-between gap-2 rounded px-3 text-[11px] font-medium tracking-[0.12em] text-gray-400 uppercase transition hover:text-gray-600 dark:hover:text-gray-300">
                    <span>{{ $group['title'] }}</span>
                    <span class="shrink-0 transition-transform duration-200"
                          :class="$store.sidebar.groupOpen('{{ $groupKey }}') ? '' : '-rotate-90'">
                        <x-icon name="chevron-down" class="size-3.5" />
                    </span>
                </button>
                <ul class="flex flex-col gap-0.5"
                    x-show="! ($store.sidebar.expanded || $store.sidebar.mobileOpen) || $store.sidebar.groupOpen('{{ $groupKey }}')">
                    @foreach ($group['items'] as $item)
                        @php($badge = $badges[$item['route'] ?? ''] ?? 0)
                        <li>
                            @if ($item['route'])
                                @php($active = request()->routeIs($item['route']) || request()->routeIs(\Illuminate\Support\Str::beforeLast($item['route'], '.index').'.*'))
                                <a href="{{ route($item['route']) }}" wire:navigate
                                   @class(['menu-item group relative', 'menu-item-active' => $active, 'menu-item-inactive' => ! $active])
                                   :class="!$store.sidebar.expanded && !$store.sidebar.mobileOpen ? 'xl:justify-center' : ''"
                                   @if ($active) aria-current="page" @endif
                                   title="{{ $item['label'] }}">
                                    <x-icon :name="$item['icon']" class="size-5 shrink-0" />
                                    <span x-show="$store.sidebar.expanded || $store.sidebar.mobileOpen" class="truncate">{{ $item['label'] }}</span>
                                    @if ($badge > 0)
                                        <span class="ml-auto rounded-full bg-brand-600 px-1.5 py-0.5 text-[10px] leading-none font-semibold text-white dark:bg-brand-500"
                                              x-show="$store.sidebar.expanded || $store.sidebar.mobileOpen">{{ $badge > 99 ? '99+' : $badge }}</span>
                                        <span class="absolute top-1.5 right-2 size-2 rounded-full bg-brand-600 dark:bg-brand-500"
                                              x-cloak x-show="! ($store.sidebar.expanded || $store.sidebar.mobileOpen)"></span>
                                    @endif
                                </a>
                            @else
                                <span class="menu-item menu-item-disabled"
                                      :class="!$store.sidebar.expanded && !$store.sidebar.mobileOpen ? 'xl:justify-center' : ''"
                                      title="{{ $item['label'] }} ({{ str_starts_with((string) $item['phase'], 'P') ? 'arrives in '.$item['phase'] : 'run from the command line' }})" aria-disabled="true">
                                    <x-icon :name="$item['icon']" class="size-5 shrink-0" />
                                    <span x-show="$store.sidebar.expanded || $store.sidebar.mobileOpen" class="flex flex-1 items-center justify-between gap-2 truncate">
                                        {{ $item['label'] }}
                                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-medium text-gray-500 dark:bg-white/5 dark:text-gray-400">{{ $item['phase'] }}</span>
                                    </span>
                                </span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </nav>
</aside>
