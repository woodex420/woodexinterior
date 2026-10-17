<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline security headers (NFR-005). The admin is never indexed by search engines.
 */
final class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $frameOptions = (string) config('woodex.security.frame_options', 'SAMEORIGIN');

        if ($frameOptions !== '') {
            $response->headers->set('X-Frame-Options', $frameOptions);
        }

        // OAuth callbacks carry short-lived authorization codes in the query string. Never forward them as Referer.
        $response->headers->set('Referrer-Policy', $request->routeIs('google.*') ? 'no-referrer' : 'strict-origin-when-cross-origin');
        if ($request->routeIs('google.*')) {
            $response->headers->set('Cache-Control', 'no-store, private');
        }
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');

        if (app()->isProduction() && $request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        if ($this->isAdminRequest($request)) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }

    private function isAdminRequest(Request $request): bool
    {
        $domain = config('woodex.admin.domain');

        if (is_string($domain) && $domain !== '' && $request->getHost() === $domain) {
            return true;
        }

        $path = (string) config('woodex.admin.path');

        return $path !== '' && ($request->is($path) || $request->is($path.'/*'));
    }
}
