<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Conversations;

use App\Domain\Conversations\Actions\SendConversationMessage;
use App\Domain\Conversations\Enums\Channel;
use App\Domain\Conversations\Enums\ConversationStatus;
use App\Domain\Conversations\Enums\MessageDirection;
use App\Domain\Conversations\Models\Conversation;
use App\Domain\Conversations\Models\Message;
use App\Livewire\Concerns\InteractsWithAdmin;
use App\Settings\NotificationSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * P10.2: the live-chat presence in the admin header (docs/27, owner decision P10-C). A visitor
 * texting the website bubble becomes visible within seconds on every admin page: unread count on
 * the icon, a green dot while the visitor is active, and one toast per new message. The drawer
 * opens itself on a new visitor message (owner request, 2026-10-20 — it supersedes the earlier
 * click-only rule), once per message, and never re-opens the thread the reader just closed.
 * Polling (10 s, visible tabs) is honest on
 * shared hosting: no websockets, one indexed query per tick. The "already announced" baseline
 * lives in the session, so navigating between admin pages never replays old messages, and a
 * teammate reading the thread (unread back to 0) is enough to stop the toast for everyone.
 */
class LiveChatBell extends Component
{
    use InteractsWithAdmin;

    /** The drawer is only ever opened by a click (owner decision: never auto-open). */
    public bool $drawer = false;

    public ?int $selectedId = null;

    public string $draft = '';

    /**
     * The baseline is taken when the header mounts, not on every render: a message that arrives
     * while the page is open is new, everything already waiting is history.
     */
    public function mount(): void
    {
        if ($this->user()->can('inbox.view')) {
            $this->seenMessageId();
        }
    }

    /** One poll tick: announce a new visitor message and open the drawer on it. */
    public function poll(): void
    {
        if (! $this->user()->can('inbox.view')) {
            return;
        }

        $newest = $this->newestVisitorMessage();

        if ($newest === null || $newest->id <= $this->seenMessageId()) {
            return;
        }

        session(['livechat.seen_message_id' => $newest->id]);

        $this->dispatch('chat-alert',
            name: $newest->conversation->contact->name ?? 'Website visitor',
            preview: (string) Str::limit($newest->body, 90),
            sound: (bool) app(NotificationSettings::class)->chat_alert_sound,
        );

        if (! $this->drawer) {
            // Open on the thread that just spoke, not simply the most recent one.
            $this->selectedId = $newest->conversation_id;
            $this->openDrawer();
        }
    }

    public function render(): View
    {
        if (! $this->user()->can('inbox.view')) {
            return view('livewire.admin.conversations.live-chat-bell', [
                'canUse' => false, 'live' => new Collection, 'unread' => 0, 'visitorActive' => false,
                'messages' => new Collection, 'soundOn' => false, 'canManageSound' => false,
            ]);
        }

        $live = $this->liveConversations();
        $unread = $live->where('unread_count', '>', 0)->count();
        $visitorActive = $live->contains(fn (Conversation $c): bool => $c->last_direction === 'in'
            && $c->last_message_at !== null && $c->last_message_at->gt(now()->subMinutes(2)));

        $messages = new Collection;
        if ($this->selectedId !== null) {
            $conversation = Conversation::query()->findOrFail($this->selectedId);
            $messages = $conversation->messages()->orderBy('created_at')->limit(100)->get();
        }

        return view('livewire.admin.conversations.live-chat-bell', [
            'canUse' => true,
            'live' => $live,
            'unread' => $unread,
            'visitorActive' => $visitorActive,
            'messages' => $messages,
            'soundOn' => (bool) app(NotificationSettings::class)->chat_alert_sound,
            'canManageSound' => $this->user()->can('settings.manage'),
        ]);
    }

    /** Opened by the bell, by another screen, or by poll() when a visitor writes. */
    #[On('open-live-chat')]
    public function openDrawer(): void
    {
        $this->drawer = true;

        if ($this->selectedId === null) {
            $this->selectedId = $this->liveConversations()->first()?->id;
        }

        $this->markSelectedRead();
    }

    public function closeDrawer(): void
    {
        $this->drawer = false;
    }

    public function select(int $id): void
    {
        $conversation = Conversation::query()->findOrFail($id);
        $this->authorize('view', $conversation);

        $this->selectedId = $conversation->id;
        $this->draft = '';
        $this->markSelectedRead();
    }

    public function send(): void
    {
        $this->validate(['draft' => ['required', 'string', 'min:1', 'max:2000']]);

        $conversation = Conversation::query()->findOrFail((int) $this->selectedId);
        $this->authorize('reply', $conversation);

        app(SendConversationMessage::class)->handle($conversation, $this->draft, $this->user());

        $this->draft = '';
        $this->toast('Sent — the visitor sees it on their next poll.');
    }

    /**
     * Studio-wide sound switch (Settings → Notifications carries the same flag). The owner's
     * call: default off, nothing beeps until somebody turns it on.
     */
    public function toggleSound(NotificationSettings $settings): void
    {
        abort_unless($this->user()->can('settings.manage'), 403);

        $settings->chat_alert_sound = ! $settings->chat_alert_sound;
        $settings->save();

        $this->toast($settings->chat_alert_sound ? 'Chat sound on.' : 'Chat sound off.');
    }

    /** @return Collection<int, Conversation> */
    private function liveConversations(): Collection
    {
        return Conversation::query()
            ->where('channel', Channel::WebChat->value)
            ->whereIn('status', [ConversationStatus::Open->value, ConversationStatus::Pending->value])
            ->with('contact:id,name')
            ->orderByDesc('last_message_at')
            ->limit(30)
            ->get();
    }

    /**
     * The newest inbound message on an unread website thread — the only thing allowed to announce
     * itself. It is read from the messages, not from `last_direction`: an assistant reply must not
     * hide the visitor's enquiry from the team (owner request, 2026-10-20).
     */
    private function newestVisitorMessage(): ?Message
    {
        return Message::query()
            ->where('direction', MessageDirection::In->value)
            ->whereHas('conversation', function ($query): void {
                $query->where('channel', Channel::WebChat->value)
                    ->whereIn('status', [ConversationStatus::Open->value, ConversationStatus::Pending->value])
                    ->where('unread_count', '>', 0);
            })
            ->with('conversation.contact:id,name')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * The id of the last message this session has already announced (the newest waiting one on the
     * first call, so opening the admin page never replays history). Ids are monotonic — safer than
     * comparing timestamps across database precision and time zones.
     */
    private function seenMessageId(): int
    {
        $seen = session('livechat.seen_message_id');

        if (is_int($seen) || (is_string($seen) && ctype_digit($seen))) {
            return (int) $seen;
        }

        $newest = $this->newestVisitorMessage();
        $baseline = $newest instanceof Message ? $newest->id : 0;
        session(['livechat.seen_message_id' => $baseline]);

        return $baseline;
    }

    private function markSelectedRead(): void
    {
        if ($this->selectedId === null) {
            return;
        }

        Conversation::query()
            ->whereKey($this->selectedId)
            ->where('unread_count', '>', 0)
            ->update(['unread_count' => 0]);
    }
}
