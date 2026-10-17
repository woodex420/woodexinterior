<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Conversations;

use App\Domain\Conversations\Actions\SendConversationMessage;
use App\Domain\Conversations\Enums\Channel;
use App\Domain\Conversations\Enums\ConversationStatus;
use App\Domain\Conversations\Exceptions\ConsentRequired;
use App\Domain\Conversations\Exceptions\OutsideWindow;
use App\Domain\Conversations\Models\Conversation;
use App\Domain\Conversations\Models\Message;
use App\Integrations\WhatsApp\Contracts\WhatsAppGateway;
use App\Livewire\Concerns\InteractsWithAdmin;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Throwable;

/**
 * The unified inbox (CNV-001, CNV-002): every conversation on the left, one thread on the right.
 *
 * It is deliberately plain: a list, a thread, a composer. The composer knows Meta's 24-hour rule (CNV-005) — when
 * the window is closed it says so and refuses to send free-form text, because a message Meta rejects is worse
 * than no message. Real-time updates arrive with the rest of the phase; until then the thread refreshes on every
 * action and the list polls.
 */
#[Layout('layouts::admin')]
#[Title('Inbox')]
class Inbox extends Component
{
    use InteractsWithAdmin;

    #[Url(except: 'open')]
    public string $status = 'open';

    #[Url(except: '')]
    public string $channel = '';

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: false)]
    public bool $unreadOnly = false;

    public ?int $selectedId = null;

    public string $draft = '';

    public string $note = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Conversation::class);
    }

    public function updating(string $name): void
    {
        if (in_array($name, ['status', 'channel', 'search', 'unreadOnly'], true)) {
            $this->selectedId = null;
        }
    }

    /**
     * @return Collection<int, Conversation>
     */
    public function conversations(): Collection
    {
        return $this->query()->limit(60)->get();
    }

    /** @return array{open: int, pending: int, unread: int, closed: int} */
    public function counts(): array
    {
        $base = Conversation::query();

        return [
            'open' => (clone $base)->where('status', ConversationStatus::Open->value)->count(),
            'pending' => (clone $base)->where('status', ConversationStatus::Pending->value)->count(),
            'unread' => (clone $base)->where('unread_count', '>', 0)->count(),
            'closed' => (clone $base)->where('status', ConversationStatus::Closed->value)->count(),
        ];
    }

    public function select(int $id): void
    {
        $conversation = Conversation::query()->findOrFail($id);
        $this->authorize('view', $conversation);

        $this->selectedId = $id;
        $this->draft = '';
        $this->note = '';
        $conversation->markRead();
    }

    public function send(SendConversationMessage $action): void
    {
        $conversation = $this->selected();

        if ($conversation === null || trim($this->draft) === '') {
            return;
        }

        $this->authorize('reply', $conversation);

        try {
            $action->handle($conversation, $this->draft, Auth::user());
        } catch (OutsideWindow|ConsentRequired $exception) {
            $this->toast($exception->getMessage(), 'error');

            return;
        } catch (AuthorizationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            $this->toast('WhatsApp did not take that message. It is saved as failed — try again.', 'error');

            return;
        }

        $this->draft = '';
        $this->toast('Sent.');
    }

    public function addNote(SendConversationMessage $action): void
    {
        $conversation = $this->selected();
        $user = Auth::user();

        if ($conversation === null || trim($this->note) === '' || ! $user instanceof User) {
            return;
        }

        $this->authorize('reply', $conversation);
        $action->note($conversation, $this->note, $user);

        $this->note = '';
        $this->toast('Note added — the client never sees it.');
    }

    public function assign(int $userId): void
    {
        $conversation = $this->selected();

        if ($conversation === null) {
            return;
        }

        $this->authorize('update', $conversation);
        $conversation->update(['assignee_id' => $userId > 0 ? $userId : null]);
        $this->toast($userId > 0 ? 'Assigned.' : 'Unassigned.');
    }

    public function close(): void
    {
        $conversation = $this->selected();

        if ($conversation === null) {
            return;
        }

        $this->authorize('update', $conversation);
        $conversation->update([
            'status' => ConversationStatus::Closed,
            'closed_at' => now(),
            'closed_by' => Auth::id(),
        ]);
        $this->toast('Conversation closed.');
    }

    public function reopen(): void
    {
        $conversation = $this->selected();

        if ($conversation === null) {
            return;
        }

        $this->authorize('update', $conversation);
        $conversation->update(['status' => ConversationStatus::Open, 'closed_at' => null, 'closed_by' => null]);
        $this->toast('Reopened.');
    }

    /** Poll the thread: new messages and delivery ticks appear without a reload until broadcasting lands (CNV-006). */
    public function refresh(): void
    {
        // Rendering re-reads the thread; the poll only has to wake the component up.
    }

    public function render(WhatsAppGateway $whatsapp): View
    {
        $conversation = $this->selected();

        return view('livewire.admin.conversations.inbox', [
            'conversations' => $this->conversations(),
            'counts' => $this->counts(),
            'selected' => $conversation,
            'thread' => $conversation?->messages()->with('author')->oldest()->get() ?? new Collection,
            'configured' => $whatsapp->isConfigured(),
            'channels' => Channel::cases(),
            'staff' => $this->staff(),
        ]);
    }

    private function selected(): ?Conversation
    {
        return $this->selectedId === null ? null : Conversation::query()->find($this->selectedId);
    }

    /** @return Builder<Conversation> */
    private function query(): Builder
    {
        $status = ConversationStatus::tryFrom($this->status);

        return Conversation::query()
            ->with(['contact', 'assignee'])
            ->when($status !== null, fn (Builder $q): Builder => $q->where('status', $status->value))
            ->when($this->channel !== '', fn (Builder $q): Builder => $q->where('channel', $this->channel))
            ->when($this->unreadOnly, fn (Builder $q): Builder => $q->where('unread_count', '>', 0))
            ->when($this->search !== '', function (Builder $q): Builder {
                $term = '%'.$this->search.'%';

                return $q->where(function (Builder $inner) use ($term): void {
                    $inner->where('last_preview', 'like', $term)
                        ->orWhere('external_number', 'like', $term)
                        ->orWhereHas('contact', fn (Builder $c): Builder => $c->where('name', 'like', $term));
                });
            })
            ->orderByDesc('last_message_at');
    }
}
