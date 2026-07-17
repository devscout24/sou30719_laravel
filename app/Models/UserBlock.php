<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserBlock extends Model
{
    protected $fillable = [
        'user_id',
        'blocked_user_id',
    ];

    // ─── Relationships ───────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function blockedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_user_id');
    }

    // ─── Helpers ─────────────────────────────────────

    public static function isBlocked(int $userId, int $otherUserId): bool
    {
        return static::where(function ($q) use ($userId, $otherUserId) {
            $q->where('user_id', $userId)->where('blocked_user_id', $otherUserId);
        })->orWhere(function ($q) use ($userId, $otherUserId) {
            $q->where('user_id', $otherUserId)->where('blocked_user_id', $userId);
        })->exists();
    }
}
