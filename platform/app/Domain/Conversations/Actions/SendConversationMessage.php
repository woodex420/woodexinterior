<?php

declare(strict_types=1);

namespace App\Domain\Conversations\Actions;

use App\Domain\Conversations\Enums\MessageDirection;
use App\Domain\Conversations\Enums\MessageStatus;
use App\Domain\Conversations\Enums\MessageType;
use App\Domain\Conversations\Exceptions\ConsentRequired;
use App\Domain\Conversations\Exceptions\OutsideWindow;
use App\Domain\Conversations\Models\Conversation;
use App\Domain\Conversations\Models\Message;
use App\Domain\Crm\Models\Contact;
use App\Integrations\WhatsApp\Contracts\WhatsAppGateway;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * A reply from the team (WAP-004).
 *
 * Meta's rule is enforced here rather than in the screen: inside the 24-hour window the message goes as written;
 * outside it, only an approved template may go out. A failed send is stored as a message with status `failed` and
 * Meta's error next to it — the team sees that the client did not receive it, instead of assuming they did.
 */
final class SendConversationMessage
{
    public function __construct(private readonly WhatsAppGateway $whatsapp) {}

    /**
     * @throws OutsideWindow when the window has closed and this is a free-form message
     */
    public function handle(Conversation $conversation, string $body, ?User $user = null): Message
    {
        $conversation = $this->authorizedConversation($conversation, $user);
        $this->assertConsent($conversation);
        $body = trim($body);

        if ($body === '') {
            throw new \InvalidArgumentException('An empty message is not sent.');
        }

        if (! $conversation->isInWindow()) {
            throw new OutsideWindow;
        }

        $result = $this->whatsapp->sendText((string) $conversation->external_number, $body);

        return DB::transaction(function () use ($conversation, $body, $user, $result): Message {
            /** @var Message $message */
            $message = $conversation->messages()->create([
                'direction' => MessageDirection::Out,
                'type' => MessageType::Text,
                'body' => $body,
                'user_id' => $user?->id,
                'external_id' => $result->messageId,
                'status' => $result->ok ? MessageStatus::Sent : MessageStatus::Failed,
                'error_message' => $result->ok ? null : $result->error,
                'sent_at' => $result->ok ? now() : null,
            ]);

            if ($result->ok) {
                $conversation->touchLastMessage(MessageDirection::Out, $body);
            }

            return $message;
        });
    }

    /**
     * An approved template, the only thing Meta allows outside the window (WAP-004/WAP-005).
     *
     * @param  list<array<string, mixed>>  $components
     */
    public function template(Conversation $conversation, string $name, array $components = [], string $language = 'en', ?User $user = null): Message
    {
        $conversation = $this->authorizedConversation($conversation, $user);
        $this->assertConsent($conversation, template: true);
        $result = $this->whatsapp->sendTemplate((string) $conversation->external_number, $name, $language, $components);

        return DB::transaction(function () use ($conversation, $name, $components, $language, $user, $result): Message {
            /** @var Message $message */
            $message = $conversation->messages()->create([
                'direction' => MessageDirection::Out,
                'type' => MessageType::Template,
                'body' => $name,
                'user_id' => $user?->id,
                'external_id' => $result->messageId,
                'status' => $result->ok ? MessageStatus::Sent : MessageStatus::Failed,
                'error_message' => $result->ok ? null : $result->error,
                'meta' => ['template' => $name, 'language' => $language, 'components' => $components],
                'sent_at' => $result->ok ? now() : null,
            ]);

            if ($result->ok) {
                $conversation->touchLastMessage(MessageDirection::Out, 'Template: '.$name);
            }

            return $message;
        });
    }

    /** An internal note: written for the team, never sent (CNV-003). */
    public function note(Conversation $conversation, string $body, User $user): Message
    {
        $conversation = $this->authorizedConversation($conversation, $user);
        /** @var Message $message */
        $message = $conversation->messages()->create([
            'direction' => MessageDirection::Out,
            'type' => MessageType::Note,
            'body' => trim($body),
            'user_id' => $user->id,
            'status' => MessageStatus::Sent,
            'sent_at' => now(),
        ]);

        return $message;
    }

    private function authorizedConversation(Conversation $conversation, ?User $user): Conversation
    {
        $user = $user === null ? null : User::query()->find($user->id);
        if ($user === null || ! $user->is_active) {
            throw new AuthorizationException;
        }
        $conversation = Conversation::query()->findOrFail($conversation->id);
        Gate::forUser($user)->authorize('reply', $conversation);

        return $conversation;
    }

    private function assertConsent(Conversation $conversation, bool $template = false): void
    {
        $contact = $conversation->contact_id === null ? null : Contact::query()->find($conversation->contact_id);
        if ($contact === null || $contact->opted_out_at !== null || ($template && ! $contact->consent_whatsapp)) {
            throw new ConsentRequired;
        }
    }
}
