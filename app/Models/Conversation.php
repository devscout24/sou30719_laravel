<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    protected $fillable = [
        'connection_id',
        'type',
    ];

    protected $casts = [
        'type' => 'string',
    ];

    // ─── Relationships ───────────────────────────────

    public function connection(): BelongsTo
    {
        return $this->belongsTo(UserConnection::class, 'connection_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    // ─── Helpers ─────────────────────────────────────

    public function latestMessage(): ?Message
    {
        return $this->messages()->latest()->first();
    }

    public function isPrivate(): bool
    {
        return $this->type === 'private';
    }
}
