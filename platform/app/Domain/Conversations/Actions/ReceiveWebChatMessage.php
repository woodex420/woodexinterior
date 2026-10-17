<?php

declare(strict_types=1);

namespace App\Domain\Conversations\Actions;

use App\Domain\Conversations\Enums\MessageDirection;
use App\Domain\Conversations\Enums\MessageStatus;
use App\Domain\Conversations\Enums\MessageType;
use App\Domain\Conversations\Models\Conversation;
use App\Domain\Conversations\Models\Message;
use App\Domain\Crm\Actions\CaptureLead;
use App\Domain\Crm\Actions\LogLeadActivity;
use App\Domain\Crm\Actions\ManageTask;
use App\Domain\Crm\Data\LeadInput;
use App\Domain\Crm\Enums\ActivityType;
use App\Domain\Crm\Enums\LeadSource;
use App\Domain\Crm\Jobs\AskForPhoneNumber;
use App\Settings\BusinessSettings;
use App\Support\Phone;

/**
 * One visitor message through the widget's rule-based intake (P8.5): asked_name → asked_number →
 * asked_service|<contact> → live. The service answer creates the lead via CaptureLead (the single
 * entry point, CAP-003) — that fires LeadCaptured, which alerts the team exactly like any other
 * enquiry. From there the thread is a normal web_chat conversation the team answers from the inbox.
 *
 * Two consent rules hold: typing a number into a chat box is not WhatsApp consent (consent_whatsapp
 * stays false), and when the visitor gives an email instead of a number we ask for the number
 * inside the chat itself and open the same follow-up task the email flow uses — the task is the
 * exactly-once anchor, so the client is never asked twice.
 */
final class ReceiveWebChatMessage
{
    private const STATE_ASKED_NAME = 'asked_name';

    private const STATE_ASKED_NUMBER = 'asked_number';

    private const STATE_LIVE = 'live';

    public function __construct(
        private readonly StartWebChat $startWebChat,
        private readonly CaptureLead $captureLead,
        private readonly ManageTask $manageTask,
        private readonly LogLeadActivity $logActivity,
        private readonly BusinessSettings $business,
    ) {}

    public function handle(Conversation $conversation, string $body): Message
    {
        $body = trim($body);
        $body = $body === '' ? '👍' : mb_substr($body, 0, 2000);

        $message = $conversation->messages()->create([
            'direction' => MessageDirection::In,
            'type' => MessageType::Text,
            'body' => $body,
            'status' => MessageStatus::Delivered,
            'delivered_at' => now(),
        ]);
        $conversation->touchLastMessage(MessageDirection::In, $body); // also counts the unread badge

        $state = (string) ($conversation->widget_state ?? self::STATE_LIVE);

        if ($state === self::STATE_ASKED_NAME) {
            $this->handleName($conversation, $body);
        } elseif (str_starts_with($state, 'asked_service')) {
            $this->handleService($conversation, $state, $body);
        } elseif ($state === self::STATE_ASKED_NUMBER) {
            $this->handleNumber($conversation, $body);
        } else {
            // Live thread: nothing to run — the team replies from the inbox.
        }

        $conversation->refresh();

        return $message;
    }

    /** The name becomes the conversation subject and later the lead's name. */
    private function handleName(Conversation $conversation, string $body): void
    {
        $name = trim(preg_replace('/\s+/u', ' ', $body) ?? '');

        if (mb_strlen($name) < 2 || mb_strlen($name) > 60) {
            $this->startWebChat->bot($conversation, 'Sorry, I did not catch that — what name should the team use for you?');

            return;
        }

        $conversation->update([
            'subject' => $name,
            'widget_state' => self::STATE_ASKED_NUMBER,
        ]);

        $this->startWebChat->bot($conversation, "Nice to meet you, {$name}! What's the best WhatsApp number to reach you on?");
    }

    /** A phone number (preferred) or an email address, both validated before we move on. */
    private function handleNumber(Conversation $conversation, string $body): void
    {
        $candidate = trim(str_replace(['tel:', ' ', '-', '(', ')'], '', $body));

        if (Phone::isValid($candidate) || Phone::isValid('+'.ltrim($candidate, '+'))) {
            $conversation->update(['widget_state' => 'asked_service|'.Phone::normalize($candidate)]);
            $this->startWebChat->bot($conversation, 'Got it. And what do you need done — for example a wardrobe, a full home interior, an office fit-out, or a kitchen?');

            return;
        }

        $email = filter_var(trim($body), FILTER_VALIDATE_EMAIL);
        if ($email !== false) {
            $conversation->update(['widget_state' => 'asked_service|email:'.mb_strtolower($email)]);
            $this->startWebChat->bot($conversation, "Thanks — we'll use {$email}. What do you need done — a wardrobe, a full home interior, an office fit-out, a kitchen?");

            return;
        }

        $this->startWebChat->bot($conversation, "Hmm, that doesn't look like a number. Please reply with your WhatsApp number (like 0322 4000768) — or an email address if you prefer.");
    }

    /** The service answer becomes the enquiry: one CaptureLead call and the thread goes live. */
    private function handleService(Conversation $conversation, string $state, string $body): void
    {
        $contact = mb_substr(substr($state, strlen('asked_service|')), 0, 100);
        $isEmail = str_starts_with($contact, 'email:');
        $email = $isEmail ? mb_substr($contact, strlen('email:')) : null;
        $phone = $isEmail ? null : $contact;
        $name = (string) ($conversation->subject ?? 'Website visitor');
        $service = mb_substr(trim($body), 0, 190);

        $result = $this->captureLead->handle(new LeadInput(
            name: $name,
            phone: $phone,
            email: $email,
            source: LeadSource::WebsiteChat,
            service: $service,
            message: 'Web chat enquiry: '.$service,
            sourceDetail: 'Chat widget',
        ));

        $lead = $result->lead;

        $conversation->update([
            'contact_id' => $lead->contact_id,
            'lead_id' => $lead->id,
            'widget_state' => self::STATE_LIVE,
        ]);

        if ($isEmail) {
            // AskForPhoneNumber's email job would send the same ask we just typed into the chat.
            // Creating its anchor task here makes that job a no-op — one ask, never two.
            if (! $lead->tasks()->where('title', AskForPhoneNumber::TASK_TITLE)->exists()) {
                $this->manageTask->create(
                    AskForPhoneNumber::TASK_TITLE,
                    null,
                    null,
                    null,
                    $lead,
                );
            }

            $this->logActivity->handle(
                $lead,
                ActivityType::System,
                null,
                'Website chat: the visitor gave an email, not a number. The chat asked for their WhatsApp number; the team should follow up if they stay silent.',
            );
        }

        $person = $this->business->brand_name.' team';
        $closing = "Thank you, {$name} — that's everything I need. I've passed your enquiry to the {$person}; they'll reply right here. You can also leave this page open and check back later.";

        if ($isEmail) {
            $closing .= ' And if you would like to continue on WhatsApp, just send your number here.';
        }

        $this->startWebChat->bot($conversation, $closing);
    }
}
