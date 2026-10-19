<?php

declare(strict_types=1);

use App\Domain\Crm\Actions\CaptureLead;
use App\Domain\Crm\Data\LeadInput;
use App\Domain\Crm\Enums\BusinessLine;
use App\Domain\Crm\Enums\LeadSource;
use App\Domain\Crm\Models\PipelineStage;
use App\Livewire\Admin\Crm\LeadsIndex;
use Livewire\Livewire;

/*
| The owner works the list, not a page per lead (request, 2026-10-20): the client drawer opens over the
| list, and stage/owner changes save in place without navigating away.
*/

beforeEach(function () {
    $this->owner = $this->userWithRole('owner');
    $this->actingAs($this->owner);

    $this->lead = app(CaptureLead::class)->handle(new LeadInput(
        name: 'Ayesha Khan',
        phone: '0322 4000768',
        email: null,
        source: LeadSource::WebsiteForm,
        businessLine: BusinessLine::Interior,
        city: 'Lahore',
        message: 'Wardrobe for the master bedroom.',
    ))->lead->refresh();
});

it('opens the client drawer without leaving the list', function () {
    Livewire::test(LeadsIndex::class)
        ->call('openLeadDrawer', $this->lead->id)
        ->assertSet('drawerId', $this->lead->id)
        ->assertSet('drawerStage', $this->lead->stage_id)
        ->assertSee('Ayesha Khan')
        ->call('closeLeadDrawer')
        ->assertSet('drawerId', null);
});

it('moves a lead to another stage from the drawer', function () {
    $contacted = PipelineStage::query()->where('key', 'contacted')->firstOrFail();

    Livewire::test(LeadsIndex::class)
        ->call('openLeadDrawer', $this->lead->id)
        ->set('drawerStage', $contacted->id)
        ->call('updateDrawerStage')
        ->assertHasNoErrors()
        ->assertSet('drawerStage', $contacted->id);

    expect($this->lead->refresh()->stage_id)->toBe($contacted->id);
});

it('reassigns the owner from the drawer', function () {
    $manager = $this->userWithRole('sales_manager');

    Livewire::test(LeadsIndex::class)
        ->call('openLeadDrawer', $this->lead->id)
        ->set('drawerOwner', $manager->id)
        ->call('updateDrawerOwner');

    expect($this->lead->refresh()->owner_id)->toBe($manager->id);
});

it('keeps the stage when a manager tries to mark a lead won without an accepted quotation', function () {
    $manager = $this->userWithRole('sales_manager');
    $this->actingAs($manager);

    $won = PipelineStage::query()->where('key', 'won')->firstOrFail();

    Livewire::test(LeadsIndex::class)
        ->call('openLeadDrawer', $this->lead->id)
        ->set('drawerStage', $won->id)
        ->call('updateDrawerStage')
        ->assertSet('drawerStage', $this->lead->stage_id);

    expect($this->lead->refresh()->stage_id)->not->toBe($won->id);
});
