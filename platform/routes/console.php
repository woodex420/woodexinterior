<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduler: driven by ONE hPanel cron entry every minute (docs/06 §5)
|   /usr/bin/php /home/uXXXX/apps/woodexinterior/platform/artisan schedule:run
|--------------------------------------------------------------------------
*/

// Recover durable WhatsApp events before draining the same database queue.
Schedule::command('woodex:retry-whatsapp-webhooks')->everyMinute()->withoutOverlapping(5);

// Drain the database queue without a daemon (D-17).
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')
    ->everyMinute()
    ->withoutOverlapping(5)
    ->runInBackground();

// Cron heartbeat for System → Health.
Schedule::command('woodex:heartbeat')->everyMinute();

// First-response SLA: flag uncontacted leads and alert owner + managers (CRM-010).
Schedule::command('woodex:sla-check')->everyMinute()->withoutOverlapping(5);

// Task reminders and site-visit reminders to staff (CRM-007, CRM-008).
Schedule::command('woodex:reminders')->everyMinute()->withoutOverlapping(5);

// Review requests a few days after a handover was signed (PRJ-007).
Schedule::command('woodex:review-requests')->dailyAt('10:30');

// Backups (spatie/laravel-backup) and housekeeping.
Schedule::command('backup:run --only-db')->dailyAt('02:30');
Schedule::command('backup:run')->weeklyOn(0, '03:00');
Schedule::command('backup:clean')->dailyAt('03:30');
Schedule::command('backup:monitor')->dailyAt('04:00');
Schedule::command('queue:prune-failed --hours=720')->dailyAt('04:15');
Schedule::command('model:prune')->dailyAt('04:30');
