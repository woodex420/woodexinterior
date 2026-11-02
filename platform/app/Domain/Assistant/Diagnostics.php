<?php

declare(strict_types=1);

namespace App\Domain\Assistant;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

/** Bounded read-only probes. Never calls providers, reads credentials into answers or executes fixes. */
final class Diagnostics
{
    public function runtime(User $user): AssistantAnswer
    {
        abort_unless($user->can('system.health'), 403);
        $url = route('admin.system.health');
        $rows = [['label' => 'PHP / Laravel', 'detail' => PHP_VERSION.' / '.app()->version(), 'url' => $url]];
        try {
            $connection = DB::connection();
            $connection->select('SELECT 1');
            $driver = $connection->getDriverName();
            $rows[] = ['label' => 'Database probe', 'detail' => 'Connected: '.$driver.'. SELECT 1 passed.', 'url' => $url];
        } catch (Throwable) {
            // Connection exceptions can contain passwords, DSNs and hosts: never include their messages.
            $rows[] = ['label' => 'Database probe', 'detail' => 'Failed. Ask the owner to review private database configuration; no change made.', 'url' => $url];
        }
        $manifest = public_path('build/manifest.json');
        $build = is_file($manifest) && filesize($manifest) < 1_048_576
            ? json_decode((string) file_get_contents($manifest), true) : null;
        $ready = is_array($build) && isset($build['resources/js/admin.js'], $build['resources/js/auth.js'], $build['resources/css/admin.css']);
        $rows[] = ['label' => 'Frontend build', 'detail' => $ready ? 'Manifest entries present; browser asset loading still needs verification.' : 'Missing/incomplete manifest. Recommend rebuilding reviewed assets in staging.', 'url' => $url];
        $unsafeCookie = (config('session.same_site') === 'none' || config('session.partitioned')) && ! config('session.secure');
        $rows[] = ['label' => 'Sign-in cookies', 'detail' => $unsafeCookie ? 'Review required: cross-site cookies need Secure. Recommend owner-approved configuration review.' : 'No cross-site cookie configuration conflict detected; browser retention is not proven by this check.', 'url' => $url];
        $rows[] = ['label' => 'Debug mode', 'detail' => config('app.debug') ? 'Enabled. Recommend disabling before a production release.' : 'Disabled.', 'url' => $url];
        $rows[] = ['label' => 'Queue worker', 'detail' => 'Worker execution not tested. Check the platform health heartbeat; no job dispatched.', 'url' => $url];

        return new AssistantAnswer('Runtime diagnostics — read-only. Recommendations require owner review; no fixes were executed.', $rows, 'Local PHP/configuration, frontend manifest and SELECT 1. No shell commands or external requests.');
    }

    public function connections(User $user): AssistantAnswer
    {
        abort_unless($user->can('integrations.manage'), 403);
        $url = route('admin.integrations');
        $telegram = filled(config('services.telegram.bot_token'));
        $google = filled(config('services.google.client_id')) && filled(config('services.google.client_secret'));
        $mailer = (string) config('mail.default');
        $mail = in_array($mailer, ['array', 'log'], true) ? 'Non-sending transport configured.' : 'Sending transport configured; delivery not tested.';
        $rows = [
            ['label' => 'Telegram', 'detail' => $telegram ? 'Token configured; identity, owner pairing, connectivity and receipt NOT tested. Rotate exposed tokens before the approved one-report test.' : 'Token not configured. Configure a rotated token privately before testing owner pairing.', 'url' => $url],
            ['label' => 'Google', 'detail' => $google ? 'Credentials configured; authorization and reachability NOT tested.' : 'Credentials not configured. No authorization or reachability test performed.', 'url' => $url],
            ['label' => 'Email', 'detail' => $mail.' No message sent.', 'url' => $url],
            ['label' => 'External AI', 'detail' => 'Not integrated into this Assistant. Approved aggregate policy still needs model, budget and a guarded connector; no provider call made.', 'url' => $url],
        ];

        return new AssistantAnswer('Connection diagnostics — configuration is not proof of connectivity or delivery. No external tests or writes performed.', $rows, 'Allowlisted configuration presence only; no tokens, endpoints, account details or provider responses exposed.');
    }
}
