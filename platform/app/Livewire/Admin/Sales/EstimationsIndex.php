<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Sales;

use App\Domain\Sales\Enums\EstimateStatus;
use App\Domain\Sales\Models\Estimate;
use App\Livewire\Concerns\InteractsWithAdmin;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * P8.3: the estimates list — every service line, one screen, oldest work at the top and the
 * status visible at a glance (draft → reviewed → converted). Building happens in the builder.
 */
#[Layout('layouts::admin')]
#[Title('Estimations')]
class EstimationsIndex extends Component
{
    use InteractsWithAdmin;

    public string $status = '';

    public function render(): View
    {
        $estimates = Estimate::query()
            ->with(['contact:id,name', 'quote:id,number,status', 'creator:id,name'])
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->orderByDesc('id')
            ->limit(60)
            ->get();

        return view('livewire.admin.sales.estimations-index', [
            'estimates' => $estimates,
            'statuses' => EstimateStatus::cases(),
        ]);
    }
}
