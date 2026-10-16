<?php

declare(strict_types=1);

namespace App\Domain\Website\Models;

use App\Models\User;
use Database\Factories\Website\MediaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An image in the media library (docs/09 §2.1). Alt text is part of the record: the editor refuses to publish a
 * page whose images have none.
 *
 * @property int $id
 * @property string $disk
 * @property string $path
 * @property string $alt
 * @property ?string $title
 * @property ?int $width
 * @property ?int $height
 * @property ?string $folder
 */
class Media extends Model
{
    /** @use HasFactory<MediaFactory> */
    use HasFactory;

    protected static function newFactory(): MediaFactory
    {
        return MediaFactory::new();
    }

    /** Spatie's media library already owns the `media` table (admin uploads). */
    protected $table = 'website_media';

    protected $fillable = ['disk', 'path', 'alt', 'title', 'width', 'height', 'folder', 'uploaded_by'];

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * The public URL of this image. Served by the app (see Site\WebsiteMediaController) and versioned by the
     * time it last changed, so a browser can cache it forever and still see an edit at once.
     */
    public function url(): string
    {
        return route('website.media.file', [
            'media' => $this->id,
            'name' => basename($this->path),
            'v' => $this->updated_at->timestamp,
        ]);
    }
}
