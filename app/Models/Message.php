<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Message extends Model
{
    protected $fillable = [
        'conversation_id',
        'sender_id',
        'message_type',
        'message',
        'is_read',
    ];

    protected $casts = [
        'message_type' => 'string',
        'is_read'      => 'boolean',
    ];

    // ─── Relationships ───────────────────────────────

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(MessageAttachment::class);
    }

    // ─── Helpers ─────────────────────────────────────

    public function markAsRead(): void
    {
        $this->update(['is_read' => true]);
    }

    public function hasAttachment(): bool
    {
        return $this->message_type === 'attachment';
    }

    // ─── Scopes ──────────────────────────────────────

    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }
}
