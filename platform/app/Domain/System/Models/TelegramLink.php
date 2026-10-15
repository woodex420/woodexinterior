<?php

declare(strict_types=1);

namespace App\Domain\System\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Link between a staff user (or a group chat) and a Telegram chat (TGM-001).
 */
class TelegramLink extends Model
{
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'chat_id' => 'integer',
            'is_active' => 'boolean',
            'muted_until' => 'datetime',
            'link_code_expires_at' => 'datetime',
            'linked_at' => 'datetime',
            'preferences' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Create (or refresh) a one-time code the user sends to the bot as "/start CODE".
     */
    public static function issueCodeFor(User $user, int $minutes = 30): self
    {
        $link = self::query()->firstOrNew(['user_id' => $user->id]);
        $link->link_code = Str::upper(Str::random(8));
        $link->link_code_expires_at = now()->addMinutes($minutes);
        $link->save();

        return $link;
    }
}
