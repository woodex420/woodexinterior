<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Crm;

use App\Domain\Crm\Actions\CaptureLead;
use App\Domain\Crm\Data\LeadInput;
use App\Domain\Crm\Enums\BusinessLine;
use App\Domain\Crm\Enums\LeadSource;
use App\Domain\Crm\Models\Lead;
use App\Domain\Crm\Models\PipelineStage;
use App\Models\User;
use App\Support\Phone;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Leads list with search (name / phone in any format / reference), filters and quick add (CRM-003, CAP-008).
 */
#[Layout('layouts::admin')]
#[Title('Leads')]
class LeadsIndex extends Component
{
    use WithPagination;

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
    }

    public function updating(string $name): void
    {
        if (in_array($name, ['search', 'stage', 'line', 'source', 'owner'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'stage', 'line', 'source', 'owner');
        $this->resetPage();
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
            'newSource' => ['required', Rule::in(['manual', 'phone_call', 'walk_in', 'referral', 'whatsapp', 'returning_client'])],
            'newMessage' => ['nullable', 'string', 'max:2000'],
        ], attributes: ['newName' => 'name', 'newPhone' => 'phone', 'newEmail' => 'email']);

        /** @var User $user */
        $user = Auth::user();

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
    }

    public function render(): View
    {
        /** @var User $user */
        $user = Auth::user();
        $term = trim($this->search);

        $leads = Lead::query()
            ->with(['contact', 'stage', 'owner'])
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
            ->latest('id')
            ->paginate(25);

        return view('livewire.admin.crm.leads-index', [
            'leads' => $leads,
            'stages' => PipelineStage::query()->ordered()->get(),
            'lines' => BusinessLine::options(),
            'sources' => LeadSource::options(),
            'hasFilters' => $term !== '' || $this->stage !== '' || $this->line !== '' || $this->source !== '' || $this->owner !== '',
        ]);
    }
}
