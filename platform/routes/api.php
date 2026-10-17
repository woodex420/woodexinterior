<?php

use App\Http\Controllers\Api\Public\LeadController;
use App\Http\Controllers\Website\LegacySiteBridgeController;
use Illuminate\Support\Facades\Route;

/*
| Public, stateless JSON API (website forms, estimator, booking, chat in P4, live-site bridge).
| Prefix "api" is added by the framework: POST /api/public/leads
*/
Route::prefix('public')->name('api.public.')->group(function (): void {
    Route::post('/leads', [LeadController::class, 'store'])
        ->middleware('throttle:public-leads')
        ->name('leads.store');
});

/*
| The two addresses the original website's JavaScript calls (docs/11 §2). The 142 pages are served as the old site
| made them, so they come with their own scripts, which post here. Answering in the shape they expect means the
| pages work without a single character being changed on them, and an enquiry lands in the CRM like any other.
*/
Route::get('/forms.php', [LegacySiteBridgeController::class, 'config'])
    ->middleware('throttle:public-site')
    ->name('legacy.forms.config');

Route::post('/forms.php', [LegacySiteBridgeController::class, 'forms'])
    ->middleware('throttle:public-site')
    ->name('legacy.forms.store');

Route::match(['get', 'post'], '/chat.php', [LegacySiteBridgeController::class, 'chat'])
    ->middleware('throttle:public-site')
    ->name('legacy.chat');
