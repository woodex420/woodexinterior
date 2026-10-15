<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Owner, admin and accountant must turn on TOTP 2FA before using the admin (D-20, SYS-003).
 */
final class EnsureTwoFactorIsEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Page loads only: users without 2FA can reach nothing but the Security page, and the
        // Livewire requests made from that page must keep working while 2FA is being set up.
        if (
            $user !== null
            && $request->isMethod('GET')
            && config('woodex.security.enforce_2fa')
            && ! $user->hasTwoFactorEnabled()
            && $user->hasAnyRole((array) config('woodex.security.require_2fa_roles', []))
            && ! $request->routeIs('admin.profile.security')
        ) {
            return redirect()->route('admin.profile.security')
                ->with('warning', 'Please turn on two-factor authentication to continue.');
        }

        return $next($request);
    }
}
