<?php

use App\Http\Controllers\Portal\QuotePortalController;
use App\Http\Controllers\Site\HomeController;
use App\Http\Controllers\Site\SitemapController;
use App\Http\Controllers\Webhooks\TelegramWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Woodex OS admin
|--------------------------------------------------------------------------
| Registered first, so on the admin domain these routes win over public ones.
| Local/CI: http://localhost:8000/admin · Production: https://admin.woodex.com.pk
*/
// db-current before two-factor: after a deploy with new migrations the owner applies the update first (D-58).
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
| Public website (phase P6)
|--------------------------------------------------------------------------
| The home page is the first release of the new website: Studio look, the enquiry form posting to the public
| lead API, and SEO/E-E-A-T structured data. The other 141 legacy URLs, page builder and theme builder follow
| in this phase; until then the menu on the home page points at its own sections (no dead links).
*/
Route::get('/', HomeController::class)->name('home');
Route::get('/robots.txt', [SitemapController::class, 'robots'])->name('robots');
Route::get('/sitemap.xml', [SitemapController::class, 'sitemap'])->name('sitemap');
