<?php

use App\Domain\Conversations\Models\Conversation;
use App\Domain\Crm\Actions\CaptureLead;
use App\Domain\Crm\Data\LeadInput;
use App\Domain\Crm\Enums\ActivityType;
use App\Domain\Crm\Enums\BusinessLine;
use App\Domain\Crm\Enums\LeadSource;
use App\Domain\Crm\Models\Activity;
use App\Domain\Crm\Models\Lead;
use App\Domain\System\Enums\ApprovalStatus;
use App\Domain\System\Models\Approval;
use App\Livewire\Admin\Crm\LeadTrackingBoard;
use App\Models\User;
use App\Support\AdminNavigation;
use Illuminate\Support\Carbon;

use function Pest\Livewire\livewire;

/*
| P10.1 (docs/27): the sidebar consolidation — one page per workflow, tabs inside. The old URLs
| redirect to the right tab so bookmarks, notifications and the Telegram buttons keep working;
| a tab the user cannot open is hidden and a directly requested one aborts 403, the same promise
| the old route middleware made. The lead tracking board is event-driven like the P8.4 quote
| board: no new states, no new columns, passive alerts only (A0).
*/

beforeEach(function () {
    $this->owner = $this->userWithRole('owner');
    $this->sales = $this->userWithRole('sales');
});

function captureLead(int $sequence = 1): Lead
{
    $result = app(CaptureLead::class)->handle(new LeadInput(
        name: 'Ayesha Khan '.$sequence,
        phone: '0322 400076'.$sequence,
        email: null,
        source: LeadSource::WebsiteForm,
        businessLine: BusinessLine::Interior,
        city: 'Lahore',
        message: 'Wardrobe for the master bedroom.',
    ));

    return $result->lead->refresh();
}

it('consolidates the sidebar to the confirmed P10.1 map', function () {
    $groups = collect(AdminNavigation::for($this->owner))->keyBy('title');

    expect($groups->keys())->toContain('Overview', 'CRM', 'Sales', 'Accounts', 'System')
        ->and($groups->keys())->not->toContain('Reports');

    expect($groups['Overview']['items'])->toHaveCount(3)
        ->and(collect($groups['Overview']['items'])->pluck('label')->all())->toBe(['Dashboard', 'Approvals', 'Reports'])
        ->and($groups['CRM']['items'])->toHaveCount(3)
        ->and(collect($groups['CRM']['items'])->pluck('label')->all())->toBe(['Leads', 'Contacts', 'Tasks & Visits'])
        ->and($groups['Sales']['items'])->toHaveCount(5)
        ->and(collect($groups['Sales']['items'])->pluck('label')->all())->toBe(['Estimations', 'Quotations', 'Invoices', 'Payments', 'Documents']);

    $live = collect(AdminNavigation::for($this->owner))->flatMap(fn (array $g): array => $g['items'])->filter(fn (array $item): bool => $item['route'] !== null);
    expect($live)->toHaveCount(36);
});

it('renders every hub tab for the owner', function (string $path, string $marker) {
    $this->actingAs($this->owner)->get($path)->assertOk()->assertSee($marker);
})->with([
    'dashboard overview' => ['/admin', 'Dashboard'],
    'dashboard my day' => ['/admin?tab=my-day', 'My Day'],
    'reports funnel' => ['/admin/reports', 'Sales funnel'],
    'reports revenue' => ['/admin/reports?tab=revenue', 'Revenue'],
    'leads list' => ['/admin/leads', 'List'],
    'leads pipeline' => ['/admin/leads?tab=pipeline', 'Pipeline'],
    'leads tracking' => ['/admin/leads?tab=tracking', 'Lead tracking'],
    'contacts people' => ['/admin/contacts', 'People'],
    'contacts companies' => ['/admin/contacts?tab=companies', 'Companies'],
    'tasks' => ['/admin/tasks', 'Tasks'],
    'visits' => ['/admin/tasks?tab=visits', 'Site visits'],
    'quotes all' => ['/admin/quotes', 'All quotes'],
    'quote tracking' => ['/admin/quotes?tab=tracking', 'Quote tracking'],
    'quote templates' => ['/admin/quotes?tab=templates', 'Templates'],
    'invoices all' => ['/admin/invoices', 'All invoices'],
    'invoice tracking' => ['/admin/invoices?tab=tracking', 'Tracking'],
    'payments all' => ['/admin/payments', 'Payments'],
    'due payments' => ['/admin/payments?tab=dues', 'Due payments'],
    'receivables' => ['/admin/payments?tab=receivables', 'Receivables'],
    'documents descriptions' => ['/admin/sales/documents', 'Descriptions'],
    'document design' => ['/admin/sales/documents?tab=design', 'Document design'],
    'rate book' => ['/admin/sales/documents?tab=rate-book', 'Rate book'],
]);

it('redirects every merged URL to its tab', function (string $old, string $target) {
    $this->actingAs($this->owner)->get($old)->assertRedirect($target);
})->with([
    'my day' => ['/admin/my-day', '/admin?tab=my-day'],
    'pipeline' => ['/admin/pipeline', '/admin/leads?tab=pipeline'],
    'companies' => ['/admin/companies', '/admin/contacts?tab=companies'],
    'site visits' => ['/admin/site-visits', '/admin/tasks?tab=visits'],
    'quote templates' => ['/admin/quote-templates', '/admin/quotes?tab=templates'],
    'quote tracking' => ['/admin/sales/tracking/quotes', '/admin/quotes?tab=tracking'],
    'invoice tracking' => ['/admin/sales/tracking/invoices', '/admin/invoices?tab=tracking'],
    'due payments' => ['/admin/sales/dues', '/admin/payments?tab=dues'],
    'receivables' => ['/admin/receivables', '/admin/payments?tab=receivables'],
    'rate book' => ['/admin/rate-book', '/admin/sales/documents?tab=rate-book'],
    'description library' => ['/admin/description-library', '/admin/sales/documents?tab=descriptions'],
    'document design' => ['/admin/document-design', '/admin/sales/documents?tab=design'],
    'funnel' => ['/admin/reports/sales-funnel', '/admin/reports?tab=funnel'],
    'revenue collections' => ['/admin/reports/revenue-collections', '/admin/reports?tab=revenue'],
]);

it('hides tabs the user cannot open and refuses a directly requested one', function () {
    $this->actingAs($this->sales);

    // The revenue tab needs reports.finance: the tab bar hides it, the direct request aborts 403.
    $this->get('/admin/reports')->assertOk()->assertDontSee('Revenue & collections');
    $this->get('/admin/reports?tab=revenue')->assertForbidden();

    // Document design needs settings.manage — same promise on the documents hub.
    $this->get('/admin/sales/documents')->assertOk()->assertDontSee('Document design');
    $this->get('/admin/sales/documents?tab=design')->assertForbidden();
});

it('shows live sidebar badges for unread conversations and pending approvals', function () {
    $contact = captureLead(6)->contact;
    Conversation::factory()->create(['contact_id' => $contact->id, 'unread_count' => 3]);
    Conversation::factory()->create(['contact_id' => $contact->id, 'unread_count' => 1]);
    Approval::create([
        'approvable_type' => 'quote',
        'approvable_id' => 999999,
        'type' => 'quote',
        'requested_by' => $this->owner->id,
        'status' => ApprovalStatus::Pending,
    ]);

    $body = $this->actingAs($this->owner)->get('/admin/settings')->assertOk()->getContent();

    // Two conversations with unread messages, one quotation waiting for approval.
    expect(preg_match('/bg-brand-600[^>]*>\s*2\s*</u', $body))->toBe(1)
        ->and(preg_match('/bg-brand-600[^>]*>\s*1\s*</u', $body))->toBe(1);
});

it('classifies the lead tracking board without inventing states', function () {
    $this->actingAs($this->owner);
    Carbon::setTestNow(Carbon::parse('today 10:00'));

    $overdue = captureLead(1);
    $overdue->forceFill(['first_response_at' => now()->subDays(2), 'next_action_at' => now()->subDay()])->save();

    $today = captureLead(2);
    $today->forceFill(['first_response_at' => now()->subDays(2), 'next_action_at' => now()->copy()->setTime(14, 0)])->save();

    $snoozed = captureLead(3);
    $snoozed->forceFill(['first_response_at' => now()->subDays(2), 'next_action_at' => now()->addDays(5)])->save();

    $stale = captureLead(4);
    $stale->forceFill(['first_response_at' => now()->subDays(20), 'next_action_at' => null, 'last_activity_at' => now()->subDays(10)])->save();

    $healthy = captureLead(5);
    $healthy->forceFill(['first_response_at' => now()->subDays(1), 'next_action_at' => null, 'last_activity_at' => now()->subDay()])->save();

    livewire(LeadTrackingBoard::class)
        ->assertSet('group', 'overdue')
        ->assertSee($overdue->ref)
        ->call('showGroup', 'uncontacted')->assertSee('Uncontacted')
        ->call('showGroup', 'due_today')->assertSee($today->ref)
        ->call('showGroup', 'snoozed')->assertSee($snoozed->ref)
        ->call('showGroup', 'stale')->assertSee($stale->ref)
        ->assertDontSee($healthy->ref);
});

it('logs a follow-up onto the lead timeline and moves the next action', function () {
    $this->actingAs($this->owner);

    $lead = captureLead(7);
    $lead->forceFill(['owner_id' => $this->owner->id])->save();
    $next = now()->addDay()->startOfHour();

    livewire(LeadTrackingBoard::class)
        ->call('startLog', $lead->id)
        ->set('logType', 'call')
        ->set('logNote', 'Called — wants the revised estimate by Friday')
        ->set('logNextType', 'whatsapp')
        ->set('logNextAt', $next->format('Y-m-d\TH:i'))
        ->call('logFollowUp')
        ->assertHasNoErrors()
        ->assertSet('logLeadId', 0);

    $lead->refresh();

    expect(Activity::query()->where('lead_id', $lead->id)->where('type', ActivityType::Call->value)->where('body', 'Called — wants the revised estimate by Friday')->exists())->toBeTrue()
        ->and($lead->first_response_at)->not->toBeNull()
        ->and($lead->next_action_at?->format('Ymd Hi'))->toBe($next->format('Ymd Hi'));

    $this->assertDatabaseHas('leads', ['id' => $lead->id, 'next_action_type' => 'whatsapp']);
});

it('renders the tracking tab through the hub for the owner', function () {
    $lead = captureLead(8);

    $this->actingAs($this->owner)
        ->get('/admin/leads?tab=tracking')
        ->assertOk()
        ->assertSee('Lead tracking');
});
