<?php

declare(strict_types=1);

namespace App\Domain\Website\Models;

use App\Domain\Website\Enums\BlockType;
use Database\Factories\Website\PageBlockFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One section of a page (docs/09 §4). Data is a plain array shaped by BlockType::fields().
 *
 * @property int $id
 * @property int $page_id
 * @property BlockType $type
 * @property int $position
 * @property array<string, mixed>|null $data
 * @property bool $visible
 */
class PageBlock extends Model
{
    /** @use HasFactory<PageBlockFactory> */
    use HasFactory;

    protected static function newFactory(): PageBlockFactory
    {
        return PageBlockFactory::new();
    }

    protected $fillable = ['page_id', 'type', 'position', 'data', 'visible'];

    protected function casts(): array
    {
        return [
            'type' => BlockType::class,
            'data' => 'array',
            'position' => 'integer',
            'visible' => 'boolean',
        ];
    }

    /** @return BelongsTo<Page, $this> */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    /** Missing keys fall back to the defaults of the block type, so a section never renders half-empty. */
    public function field(string $key, mixed $default = null): mixed
    {
        $value = $this->data[$key] ?? null;

        return $value ?? $default ?? ($this->type->fields()[$key] ?? null);
    }
}
