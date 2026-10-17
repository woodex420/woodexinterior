<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Conversations;

use App\Domain\Conversations\Actions\SendConversationMessage;
use App\Domain\Conversations\Enums\Channel;
use App\Domain\Conversations\Enums\ConversationStatus;
use App\Domain\Conversations\Models\Conversation;
use App\Livewire\Concerns\InteractsWithAdmin;
use App\Settings\NotificationSettings;
use Carbon\CarbonInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * P10.2: the live-chat presence in the admin header (docs/27, owner decision P10-C). A visitor
 * texting the website bubble becomes visible within seconds on every admin page: unread count on
 * the icon, a green dot while the visitor is active, and one toast per new message — the drawer
 * NEVER opens by itself; it opens when somebody clicks. Polling (10 s, visible tabs) is honest on
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

        // One toast per new visitor message: the newest unread inbound message beyond the
        // session baseline announces itself once, then the baseline advances.
        $newest = $this->newestUnread();
        $seenAt = $this->sessionBaseline();

        if ($newest !== null && $newest->last_message_at->gt($seenAt)) {
            $this->dispatch('chat-alert',
                name: $newest->contact->name ?? 'Website visitor',
                preview: (string) Str::limit((string) $newest->last_preview, 90),
                sound: (bool) app(NotificationSettings::class)->chat_alert_sound,
            );

            session(['livechat.seen_at' => $newest->last_message_at->toISOString()]);
        }

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

    /** The conversation with the newest unread inbound web_chat message — the only one allowed to announce itself. */
    private function newestUnread(): ?Conversation
    {
        return Conversation::query()
            ->where('channel', Channel::WebChat->value)
            ->whereIn('status', [ConversationStatus::Open->value, ConversationStatus::Pending->value])
            ->where('unread_count', '>', 0)
            ->where('last_direction', 'in')
            ->with('contact:id,name')
            ->orderByDesc('last_message_at')
            ->first();
    }

    /** The last message this session has already announced (or the current newest, first time). */
    private function sessionBaseline(): CarbonInterface
    {
        $seen = session('livechat.seen_at');

        if (is_string($seen) && $seen !== '') {
            return Carbon::parse($seen);
        }

        $baseline = $this->newestUnread()->last_message_at ?? now();
        session(['livechat.seen_at' => $baseline->toISOString()]);

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
