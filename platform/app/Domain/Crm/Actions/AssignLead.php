<?php

declare(strict_types=1);

namespace App\Domain\Crm\Actions;

use App\Domain\Crm\Enums\ActivityType;
use App\Domain\Crm\Models\Activity;
use App\Domain\Crm\Models\Lead;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Auto-assignment (CRM-009, first version): least-loaded active sales user, falling back to the
 * sales manager. Rule-based routing by line/area/source arrives in P1 on top of this action.
 */
final class AssignLead
{
    /** @var list<string> */
    private const ASSIGNABLE_ROLES = ['sales', 'sales_manager'];

    public function handle(Lead $lead, ?User $to = null, ?User $by = null, ?string $reason = null): ?User
    {
        $owner = $to ?? $this->pickOwner();

        if ($owner === null) {
            return null;
        }

        $previous = $lead->owner_id;
        $lead->owner_id = $owner->id;
        $lead->save();

        Activity::create([
            'contact_id' => $lead->contact_id,
            'lead_id' => $lead->id,
            'user_id' => $by?->id,
            'type' => ActivityType::Assignment,
            'body' => $reason ?? ($by === null ? "Auto-assigned to {$owner->name}" : "Assigned to {$owner->name}"),
            'data' => ['from' => $previous, 'to' => $owner->id],
            'occurred_at' => now(),
        ]);

        return $owner;
    }

    private function pickOwner(): ?User
    {
        foreach (self::ASSIGNABLE_ROLES as $role) {
            $candidates = User::query()
                ->where('is_active', true)
                ->whereHas('roles', fn (Builder $q) => $q->where('name', $role))
                ->get();

            if ($candidates->isEmpty()) {
                continue;
            }

            $openCounts = Lead::query()
                ->open()
                ->whereIn('owner_id', $candidates->modelKeys())
                ->selectRaw('owner_id, count(*) as aggregate')
                ->groupBy('owner_id')
                ->pluck('aggregate', 'owner_id');

            return $candidates
                ->sortBy(fn (User $user): string => sprintf('%08d-%08d', (int) ($openCounts[$user->id] ?? 0), $user->id))
                ->first();
        }

        return null;
    }
}
