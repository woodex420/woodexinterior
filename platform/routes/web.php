<?php

use App\Http\Controllers\Webhooks\TelegramWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Woodex OS admin
|--------------------------------------------------------------------------
| Registered first, so on the admin domain these routes win over public ones.
| Local/CI: http://localhost:8000/admin · Production: https://admin.woodex.com.pk
*/
$admin = Route::middleware(['auth', 'active', 'two-factor'])
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
| Public website (phase P6)
|--------------------------------------------------------------------------
| Until the website cut-over the current live site keeps serving woodex.com.pk.
| The 142 legacy URLs, page builder and theme builder are registered here in P6.
*/
Route::view('/', 'site.holding')->name('home');
