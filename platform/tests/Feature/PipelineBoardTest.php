<?php

use App\Domain\Crm\Models\Activity;
use App\Domain\Crm\Models\Lead;
use App\Domain\Crm\Models\PipelineStage;
use App\Livewire\Admin\Crm\PipelineBoard;
use Livewire\Livewire;

beforeEach(function () {
    $this->manager = $this->userWithRole('sales_manager');
    $this->lead = Lead::factory()->create(['owner_id' => $this->manager->id]);
    $this->actingAs($this->manager);
});

it('moves a lead between open stages and records it on the timeline', function () {
    Livewire::test(PipelineBoard::class)
        ->call('moveLead', $this->lead->id, 'contacted')
        ->assertDispatched('toast');

    expect($this->lead->fresh()->stage->key)->toBe('contacted')
        ->and(Activity::query()->where('lead_id', $this->lead->id)->where('type', 'stage_change')->exists())->toBeTrue();
});

it('asks for a reason before marking a lead as lost', function () {
    Livewire::test(PipelineBoard::class)
        ->call('moveLead', $this->lead->id, 'lost')
        ->assertSet('pendingLeadId', $this->lead->id)
        ->call('confirmLost')
        ->assertHasErrors('lostReason')
        ->set('lostReason', 'Budget too high')
        ->call('confirmLost')
        ->assertSet('pendingLeadId', null);

    $lead = $this->lead->fresh();
    expect($lead->stage->key)->toBe('lost')
        ->and($lead->lost_reason)->toBe('Budget too high')
        ->and($lead->lost_at)->not->toBeNull();
});

it('refuses Won without an accepted quotation', function () {
    Livewire::test(PipelineBoard::class)
        ->call('moveLead', $this->lead->id, 'won')
        ->assertDispatched('toast', type: 'error');

    expect($this->lead->fresh()->stage_id)->toBe(PipelineStage::byKey('new')->id);
});

it('does not let a sales user move someone else\'s lead', function () {
    $other = $this->userWithRole('sales');

    $this->actingAs($other);

    Livewire::test(PipelineBoard::class)
        ->call('moveLead', $this->lead->id, 'contacted')
        ->assertForbidden();
});
