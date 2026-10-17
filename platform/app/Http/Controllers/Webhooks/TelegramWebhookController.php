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
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Telegram bot webhook (TGM-001, TGM-004, TGM-006): linking (/start CODE), change requests from mobile
 * (/idea, /issue) and the command centre — every business command lives in the TelegramCommandBus, one class
 * per command, behind the linked user's own permissions.
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

    public function __invoke(Request $request, string $secret): JsonResponse
    {
        $expected = (string) config('services.telegram.webhook_secret');

        if ($expected === '' || ! hash_equals($expected, $secret)) {
            abort(404);
        }

        $header = (string) $request->header('X-Telegram-Bot-Api-Secret-Token');

        if ($header !== '' && ! hash_equals($expected, $header)) {
            abort(403);
        }

        $updateId = (string) $request->input('update_id', '');

        if ($updateId === '') {
            return response()->json(['ok' => true]);
        }

        try {
            $event = WebhookEvent::create([
                'provider' => 'telegram',
                'external_id' => $updateId,
                'type' => $request->has('message') ? 'message' : ($request->has('callback_query') ? 'callback' : 'other'),
                'payload' => $request->all(),
                'signature_valid' => true,
                'status' => WebhookStatus::Received,
            ]);
        } catch (UniqueConstraintViolationException) {
            return response()->json(['ok' => true, 'duplicate' => true]);
        }

        $chatId = $request->input('message.chat.id');
        $text = trim((string) $request->input('message.text', ''));
        $callback = $request->input('callback_query');

        if (is_array($callback)) {
            $this->handleCallback($callback);
        } elseif ($chatId !== null && str_starts_with($text, '/')) {
            $this->handleCommand($request, (int) $chatId, $text);
        }

        $event->update(['status' => WebhookStatus::Processed, 'processed_at' => now()]);

        return response()->json(['ok' => true]);
    }

    /**
     * An inline button press (TGM-005): answer the button itself, and say the outcome in the chat too, so it
     * survives the toast scrolling away.
     *
     * @param  array<string, mixed>  $callback
     */
    private function handleCallback(array $callback): void
    {
        $callbackId = (string) ($callback['id'] ?? '');
        $chatId = $callback['message']['chat']['id'] ?? null;
        $data = (string) ($callback['data'] ?? '');

        if ($chatId === null || $chatId === '' || $data === '') {
            $this->telegram->answerCallbackQuery($callbackId, 'That button is not recognised anymore.');

            return;
        }

        $user = $this->linkedUserOrNull((int) $chatId)?->user;
        $reply = $this->callbacks->dispatch($user, $data);

        $this->telegram->answerCallbackQuery($callbackId, $reply);
        $this->telegram->send((int) $chatId, new TelegramMessage(TelegramMessage::escape($reply)));
    }

    private function handleCommand(Request $request, int $chatId, string $text): void
    {
        [$command, $argument] = array_pad(preg_split('/\s+/', $text, 2) ?: [], 2, '');
        $command = Str::lower((string) Str::before($command, '@'));
        $argument = trim((string) $argument);

        if (RateLimiter::tooManyAttempts('telegram-command:'.$chatId, self::COMMANDS_PER_MINUTE)) {
            $this->telegram->send($chatId, new TelegramMessage('Too many commands in one minute — give it a moment and try again.'));

            return;
        }

        RateLimiter::hit('telegram-command:'.$chatId);

        $user = $this->linkedUserOrNull($chatId)?->user;

        $reply = match ($command) {
            '/start', '/link' => $this->link($request, $chatId, $argument),
            '/idea', '/issue' => $this->changeRequest($chatId, $command === '/idea' ? ChangeRequestType::Idea : ChangeRequestType::Issue, $argument, (int) $request->input('message.message_id')),
            '/help' => $this->bus->help(),
            default => $this->bus->dispatch($user, $command, $argument),
        };

        $this->telegram->send($chatId, new TelegramMessage(TelegramMessage::escape($reply)));
    }

    private function link(Request $request, int $chatId, string $code): string
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

        if ($link === null) {
            return 'That code is invalid or expired. Create a new code from your profile in the admin.';
        }

        // A chat can belong to one link only.
        TelegramLink::query()->where('chat_id', $chatId)->whereKeyNot($link->id)->update(['chat_id' => null, 'is_active' => false]);

        $link->forceFill([
            'chat_id' => $chatId,
            'chat_type' => (string) $request->input('message.chat.type', 'private'),
            'title' => $request->input('message.chat.title') ?? trim($request->input('message.from.first_name', '').' '.$request->input('message.from.last_name', '')),
            'username' => $request->input('message.from.username'),
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
        return TelegramLink::query()->where('chat_id', $chatId)->where('is_active', true)->whereNotNull('user_id')->first();
    }
}
