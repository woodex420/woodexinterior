<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Domain\Crm\Actions\ManageTask;
use App\Domain\Crm\Enums\TaskStatus;
use App\Domain\Crm\Enums\VisitStatus;
use App\Domain\Crm\Models\Lead;
use App\Domain\Crm\Models\SiteVisit;
use App\Domain\Crm\Models\Task;
use App\Livewire\Concerns\InteractsWithAdmin;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * My Day (CRM-014): what needs doing now — uncontacted leads, overdue follow-ups, today's visits and tasks.
 */
#[Layout('layouts::admin')]
#[Title('My Day')]
class MyDay extends Component
{
    use InteractsWithAdmin;

    public function mount(): void
    {
        $this->authorize('viewAny', Lead::class);
    }

    public function completeTask(int $taskId): void
    {
        $task = Task::query()->where('assignee_id', $this->user()->id)->findOrFail($taskId);
        app(ManageTask::class)->complete($task);
        $this->toast('Task done.');
    }

    public function render(): View
    {
        $user = $this->user();
        $now = now();
        $endOfDay = $now->copy()->endOfDay();

        $mine = fn (): Builder => Lead::query()->open()->where('owner_id', $user->id)->with(['contact:id,name,phone,whatsapp', 'stage']);

        $uncontacted = $mine()->whereNull('first_response_at')->orderByRaw('sla_due_at is null')->orderBy('sla_due_at')->latest('id')->limit(20)->get();
        $uncontactedIds = $uncontacted->modelKeys();

        $overdue = $mine()->whereNotIn('id', $uncontactedIds)->where('next_action_at', '<', $now)->orderBy('next_action_at')->limit(30)->get();
        $today = $mine()->whereNotIn('id', $uncontactedIds)->whereBetween('next_action_at', [$now, $endOfDay])->orderBy('next_action_at')->limit(30)->get();

        $visits = SiteVisit::query()
            ->with(['contact:id,name,phone', 'lead:id,ref'])
            ->whereIn('status', [VisitStatus::Scheduled->value, VisitStatus::Confirmed->value, VisitStatus::Rescheduled->value])
            ->whereBetween('scheduled_at', [$now->copy()->startOfDay(), $endOfDay])
            ->where(fn (Builder $q) => $q->where('assigned_to', $user->id)->orWhereHas('lead', fn (Builder $l) => $l->where('owner_id', $user->id)))
            ->orderBy('scheduled_at')
            ->get();

        $tasks = Task::query()
            ->with('lead:id,ref,contact_id', 'lead.contact:id,name')
            ->where('assignee_id', $user->id)
            ->where('status', TaskStatus::Open->value)
            ->where(fn (Builder $q) => $q->whereNull('due_at')->orWhere('due_at', '<=', $endOfDay))
            ->orderByRaw('due_at is null')
            ->orderBy('due_at')
            ->limit(30)
            ->get();

        $unassigned = $user->can('leads.assign')
            ? Lead::query()->open()->whereNull('owner_id')->with('contact:id,name')->latest('id')->limit(10)->get()
            : collect();

        $hour = (int) $now->format('G');

        return view('livewire.admin.my-day', [
            'greeting' => $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening'),
            'uncontacted' => $uncontacted,
            'overdue' => $overdue,
            'today' => $today,
            'visits' => $visits,
            'tasks' => $tasks,
            'unassigned' => $unassigned,
        ]);
    }
}
