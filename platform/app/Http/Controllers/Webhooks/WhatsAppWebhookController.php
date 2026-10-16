<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Domain\Conversations\Actions\ReceiveWhatsAppMessage;
use App\Domain\Conversations\Actions\RecordMessageStatus;
use App\Domain\Sales\Actions\SuggestQuoteAcceptance;
use App\Domain\System\Enums\WebhookStatus;
use App\Domain\System\Models\WebhookEvent;
use App\Http\Controllers\Controller;
use App\Integrations\WhatsApp\WhatsAppCredentials;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * WhatsApp Cloud API webhook (WAP-002): Meta's `GET` handshake, then every inbound message and status.
 *
 * Three rules, in this order: prove the request is Meta's (X-Hub-Signature-256), answer 200 immediately, and
 * never process the same event twice (the `webhook_events` unique index does that, so a replay Meta sends because
 * we were slow costs one insert and nothing else).
 */
final class WhatsAppWebhookController extends Controller
{
    public function __construct(
        private readonly ReceiveWhatsAppMessage $receive,
        private readonly RecordMessageStatus $status,
        private readonly WhatsAppCredentials $credentials,
    ) {}

    /** Meta's handshake: it asks for the token we configured, and we echo its challenge back. */
    public function verify(Request $request): Response|JsonResponse
    {
        $expected = (string) $this->credentials->verifyToken();
        $mode = (string) $request->query('hub_mode');
        $token = (string) $request->query('hub_verify_token');
        $challenge = (string) $request->query('hub_challenge');

        if ($expected === '' || $mode !== 'subscribe' || ! hash_equals($expected, $token)) {
            return response()->json(['error' => 'Verification failed.'], 403);
        }

        return response($challenge, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function __invoke(Request $request): JsonResponse
    {
        $secret = (string) $this->credentials->appSecret();
        $signature = (string) $request->header('X-Hub-Signature-256', '');

        if ($secret === '' || ! $this->signatureMatches($secret, $signature, $request->getContent())) {
            Log::warning('WhatsApp webhook: signature did not match.', ['ip' => $request->ip()]);

            abort(403);
        }

        $payload = $request->json()->all();
        $entry = $payload['entry'][0] ?? [];
        $value = $entry['changes'][0]['value'] ?? [];

        if (! is_array($value) || $value === []) {
            return response()->json(['ok' => true]);
        }

        foreach ($this->events($value) as $event) {
            try {
                WebhookEvent::create([
                    'provider' => 'whatsapp',
                    'external_id' => $event['id'],
                    'type' => $event['type'],
                    'payload' => $value,
                    'signature_valid' => true,
                    'status' => WebhookStatus::Received,
                ]);
            } catch (UniqueConstraintViolationException) {
                continue; // Meta replaying something we already handled: nothing to do
            }

            try {
                $this->handle($value, $event);
                WebhookEvent::query()
                    ->where('provider', 'whatsapp')
                    ->where('external_id', $event['id'])
                    ->update(['status' => WebhookStatus::Processed, 'processed_at' => now()]);
            } catch (Throwable $exception) {
                report($exception);

                WebhookEvent::query()
                    ->where('provider', 'whatsapp')
                    ->where('external_id', $event['id'])
                    ->update(['status' => WebhookStatus::Failed, 'error' => $exception->getMessage(), 'processed_at' => now()]);
            }
        }

        return response()->json(['ok' => true]);
    }

    /**
     * The statuses and messages in one payload, each with the id that makes it idempotent.
     *
     * @param  array<string, mixed>  $value
     * @return list<array{id: string, type: string}>
     */
    private function events(array $value): array
    {
        $events = [];

        foreach ((array) ($value['statuses'] ?? []) as $status) {
            if (is_string($status['id'] ?? null)) {
                $events[] = ['id' => $status['id'].':'.$status['status'], 'type' => 'status'];
            }
        }

        foreach ((array) ($value['messages'] ?? []) as $message) {
            if (is_string($message['id'] ?? null)) {
                $events[] = ['id' => $message['id'], 'type' => 'message'];
            }
        }

        return $events;
    }

    /**
     * @param  array<string, mixed>  $value
     * @param  array{id: string, type: string}  $event
     */
    private function handle(array $value, array $event): void
    {
        if ($event['type'] === 'status') {
            $status = (array) ($value['statuses'][0] ?? []);
            $this->status->handle(
                (string) ($status['id'] ?? ''),
                (string) ($status['status'] ?? ''),
                isset($status['errors']) ? (array) $status['errors'] : null,
            );

            return;
        }

        $message = $this->receive->handle($value);

        // A client's "ok" on WhatsApp is a hint, never an acceptance (D-112): it is remembered for the team to
        // confirm. Outside the transaction above, so a notification can never roll back a stored message.
        if ($message !== null) {
            app(SuggestQuoteAcceptance::class)->handle($message);
        }
    }

    private function signatureMatches(string $secret, string $signature, string $body): bool
    {
        if (! str_starts_with($signature, 'sha256=')) {
            return false;
        }

        return hash_equals('sha256='.hash_hmac('sha256', $body, $secret), $signature);
    }
}
