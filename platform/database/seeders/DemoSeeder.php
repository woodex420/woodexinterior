<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Crm\Models\Contact;
use App\Domain\Crm\Models\Lead;
use App\Domain\Crm\Models\PipelineStage;
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

        if (Lead::query()->exists()) {
            return;
        }

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
}
