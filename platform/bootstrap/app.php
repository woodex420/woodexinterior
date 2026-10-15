<?php

use App\Http\Middleware\EnsureDatabaseIsCurrent;
use App\Http\Middleware\EnsureTwoFactorIsEnabled;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\RedirectToInstaller;
use App\Http\Middleware\SecurityHeaders;
use App\Support\Install\SiteState;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        // The web installer runs without the "web" group: no APP_KEY, sessions or CSRF exist yet (D-58).
        then: fn () => Route::group([], __DIR__.'/../routes/install.php'),
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'active' => EnsureUserIsActive::class,
            'two-factor' => EnsureTwoFactorIsEnabled::class,
            'db-current' => EnsureDatabaseIsCurrent::class,
        ]);

        // A freshly deployed site without .env opens the installer instead of failing (D-58).
        $middleware->prepend(RedirectToInstaller::class);
        $middleware->append(SecurityHeaders::class);

        // Passwords typed into the installer are stored exactly as typed.
        $middleware->trimStrings(except: ['db_password', 'owner_password', 'owner_password_confirmation']);
        // Trusted proxies come from config/trustedproxy.php (TRUSTED_PROXIES in .env), read per request.

        // Webhooks authenticate with signatures, not CSRF tokens.
        $middleware->validateCsrfTokens(except: ['webhooks/*']);

        $middleware->redirectGuestsTo(fn () => route('login'));
        // Signed-in staff who open a guest page (sign-in form, a second sign-in POST) go to the admin, not to the
        // public site: Laravel's default target is the route named "home", which is the website (D-51).
        $middleware->redirectUsersTo(fn () => route('admin.dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->is('webhooks/*') || $request->expectsJson(),
        );

        // A failed sign-in (wrong password, lockout, empty field) always goes back to its own form. Laravel's
        // default is the Referer or the session's previous URL and, when both are missing (a proxy that drops
        // Referer, a lost session cookie), "/": the public website when the admin lives under ADMIN_PATH. D-51.
        $exceptions->render(function (ValidationException $exception, Request $request) {
            foreach (['login.store' => 'login', 'two-factor.login.store' => 'two-factor.login', 'password.email' => 'password.request'] as $post => $form) {
                if ($request->routeIs($post) && Route::has($form)) {
                    $exception->redirectTo(route($form));
                }
            }

            return null; // keep Laravel's normal rendering (errors + old input flashed to the session)
        });
    })->create();

// Where .env and storage/ live: platform/ (classic: local, CI, SSH install) or, for a Hostinger Git deploy that
// replaces the site folder on every push, ~/woodex-os/<site>/ outside every web folder (docs/06 §2, D-57).
$site = SiteState::detect($app->basePath());
$app->instance(SiteState::class, $site);

if ($site->usesExternalState()) {
    $app->useEnvironmentPath((string) $site->environmentDirectory());
    $app->useStoragePath($site->environmentDirectory().'/storage');
}

SiteState::ensureStorage($app->storagePath());

// Hostinger "copy public/ into the subdomain folder" fallback (docs/06 §3).
if (! empty($_ENV['APP_PUBLIC_PATH'] ?? getenv('APP_PUBLIC_PATH'))) {
    $app->usePublicPath($_ENV['APP_PUBLIC_PATH'] ?? getenv('APP_PUBLIC_PATH'));
}

return $app;
