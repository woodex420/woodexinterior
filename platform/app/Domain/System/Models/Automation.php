<?php

declare(strict_types=1);

namespace App\Domain\System\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * The studio's automations (P8.5). The catalog is code — names, descriptions and cadences travel
 * with the deploy, never with a seed — and a missing row means ENABLED, so a database that has
 * never seen a toggle is already in the correct state (fail-safe: automations keep the funnel
 * moving). A row appears only when somebody pauses or resumes one, carrying who and when.
 *
 * A0 law holds: an automation switched ON never gains new powers from this table — it merely
 * allows the existing, consent-gated flows to run. Pausing is always safe.
 */
class Automation extends Model
{
    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_enabled' => 'bool',
            'toggled_at' => 'datetime',
        ];
    }

    /**
     * What the studio can switch on and off, and what merely keeps the lights on. The schedule
     * entries in routes/console.php reference exactly these keys — keep them in step.
     *
     * @return list<array{key: string, name: string, description: string, cadence: string, toggleable: bool}>
     */
    public static function catalog(): array
    {
        return [
            [
                'key' => 'woodex:sla-check',
                'name' => 'First-response SLA',
                'description' => 'Flags leads nobody contacted in time and alerts the owner and sales managers on Telegram.',
                'cadence' => 'Every minute',
                'toggleable' => true,
            ],
            [
                'key' => 'woodex:reminders',
                'name' => 'Task & visit reminders',
                'description' => 'Sends D−1 and 2-hour reminders for tasks and site visits to the assigned staff.',
                'cadence' => 'Every minute',
                'toggleable' => true,
            ],
            [
                'key' => 'woodex:quote-follow-ups',
                'name' => 'Quotation follow-up chaser',
                'description' => 'Nudges the team about quotations nobody answered, warns before prices lapse and retires stale ones.',
                'cadence' => 'Daily 09:00',
                'toggleable' => true,
            ],
            [
                'key' => 'woodex:review-requests',
                'name' => 'Review requests',
                'description' => 'Asks a client for a review a few days after a signed handover — consent-checked before anything is sent.',
                'cadence' => 'Daily 10:30',
                'toggleable' => true,
            ],
            [
                'key' => 'woodex:retry-whatsapp-webhooks',
                'name' => 'WhatsApp recovery queue',
                'description' => 'Replays client messages the webhook could not process — keep this on.',
                'cadence' => 'Every minute',
                'toggleable' => false,
            ],
            [
                'key' => 'queue:work --stop-when-empty --max-time=50 --tries=3',
                'name' => 'Queue worker',
                'description' => 'Drains queued jobs (notifications, sends) once a minute without a daemon.',
                'cadence' => 'Every minute',
                'toggleable' => false,
            ],
            [
                'key' => 'woodex:heartbeat',
                'name' => 'Cron heartbeat',
                'description' => 'Proof every minute that the scheduler itself is alive (System → Health).',
                'cadence' => 'Every minute',
                'toggleable' => false,
            ],
            [
                'key' => 'backup:run --only-db',
                'name' => 'Nightly database backup',
                'description' => 'Full database backup at 02:30, cleaned and monitored by the backup package.',
                'cadence' => 'Daily 02:30',
                'toggleable' => false,
            ],
            [
                'key' => 'woodex:prune-task-runs',
                'name' => 'Housekeeping',
                'description' => 'Prunes old scheduled-task runs and failed jobs.',
                'cadence' => 'Daily 04:45',
                'toggleable' => false,
            ],
        ];
    }

    /**
     * May this automation run right now? Missing row = enabled (fail-safe: an automation the
     * catalog does not know about still runs). Cached for a minute so the scheduler's per-task
     * checks cost one cache hit, and an owner's toggle lands within a minute.
     */
    public static function enabled(string $key): bool
    {
        return (bool) Cache::remember('woodex:automation.'.$key, 60, static fn (): bool => ! static::query()->where('key', $key)->where('is_enabled', false)->exists());
    }

    /**
     * Pause or resume, attributed. Returns the new state.
     */
    public static function toggle(string $key, ?User $by): bool
    {
        $automation = static::query()->firstOrCreate(['key' => $key], ['is_enabled' => true]);

        $automation->forceFill([
            'is_enabled' => ! $automation->is_enabled,
            'toggled_by' => $by?->id,
            'toggled_at' => now(),
        ])->save();

        Cache::forget('woodex:automation.'.$key);

        return $automation->fresh()->is_enabled;
    }
}
