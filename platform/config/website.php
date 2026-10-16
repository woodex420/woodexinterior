<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Public website (docs/09)
    |--------------------------------------------------------------------------
    | `cache` renders published pages once and serves them from the Laravel cache. Publishing touches the page,
    | which changes the cache key, so nothing needs flushing. Turn it off while editing content on a live site.
    */
    'cache' => (bool) env('WEBSITE_CACHE', true),
];
