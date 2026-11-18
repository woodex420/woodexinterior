<?php

declare(strict_types=1);

namespace App\Domain\Website\Models;

use Illuminate\Database\Eloquent\Model;

/** Private editor state. Public page queries and renderers never read this table. */
final class StudioDraft extends Model
{
    protected $table = 'website_studio_drafts';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['payload' => 'array', 'revision' => 'integer'];
    }
}
