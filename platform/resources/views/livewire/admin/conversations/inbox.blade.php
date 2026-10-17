@use('App\Domain\Conversations\Enums\MessageStatus')
@use('App\Domain\Conversations\Enums\MessageType')
<div wire:poll.10s="refresh">
    <x-admin.page-header title="Inbox" description="Every WhatsApp conversation in one place. A client's message opens a 24-hour window to reply freely; outside it, only an approved template can go out."
                         :breadcrumbs="['Inbox' => null, 'Conversations' => null]">
        <x-slot:actions>
            @if (! $configured)
                <span class="inline-flex items-center gap-2 rounded-full border border-warning-200 bg-warning-50 px-3 py-1.5 text-theme-sm text-warning-700 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-400">
                    WhatsApp not connected
                </span>
            @endif
        </x-slot:actions>
    </x-admin.page-header>

    {{-- Status chips: open, pending, unread, closed --}}
    <div class="mb-4 flex flex-wrap gap-2" role="tablist" aria-label="Filter conversations">
        @php
            $chip = fn (bool $active) => 'inline-flex shrink-0 items-center gap-2 rounded-full border px-3.5 py-1.5 text-theme-sm font-medium transition '
                .($active ? 'border-navy-900 bg-navy-900 text-white dark:border-white dark:bg-white dark:text-navy-900' : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-300');
            $tabs = ['open' => 'Open', 'pending' => 'Pending', 'unread' => 'Unread', 'closed' => 'Closed'];
        @endphp
        @foreach ($tabs as $key => $label)
            <button type="button" wire:click="$set('status', '{{ $key === 'unread' ? 'open' : $key }}')" @class([$chip($status === $key || ($key === 'unread' && $unreadOnly))]) role="tab"
                    @if ($key === 'unread') wire:click="$toggle('unreadOnly')" @endif
                    aria-selected="{{ $status === $key ? 'true' : 'false' }}">
                {{ $label }} <span class="tabular-nums opacity-70">{{ (int) ($counts[$key] ?? 0) }}</span>
            </button>
        @endforeach
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,22rem)_minmax(0,1fr)]">
        {{-- The list --}}
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="border-b border-gray-200 p-3 dark:border-gray-800">
                <div class="relative">
                    <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-gray-400" />
                    <input type="search" wire:model.live.debounce.300ms="search" placeholder="Name, number or message" class="wx-input pl-10" aria-label="Search conversations">
                </div>
            </div>

            @if ($conversations->isEmpty())
                <p class="p-6 text-center text-theme-sm text-gray-500 dark:text-gray-400">
                    Nothing here yet. When a client writes on WhatsApp, the conversation appears.
                </p>
            @else
                <ul role="list" class="max-h-[70vh] divide-y divide-gray-200 overflow-y-auto dark:divide-gray-800">
                    @foreach ($conversations as $conversation)
                        <li>
                            <button type="button" wire:click="select({{ $conversation->id }})"
                                    @class(['flex w-full gap-3 p-3 text-left transition hover:bg-gray-50 dark:hover:bg-white/[0.04]', 'bg-gray-50 dark:bg-white/[0.06]' => $selectedId === $conversation->id])>
                                <span class="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-full bg-navy-900/10 text-theme-sm font-semibold text-navy-900 dark:bg-white/10 dark:text-white">
                                    {{ mb_strtoupper(mb_substr($conversation->contact?->name ?? $conversation->external_number ?? '?', 0, 1)) }}
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="flex items-baseline justify-between gap-2">
                                        <span class="truncate text-theme-sm font-medium text-gray-900 dark:text-white">
                                            {{ $conversation->contact?->name ?? $conversation->external_number }}
                                        </span>
                                        <span class="shrink-0 text-theme-xs text-gray-400">
                                            {{ $conversation->last_message_at?->diffForHumans(short: true) ?? '' }}
                                        </span>
                                    </span>
                                    <span class="mt-0.5 flex items-center gap-2">
                                        @if ($conversation->last_direction === 'in')
                                            <x-icon name="arrow-left" class="size-3.5 shrink-0 text-gray-400" />
                                        @endif
                                        <span class="truncate text-theme-sm text-gray-500 dark:text-gray-400">
                                            {{ $conversation->last_preview ?: 'No messages yet' }}
                                        </span>
                                        @if ($conversation->unread_count > 0)
                                            <span class="ml-auto inline-flex size-5 shrink-0 items-center justify-center rounded-full bg-navy-900 text-theme-xs font-semibold text-white dark:bg-white dark:text-navy-900">
                                                {{ $conversation->unread_count }}
                                            </span>
                                        @endif
                                    </span>
                                </span>
                            </button>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        {{-- The thread --}}
        <div class="flex min-h-[70vh] flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
            @if ($selected === null)
                <div class="flex flex-1 items-center justify-center p-8 text-center text-theme-sm text-gray-500 dark:text-gray-400">
                    Pick a conversation to read it and reply.
                </div>
            @else
                <div class="flex items-start justify-between gap-3 border-b border-gray-200 p-4 dark:border-gray-800">
                    <div class="min-w-0">
                        <p class="truncate text-theme-base font-semibold text-gray-900 dark:text-white">
                            {{ $selected->contact?->name ?? $selected->external_number }}
                        </p>
                        <p class="mt-0.5 text-theme-xs text-gray-500 dark:text-gray-400">
                            {{ $selected->contact?->phone ?? $selected->external_number }} · {{ $selected->channel->label() }}
                            @if ($selected->contact?->opted_out_at)
                                · <span class="text-error-600 dark:text-error-400">opted out {{ $selected->contact->opted_out_at->format('j M Y') }}</span>
                            @endif
                        </p>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <select wire:change="assign($event.target.value)" class="wx-input w-auto py-1.5 text-theme-xs" aria-label="Assign to">
                            <option value="0">Unassigned</option>
                            @foreach ($staff as $member)
                                <option value="{{ $member->id }}" @selected($selected->assignee_id === $member->id)>{{ $member->name }}</option>
                            @endforeach
                        </select>
                        @if ($selected->status === App\Domain\Conversations\Enums\ConversationStatus::Closed)
                            <x-admin.button variant="secondary" wire:click="reopen">Reopen</x-admin.button>
                        @else
                            <x-admin.button variant="secondary" wire:click="close">Close</x-admin.button>
                        @endif
                    </div>
                </div>

                <div class="flex-1 space-y-3 overflow-y-auto p-4" id="thread">
                    @foreach ($thread as $message)
                        @if ($message->type === MessageType::Note)
                            <div class="mx-auto max-w-lg rounded-xl border border-dashed border-warning-300 bg-warning-50 px-3 py-2 text-theme-sm text-warning-800 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-300">
                                <span class="font-medium">Note</span> · {{ $message->author?->name }} · {{ $message->body }}
                            </div>
                        @else
                            <div @class(['flex', 'justify-end' => $message->direction === App\Domain\Conversations\Enums\MessageDirection::Out])>
                                <div @class([
                                    'max-w-[34rem] rounded-2xl px-3.5 py-2 text-theme-sm',
                                    'bg-navy-900 text-white dark:bg-white dark:text-navy-900' => $message->direction === App\Domain\Conversations\Enums\MessageDirection::Out,
                                    'bg-gray-100 text-gray-900 dark:bg-white/10 dark:text-white' => $message->direction !== App\Domain\Conversations\Enums\MessageDirection::Out,
                                ])>
                                    @if ($message->type !== MessageType::Text && $message->type !== MessageType::Template)
                                        <p class="mb-1 text-theme-xs uppercase opacity-70">{{ $message->type->value }}</p>
                                    @endif
                                    <p class="whitespace-pre-wrap">{{ $message->body }}</p>
                                    <p class="mt-1 flex items-center gap-1.5 text-theme-xs opacity-70">
                                        {{ $message->created_at->format('H:i') }}
                                        @if ($message->direction === App\Domain\Conversations\Enums\MessageDirection::Out)
                                            · {{ $message->status->label() }}
                                            @if ($message->status === MessageStatus::Read) ✓✓ @elseif ($message->status === MessageStatus::Delivered) ✓✓ @elseif ($message->status === MessageStatus::Sent) ✓ @endif
                                        @endif
                                    </p>
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>

                <div class="border-t border-gray-200 p-3 dark:border-gray-800">
                    @if (! $selected->isInWindow())
                        <p class="mb-2 rounded-xl border border-warning-200 bg-warning-50 px-3 py-2 text-theme-sm text-warning-700 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-400">
                            The 24-hour window is closed. Ask the client to write, or send an approved template.
                        </p>
                    @else
                        <p class="mb-2 text-theme-xs text-gray-400">Free replies for {{ $selected->windowMinutesLeft() }} more minutes.</p>
                    @endif

                    @if ($this->cannedMatches !== [])
                        <div class="mb-2 overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700">
                            <p class="border-b border-gray-100 bg-gray-50 px-3 py-1.5 text-theme-xs text-gray-500 dark:border-gray-800 dark:bg-white/5">Saved replies — click one to fill it in for this client:</p>
                            @foreach ($this->cannedMatches as $match)
                                <button type="button" wire:click="useCanned({{ $match['id'] }})" wire:key="canned-{{ $match['id'] }}"
                                        class="block w-full border-b border-gray-100 px-3 py-2 text-left last:border-0 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-white/5">
                                    <span class="text-theme-sm font-medium text-gray-800 dark:text-white/90">{{ $match['title'] }}
                                        @if ($match['locale'] !== 'en')<x-admin.badge color="gray">{{ strtoupper($match['locale']) }}</x-admin.badge>@endif
                                    </span>
                                    <span class="block text-theme-xs text-gray-500">{{ $match['snippet'] }}</span>
                                </button>
                            @endforeach
                        </div>
                    @endif

                    <div class="flex items-end gap-2">
                        <textarea wire:model="draft" rows="2" placeholder="Write a reply… (type / for saved replies)" class="wx-input" aria-label="Reply" @disabled(! $selected->allowsFreeFormReply())></textarea>
                        <x-admin.button variant="primary" wire:click="send" icon="send" :disabled="! $selected->allowsFreeFormReply()">Send</x-admin.button>
                    </div>

                    <div class="mt-2 flex items-end gap-2">
                        <input type="text" wire:model="note" placeholder="Internal note — the client never sees it" class="wx-input text-theme-sm" aria-label="Internal note">
                        <x-admin.button variant="secondary" wire:click="addNote">Add note</x-admin.button>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
