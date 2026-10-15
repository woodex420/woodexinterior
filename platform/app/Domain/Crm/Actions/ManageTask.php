<?php

declare(strict_types=1);

namespace App\Domain\Crm\Actions;

use App\Domain\Crm\Enums\LeadPriority;
use App\Domain\Crm\Enums\TaskStatus;
use App\Domain\Crm\Enums\TaskType;
use App\Domain\Crm\Models\Lead;
use App\Domain\Crm\Models\Task;
use App\Models\User;
use Carbon\CarbonInterface;

/**
 * Create, complete and reopen follow-up tasks (CRM-007).
 */
final class ManageTask
{
    public function create(
        string $title,
        ?CarbonInterface $dueAt,
        ?User $assignee,
        ?User $by,
        ?Lead $lead = null,
        TaskType $type = TaskType::Todo,
        LeadPriority $priority = LeadPriority::Normal,
        ?string $description = null,
    ): Task {
        return Task::create([
            'title' => trim($title),
            'description' => $description,
            'type' => $type,
            'due_at' => $dueAt,
            'remind_at' => $dueAt?->copy()->subMinutes(30),
            'assignee_id' => $assignee->id ?? $lead->owner_id ?? $by?->id,
            'created_by' => $by?->id,
            'lead_id' => $lead?->id,
            'contact_id' => $lead?->contact_id,
            'priority' => $priority,
            'status' => TaskStatus::Open,
        ]);
    }

    public function complete(Task $task): Task
    {
        $task->forceFill(['status' => TaskStatus::Done, 'completed_at' => now()])->save();

        if ($task->lead_id !== null) {
            $task->lead()->update(['last_activity_at' => now()]);
        }

        return $task;
    }

    public function reopen(Task $task): Task
    {
        $task->forceFill(['status' => TaskStatus::Open, 'completed_at' => null])->save();

        return $task;
    }
}
