<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Website\Builder\BuilderApi;
use App\Domain\Website\Builder\BuilderPages;
use App\Domain\Website\Builder\StalePageException;
use App\Domain\Website\Models\Page;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * The Visual Page Builder (v2.1 front-end, Woodex OS back-end).
 *
 * The screen is a full-document HTML app of its own — it owns the viewport, the way the old /builder/
 * did — but it is a normal admin route: the OS session, the `website.edit` policy and the CSRF token
 * guard it. There is no second password and no second user list.
 */
final class WebsiteBuilderController
{
    public function show(?Page $page = null): View
    {
        $page ??= Page::query()->where('slug', '/')->firstOrFail();

        return view('admin.website.builder', [
            'page' => $page,
            'bootPage' => BuilderPages::pathOf($page),
        ]);
    }

    public function api(Request $request, BuilderApi $api): JsonResponse
    {
        try {
            $action = (string) $request->input('action', 'status');

            $out = $action === 'upload'
                ? $api->upload($request->file('file'), $request->user())
                : $api->handle($action, $request->all(), $request->user());
        } catch (StalePageException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 409);
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'error' => $e->validator->errors()->first()], 422);
        } catch (ModelNotFoundException) {
            return response()->json(['ok' => false, 'error' => 'Page not found.'], 404);
        } catch (AuthorizationException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 403);
        }

        return response()->json($out);
    }
}
