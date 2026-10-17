<div x-data="{ drawer: $wire.entangle('drawer') }"
     x-on:open-live-chat.window="$wire.openDrawer()"
     @if ($canUse) wire:poll.10s.visible @endif>
    @if ($canUse)
        @php($show = $unread > 0 || $visitorActive)
        @if ($show)
            <div class="relative">
                <button type="button" x-on:click="$wire.openDrawer()"
                        class="relative flex size-10 items-center justify-center rounded-full border border-gray-200 text-gray-500 hover:bg-gray-100 dark:border-gray-800 dark:text-gray-400 dark:hover:bg-white/5"
                        aria-label="Website chat{{ $unread > 0 ? " ({$unread} unread)" : '' }}">
                    <x-icon name="whatsapp" />
                    @if ($unread > 0)
                        <span class="absolute -top-0.5 -right-0.5 flex size-5 items-center justify-center rounded-full bg-wood-500 text-[10px] font-semibold text-white">{{ min($unread, 9) }}{{ $unread > 9 ? '+' : '' }}</span>
                    @endif
                    @if ($visitorActive)
                        <span class="absolute -bottom-0.5 -right-0.5 size-3 rounded-full border-2 border-white bg-success-500 dark:border-gray-900" title="A visitor is chatting right now"></span>
                    @endif
                </button>
            </div>
        @endif

        {{-- The drawer: opened only by a click, never by a message (owner decision P10-C). --}}
        <div x-cloak x-show="drawer" x-transition.opacity class="fixed inset-0 z-[9998] bg-gray-900/40" @click="drawer = false"></div>
        <aside x-cloak x-show="drawer"
               x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
               role="dialog" aria-label="Website chats"
               class="fixed inset-y-0 right-0 z-[9999] flex w-full max-w-md flex-col border-l border-gray-200 bg-white shadow-2xl dark:border-gray-800 dark:bg-gray-900">
            <header class="flex h-16 shrink-0 items-center justify-between border-b border-gray-200 px-4 dark:border-gray-800">
                <div>
                    <p class="text-theme-base font-semibold text-navy-900 dark:text-white">Website chats</p>
                    <p class="text-theme-xs text-gray-500">{{ $unread }} unread · live visitors text the bubble on the site</p>
                </div>
                <div class="flex items-center gap-1">
                    @if ($canManageSound)
                        <button type="button" wire:click="toggleSound" title="{{ $soundOn ? 'Chat sound on — click to mute' : 'Chat sound off — click to enable' }}"
                                class="flex size-9 items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-white/5"
                                aria-label="Chat alert sound">
                            <x-icon :name="$soundOn ? 'volume-2' : 'volume-x'" class="size-5" />
                        </button>
                    @endif
                    <a href="{{ route('admin.inbox') }}" wire:navigate class="flex size-9 items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-white/5" aria-label="Open the full inbox">
                        <x-icon name="inbox" class="size-5" />
                    </a>
                    <button type="button" x-on:click="drawer = false" class="flex size-9 items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-white/5" aria-label="Close">
                        <x-icon name="x" class="size-5" />
                    </button>
                </div>
            </header>

            <div class="flex min-h-0 flex-1">
                <nav class="w-44 shrink-0 overflow-y-auto border-r border-gray-200 p-2 dark:border-gray-800" aria-label="Website conversations">
                    @forelse ($live as $conversation)
                        <button type="button" wire:key="lc-{{ $conversation->id }}" wire:click="select({{ $conversation->id }})"
                                @class(['mb-1 block w-full rounded-lg px-2 py-2 text-left text-theme-xs', 'bg-brand-50 text-navy-900 dark:bg-brand-900/30 dark:text-white' => $conversation->id === $selectedId, 'hover:bg-gray-50 dark:hover:bg-white/5' => $conversation->id !== $selectedId])>
                            <span class="flex items-center gap-1.5">
                                @if ($conversation->last_direction === 'in' && $conversation->last_message_at?->gt(now()->subMinutes(2)))
                                    <span class="size-2 shrink-0 rounded-full bg-success-500"></span>
                                @endif
                                <span class="truncate font-medium">{{ $conversation->contact?->name ?? 'Visitor' }}</span>
                                @if ($conversation->unread_count > 0)
                                    <span class="ml-auto shrink-0 rounded-full bg-wood-500 px-1.5 text-[10px] font-semibold text-white">{{ $conversation->unread_count }}</span>
                                @endif
                            </span>
                            <span class="mt-0.5 block truncate text-gray-500 dark:text-gray-400">{{ $conversation->last_preview ?? '—' }}</span>
                            <span class="block text-[10px] text-gray-400">{{ $conversation->last_message_at?->diffForHumans() }}</span>
                        </button>
                    @empty
                        <p class="px-2 py-6 text-center text-theme-xs text-gray-500">No website chats yet.</p>
                    @endforelse
                </nav>

                <div class="flex min-w-0 flex-1 flex-col">
                    @if ($selectedId === null)
                        <div class="flex flex-1 items-center justify-center p-6 text-center text-theme-sm text-gray-500">
                            Pick a conversation to read and reply.
                        </div>
                    @else
                        <div class="min-h-0 flex-1 space-y-3 overflow-y-auto p-4">
                            @forelse ($messages as $message)
                                <div wire:key="lcm-{{ $message->id }}" @class(['flex', 'justify-end' => $message->direction?->value === 'out'])>
                                    <div @class(['max-w-[85%] rounded-2xl px-3.5 py-2 text-theme-sm',
                                        'bg-brand-600 text-white' => $message->direction?->value === 'out',
                                        'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200' => $message->direction?->value !== 'out'])>
                                        <p class="whitespace-pre-wrap">{{ $message->body }}</p>
                                        <p class="mt-0.5 text-right text-[10px] {{ $message->direction?->value === 'out' ? 'text-white/70' : 'text-gray-400' }}">{{ $message->created_at?->format('H:i') }}</p>
                                    </div>
                                </div>
                            @empty
                                <p class="py-8 text-center text-theme-sm text-gray-500">No messages in this thread yet.</p>
                            @endforelse
                        </div>
                        <form wire:submit="send" class="flex shrink-0 items-end gap-2 border-t border-gray-200 p-3 dark:border-gray-800">
                            <textarea wire:model="draft" rows="1" wire:keydown.enter.prevent="send"
                                      class="wx-input max-h-28 flex-1 resize-none" placeholder="Reply to the visitor…" aria-label="Reply"></textarea>
                            <x-admin.button type="submit" icon="send">Send</x-admin.button>
                        </form>
                    @endif
                </div>
            </div>
        </aside>
    @endif
</div>
