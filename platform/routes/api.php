<?php

use App\Http\Controllers\Api\Public\LeadController;
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
