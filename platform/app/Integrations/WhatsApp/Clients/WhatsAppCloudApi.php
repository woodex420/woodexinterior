<?php

declare(strict_types=1);

namespace App\Integrations\WhatsApp\Clients;

use App\Domain\System\Models\IntegrationLog;
use App\Integrations\WhatsApp\Contracts\WhatsAppGateway;
use App\Integrations\WhatsApp\SendResult;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * WhatsApp Cloud API (Graph) client, WAP-001…WAP-004.
 *
 * Credentials come from the server .env (docs/06 §4) and are never shown in the dashboard: the Integrations card
 * only says whether they are set. Every call is written to the integration log, so a message that never arrived
 * can be explained from the admin.
 */
final class WhatsAppCloudApi implements WhatsAppGateway
{
    public function __construct(
        private readonly ?string $token,
        private readonly ?string $phoneNumberId,
        private readonly string $apiVersion = 'v23.0',
        private readonly string $apiBase = 'https://graph.facebook.com',
    ) {}

    public function isConfigured(): bool
    {
        return filled($this->token) && filled($this->phoneNumberId);
    }

    public function sendText(string $to, string $body): SendResult
    {
        return $this->send([
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'text',
            'text' => ['preview_url' => false, 'body' => $body],
        ]);
    }

    public function sendTemplate(string $to, string $template, string $language = 'en', array $components = []): SendResult
    {
        return $this->send([
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'template',
            'template' => [
                'name' => $template,
                'language' => ['code' => $language],
                'components' => $components,
            ],
        ]);
    }

    public function media(string $mediaId): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        try {
            $response = Http::timeout(8)->connectTimeout(4)->acceptJson()
                ->withToken((string) $this->token)
                ->get($this->url($mediaId));
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $url = $response->json('url');

        return is_string($url) && $url !== ''
            ? [
                'url' => $url,
                'mime_type' => (string) ($response->json('mime_type') ?? ''),
                'sha256' => (string) ($response->json('sha256') ?? ''),
                'file_size' => (int) ($response->json('file_size') ?? 0),
            ]
            : null;
    }

    /** @param  array<string, mixed>  $payload */
    private function send(array $payload): SendResult
    {
        if (! $this->isConfigured()) {
            return SendResult::failed('WhatsApp is not configured in the server .env.');
        }

        $started = microtime(true);
        $status = null;
        $result = SendResult::failed('Unknown error.');

        try {
            $response = Http::timeout(8)->connectTimeout(4)->acceptJson()
                ->withToken((string) $this->token)
                ->post($this->url('messages'), $payload);

            $status = $response->status();
            $messageId = $response->json('messages.0.id');
            $error = $response->json('error.message');

            $result = $response->successful() && is_string($messageId)
                ? SendResult::sent($messageId)
                : SendResult::failed(is_string($error) ? $error : 'Meta refused the message.', $status);
        } catch (Throwable $exception) {
            report($exception);
            $result = SendResult::failed($exception->getMessage());
        }

        IntegrationLog::create([
            'integration' => 'whatsapp',
            'direction' => 'out',
            'action' => is_string($payload['type'] ?? null) ? (string) $payload['type'] : 'message',
            'status_code' => $status,
            'ok' => $result->ok,
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            // Never log the token or the message body (it is client conversation, D-47): only its length.
            'request' => ['to' => $payload['to'] ?? null, 'length' => isset($payload['text']['body']) ? mb_strlen($payload['text']['body']) : null],
            'response' => $result->messageId !== null ? ['message_id' => $result->messageId] : null,
            'error' => $result->ok ? null : $result->error,
            'created_at' => now(),
        ]);

        return $result;
    }

    /** https://graph.facebook.com/{version}/{phone-number-id}/messages */
    private function url(string $path): string
    {
        return sprintf('%s/%s/%s/%s', rtrim($this->apiBase, '/'), trim($this->apiVersion, '/'), (string) $this->phoneNumberId, ltrim($path, '/'));
    }
}
