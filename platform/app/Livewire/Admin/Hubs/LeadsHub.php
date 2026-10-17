<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Hubs;

use App\Livewire\Admin\Crm\LeadsIndex;
use App\Livewire\Admin\Crm\LeadTrackingBoard;
use App\Livewire\Admin\Crm\PipelineBoard;

/**
 * P10.1: the Leads workflow page — list, board and the new tracking view on one page.
 */
final class LeadsHub extends Hub
{
    protected function hub(): array
    {
        return [
            'title' => 'Leads',
            'description' => 'Every enquiry, three working views: the list, the pipeline board and the follow-up tracking board.',
            'breadcrumbs' => ['CRM' => null, 'Leads' => null],
            'tabs' => [
                ['id' => 'list', 'label' => 'List', 'component' => LeadsIndex::class, 'permission' => 'leads.view'],
                ['id' => 'pipeline', 'label' => 'Pipeline', 'component' => PipelineBoard::class, 'permission' => 'leads.view'],
                ['id' => 'tracking', 'label' => 'Tracking', 'component' => LeadTrackingBoard::class, 'permission' => 'leads.view'],
            ],
        ];
    }
}
