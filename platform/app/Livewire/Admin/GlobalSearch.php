<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Domain\Crm\Models\Contact;
use App\Domain\Crm\Models\Lead;
use App\Livewire\Concerns\InteractsWithAdmin;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * Command palette (GLB-002): Ctrl/⌘ K from anywhere. Finds contacts and leads by name, phone in any
 * format (0322…, +92 322…, 92322…), email or lead reference, within the user's visibility.
 */
class GlobalSearch extends Component
{
    use InteractsWithAdmin;

    public string $term = '';

    public function render(): View
    {
        $user = $this->user();
        $term = trim($this->term);

        /** @var Collection<int, Contact> $contacts */
        $contacts = collect();
        /** @var Collection<int, Lead> $leads */
        $leads = collect();

        if (mb_strlen($term) >= 2) {
            if ($user->can('contacts.view')) {
                $contacts = Contact::query()->visibleTo($user)->search($term)->withCount('leads')
                    ->orderByDesc('last_activity_at')->limit(6)->get();
            }

            if ($user->can('leads.view')) {
                $leads = Lead::query()->visibleTo($user)->with(['contact:id,name', 'stage'])
                    ->where(fn (Builder $q) => $q->where('ref', 'like', "%{$term}%")->orWhereHas('contact', fn (Builder $c) => $c->search($term)))
                    ->latest('id')->limit(6)->get();
            }
        }

        return view('livewire.admin.global-search', [
            'contacts' => $contacts,
            'leads' => $leads,
            'searched' => mb_strlen($term) >= 2,
        ]);
    }
}
