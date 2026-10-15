<?php

declare(strict_types=1);

namespace App\Integrations\Telegram\Contracts;

use App\Integrations\Telegram\TelegramMessage;

/**
 * Telegram Bot API boundary (D-23). Real client: TelegramBotApi. Tests: FakeTelegramGateway.
 */
interface TelegramGateway
{
    public function isConfigured(): bool;

    public function send(int|string $chatId, TelegramMessage $message): bool;

    public function setWebhook(string $url, string $secretToken): bool;
}
