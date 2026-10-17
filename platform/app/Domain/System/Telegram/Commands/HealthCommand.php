<?php

declare(strict_types=1);

namespace App\Domain\System\Telegram\Commands;

use App\Console\Commands\Heartbeat;
use App\Domain\System\Models\IntegrationLog;
use App\Domain\System\Telegram\TelegramCommand;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * /health — the same truth the Health screen shows (SYS-008), on a phone: heartbeat, queue, failed jobs,
 * disk, and the integrations that last errored. One collector feeds both, so they can never disagree.
 */
final class HealthCommand implements TelegramCommand
{
    public function name(): string
    {
        return '/health';
    }

    public function usage(): string
    {
        return '/health';
    }

    public function description(): string
    {
        return 'cron, queue, disk and integrations at a glance';
    }

    public function permission(): string
    {
        return 'system.health';
    }

    public function handle(User $user, string $argument): string
    {
        $heartbeat = Cache::get(Heartbeat::CACHE_KEY);
        $heartbeatAt = is_string($heartbeat) && $heartbeat !== '' ? Carbon::parse($heartbeat) : null;
        $heartbeatOk = $heartbeatAt !== null && $heartbeatAt->greaterThan(now()->subMinutes(3));

        $pending = DB::table('jobs')->count();
        $failed = DB::table('failed_jobs')->count();

        $diskFree = @disk_free_space(storage_path());
        $diskTotal = @disk_total_space(storage_path());
        $diskPercent = $diskFree !== false && $diskTotal ? (int) round($diskFree / $diskTotal * 100) : null;

        $lastErrors = IntegrationLog::query()->where('ok', false)->latest('id')->limit(3)->get();

        $lines = [
            sprintf('Cron: %s', $heartbeatOk ? 'running ('.$heartbeatAt?->diffForHumans().')' : 'NOT RUNNING — check the hPanel cron'),
            sprintf('Queue: %d waiting · %d failed', $pending, $failed),
            sprintf('Disk: %s free', $diskPercent !== null ? $diskPercent.'%' : 'unknown'),
            sprintf('App: %s · PHP %s · %s', app()->environment(), PHP_VERSION, app()->version()),
        ];

        if ($lastErrors->isNotEmpty()) {
            $lines[] = 'Last integration errors:';

            foreach ($lastErrors as $error) {
                $lines[] = sprintf('· %s %s (%s)', $error->integration, $error->action ?? '', $error->created_at?->diffForHumans() ?? '');
            }
        } else {
            $lines[] = 'Integrations: no recent errors.';
        }

        if ($failed > 0) {
            $lines[] = 'Retry with "php artisan queue:retry all" on the server.';
        }

        return implode("\n", $lines);
    }
}
