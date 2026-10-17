<?php

declare(strict_types=1);

namespace App\Integrations\Telegram;

use App\Integrations\Telegram\Contracts\TelegramGateway;
use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Laravel notification channel. Notifications implement toTelegram(object $notifiable): TelegramMessage.
 * Delivery failures are logged, never thrown, so an outage cannot break saving business data.
 */
final class TelegramChannel
{
    public function __construct(private readonly TelegramGateway $gateway) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if ($notifiable instanceof User) {
            $notifiable = User::query()->with('telegramLink')->find($notifiable->id);
            if ($notifiable === null || ! $notifiable->is_active) {
                return;
            }
        }

        if (! method_exists($notifiable, 'routeNotificationFor') || ! method_exists($notification, 'toTelegram')) {
            return;
        }

        $chatId = $notifiable->routeNotificationFor('telegram', $notification);

        if ($chatId === null || $chatId === '' || ! $this->gateway->isConfigured()) {
            return;
        }

        try {
            $this->gateway->send($chatId, $notification->toTelegram($notifiable));
        } catch (Throwable $exception) {
            Log::warning('Telegram notification delivery failed.', ['exception' => $exception::class]);
        }
    }
}
