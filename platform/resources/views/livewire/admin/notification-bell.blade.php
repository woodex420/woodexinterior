<div class="relative" x-data="{ open: false }" x-on:keydown.escape.window="open = false" x-on:click.outside="open = false" wire:poll.30s.visible>
    <button type="button" x-on:click="open = ! open" :aria-expanded="open"
            class="relative flex size-10 items-center justify-center rounded-full border border-gray-200 text-gray-500 hover:bg-gray-100 dark:border-gray-800 dark:text-gray-400 dark:hover:bg-white/5"
            aria-haspopup="menu" aria-label="Notifications{{ $unread > 0 ? " ({$unread} unread)" : '' }}">
        <x-icon name="bell" />
        @if ($unread > 0)
            <span class="absolute -top-0.5 -right-0.5 flex size-5 items-center justify-center rounded-full bg-wood-500 text-[10px] font-semibold text-white">{{ min($unread, 9) }}{{ $unread > 9 ? '+' : '' }}</span>
        @endif
    </button>
    <div x-cloak x-show="open" x-transition.opacity role="menu"
         class="absolute right-0 z-50 mt-2 w-[22rem] max-w-[calc(100vw-2rem)] rounded-xl border border-gray-200 bg-white p-2 shadow-theme-lg dark:border-gray-800 dark:bg-gray-900">
        <div class="flex items-center justify-between px-3 py-2">
            <p class="text-theme-xs font-medium tracking-wide text-gray-500 uppercase">Notifications</p>
            @if ($unread > 0)
                <button type="button" wire:click="markAllRead" class="text-theme-xs font-medium text-navy-700 hover:underline dark:text-gray-300">Mark all read</button>
            @endif
        </div>
        <div class="max-h-96 overflow-y-auto">
            @forelse ($notifications as $notification)
                <button type="button" wire:key="bell-{{ $notification->id }}" wire:click="open('{{ $notification->id }}')"
                        class="flex w-full gap-3 rounded-lg px-3 py-2 text-left hover:bg-gray-50 dark:hover:bg-white/5">
                    <span @class(['mt-1.5 size-2 shrink-0 rounded-full', 'bg-wood-500' => $notification->read_at === null, 'bg-transparent' => $notification->read_at !== null])></span>
                    <span class="min-w-0">
                        <span @class(['block text-theme-sm text-gray-800 dark:text-white/90', 'font-medium' => $notification->read_at === null])>{{ $notification->data['title'] ?? 'Update' }}</span>
                        <span class="block truncate text-theme-xs text-gray-500">{{ $notification->data['body'] ?? '' }}</span>
                        <span class="block text-[11px] text-gray-400">{{ $notification->created_at?->diffForHumans() }}</span>
                    </span>
                </button>
            @empty
                <p class="px-3 py-6 text-center text-theme-sm text-gray-500">You're all caught up.</p>
            @endforelse
        </div>
        <a href="{{ route('admin.notifications') }}" wire:navigate class="mt-1 block rounded-lg px-3 py-2 text-center text-theme-sm font-medium text-navy-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-white/5">View all</a>
    </div>
</div>
