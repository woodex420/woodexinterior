<?php

use App\Domain\Website\Support\ThemeCss;
use App\Http\Controllers\Portal\ClientPortalController;
use App\Http\Controllers\Portal\InvoicePortalController;
use App\Http\Controllers\Portal\ProjectPortalController;
use App\Http\Controllers\Portal\QuotePortalController;
use App\Http\Controllers\Site\SitemapController;
use App\Http\Controllers\Site\WebsiteMediaController;
use App\Http\Controllers\Webhooks\TelegramWebhookController;
use App\Http\Controllers\Webhooks\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Woodex OS admin
|--------------------------------------------------------------------------
| Registered first, so on the admin domain these routes win over public ones.
| Local/CI: http://localhost:8000/admin · Production: https://admin.woodex.com.pk
*/
// db-current before two-factor: after a deploy with new migrations the owner applies the update first (D-58).
// The site's theme tokens as CSS (V2-1, docs/12 §5.1, D-116). Two URLs, one theme:
//  - /assets/theme.css is linked last by every v1 page: serving it from the database (the static stub was
//    removed) means a token saved in the Theme Builder re-skins the live site, no deploy.
//  - /theme/{hash}.css is what the v2 pages link; the hash is the content, so a saved change mints a fresh
//    immutable URL while pages still holding an old URL get the current tokens and re-skin anyway.
Route::get('/assets/theme.css', function (ThemeCss $theme) {
    return response($theme->css(), 200, $theme->liveHeaders());
})->name('theme.live');

Route::get('/theme/{hash}.css', function (string $hash, ThemeCss $theme) {
    return response($theme->css(), 200, $theme->headersFor($hash));
})->name('theme.css');

$admin = Route::middleware(['auth', 'active', 'db-current', 'two-factor'])
    ->prefix(config('woodex.admin.path'))
    ->name('admin.');

if (filled(config('woodex.admin.domain'))) {
    $admin->domain(config('woodex.admin.domain'));
}

$admin->group(base_path('routes/admin.php'));

/*
|--------------------------------------------------------------------------
| Webhooks (signature-verified, CSRF-exempt, throttled)
|--------------------------------------------------------------------------
*/
Route::post('/webhooks/telegram/{secret}', TelegramWebhookController::class)
    ->middleware('throttle:webhooks')
    ->name('webhooks.telegram');

// WhatsApp Cloud API (WAP-002): Meta's GET handshake verifies the token, and every POST is checked against
// X-Hub-Signature-256 inside the controller.
Route::get('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'verify'])
    ->middleware('throttle:webhooks')
    ->name('webhooks.whatsapp.verify');

Route::post('/webhooks/whatsapp', WhatsAppWebhookController::class)
    ->middleware('throttle:webhooks')
    ->name('webhooks.whatsapp');

/*
|--------------------------------------------------------------------------
| Client quotation page (QUO-009): signed, expiring links, no login
|--------------------------------------------------------------------------
| Relative signatures, so a link works on whichever domain serves the app.
*/
Route::middleware('signed:relative')->prefix('q')->name('quotes.portal')->group(function (): void {
    Route::get('/{quote:public_id}', [QuotePortalController::class, 'show'])->name('');
    Route::get('/{quote:public_id}/pdf', [QuotePortalController::class, 'pdf'])->name('.pdf');
    Route::post('/{quote:public_id}/accept', [QuotePortalController::class, 'accept'])->name('.accept')->middleware('throttle:10,1');
    Route::post('/{quote:public_id}/changes', [QuotePortalController::class, 'changes'])->name('.changes')->middleware('throttle:10,1');
});

/*
|--------------------------------------------------------------------------
| Client invoice page (INV-005/006): signed, expiring links, no login
|--------------------------------------------------------------------------
| Payment reporting and proof upload come from here; the accountant confirms them in the admin.
*/
Route::middleware('signed:relative')->prefix('i')->name('invoices.portal')->group(function (): void {
    Route::get('/{invoice:public_id}', [InvoicePortalController::class, 'show'])->name('');
    Route::get('/{invoice:public_id}/pdf', [InvoicePortalController::class, 'pdf'])->name('.pdf');
    Route::post('/{invoice:public_id}/proof', [InvoicePortalController::class, 'proof'])->name('.proof')->middleware('throttle:10,1');
});

/*
|--------------------------------------------------------------------------
| Client project portal (PRJ-006/007): a signed link, or a six-digit code
|--------------------------------------------------------------------------
| The client's own page for a job: the diary from the site, the drawings we chose to show, invoices and
| receipts, and the handover checklist they sign for. No client password exists anywhere (D-90).
*/
Route::middleware('signed:relative')->prefix('p')->name('projects.portal')->group(function (): void {
    Route::get('/{project:public_id}', [ProjectPortalController::class, 'show'])->name('');
    Route::get('/{project:public_id}/documents/{media}', [ProjectPortalController::class, 'document'])->name('.document')->whereNumber('media');
    Route::get('/{project:public_id}/receipts/{payment}', [ProjectPortalController::class, 'receipt'])->name('.receipt')->whereNumber('payment');
    Route::post('/{project:public_id}/handover', [ProjectPortalController::class, 'handover'])->name('.handover')->middleware('throttle:10,1');
});

// Ask for a code instead of a link: /portal.
Route::prefix('portal')->name('portal')->group(function (): void {
    Route::get('/', [ClientPortalController::class, 'form'])->name('');
    Route::post('/code', [ClientPortalController::class, 'sendCode'])->name('.code')->middleware('throttle:5,1');
    Route::post('/enter', [ClientPortalController::class, 'enter'])->name('.enter')->middleware('throttle:10,1');
    Route::post('/leave', [ClientPortalController::class, 'leave'])->name('.leave');
});

/*
|--------------------------------------------------------------------------
| Public website (phase P6)
|--------------------------------------------------------------------------
| There is no home page route: the home page is a page in the CMS like any other (slug "/"), rendered by the
| SiteRouter on the 404 handler with everything else (docs/09 §3). The owner edits it in Website -> Pages, and
| what the studio sees in the page editor is what visitors get — no second, hidden template to keep in step.
*/

/*
| Website images (docs/09 §2.1). Served through the app rather than from a public symlink: on shared hosting the
| deployment replaces the web folder, and uploads live in storage/ outside it so they survive every deploy.
| The URL carries the image id and its change time, so browsers may cache it forever.
*/
Route::get('/media/{media}/{name}', WebsiteMediaController::class)->name('website.media.file')->whereNumber('media');
Route::get('/robots.txt', [SitemapController::class, 'robots'])->name('robots');
Route::get('/sitemap.xml', [SitemapController::class, 'sitemap'])->name('sitemap');
