<?php

declare(strict_types=1);

namespace App\Domain\Crm\Jobs;

use App\Domain\Crm\Models\Lead;
use App\Domain\Crm\Notifications\NewLeadNotification;
use App\Models\User;
use App\Settings\NotificationSettings;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification;

/**
 * Notify the lead owner (or the managers when unassigned) in-app and on Telegram (TGM-002).
 */
final class SendLeadAlert implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $leadId,
        public readonly bool $isNewLead,
    ) {}

    public function handle(NotificationSettings $settings): void
    {
        $lead = Lead::query()->with(['contact', 'owner', 'stage'])->find($this->leadId);

        if ($lead === null) {
            return;
        }

        $recipients = $this->recipients($lead, $settings);

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new NewLeadNotification($lead, $this->isNewLead));
        }
    }

    /**
     * @return Collection<int, User>
     */
    private function recipients(Lead $lead, NotificationSettings $settings): Collection
    {
        if ($lead->owner !== null && $lead->owner->is_active) {
            return new Collection([$lead->owner]);
        }

        return User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn (Builder $q) => $q->whereIn('name', $settings->unassigned_lead_roles))
            ->get();
    }
}
