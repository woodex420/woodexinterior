<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Hubs;

use App\Livewire\Admin\Dashboard;
use App\Livewire\Admin\MyDay;

/**
 * P10.1: the Overview workflow page — what is happening and what needs doing now.
 */
final class DashboardHub extends Hub
{
    protected function hub(): array
    {
        return [
            'title' => 'Dashboard',
            'description' => 'The studio at a glance, and your day — the KPI overview and the work queue on one page.',
            'breadcrumbs' => ['Overview' => null, 'Dashboard' => null],
            'tabs' => [
                ['id' => 'overview', 'label' => 'Overview', 'component' => Dashboard::class, 'permission' => 'dashboard.view'],
                ['id' => 'my-day', 'label' => 'My Day', 'component' => MyDay::class, 'permission' => 'leads.view'],
            ],
        ];
    }
}
