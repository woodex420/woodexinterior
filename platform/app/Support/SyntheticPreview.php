<?php

declare(strict_types=1);

namespace App\Support;

final class SyntheticPreview
{
    public static function enabled(): bool
    {
        return config('preview.synthetic') === true
            && app()->environment('local', 'testing')
            && config('database.default') === 'sqlite';
    }
}
