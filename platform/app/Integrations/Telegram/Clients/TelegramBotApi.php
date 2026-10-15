<?php

declare(strict_types=1);

namespace App\Integrations\Telegram\Clients;

use App\Domain\System\Models\IntegrationLog;
use App\Integrations\Telegram\Contracts\TelegramGateway;
use App\Integrations\Telegram\TelegramMessage;
use Illuminate\Support\Facades\Http;
use Throwable;

final class TelegramBotApi implements TelegramGateway
{
    public function __construct(
        private readonly ?string $token,
        private readonly string $apiBase = 'https://api.telegram.org',
    ) {}

    public function isConfigured(): bool
    {
        return $this->token !== null && $this->token !== '';
    }

    public function send(int|string $chatId, TelegramMessage $message): bool
    {
        $payload = [
            'chat_id' => $chatId,
            'text' => $message->text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ];

        if ($message->buttons !== []) {
            $payload['reply_markup'] = ['inline_keyboard' => $message->buttons];
        }

        return $this->call('sendMessage', $payload);
    }

    public function setWebhook(string $url, string $secretToken): bool
    {
        return $this->call('setWebhook', [
            'url' => $url,
            'secret_token' => $secretToken,
            'allowed_updates' => ['message', 'callback_query'],
            'drop_pending_updates' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function call(string $method, array $payload): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        $started = microtime(true);
        $status = null;
        $ok = false;
        $error = null;
        $description = null;

        try {
            $response = Http::timeout(5)->connectTimeout(3)->acceptJson()
                ->post(rtrim($this->apiBase, '/').'/bot'.$this->token.'/'.$method, $payload);

            $status = $response->status();
            $ok = $response->successful() && $response->json('ok') === true;
            $description = $ok ? null : (string) $response->json('description');
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
            report($exception);
        }

        IntegrationLog::create([
            'integration' => 'telegram',
            'direction' => 'out',
            'action' => $method,
            'status_code' => $status,
            'ok' => $ok,
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            // Never log the token or full message bodies (they can contain client data).
            'request' => ['chat_id' => $payload['chat_id'] ?? null, 'length' => isset($payload['text']) ? mb_strlen((string) $payload['text']) : null],
            'response' => $description !== null ? ['description' => $description] : null,
            'error' => $error,
            'created_at' => now(),
        ]);

        return $ok;
    }
}
