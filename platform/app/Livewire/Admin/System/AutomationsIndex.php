<?php

declare(strict_types=1);

namespace App\Livewire\Admin\System;

use App\Domain\System\Models\Automation;
use App\Domain\System\Models\ScheduledTaskRun;
use App\Livewire\Concerns\InteractsWithAdmin;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * P8.5 part 1: the automation control dashboard. One screen answers "what runs by itself, is it
 * on, and did it last run?" — the catalog is code, the on/off rows are the only stored state,
 * and the run history is the scheduler's own record (ScheduledTaskRun), so this screen can
 * never claim health it did not observe.
 */
#[Layout('layouts::admin')]
#[Title('Automations')]
class AutomationsIndex extends Component
{
    use InteractsWithAdmin;

    public function toggle(string $key): void
    {
        $entry = collect(Automation::catalog())->firstWhere('key', $key);

        abort_unless($entry !== null && $entry['toggleable'], 403);

        $enabled = Automation::toggle($key, $this->user());

        activity('system')
            ->causedBy($this->user())
            ->withProperties(['automation' => $key, 'enabled' => $enabled])
            ->log(($enabled ? 'Resumed automation ' : 'Paused automation ').$entry['name'].'.');
    }

    public function render(): View
    {
        $rows = Automation::query()->get()->keyBy('key');
        $runs = ScheduledTaskRun::latestPerTask();

        $automations = collect(Automation::catalog())->map(static function (array $entry) use ($rows, $runs): array {
            $row = $rows->get($entry['key']);
            $run = $runs[$entry['key']] ?? null;

            return $entry + [
                'enabled' => $row->is_enabled ?? true,
                'toggled_by' => $row?->toggled_by,
                'toggled_at' => $row?->toggled_at,
                'last_run' => $run?->ran_at,
                'last_runtime_ms' => $run?->runtime_ms,
            ];
        })->all();

        return view('livewire.admin.system.automations', [
            'automations' => $automations,
            'canToggle' => $this->user()->can('automation.manage'),
        ]);
    }
}
