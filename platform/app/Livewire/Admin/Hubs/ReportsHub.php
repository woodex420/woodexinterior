<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Hubs;

use App\Livewire\Admin\Reports\RevenueCollections;
use App\Livewire\Admin\Reports\SalesFunnel;

/**
 * P10.1: the Reports page — the old Reports group folded into Overview (owner decision, 17 Oct).
 */
final class ReportsHub extends Hub
{
    protected function hub(): array
    {
        return [
            'title' => 'Reports',
            'description' => 'How the funnel moves and how the money lands — the two report views side by side.',
            'breadcrumbs' => ['Overview' => null, 'Reports' => null],
            'tabs' => [
                ['id' => 'funnel', 'label' => 'Sales funnel', 'component' => SalesFunnel::class, 'permission' => 'reports.view'],
                ['id' => 'revenue', 'label' => 'Revenue & collections', 'component' => RevenueCollections::class, 'permission' => 'reports.finance'],
            ],
        ];
    }
}
