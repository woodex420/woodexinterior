<?php

declare(strict_types=1);

return [
    // Normal application entry points always require Fortify. Only the separate private sandbox launcher reads direct_admin.
    'direct_admin' => (bool) env('WOODEX_PREVIEW_DIRECT_ADMIN', false),
    'synthetic' => (bool) env('WOODEX_SYNTHETIC_PREVIEW', false),
    'login_email' => env('WOODEX_PREVIEW_LOGIN_EMAIL'),
    'show_credentials' => (bool) env('WOODEX_PREVIEW_SHOW_CREDENTIALS', false),
    'login_password' => env('WOODEX_PREVIEW_LOGIN_PASSWORD'),
];
