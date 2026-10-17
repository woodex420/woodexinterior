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
     * What the studio can switch on and off, and what merely keeps the lights on. Scheduled
     * entries are gated in routes/console.php — keep them in step; event-driven ones are gated
     * where they fire.
     *
     * @return list<array{key: string, name: string, description: string, cadence: string, toggleable: bool, scheduled: bool, default_enabled?: bool}>
     */
    public static function catalog(): array
    {
        return [
            [
                'key' => 'woodex:new-lead-alert',
                'name' => 'New-enquiry alerts',
                'description' => 'Telegram and in-app alert to the lead\'s owner and the sales managers the moment an enquiry arrives — by email for staff without a Telegram link.',
                'cadence' => 'On every new enquiry',
                'toggleable' => true,
                'scheduled' => false,
            ],
            [
                'key' => 'woodex:ask-for-number',
                'name' => 'Ask for a missing number',
                'description' => 'When an enquiry arrives with an email but no phone number, replies once asking for a WhatsApp number and opens a follow-up task.',
                'cadence' => 'On enquiries without a number',
                'toggleable' => true,
                'scheduled' => false,
            ],
            [
                'key' => 'woodex:monthly-report',
                'scheduled' => true,
                'name' => 'Monthly CRM report',
                'description' => 'On the 1st at 09:00 the owner gets the month that just ended in one message — enquiries, quotations, wins, money in, outstanding — with the month before for comparison, archived under this screen.',
                'cadence' => '1st of the month, 09:00',
                'toggleable' => true,
            ],
            [
                'key' => 'woodex:sla-check',
                'scheduled' => true,
                'name' => 'First-response SLA',
                'description' => 'Flags leads nobody contacted in time and alerts the owner and sales managers on Telegram.',
                'cadence' => 'Every minute',
                'toggleable' => true,
            ],
            [
                'key' => 'woodex:reminders',
                'scheduled' => true,
                'name' => 'Task & visit reminders',
                'description' => 'Sends D−1 and 2-hour reminders for tasks and site visits to the assigned staff.',
                'cadence' => 'Every minute',
                'toggleable' => true,
            ],
            [
                'key' => 'woodex:quote-follow-ups',
                'scheduled' => true,
                'name' => 'Quotation follow-up chaser',
                'description' => 'Nudges the team about quotations nobody answered, warns before prices lapse and retires stale ones.',
                'cadence' => 'Daily 09:00',
                'toggleable' => true,
            ],
            [
                'key' => 'woodex:review-requests',
                'scheduled' => true,
                'name' => 'Review requests',
                'description' => 'Asks a client for a review a few days after a signed handover — consent-checked before anything is sent.',
                'cadence' => 'Daily 10:30',
                'toggleable' => true,
            ],
            [
                'key' => 'woodex:seo-agent',
                'scheduled' => true,
                'name' => 'SEO health check',
                'description' => 'A weekly, read-only look at the website\'s search basics: pages missing a meta description, overlong titles, redirects pointing nowhere — a short list for the owner. Dormant until switched on (A0); it never edits or publishes anything by itself.',
                'cadence' => 'Sundays 08:00',
                'toggleable' => true,
                'default_enabled' => false,
            ],
            [
                'key' => 'woodex:social-agent',
                'scheduled' => true,
                'name' => 'Social post ideas',
                'description' => 'A weekly digest of what is worth posting about — handovers signed, pages published, projects won — sent only to the owner, who decides what to do with it. Dormant until switched on (A0); it never posts anywhere and never writes client-facing copy.',
                'cadence' => 'Mondays 09:30',
                'toggleable' => true,
                'default_enabled' => false,
            ],
            [
                'key' => 'woodex:retry-whatsapp-webhooks',
                'scheduled' => true,
                'name' => 'WhatsApp recovery queue',
                'description' => 'Replays client messages the webhook could not process — keep this on.',
                'cadence' => 'Every minute',
                'toggleable' => false,
            ],
            [
                'key' => 'queue:work --stop-when-empty --max-time=50 --tries=3',
                'scheduled' => true,
                'name' => 'Queue worker',
                'description' => 'Drains queued jobs (notifications, sends) once a minute without a daemon.',
                'cadence' => 'Every minute',
                'toggleable' => false,
            ],
            [
                'key' => 'woodex:heartbeat',
                'scheduled' => true,
                'name' => 'Cron heartbeat',
                'description' => 'Proof every minute that the scheduler itself is alive (System → Health).',
                'cadence' => 'Every minute',
                'toggleable' => false,
            ],
            [
                'key' => 'backup:run --only-db',
                'scheduled' => true,
                'name' => 'Nightly database backup',
                'description' => 'Full database backup at 02:30, cleaned and monitored by the backup package.',
                'cadence' => 'Daily 02:30',
                'toggleable' => false,
            ],
            [
                'key' => 'woodex:prune-task-runs',
                'scheduled' => true,
                'name' => 'Housekeeping',
                'description' => 'Prunes old scheduled-task runs and failed jobs.',
                'cadence' => 'Daily 04:45',
                'toggleable' => false,
            ],
        ];
    }

    /**
     * May this automation run right now? Missing row = the catalog's default: enabled, except the
     * agents that ship dormant (P8.5 A0 — an agent that reaches beyond the studio's own walls
     * starts OFF and waits for the owner's explicit switch). Cached for a minute so the
     * scheduler's per-task checks cost one cache hit, and an owner's toggle lands within a minute.
     */
    public static function enabled(string $key): bool
    {
        return (bool) Cache::remember(
            'woodex:automation.'.$key,
            60,
            static fn (): bool => (bool) (static::query()->where('key', $key)->value('is_enabled') ?? static::defaultEnabled($key)),
        );
    }

    /** The catalog's default state for a key — on, unless the entry ships dormant. */
    public static function defaultEnabled(string $key): bool
    {
        $entry = collect(static::catalog())->firstWhere('key', $key);

        return ! (is_array($entry) && array_key_exists('default_enabled', $entry) && $entry['default_enabled'] === false);
    }

    /**
     * Pause or resume, attributed. Returns the new state.
     */
    public static function toggle(string $key, ?User $by): bool
    {
        // The row starts at the catalog's default (a dormant agent wakes up, the rest pause).
        $automation = static::query()->firstOrCreate(['key' => $key], ['is_enabled' => static::defaultEnabled($key)]);

        $automation->forceFill([
            'is_enabled' => ! $automation->is_enabled,
            'toggled_by' => $by?->id,
            'toggled_at' => now(),
        ])->save();

        Cache::forget('woodex:automation.'.$key);

        return $automation->fresh()->is_enabled;
    }
}
