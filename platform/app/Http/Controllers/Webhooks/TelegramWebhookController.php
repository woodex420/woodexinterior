<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Domain\System\Actions\HandleTelegramCommand;
use App\Domain\System\Enums\WebhookStatus;
use App\Domain\System\Models\WebhookEvent;
use App\Http\Controllers\Controller;
use App\Integrations\Telegram\Contracts\TelegramGateway;
use App\Integrations\Telegram\TelegramMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Authenticated by VerifyTelegramWebhook. Business writes and the replay marker commit together. */
final class TelegramWebhookController extends Controller
{
    public function __construct(private readonly TelegramGateway $telegram, private readonly HandleTelegramCommand $commands) {}

    public function __invoke(Request $request): JsonResponse
    {
        $rawId = $request->input('update_id');
        if (! is_int($rawId) && (! is_string($rawId) || ! ctype_digit($rawId))) {
            return response()->json(['ok' => true]);
        }
        $updateId = filter_var($rawId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if ($updateId === false) {
            return response()->json(['ok' => true]);
        }
        $event = WebhookEvent::query()->firstOrCreate([
            'provider' => 'telegram', 'external_id' => (string) $updateId,
        ], [
            'type' => $request->has('message') ? 'message' : 'other', 'payload' => $request->json()->all(),
            'signature_valid' => true, 'status' => WebhookStatus::Received,
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
                $reply = is_array($message) ? $this->commands->handle($message) : null;
                $event->forceFill([
                    'status' => $reply === null ? WebhookStatus::Ignored : WebhookStatus::Processed,
                    'attempts' => $event->attempts + 1, 'processed_at' => now(), 'error' => null,
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
                $this->telegram->send($reply['chat_id'], new TelegramMessage(TelegramMessage::escape($reply['text'])));
            } catch (Throwable $exception) {
                // Business effects already committed. Never rerun them just because a reply failed.
                Log::warning('Telegram command reply failed.', ['event_id' => $event->id, 'exception' => $exception::class]);
            }
        }

        return response()->json(['ok' => true, 'duplicate' => $duplicate]);
    }
}
