<?php

declare(strict_types=1);

namespace App\Livewire\Admin\System;

use App\Console\Commands\Heartbeat;
use App\Domain\System\Models\IntegrationLog;
use App\Integrations\Telegram\Contracts\TelegramGateway;
use App\Integrations\Turnstile\TurnstileVerifier;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * System health (SYS-008, first version): cron heartbeat, queue, failed jobs, disk, integrations.
 */
#[Layout('layouts::admin')]
#[Title('System health')]
class Health extends Component
{
    public function render(): View
    {
        $heartbeat = Cache::get(Heartbeat::CACHE_KEY);
        $heartbeatAt = is_string($heartbeat) ? CarbonImmutable::parse($heartbeat) : null;

        $diskFree = @disk_free_space(storage_path());
        $diskTotal = @disk_total_space(storage_path());

        $checks = [
            [
                'label' => 'Cron heartbeat',
                'ok' => $heartbeatAt !== null && $heartbeatAt->greaterThan(now()->subMinutes(3)),
                'value' => $heartbeatAt?->diffForHumans() ?? 'never',
                'hint' => 'hPanel cron must run "artisan schedule:run" every minute.',
            ],
            [
                'label' => 'Queued jobs waiting',
                'ok' => ($pending = DB::table('jobs')->count()) < 100,
                'value' => (string) $pending,
                'hint' => 'Drained by the scheduler every minute.',
            ],
            [
                'label' => 'Failed jobs',
                'ok' => ($failed = DB::table('failed_jobs')->count()) === 0,
                'value' => (string) $failed,
                'hint' => 'Retry with "php artisan queue:retry all".',
            ],
            [
                'label' => 'Disk space free',
                'ok' => $diskFree !== false && $diskTotal ? ($diskFree / $diskTotal) > 0.15 : true,
                'value' => $diskFree !== false ? number_format($diskFree / 1024 / 1024 / 1024, 1).' GB' : 'n/a',
                'hint' => 'Keep at least 15% free for backups and uploads.',
            ],
            [
                'label' => 'Debug mode off in production',
                'ok' => ! (app()->isProduction() && config('app.debug')),
                'value' => config('app.debug') ? 'debug on' : 'debug off',
                'hint' => 'APP_DEBUG=false on the server.',
            ],
            [
                'label' => 'Telegram bot',
                'ok' => app(TelegramGateway::class)->isConfigured(),
                'value' => app(TelegramGateway::class)->isConfigured() ? 'configured' : 'not configured',
                'hint' => 'TELEGRAM_BOT_TOKEN, then php artisan woodex:telegram:set-webhook',
            ],
            [
                'label' => 'Spam protection (Turnstile)',
                'ok' => app(TurnstileVerifier::class)->enabled(),
                'value' => app(TurnstileVerifier::class)->enabled() ? 'on' : 'off',
                'hint' => 'TURNSTILE_SITE_KEY and TURNSTILE_SECRET_KEY.',
            ],
        ];

        return view('livewire.admin.system.health', [
            'checks' => $checks,
            'environment' => [
                'App environment' => app()->environment(),
                'PHP' => PHP_VERSION,
                'Laravel' => app()->version(),
                'Database' => config('database.default'),
                'Queue' => config('queue.default'),
                'Cache' => config('cache.default'),
                'Time zone' => config('app.timezone'),
            ],
            'integrationErrors' => IntegrationLog::query()->where('ok', false)->latest('id')->limit(10)->get(),
        ]);
    }
}
