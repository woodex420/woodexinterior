<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Crm\Actions\LogLeadActivity;
use App\Domain\Crm\Actions\ManageTask;
use App\Domain\Crm\Actions\ScheduleSiteVisit;
use App\Domain\Crm\Actions\UpdateSiteVisit;
use App\Domain\Crm\Enums\ActivityType;
use App\Domain\Crm\Enums\CallOutcome;
use App\Domain\Crm\Enums\LeadPriority;
use App\Domain\Crm\Enums\TaskType;
use App\Domain\Crm\Models\Contact;
use App\Domain\Crm\Models\Lead;
use App\Domain\Crm\Models\PipelineStage;
use App\Domain\Crm\Models\SiteVisit;
use App\Domain\Crm\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Local demo data so the admin is not empty on a developer machine. Never runs outside APP_ENV=local.
 * Passwords are random and printed once.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->isLocal()) {
            throw new RuntimeException('DemoSeeder only runs with APP_ENV=local.');
        }

        $people = [
            ['Demo Owner', 'owner@woodex.test', 'owner'],
            ['Demo Sales Manager', 'manager@woodex.test', 'sales_manager'],
            ['Demo Sales One', 'sales1@woodex.test', 'sales'],
            ['Demo Sales Two', 'sales2@woodex.test', 'sales'],
            ['Demo Accountant', 'accounts@woodex.test', 'accountant'],
        ];

        $sales = [];

        foreach ($people as [$name, $email, $role]) {
            $password = Str::password(14, symbols: false);
            $user = User::query()->firstOrCreate(['email' => $email], ['name' => $name, 'password' => $password, 'is_active' => true]);

            if ($user->wasRecentlyCreated) {
                $this->command?->line("  {$email} / {$password} ({$role})");
            }

            $user->syncRoles([$role]);

            if ($role === 'sales') {
                $sales[] = $user;
            }
        }

        if (! Lead::query()->exists()) {
            $this->seedLeads($sales);
        }

        $this->seedWorkload();
    }

    /**
     * @param  list<User>  $sales
     */
    private function seedLeads(array $sales): void
    {
        $stages = PipelineStage::query()->pluck('id', 'key');
        $plan = ['new' => 6, 'contacted' => 5, 'site_visit' => 4, 'quotation' => 4, 'negotiation' => 2, 'on_hold' => 1, 'won' => 2, 'lost' => 2];

        foreach ($plan as $stageKey => $count) {
            Contact::factory()->count($count)->create()->each(function (Contact $contact) use ($stages, $stageKey, $sales): void {
                Lead::factory()->for($contact)->create([
                    'stage_id' => $stages[$stageKey],
                    'owner_id' => $sales === [] ? null : $sales[array_rand($sales)]->id,
                    'won_at' => $stageKey === 'won' ? now()->subDays(random_int(1, 20)) : null,
                    'lost_at' => $stageKey === 'lost' ? now()->subDays(random_int(1, 20)) : null,
                    'lost_reason' => $stageKey === 'lost' ? 'Budget too high' : null,
                ]);
            });
        }
    }

    /**
     * Calls, visits and tasks so My Day, Tasks and Site visits have something to show (P1).
     * Uses the real actions, so the data follows the same rules as the app.
     */
    private function seedWorkload(): void
    {
        if (SiteVisit::query()->exists() || Task::query()->exists()) {
            return;
        }

        $log = app(LogLeadActivity::class);
        $schedule = app(ScheduleSiteVisit::class);
        $visits = app(UpdateSiteVisit::class);
        $tasks = app(ManageTask::class);

        $leads = Lead::query()->with(['contact', 'stage', 'owner'])->orderBy('id')->get()->groupBy(fn (Lead $lead) => $lead->stage->key)->toBase();
        $addresses = ['House 14, Block K, DHA Phase 6', '22-C Gulberg III', 'Villa 7, Bahria Orchard', 'Plot 51, Johar Town', '3rd floor, MM Alam Road'];

        foreach ($leads->except(['new'])->flatten() as $i => $lead) {
            $owner = $lead->owner;
            $log->handle($lead, ActivityType::Call, $owner, 'Discussed scope, rough budget and timeline.', CallOutcome::Connected);

            if ($i % 2 === 0) {
                $log->handle($lead, ActivityType::WhatsApp, $owner, 'Shared portfolio and the kitchen catalogue.');
            }
        }

        foreach ($leads->get('new', collect())->take(2) as $lead) {
            $log->handle($lead, ActivityType::Call, $lead->owner, null, CallOutcome::NoAnswer);
        }

        // Upcoming and overdue visits for leads at the site-visit stage.
        $slots = [now()->setTime(12, 0), now()->setTime(16, 30), now()->addDay()->setTime(11, 0), now()->subDay()->setTime(15, 0)];

        foreach ($leads->get('site_visit', collect())->values() as $i => $lead) {
            $at = $slots[$i % count($slots)];
            $visit = $schedule->handle($lead, $at, 60, $addresses[$i % count($addresses)].', '.($lead->city ?? 'Lahore'), $lead->owner, $lead->owner);

            if ($i === 1) {
                $visits->confirm($visit, $lead->owner);
            }
        }

        // Completed visits with measurements for leads now at the quotation stage.
        foreach ($leads->get('quotation', collect())->take(2)->values() as $i => $lead) {
            $visit = $schedule->handle($lead, now()->subDays(2 + $i)->setTime(14, 0), 60, $addresses[($i + 2) % count($addresses)], $lead->owner, $lead->owner);
            $visits->complete($visit, 'Measured the main areas. Client likes warm oak with matte black fittings.', [
                ['room' => 'Kitchen', 'length_ft' => 14, 'width_ft' => 11],
                ['room' => 'Lounge', 'length_ft' => 22, 'width_ft' => 16],
                ['room' => 'Master bedroom', 'length_ft' => 16, 'width_ft' => 14],
            ], 'Share quotation', $lead->owner);
        }

        // A few everyday tasks.
        $open = $leads->except(['won', 'lost'])->flatten()->values();
        $examples = [
            ['Send revised kitchen layout', now()->subHours(5), TaskType::Email],
            ['Call back about wardrobe finishes', now()->addHours(2), TaskType::Call],
            ['Confirm site visit by WhatsApp', now()->addHours(4), TaskType::WhatsApp],
            ['Prepare material samples', now()->addDays(2)->setTime(12, 0), TaskType::Todo],
            ['Follow up on the design brief', now()->addDays(4)->setTime(11, 0), TaskType::Call],
        ];

        foreach ($examples as $i => [$title, $due, $type]) {
            $lead = $open[$i % max(1, $open->count())] ?? null;
            $tasks->create($title, $due, $lead?->owner, $lead?->owner, $lead, $type, $i === 0 ? LeadPriority::High : LeadPriority::Normal);
        }
    }
}
