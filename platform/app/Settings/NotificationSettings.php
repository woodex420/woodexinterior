<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Alert delivery defaults (AUT-003, TGM-002, TGM-008).
 *
 * Note: no @var docblocks on properties. spatie/laravel-settings parses them to infer casts
 * and rejects generic types such as array<string, int>.
 */
class NotificationSettings extends Settings
{
    public bool $telegram_enabled;

    public string $quiet_hours_start;

    public string $quiet_hours_end;

    public string $daily_digest_time;

    // list<string> roles alerted when a lead has no owner
    public array $unassigned_lead_roles;

    public static function group(): string
    {
        return 'notifications';
    }
}
