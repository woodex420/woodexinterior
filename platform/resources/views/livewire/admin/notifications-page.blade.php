<div>
    <x-admin.page-header title="Notifications" :description="$unread ? $unread.' unread' : 'You are all caught up.'" :breadcrumbs="['Notifications' => null]">
        <x-slot:actions>
            @if ($unread > 0)
                <x-admin.button variant="secondary" icon="check" wire:click="markAllRead">Mark all read</x-admin.button>
            @endif
        </x-slot:actions>
    </x-admin.page-header>

    <div class="mb-4 inline-flex rounded-lg border border-gray-200 bg-white p-1 dark:border-gray-800 dark:bg-white/[0.03]" role="tablist">
        @foreach (['all' => 'All', 'unread' => 'Unread'] as $value => $label)
            <button type="button" wire:click="$set('filter', '{{ $value }}')" role="tab" aria-selected="{{ $filter === $value ? 'true' : 'false' }}"
                    @class(['rounded-md px-3 py-1.5 text-theme-sm font-medium transition', 'bg-navy-900 text-white dark:bg-white dark:text-navy-900' => $filter === $value, 'text-gray-600 hover:text-gray-900 dark:text-gray-400' => $filter !== $value])>{{ $label }}</button>
        @endforeach
    </div>

    <x-admin.card :padding="false">
        <ul class="divide-y divide-gray-100 dark:divide-gray-800">
            @forelse ($notifications as $notification)
                <li wire:key="n-{{ $notification->id }}">
                    <button type="button" wire:click="open('{{ $notification->id }}')" class="flex w-full items-start gap-3 px-5 py-4 text-left hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                        <span @class(['mt-1.5 size-2 shrink-0 rounded-full', 'bg-wood-500' => $notification->read_at === null, 'bg-gray-200 dark:bg-gray-700' => $notification->read_at !== null])></span>
                        <span class="min-w-0 flex-1">
                            <span @class(['block text-theme-sm text-gray-800 dark:text-white/90', 'font-medium' => $notification->read_at === null])>{{ $notification->data['title'] ?? 'Update' }}</span>
                            <span class="block text-theme-sm text-gray-500">{{ $notification->data['body'] ?? '' }}</span>
                        </span>
                        <span class="shrink-0 text-theme-xs text-gray-400" title="{{ $notification->created_at?->format('j M Y, g:i a') }}">{{ $notification->created_at?->diffForHumans() }}</span>
                    </button>
                </li>
            @empty
                <li><x-admin.empty icon="bell" title="No notifications" description="New leads, missed response times, reminders and @mentions appear here and on Telegram." /></li>
            @endforelse
        </ul>
        {{ $notifications->links('components.admin.pagination') }}
    </x-admin.card>
</div>
