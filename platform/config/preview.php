<?php

declare(strict_types=1);

return [
    // Opt-in guidance only. Never bypass authentication or change an account/password.
    'synthetic' => (bool) env('WOODEX_SYNTHETIC_PREVIEW', false),
    'login_email' => env('WOODEX_PREVIEW_LOGIN_EMAIL'),
];
