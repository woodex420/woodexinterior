<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Crm;

use App\Domain\Crm\Actions\AssignLead;
use App\Domain\Crm\Actions\CaptureLead;
use App\Domain\Crm\Actions\MoveLeadToStage;
use App\Domain\Crm\Data\LeadInput;
use App\Domain\Crm\Enums\BusinessLine;
use App\Domain\Crm\Enums\LeadSource;
use App\Domain\Crm\Enums\StageType;
use App\Domain\Crm\Models\Contact;
use App\Domain\Crm\Models\Lead;
use App\Domain\Crm\Models\PipelineStage;
use App\Livewire\Concerns\HasSavedViews;
use App\Livewire\Concerns\InteractsWithAdmin;
use App\Models\User;
use App\Support\Money;
use App\Support\Phone;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Leads list (CRM-003, CAP-008, GLB-003): search by name / phone in any format / reference, filters,
 * sort, page size, column chooser, saved views, CSV export and bulk assign / move / delete.
 */
#[Layout('layouts::admin')]
#[Title('Leads')]
class LeadsIndex extends Component
{
    use HasSavedViews, InteractsWithAdmin, WithPagination;

    /** @var array<string, string> optional columns */
    public const COLUMNS = ['contact' => 'Contact', 'line' => 'Line · Service', 'source' => 'Source', 'stage' => 'Stage', 'owner' => 'Owner', 'score' => 'Score', 'next' => 'Next action'];

    /** @var array<string, string> */
    public const SORTS = ['newest' => 'Newest first', 'oldest' => 'Oldest first', 'score' => 'Highest score', 'next_action' => 'Next action due', 'name' => 'Name A–Z', 'value' => 'Expected value'];

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $stage = '';

    #[Url(except: '')]
    public string $line = '';

    #[Url(except: '')]
    public string $source = '';

    #[Url(except: '')]
    public string $owner = '';

    #[Url(except: 'newest')]
    public string $sort = 'newest';

    #[Url(except: 25)]
    public int $perPage = 25;

    /** @var list<string> */
    public array $columns = ['contact', 'line', 'source', 'stage', 'owner', 'next'];

    /** @var list<int|string> lead ids ticked for bulk actions */
    public array $selected = [];

    public bool $showCreate = false;

    public string $newName = '';

    public string $newPhone = '';

    public string $newEmail = '';

    public string $newLine = 'interior';

    public string $newService = '';

    public string $newSource = 'manual';

    public string $newMessage = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Lead::class);
        $this->applyDefaultView();
    }

    public function updating(string $name): void
    {
        if (in_array($name, ['search', 'stage', 'line', 'source', 'owner', 'sort', 'perPage'], true)) {
            $this->resetPage();
            $this->selected = [];
        }
    }

    public function updatedPerPage(): void
    {
        $this->perPage = in_array($this->perPage, [25, 50, 100], true) ? $this->perPage : 25;
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'stage', 'line', 'source', 'owner', 'sort', 'selected');
        $this->resetPage();
    }

    public function toggleColumn(string $column): void
    {
        if (! array_key_exists($column, self::COLUMNS)) {
            return;
        }

        $this->columns = in_array($column, $this->columns, true)
            ? array_values(array_diff($this->columns, [$column]))
            : array_values(array_intersect(array_keys(self::COLUMNS), [...$this->columns, $column]));
    }

    protected function savedViewScreen(): string
    {
        return 'leads';
    }

    protected function savedViewProperties(): array
    {
        return ['search', 'stage', 'line', 'source', 'owner', 'sort', 'perPage', 'columns'];
    }

    public function bulkAssign(string $userId): void
    {
        $this->authorize('assign', Lead::class);
        $to = User::query()->where('is_active', true)->findOrFail((int) $userId);

        $count = 0;
        foreach ($this->selectedLeads() as $lead) {
            if ($lead->owner_id !== $to->id) {
                app(AssignLead::class)->handle($lead, $to, $this->user(), "Bulk assigned to {$to->name}");
                $count++;
            }
        }

        $this->selected = [];
        $this->toast("{$count} ".str('lead')->plural($count)." assigned to {$to->name}.");
    }

    public function bulkMove(string $stageKey): void
    {
        $stage = PipelineStage::query()->where('key', $stageKey)->where('type', StageType::Open->value)->firstOrFail();

        $moved = 0;
        foreach ($this->selectedLeads() as $lead) {
            if ($this->user()->can('update', $lead) && $lead->stage_id !== $stage->id && $lead->stage->type === StageType::Open) {
                app(MoveLeadToStage::class)->handle($lead, $stage, $this->user());
                $moved++;
            }
        }

        $this->selected = [];
        $this->toast("{$moved} ".str('lead')->plural($moved)." moved to {$stage->name}.");
    }

    public function bulkDelete(): void
    {
        $leads = $this->selectedLeads();
        foreach ($leads as $lead) {
            $this->authorize('delete', $lead);
        }

        $leads->each->delete();
        $this->selected = [];
        $this->toast($leads->count().' '.str('lead')->plural($leads->count()).' moved to the recycle bin.');
    }

    public function export(): StreamedResponse
    {
        abort_unless($this->user()->can('leads.export'), 403);

        $lakh = false;
        $query = $this->query()->with(['contact', 'stage', 'owner'])->limit(10000);
        $clean = static function (mixed $value): string {
            $value = (string) $value;

            // CSV injection guard: spreadsheet formulas never run from exported client data.
            return preg_match('/^[=+\-@\t\r]/', $value) && ! preg_match('/^\+\d+$/', $value) ? "'".$value : $value;
        };

        return response()->streamDownload(function () use ($query, $clean, $lakh): void {
            $out = fopen('php://output', 'wb');
            fputcsv($out, ['Reference', 'Created', 'Name', 'Phone', 'Email', 'Line', 'Service', 'Source', 'Stage', 'Owner', 'Score', 'Budget', 'Area (sq ft)', 'City', 'Area', 'Next action', 'Next action at', 'Expected value'], escape: '');

            foreach ($query->lazy(500) as $lead) {
                /** @var Lead $lead */
                fputcsv($out, array_map($clean, [
                    $lead->ref,
                    $lead->created_at?->format('Y-m-d H:i'),
                    $lead->contact->name,
                    $lead->contact->phone,
                    $lead->contact->email,
                    $lead->business_line->label(),
                    $lead->service,
                    $lead->source->label(),
                    $lead->stage->name,
                    $lead->owner->name ?? '',
                    $lead->score,
                    $lead->budget_band,
                    $lead->area_sqft,
                    $lead->city,
                    $lead->area,
                    $lead->next_action_type?->label(),
                    $lead->next_action_at?->format('Y-m-d H:i'),
                    $lead->expected_value_minor !== null ? Money::ofMinor($lead->expected_value_minor)->format($lakh) : '',
                ]), escape: '');
            }

            fclose($out);
        }, 'woodex-leads-'.now()->format('Y-m-d-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return Collection<int, Lead>
     */
    private function selectedLeads(): Collection
    {
        $ids = array_values(array_filter(array_map('intval', $this->selected)));

        return $this->query()->with(['stage', 'owner'])->whereIn('leads.id', $ids)->get();
    }

    /**
     * The filtered, permission-scoped query shared by the table, export and bulk actions.
     *
     * @return Builder<Lead>
     */
    private function query(): Builder
    {
        $user = $this->user();
        $term = trim($this->search);

        return Lead::query()
            ->when(! $user->can('leads.view_all'), fn (Builder $q) => $q->where('owner_id', $user->id))
            ->when($term !== '', function (Builder $q) use ($term): void {
                $q->where(function (Builder $inner) use ($term): void {
                    $inner->where('ref', 'like', "%{$term}%")
                        ->orWhereHas('contact', fn (Builder $contact) => $contact->search($term));
                });
            })
            ->when($this->stage !== '', fn (Builder $q) => $q->whereHas('stage', fn (Builder $s) => $s->where('key', $this->stage)))
            ->when($this->line !== '', fn (Builder $q) => $q->where('business_line', $this->line))
            ->when($this->source !== '', fn (Builder $q) => $q->where('source', $this->source))
            ->when($this->owner === 'me', fn (Builder $q) => $q->where('owner_id', $user->id))
            ->when($this->owner === 'unassigned', fn (Builder $q) => $q->whereNull('owner_id'))
            ->tap(fn (Builder $q) => match ($this->sort) {
                'oldest' => $q->oldest('id'),
                'score' => $q->orderByDesc('score')->latest('id'),
                'next_action' => $q->orderByRaw('next_action_at is null')->orderBy('next_action_at'),
                'name' => $q->orderBy(Contact::query()->select('name')->whereColumn('contacts.id', 'leads.contact_id')->limit(1)),
                'value' => $q->orderByRaw('expected_value_minor is null')->orderByDesc('expected_value_minor'),
                default => $q->latest('id'),
            });
    }

    public function createLead(): void
    {
        $this->authorize('create', Lead::class);

        $this->validate([
            'newName' => ['required', 'string', 'min:2', 'max:160'],
            'newPhone' => ['nullable', 'required_without:newEmail', 'string', 'max:30', function (string $attribute, mixed $value, Closure $fail): void {
                if (filled($value) && ! Phone::isValid((string) $value)) {
                    $fail('Enter a valid phone number, e.g. 0322 4000768.');
                }
            }],
            'newEmail' => ['nullable', 'required_without:newPhone', 'email', 'max:160'],
            'newLine' => ['required', Rule::enum(BusinessLine::class)],
            'newService' => ['nullable', 'string', 'max:60'],
            'newSource' => ['required', Rule::in(array_map(fn (LeadSource $s) => $s->value, LeadSource::manualSources()))],
            'newMessage' => ['nullable', 'string', 'max:2000'],
        ], attributes: ['newName' => 'name', 'newPhone' => 'phone', 'newEmail' => 'email']);

        $user = $this->user();

        $result = app(CaptureLead::class)->handle(new LeadInput(
            name: $this->newName,
            phone: $this->newPhone !== '' ? $this->newPhone : null,
            email: $this->newEmail !== '' ? $this->newEmail : null,
            source: LeadSource::from($this->newSource),
            businessLine: BusinessLine::from($this->newLine),
            service: $this->newService !== '' ? $this->newService : null,
            message: $this->newMessage !== '' ? $this->newMessage : null,
            ownerId: $user->hasRole('sales') ? $user->id : null,
        ));

        $this->reset('showCreate', 'newName', 'newPhone', 'newEmail', 'newService', 'newMessage');
        $this->newLine = 'interior';
        $this->newSource = 'manual';

        $this->dispatch('toast',
            message: $result->leadCreated
                ? "Lead {$result->lead->ref} created."
                : "This contact already has an open lead ({$result->lead->ref}); the enquiry was added to it.",
            type: $result->leadCreated ? 'success' : 'warning',
        );

        $this->redirectRoute('admin.leads.show', $result->lead, navigate: true);
    }

    public function render(): View
    {
        $user = $this->user();
        $leads = $this->query()->with(['contact', 'stage', 'owner'])->paginate(in_array($this->perPage, [25, 50, 100], true) ? $this->perPage : 25);

        return view('livewire.admin.crm.leads-index', [
            'leads' => $leads,
            'stages' => PipelineStage::query()->ordered()->get(),
            'lines' => BusinessLine::options(),
            'sources' => LeadSource::options(),
            'hasFilters' => trim($this->search) !== '' || $this->stage !== '' || $this->line !== '' || $this->source !== '' || $this->owner !== '',
            'views' => $this->savedViews(),
            'staff' => $user->can('leads.assign') ? $this->staff() : collect(),
            'canExport' => $user->can('leads.export'),
            'canDelete' => $user->can('leads.delete'),
        ]);
    }
}
