<?php

declare(strict_types=1);

namespace App\Domain\Crm\Actions;

use App\Domain\Crm\Enums\ActivityType;
use App\Domain\Crm\Models\Activity;
use App\Domain\Crm\Models\Lead;
use App\Domain\Crm\Notifications\SlaBreachedNotification;
use App\Models\User;
use App\Settings\NotificationSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * First-response SLA (CRM-010): flag open leads nobody has contacted by their due time and alert the
 * owner plus the managers (in-app + Telegram). Each lead is flagged once; runs every minute.
 */
final class CheckLeadSla
{
    public function __construct(private readonly NotificationSettings $settings) {}

    /**
     * @return int number of leads newly flagged
     */
    public function handle(): int
    {
        $flagged = 0;

        Lead::query()
            ->open()
            ->whereNull('first_response_at')
            ->whereNull('sla_breached_at')
            ->whereNotNull('sla_due_at')
            ->where('sla_due_at', '<=', now())
            ->with(['contact', 'owner.telegramLink'])
            ->chunkById(100, function (Collection $leads) use (&$flagged): void {
                foreach ($leads as $lead) {
                    /** @var Lead $lead */
                    // Claim atomically so overlapping scheduler runs never alert twice.
                    $claimed = Lead::query()->whereKey($lead->id)->whereNull('sla_breached_at')->update(['sla_breached_at' => now()]);

                    if ($claimed === 0) {
                        continue;
                    }

                    $lead->sla_breached_at = now();

                    Activity::create([
                        'contact_id' => $lead->contact_id,
                        'lead_id' => $lead->id,
                        'type' => ActivityType::System,
                        'body' => 'First response time missed (was due '.$lead->sla_due_at?->format('j M, g:i a').').',
                        'data' => ['sla_due_at' => $lead->sla_due_at?->toIso8601String()],
                        'occurred_at' => now(),
                    ]);

                    $recipients = $this->recipients($lead);

                    if ($recipients->isNotEmpty()) {
                        Notification::send($recipients, new SlaBreachedNotification($lead));
                    }

                    $flagged++;
                }
            });

        return $flagged;
    }

    /**
     * Owner plus the escalation roles from Settings → Notifications.
     *
     * @return Collection<int, User>
     */
    private function recipients(Lead $lead): Collection
    {
        $users = User::query()
            ->with('telegramLink')
            ->where('is_active', true)
            ->whereHas('roles', fn (Builder $q) => $q->whereIn('name', $this->settings->unassigned_lead_roles))
            ->get();

        if ($lead->owner !== null && $lead->owner->is_active) {
            $users->push($lead->owner);
        }

        return $users->unique('id')->values();
    }
}
