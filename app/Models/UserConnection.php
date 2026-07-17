<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class UserConnection extends Model
{
    protected $fillable = [
        'user_one_id',
        'user_two_id',
        'connection_request_id',
        'connected_at',
    ];

    protected $casts = [
        'connected_at' => 'datetime',
    ];

    // ─── Relationships ───────────────────────────────

    public function userOne(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_one_id');
    }

    public function userTwo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_two_id');
    }

    public function connectionRequest(): BelongsTo
    {
        return $this->belongsTo(ConnectionRequest::class);
    }

    public function conversation(): HasOne
    {
        return $this->hasOne(Conversation::class, 'connection_id');
    }

    // ─── Helpers ─────────────────────────────────────

    public function otherUser(int $currentUserId): ?User
    {
        if ($this->user_one_id === $currentUserId) {
            return $this->userTwo;
        }

        if ($this->user_two_id === $currentUserId) {
            return $this->userOne;
        }

        return null;
    }

    // ─── Scopes ──────────────────────────────────────

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_one_id', $userId)
            ->orWhere('user_two_id', $userId);
    }
}
