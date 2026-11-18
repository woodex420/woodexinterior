<?php

declare(strict_types=1);

namespace App\Domain\Website\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Article-layout/FAQ-group state. Public consumers must never read draft_payload.
 *
 * @property int $id
 * @property string $kind
 * @property string $scope
 * @property int $revision
 * @property string $token
 * @property array<string, mixed>|null $draft_payload
 * @property array<string, mixed>|null $published_payload
 * @property int|null $published_revision
 * @property string|null $published_hash
 */
final class StudioResource extends Model
{
    protected $table = 'website_studio_resources';

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'draft_payload' => 'array',
            'published_payload' => 'array',
            'revision' => 'integer',
            'published_revision' => 'integer',
        ];
    }
}
