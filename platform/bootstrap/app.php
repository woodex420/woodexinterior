<?php

use App\Http\Middleware\EnsureTwoFactorIsEnabled;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\SecurityHeaders;
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
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'active' => EnsureUserIsActive::class,
            'two-factor' => EnsureTwoFactorIsEnabled::class,
        ]);

        $middleware->append(SecurityHeaders::class);

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

// Hostinger "copy public/ into the subdomain folder" fallback (docs/06 §2).
if (! empty($_ENV['APP_PUBLIC_PATH'] ?? getenv('APP_PUBLIC_PATH'))) {
    $app->usePublicPath($_ENV['APP_PUBLIC_PATH'] ?? getenv('APP_PUBLIC_PATH'));
}

return $app;
