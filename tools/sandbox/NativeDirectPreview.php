<?php

declare(strict_types=1);

namespace Woodex\Sandbox;

use App\Models\User;
use App\Support\SyntheticPreview;
use Closure;
use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Auth;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Explicit sandbox launcher ONLY. No production front controller or application provider loads this file.
 * Installs a per-request demo identity, not a login/session workaround or a production auth feature.
 */
final class NativeDirectPreview
{
    public static function install(Application $app): void
    {
        $expected = realpath(dirname(__DIR__, 2).'/.cache/preview/source/platform');
        if ($expected === false || realpath($app->basePath()) !== $expected) {
            throw new RuntimeException('Direct demo can run only in the private native preview clone.');
        }
        $app->booted(function () use ($app): void {
            if (! self::allowed()) {
                throw new RuntimeException('Direct demo requires explicit opt-in, isolated disposable SQLite, non-sending mail and a queue without a worker.');
            }
            /** @var HttpKernel $kernel */
            $kernel = $app->make(Kernel::class);
            $kernel->prependToMiddlewarePriority(self::class);
            $app->make(Router::class)->prependMiddlewareToGroup('web', self::class);
        });
    }

    public static function allowed(): bool
    {
        $database = config('database.connections.sqlite.database');
        $directDatabase = $database === ':memory:' ? app()->environment('testing')
            : is_string($database) && str_starts_with(basename((string) realpath($database)), 'woodex-synthetic-direct-');

        return config('preview.direct_admin') === true
            && SyntheticPreview::enabled() && SyntheticPreview::isolatedDatabase() && $directDatabase
            && config('mail.default') === 'array' && config('queue.default') === 'database';
    }

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(self::allowed(), 503, 'The isolated direct demo is unavailable.');
        $request->attributes->set('synthetic_direct_preview', true);
        // Login/public/OAuth/webhook/API requests never receive an identity from this launcher.
        if (! $request->routeIs('admin.*', 'livewire.update', 'default-livewire.update', 'livewire.upload-file')) {
            return $next($request);
        }
        $user = User::query()->where('email', 'owner@woodex.test')->first();
        abort_unless($user !== null && $user->is_active && $user->hasRole('owner')
            && blank($user->two_factor_secret) && blank($user->two_factor_confirmed_at)
            && ! User::query()->where('email', 'not like', '%@woodex.test')->exists(), 503, 'The synthetic demo identity is unavailable.');

        // Cookie-independent DEMO context, not an auth credential. Internal session IDs are HMAC-derived
        // and encrypted with the app key; never rendered or accepted from a URL. Normal CSRF still runs.
        $context = $request->header('X-Woodex-Demo-Context');
        abort_if($context !== null && ! preg_match('/\A[a-f0-9]{32}\z/', $context), 400);
        $context ??= bin2hex(random_bytes(16));
        $request->attributes->set('synthetic_demo_context', $context);
        $encrypter = app('encrypter');
        $cookie = (string) config('session.cookie');
        $sessionId = substr(hash_hmac('sha256', 'synthetic-direct:'.$context, $encrypter->getKey()), 0, 40);
        $request->cookies->set($cookie, $encrypter->encrypt(CookieValuePrefix::create($cookie, $encrypter->getKey()).$sessionId, false));

        $guard = Auth::guard('web');
        abort_unless($guard instanceof SessionGuard, 503);
        $previous = $guard->hasUser() ? $guard->user() : null;
        $guard->onceUsingId($user->getKey());
        try {
            $response = $next($request);
            $response->headers->set('Cache-Control', 'no-store, private');
            $response->headers->set('X-Woodex-Preview', 'synthetic-direct');

            return $response;
        } finally {
            // Never persist demo authentication to a cookie or carry it into a later request.
            $guard->forgetUser();
            if ($previous !== null) {
                $guard->setUser($previous);
            }
        }
    }
}
