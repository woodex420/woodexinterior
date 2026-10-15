<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Crm;

use App\Domain\Crm\Actions\AssignLead;
use App\Domain\Crm\Actions\LogLeadActivity;
use App\Domain\Crm\Actions\ManageTask;
use App\Domain\Crm\Actions\MoveLeadToStage;
use App\Domain\Crm\Actions\ScheduleSiteVisit;
use App\Domain\Crm\Actions\SetNextAction;
use App\Domain\Crm\Enums\ActivityType;
use App\Domain\Crm\Enums\BusinessLine;
use App\Domain\Crm\Enums\CallOutcome;
use App\Domain\Crm\Enums\LeadPriority;
use App\Domain\Crm\Enums\NextActionType;
use App\Domain\Crm\Enums\StageType;
use App\Domain\Crm\Enums\TaskStatus;
use App\Domain\Crm\Models\Activity;
use App\Domain\Crm\Models\Lead;
use App\Domain\Crm\Models\PipelineStage;
use App\Livewire\Concerns\InteractsWithAdmin;
use App\Models\User;
use App\Settings\SalesSettings;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The lead workspace (CRM-003, CRM-006…CRM-010, CRM-016): log calls and messages in two taps, keep a
 * next action on every lead, book the site visit, and see the whole history on one timeline.
 *
 * @property-read Lead $lead
 */
#[Layout('layouts::admin')]
class LeadShow extends Component
{
    use InteractsWithAdmin;

    #[Locked]
    public int $leadId;

    // Quick log
    public string $logType = 'call';

    public string $logOutcome = 'connected';

    public string $logBody = '';

    // Next action
    public string $nextType = 'call';

    public string $nextAt = '';

    // Site visit
    public bool $showVisit = false;

    public string $visitAt = '';

    public int $visitDuration = 60;

    public string $visitAddress = '';

    public string $visitAssignee = '';

    // Tasks
    public string $taskTitle = '';

    public string $taskDue = '';

    // Details
    public bool $showDetails = false;

    /** @var array<string, string|int|null> */
    public array $details = [];

    // Lost
    public bool $showLost = false;

    public string $lostReason = '';

    public function mount(Lead $lead): void
    {
        $this->authorize('view', $lead);

        $this->leadId = $lead->id;
        $this->fillNextAction($lead);
    }

    #[Computed]
    public function lead(): Lead
    {
        return Lead::query()->with(['contact.tags', 'stage', 'owner', 'company'])->findOrFail($this->leadId);
    }

    public function logActivity(): void
    {
        $lead = $this->lead;
        $this->authorize('update', $lead);

        $this->validate([
            'logType' => ['required', Rule::in(['call', 'whatsapp', 'email', 'meeting', 'note'])],
            'logOutcome' => [Rule::requiredIf($this->logType === 'call'), 'nullable', Rule::enum(CallOutcome::class)],
            'logBody' => [Rule::requiredIf($this->logType === 'note'), 'nullable', 'string', 'max:5000'],
        ], attributes: ['logBody' => 'note', 'logOutcome' => 'call outcome']);

        $type = ActivityType::from($this->logType);
        $outcome = $type === ActivityType::Call ? CallOutcome::from($this->logOutcome) : null;
        $action = app(LogLeadActivity::class);

        $action->handle($lead, $type, $this->user(), $this->logBody, $outcome);

        $this->reset('logBody');
        unset($this->lead);

        if ($type === ActivityType::Call && $action->consecutiveNoAnswers($lead) >= 3) {
            $this->toast('Three calls without an answer. Try a WhatsApp message instead.', 'warning');

            return;
        }

        $this->toast(match ($type) {
            ActivityType::Call => 'Call logged: '.$outcome?->label().'.',
            ActivityType::Note => 'Note added.',
            default => $type->label().' logged.',
        });
    }

    public function saveNextAction(): void
    {
        $this->authorize('update', $this->lead);

        $this->validate([
            'nextType' => ['required', Rule::enum(NextActionType::class)],
            'nextAt' => ['required', 'date'],
        ], attributes: ['nextAt' => 'date and time']);

        app(SetNextAction::class)->handle($this->lead, NextActionType::from($this->nextType), Carbon::parse($this->nextAt));

        unset($this->lead);
        $this->toast('Next action saved.');
    }

    public function quickNext(string $preset): void
    {
        $now = CarbonImmutable::now();

        $at = match ($preset) {
            '1h' => $now->addHour()->startOfMinute(),
            'tomorrow' => $now->addDay()->setTime(11, 0),
            '3d' => $now->addDays(3)->setTime(11, 0),
            'week' => $now->addWeek()->setTime(11, 0),
            default => null,
        };

        if ($at === null) {
            return;
        }

        $this->nextAt = $at->format('Y-m-d\TH:i');
        $this->saveNextAction();
    }

    public function moveTo(string $stageKey): void
    {
        $lead = $this->lead;
        $this->authorize('update', $lead);

        $stage = PipelineStage::query()->where('key', $stageKey)->firstOrFail();

        if ($stage->type === StageType::Lost) {
            $this->showLost = true;
            $this->lostReason = '';

            return;
        }

        try {
            app(MoveLeadToStage::class)->handle($lead, $stage, $this->user());
            unset($this->lead);
            $this->toast("Moved to {$stage->name}.");
        } catch (ValidationException $exception) {
            $this->toastValidation($exception);
        }
    }

    public function confirmLost(): void
    {
        $lead = $this->lead;
        $this->authorize('update', $lead);

        $this->validate(['lostReason' => ['required', 'string', 'max:60']], attributes: ['lostReason' => 'reason']);

        app(MoveLeadToStage::class)->handle($lead, PipelineStage::byKey('lost'), $this->user(), $this->lostReason);

        $this->reset('showLost', 'lostReason');
        unset($this->lead);
        $this->toast('Lead marked as lost.');
    }

    public function assignTo(string $userId): void
    {
        $this->authorize('assign', Lead::class);

        $to = $userId === '' ? null : User::query()->where('is_active', true)->findOrFail((int) $userId);

        app(AssignLead::class)->handle($this->lead, $to, $this->user(), $to ? null : 'Unassigned');

        unset($this->lead);
        $this->toast($to ? "Assigned to {$to->name}." : 'Lead unassigned.');
    }

    public function openVisit(): void
    {
        $lead = $this->lead;
        $this->authorize('update', $lead);

        $this->visitAt = CarbonImmutable::now()->addDay()->setTime(11, 0)->format('Y-m-d\TH:i');
        $this->visitDuration = 60;
        $this->visitAddress = (string) ($lead->address ?? trim(($lead->area ?? '').', '.($lead->city ?? ''), ', '));
        $this->visitAssignee = (string) ($lead->owner_id ?? $this->user()->id);
        $this->resetValidation();
        $this->showVisit = true;
    }

    public function scheduleVisit(): void
    {
        $lead = $this->lead;
        $this->authorize('update', $lead);

        $this->validate([
            'visitAt' => ['required', 'date', 'after:now'],
            'visitDuration' => ['required', 'integer', 'between:15,480'],
            'visitAddress' => ['nullable', 'string', 'max:255'],
            'visitAssignee' => ['nullable', Rule::exists('users', 'id')->where('is_active', true)],
        ], attributes: ['visitAt' => 'visit time', 'visitAssignee' => 'team member']);

        $assignee = $this->visitAssignee !== '' ? User::query()->find((int) $this->visitAssignee) : null;

        app(ScheduleSiteVisit::class)->handle(
            $lead,
            Carbon::parse($this->visitAt),
            $this->visitDuration,
            $this->visitAddress !== '' ? $this->visitAddress : null,
            $assignee,
            $this->user(),
        );

        $this->reset('showVisit', 'visitAt', 'visitAddress', 'visitAssignee');
        unset($this->lead);
        $this->fillNextAction($this->lead);
        $this->toast('Site visit scheduled.');
    }

    public function addTask(): void
    {
        $lead = $this->lead;
        $this->authorize('update', $lead);

        $this->validate([
            'taskTitle' => ['required', 'string', 'max:200'],
            'taskDue' => ['nullable', 'date'],
        ], attributes: ['taskTitle' => 'task', 'taskDue' => 'due time']);

        app(ManageTask::class)->create(
            $this->taskTitle,
            $this->taskDue !== '' ? Carbon::parse($this->taskDue) : null,
            $lead->owner,
            $this->user(),
            $lead,
        );

        $this->reset('taskTitle', 'taskDue');
        $this->toast('Task added.');
    }

    public function toggleTask(int $taskId): void
    {
        $lead = $this->lead;
        $this->authorize('update', $lead);

        $task = $lead->tasks()->findOrFail($taskId);
        $manage = app(ManageTask::class);

        $task->status === TaskStatus::Done ? $manage->reopen($task) : $manage->complete($task);
    }

    public function editDetails(): void
    {
        $lead = $this->lead;
        $this->authorize('update', $lead);

        $this->details = [
            'business_line' => $lead->business_line->value,
            'service' => $lead->service,
            'property_type' => $lead->property_type,
            'city' => $lead->city,
            'area' => $lead->area,
            'address' => $lead->address,
            'area_sqft' => $lead->area_sqft,
            'budget_band' => $lead->budget_band,
            'timeline' => $lead->timeline,
            'priority' => $lead->priority->value,
            'expected_value' => $lead->expected_value_minor !== null ? intdiv((int) $lead->expected_value_minor, 100) : null,
            'referrer_name' => $lead->referrer_name,
        ];
        $this->resetValidation();
        $this->showDetails = true;
    }

    public function saveDetails(): void
    {
        $lead = $this->lead;
        $this->authorize('update', $lead);

        $data = $this->validate([
            'details.business_line' => ['required', Rule::enum(BusinessLine::class)],
            'details.service' => ['nullable', 'string', 'max:60'],
            'details.property_type' => ['nullable', 'string', 'max:30'],
            'details.city' => ['nullable', 'string', 'max:80'],
            'details.area' => ['nullable', 'string', 'max:120'],
            'details.address' => ['nullable', 'string', 'max:255'],
            'details.area_sqft' => ['nullable', 'integer', 'min:1', 'max:10000000'],
            'details.budget_band' => ['nullable', Rule::in(array_keys(app(SalesSettings::class)->budget_bands))],
            'details.timeline' => ['nullable', Rule::in(array_keys(self::timelines()))],
            'details.priority' => ['required', Rule::enum(LeadPriority::class)],
            'details.expected_value' => ['nullable', 'integer', 'min:0', 'max:100000000000'],
            'details.referrer_name' => ['nullable', 'string', 'max:160'],
        ], attributes: [
            'details.area_sqft' => 'size (sq ft)',
            'details.expected_value' => 'expected value',
        ])['details'];

        $expected = $data['expected_value'] ?? null;
        unset($data['expected_value']);

        $lead->fill(array_map(fn ($value) => $value === '' ? null : $value, $data));
        $lead->expected_value_minor = $expected === null || $expected === '' ? null : Money::ofRupees((int) $expected)->minor;
        $lead->save();

        $this->showDetails = false;
        unset($this->lead);
        $this->toast('Lead details saved.');
    }

    public function render(): View
    {
        $lead = $this->lead;
        $sales = app(SalesSettings::class);

        $activities = Activity::query()
            ->with('user:id,name')
            ->where(function (Builder $q) use ($lead): void {
                $q->where('lead_id', $lead->id)
                    ->orWhere(fn (Builder $c) => $c->where('contact_id', $lead->contact_id)->whereNull('lead_id'));
            })
            ->latest('occurred_at')
            ->latest('id')
            ->limit(60)
            ->get();

        return view('livewire.admin.crm.lead-show', [
            'lead' => $lead,
            'stages' => PipelineStage::query()->ordered()->get(),
            'activities' => $activities,
            'openTasks' => $lead->tasks()->with('assignee:id,name')->where('status', TaskStatus::Open->value)->orderByRaw('due_at is null')->orderBy('due_at')->get(),
            'doneTasks' => $lead->tasks()->where('status', TaskStatus::Done->value)->latest('completed_at')->limit(5)->get(),
            'visits' => $lead->siteVisits()->with('assignee:id,name')->latest('scheduled_at')->get(),
            'otherLeads' => Lead::query()->with('stage')->where('contact_id', $lead->contact_id)->whereKeyNot($lead->id)->latest('id')->get(),
            'staff' => $this->staff(),
            'callOutcomes' => CallOutcome::options(),
            'nextTypes' => NextActionType::options(),
            'budgetBands' => $sales->budget_bands,
            'lostReasons' => $sales->lost_reasons,
            'timelines' => self::timelines(),
            'lines' => BusinessLine::options(),
            'priorities' => LeadPriority::options(),
            'lakh' => $sales->lakh_grouping,
        ])->title($lead->contact->name.' · '.$lead->ref);
    }

    /**
     * @return array<string, string>
     */
    public static function timelines(): array
    {
        return [
            'asap' => 'As soon as possible',
            '1-3-months' => 'In 1–3 months',
            '3-6-months' => 'In 3–6 months',
            'later' => 'Later / just exploring',
        ];
    }

    private function fillNextAction(Lead $lead): void
    {
        $this->nextType = $lead->next_action_type->value ?? NextActionType::Call->value;
        $this->nextAt = ($lead->next_action_at ?? CarbonImmutable::now()->addHour()->startOfHour())->format('Y-m-d\TH:i');
    }
}
