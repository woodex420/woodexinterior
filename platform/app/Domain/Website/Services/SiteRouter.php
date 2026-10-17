<?php

declare(strict_types=1);

namespace App\Domain\Website\Services;

use App\Domain\Website\Models\Page;
use App\Domain\Website\Models\Redirect;
use App\Domain\Website\Support\PreviewLink;
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
    /**
     * First segments that belong to the app, never to a page: `q` is the client's quotation link (QUO-009) and
     * `i` the client's invoice link (INV-005) and `p` the client's project portal (PRJ-006), all signed routes
     * that must keep their 404s to themselves; `portal` is the code page.
     */
    private const array RESERVED = [
        'install', 'up', 'build', 'vendor', 'storage', 'api', 'webhooks', 'livewire', 'q', 'i', 'p', 'portal', 'admin',
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

        // The home page is a CMS page like any other (slug "/"), so it is served from here too. Keeping it out of
        // routes/web.php means there is one place where public pages come from, and the owner can edit it.

        $slug = Page::normalizeSlug($path);

        // One canonical form: /about and /about/ are the same page, and only the second is linked to (docs/09 §11.1).
        if (! str_ends_with((string) $request->getPathInfo(), '/')) {
            $query = $request->getQueryString();

            return $this->redirect($slug.($query !== null ? '?'.$query : ''), $request);
        }

        $page = Page::query()->where('slug', $slug)->first();

        if ($page !== null) {
            if (! $page->isPublished() && ! $this->mayPreview($request, $page)) {
                return null;
            }

            return $this->render($page, $request->user() === null);
        }

        $redirect = Redirect::query()->where('from_path', $slug)->first();

        if ($redirect !== null) {
            $redirect->increment('hits');

            return $this->redirect($redirect->to_path, $request, (int) $redirect->code);
        }

        return null;
    }

    /**
     * Signed-in staff are not visitors, so their view is never the one that gets cached (D-69): the first person
     * to open a page would otherwise decide whether everybody else is counted for the next day.
     */
    private function render(Page $page, bool $cacheable): Response
    {
        $html = PageCache::html(
            $page,
            fn (): string => (string) view('site.page', $this->renderer->viewData($page))->render(),
            $cacheable,
        );

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
    /**
     * Who may see an unpublished page (V2-2, docs/12 §5.3): a signed-in editor, or anyone holding one of the
     * owner's shareable preview links — a signed token with an expiry, so the draft can be checked on a phone
     * without a login and the link dies on its own.
     */
    private function mayPreview(Request $request, Page $page): bool
    {
        if (! $request->boolean('preview')) {
            return false;
        }

        $user = $request->user();

        if ($user !== null && $user->can('website.edit')) {
            return true;
        }

        return PreviewLink::valid($page, $request->query('token'), $request->integer('expires'));
    }
}
