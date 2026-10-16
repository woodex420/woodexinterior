<?php

declare(strict_types=1);

namespace App\Domain\Website\Services;

use App\Domain\Website\Models\Page;
use App\Domain\Website\Models\Redirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Answers a request for a path no route owns (docs/09 §3): a page from the CMS, or one of the 301s from the old
 * website. Registered on the 404 handler rather than as a catch-all route, because routes added by packages
 * (Fortify's login, Livewire) are registered after routes/web.php and would be shadowed by a catch-all.
 *
 * Canonical URLs end in a slash; a request without one is redirected once, permanently.
 */
final class SiteRouter
{
    /** First segments that belong to the app, never to a page. */
    private const array RESERVED = [
        'install', 'up', 'build', 'vendor', 'storage', 'api', 'webhooks', 'livewire', 'q', 'admin',
    ];

    public function __construct(private readonly PageRenderer $renderer) {}

    /** The response for a path, or null to let the normal 404 page render. */
    public function respond(Request $request): ?SymfonyResponse
    {
        $path = '/'.ltrim((string) $request->path(), '/');
        $firstSegment = explode('/', trim($path, '/'))[0] ?? '';

        if ($this->isReserved($firstSegment) || $this->onAdminDomain($request)) {
            return null;
        }

        if ($path === '/') { // the home page is a route of its own
            return null;
        }

        $slug = Page::normalizeSlug($path);

        // One canonical form: /about and /about/ are the same page, and only the second is linked to (docs/09 §11.1).
        if (! str_ends_with((string) $request->getPathInfo(), '/')) {
            $query = $request->getQueryString();

            return $this->redirect($slug.($query !== null ? '?'.$query : ''), $request);
        }

        $page = Page::query()->where('slug', $slug)->first();

        if ($page !== null) {
            if (! $page->isPublished() && ! $this->mayPreview($request)) {
                return null;
            }

            return $this->render($page);
        }

        $redirect = Redirect::query()->where('from_path', $slug)->first();

        if ($redirect !== null) {
            $redirect->increment('hits');

            return $this->redirect($redirect->to_path, $request, (int) $redirect->code);
        }

        return null;
    }

    private function render(Page $page): Response
    {
        $html = PageCache::html($page, fn (): string => (string) view('site.page', $this->renderer->viewData($page))->render());

        return new Response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    /**
     * An absolute URL of our own making: redirect() and url() both trim the trailing slash, and the slash is the
     * whole point of the canonical URL (RedirectToInstaller builds its target the same way).
     */
    private function redirect(string $to, Request $request, int $code = 301): RedirectResponse
    {
        $target = $request->getSchemeAndHttpHost().$request->getBaseUrl().'/'.ltrim($to, '/');
        $response = new RedirectResponse($target, $code);
        $response->setCache(['no_store' => true]);

        return $response;
    }

    private function isReserved(string $segment): bool
    {
        if ($segment === '') {
            return false;
        }

        return in_array($segment, [...self::RESERVED, trim((string) config('woodex.admin.path'), '/')], true);
    }

    private function onAdminDomain(Request $request): bool
    {
        $domain = (string) config('woodex.admin.domain');

        return $domain !== '' && $request->getHost() === $domain;
    }

    /** Staff who may edit the website can open a draft or a scheduled page before it goes live. */
    private function mayPreview(Request $request): bool
    {
        $user = $request->user();

        return $request->boolean('preview') && $user !== null && $user->can('website.edit');
    }
}
