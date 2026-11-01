<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Domain\System\Enums\ChangeRequestStatus;
use App\Domain\System\Enums\ChangeRequestType;
use App\Domain\System\Enums\WebhookStatus;
use App\Domain\System\Models\ChangeRequest;
use App\Domain\System\Models\TelegramLink;
use App\Domain\System\Models\WebhookEvent;
use App\Domain\System\Telegram\TelegramCallbackBus;
use App\Domain\System\Telegram\TelegramCommandBus;
use App\Http\Controllers\Controller;
use App\Integrations\Telegram\Contracts\TelegramGateway;
use App\Integrations\Telegram\TelegramMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Throwable;

/**
 * Telegram bot webhook (TGM-001…TGM-007): linking (/start CODE), change requests from mobile
 * (/idea, /issue), the command centre and inline buttons — every business command lives in the
 * TelegramCommandBus, one class per command, behind the linked user's own permissions.
 *
 * Hardened shell (agent security review, 17 Oct): authenticated by VerifyTelegramWebhook; every
 * update lands in the replay ledger first; business writes and the marker commit together inside
 * one transaction; replies go out only after the commit, and a failed reply never reruns the
 * business effect. A forged update (group routing, bot sender, forward, wrong addressee) is
 * ignored, never executed.
 */
final class TelegramWebhookController extends Controller
{
    /** Commands one chat may run per minute before the bot asks for a pause (TGM-007). */
    private const COMMANDS_PER_MINUTE = 30;

    public function __construct(
        private readonly TelegramGateway $telegram,
        private readonly TelegramCommandBus $bus,
        private readonly TelegramCallbackBus $callbacks,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $rawId = $request->input('update_id');
        $admitted = (is_int($rawId) && $rawId >= 0) || (is_string($rawId) && ctype_digit($rawId));
        if (! $admitted) {
            return response()->json(['ok' => true]);
        }
        $updateId = (string) $rawId;

        $event = WebhookEvent::query()->firstOrCreate([
            'provider' => 'telegram', 'external_id' => $updateId,
        ], [
            'type' => $request->has('message') ? 'message' : ($request->has('callback_query') ? 'callback' : 'other'),
            'payload' => $request->json()->all(),
            'signature_valid' => true,
            'status' => WebhookStatus::Received,
        ]);
        $duplicate = false;

        try {
            $reply = DB::transaction(function () use ($event, &$duplicate): ?array {
                $event = WebhookEvent::query()->lockForUpdate()->findOrFail($event->id);

                if (! $event->signature_valid || in_array($event->status, [WebhookStatus::Processed, WebhookStatus::Ignored], true)) {
                    $duplicate = true;

                    return null;
                }

                $message = $event->payload['message'] ?? null;
                $callback = $event->payload['callback_query'] ?? null;

                $reply = null;
                if (is_array($callback)) {
                    $reply = $this->callbackReply($callback);
                } elseif (is_array($message)) {
                    $reply = $this->commandReply($message);
                }

                $event->forceFill([
                    'status' => $reply === null ? WebhookStatus::Ignored : WebhookStatus::Processed,
                    'attempts' => $event->attempts + 1,
                    'processed_at' => now(),
                    'error' => null,
                ])->save();

                return $reply;
            });
        } catch (Throwable $exception) {
            Log::warning('Telegram command processing failed.', ['event_id' => $event->id, 'exception' => $exception::class]);

            // Preserve the trusted received row for the provider retry; do not expose an exception body.
            return response()->json(['ok' => false], 503);
        }

        if ($reply !== null) {
            try {
                if (! empty($reply['callback_id'])) {
                    $this->telegram->answerCallbackQuery((string) $reply['callback_id'], (string) $reply['text']);
                }

                $this->telegram->send((int) $reply['chat_id'], new TelegramMessage(TelegramMessage::escape((string) $reply['text'])));
            } catch (Throwable $exception) {
                // Business effects already committed. Never rerun them just because a reply failed.
                Log::warning('Telegram command reply failed.', ['event_id' => $event->id, 'exception' => $exception::class]);
            }
        }

        return response()->json(['ok' => true, 'duplicate' => $duplicate]);
    }

    /**
     * An inline button press (TGM-005): the answer goes to the button itself and to the chat, so it
     * survives the toast scrolling away.
     *
     * @param  array<string, mixed>  $callback
     * @return array{chat_id: int, text: string, callback_id: string}|null
     */
    private function callbackReply(array $callback): ?array
    {
        $callbackId = (string) ($callback['id'] ?? '');
        $chatId = $callback['message']['chat']['id'] ?? null;
        $data = (string) ($callback['data'] ?? '');

        if ($chatId === null || $chatId === '' || $data === '') {
            // A callback without a real message/chat is not button traffic — ignore it (no send, no authority).
            return null;
        }

        $user = $this->linkedUserOrNull((int) $chatId)?->user;
        $reply = $this->callbacks->dispatch($user, $data);

        return ['chat_id' => (int) $chatId, 'text' => $reply, 'callback_id' => $callbackId];
    }

    /**
     * A command from a private chat. The gate first (agent security review, 17 Oct): personal commands
     * need the actual human sender in their private chat — never a group's routing identity, a bot, or a
     * forward — then the studio's own commands run behind the linked user's permissions.
     *
     * @param  array<string, mixed>  $message
     * @return array{chat_id: int, text: string}|null
     */
    private function commandReply(array $message): ?array
    {
        $chatId = self::positiveId($message['chat']['id'] ?? null);
        $senderId = self::positiveId($message['from']['id'] ?? null);
        $text = $message['text'] ?? null;

        if ($chatId === false || $senderId === false || $chatId !== $senderId
            || ($message['chat']['type'] ?? null) !== 'private' || ($message['from']['is_bot'] ?? false)
            || isset($message['sender_chat']) || isset($message['via_bot'])
            || isset($message['forward_origin']) || isset($message['forward_from']) || isset($message['forward_from_chat'])
            || ! is_string($text) || ! str_starts_with(trim($text), '/') || mb_strlen($text) > 4096) {
            return null;
        }

        [$command, $argument] = array_pad(preg_split('/\s+/', trim($text), 2) ?: [], 2, '');
        $argument = trim((string) $argument);

        if (str_contains((string) $command, '@') && strcasecmp((string) Str::after((string) $command, '@'), ltrim((string) config('services.telegram.bot_username'), '@')) !== 0) {
            return null;
        }

        $command = Str::lower((string) Str::before((string) $command, '@'));

        if (RateLimiter::tooManyAttempts('telegram-command:'.$chatId, self::COMMANDS_PER_MINUTE)) {
            return ['chat_id' => $chatId, 'text' => 'Too many commands in one minute — give it a moment and try again.'];
        }

        RateLimiter::hit('telegram-command:'.$chatId);

        $user = $this->linkedUserOrNull($chatId)?->user;

        $reply = match ($command) {
            '/start', '/link' => $this->link($message, $chatId, $argument),
            '/idea', '/issue' => $this->changeRequest($chatId, $command === '/idea' ? ChangeRequestType::Idea : ChangeRequestType::Issue, $argument, (int) ($message['message_id'] ?? 0)),
            '/help' => $this->bus->help(),
            default => $this->bus->dispatch($user, $command, $argument),
        };

        return ['chat_id' => $chatId, 'text' => $reply];
    }

    private static function positiveId(mixed $value): int|false
    {
        return is_int($value) && $value > 0 ? $value : false;
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function link(array $message, int $chatId, string $code): string
    {
        if ($code === '') {
            return $this->linkedUserOrNull($chatId) !== null
                ? 'You are already linked to Woodex OS. Send /help to see the commands.'
                : 'Welcome to Woodex OS. To link this chat, open your profile in the admin, tap "Link Telegram" and send the code shown there: /start CODE';
        }

        $link = TelegramLink::query()
            ->where('link_code', Str::upper($code))
            ->where('link_code_expires_at', '>', now())
            ->first();

        if ($link === null || ! $link->user()->where('is_active', true)->exists()) {
            return 'That code is invalid or expired. Create a new code from your profile in the admin.';
        }

        // A chat can belong to one link only, and a code may never steal a chat that another
        // account already holds: that would hand one phone's authority to whoever gets a second code.
        $occupant = TelegramLink::query()->where('chat_id', $chatId)->where('is_active', true)->whereKeyNot($link->id)->first();
        if ($occupant !== null && $occupant->user_id !== $link->user_id) {
            return 'This chat is already linked to another Woodex OS account. Ask an admin to unlink it first.';
        }

        TelegramLink::query()->where('chat_id', $chatId)->whereKeyNot($link->id)->update(['chat_id' => null, 'is_active' => false]);

        $link->forceFill([
            'chat_id' => $chatId,
            'chat_type' => (string) ($message['chat']['type'] ?? 'private'),
            'title' => $message['chat']['title'] ?? trim(($message['from']['first_name'] ?? '').' '.($message['from']['last_name'] ?? '')),
            'username' => $message['from']['username'] ?? null,
            'link_code' => null,
            'link_code_expires_at' => null,
            'is_active' => true,
            'linked_at' => now(),
        ])->save();

        $name = $link->user()->value('name') ?? 'the Woodex team';

        return "✅ Linked to Woodex OS as {$name}. You will receive alerts here. Send /help for commands.";
    }

    private function changeRequest(int $chatId, ChangeRequestType $type, string $text, int $messageId): string
    {
        $link = $this->linkedUserOrNull($chatId);

        if ($link === null) {
            return 'Please link this chat to your Woodex OS account first (profile → Link Telegram).';
        }

        if (mb_strlen($text) < 3) {
            return $type === ChangeRequestType::Idea
                ? 'Write your idea after the command, for example: /idea Add a kitchen filter to the portfolio'
                : 'Describe the problem after the command, for example: /issue The quote PDF shows the wrong phone number';
        }

        $request = ChangeRequest::create([
            'type' => $type,
            'title' => Str::limit($text, 120, '…'),
            'body' => $text,
            'status' => ChangeRequestStatus::New,
            'source' => 'telegram',
            'requested_by' => $link->user_id,
            'telegram_message_id' => $messageId ?: null,
        ]);

        return "Saved as {$request->reference()} ({$type->value}). You will be notified when it is planned.";
    }

    private function linkedUserOrNull(int $chatId): ?TelegramLink
    {
        return TelegramLink::query()
            ->where('chat_id', $chatId)
            ->where('is_active', true)
            ->whereNotNull('user_id')
            // Group/supergroup/channel links never carry private-message authority; null chat_type is a
            // legacy private link from before the column existed and keeps its authority.
            ->where(fn ($query) => $query->where('chat_type', 'private')->orWhereNull('chat_type'))
            ->whereHas('user', fn ($query) => $query->where('is_active', true))
            ->first();
    }
}
