<?php

use App\Domain\Crm\Models\Contact;
use App\Domain\Crm\Models\Lead;
use App\Livewire\Admin\Crm\LeadsIndex;
use Livewire\Livewire;

it('finds a lead by phone typed in local format', function () {
    $manager = $this->userWithRole('sales_manager');
    $contact = Contact::factory()->create(['name' => 'Bilal Raza', 'phone' => '+923001112233']);
    Lead::factory()->for($contact)->create();
    Lead::factory()->create();

    $this->actingAs($manager);

    Livewire::test(LeadsIndex::class)
        ->set('search', '0300-1112233')
        ->assertSee('Bilal Raza')
        ->assertViewHas('leads', fn ($leads) => $leads->total() === 1);
});

it('creates a lead from the quick-add form through CaptureLead', function () {
    $sales = $this->userWithRole('sales');
    $this->actingAs($sales);

    Livewire::test(LeadsIndex::class)
        ->set('showCreate', true)
        ->set('newName', 'Walk-in Client')
        ->set('newPhone', '0333 7654321')
        ->set('newSource', 'walk_in')
        ->call('createLead')
        ->assertHasNoErrors()
        ->assertSet('showCreate', false)
        ->assertDispatched('toast');

    $lead = Lead::query()->sole();

    expect($lead->source->value)->toBe('walk_in')
        ->and($lead->owner_id)->toBe($sales->id)
        ->and($lead->contact->phone)->toBe('+923337654321');
});

it('limits sales users to their own leads', function () {
    $sales = $this->userWithRole('sales');
    Lead::factory()->create(['owner_id' => $sales->id]);
    Lead::factory()->count(3)->create();

    $this->actingAs($sales);

    Livewire::test(LeadsIndex::class)->assertViewHas('leads', fn ($leads) => $leads->total() === 1);
});
