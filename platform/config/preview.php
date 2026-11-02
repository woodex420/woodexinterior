<?php

declare(strict_types=1);

return [
    // Opt-in synthetic workspace only. Normal Fortify authentication remains mandatory.
    'synthetic' => (bool) env('WOODEX_SYNTHETIC_PREVIEW', false),
    'login_email' => env('WOODEX_PREVIEW_LOGIN_EMAIL'),
    'show_credentials' => (bool) env('WOODEX_PREVIEW_SHOW_CREDENTIALS', false),
    'login_password' => env('WOODEX_PREVIEW_LOGIN_PASSWORD'),
];
