<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeBaseItem extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'content',
        'image_path',
    ];

    protected $casts = [
        'type' => 'string',
    ];

    // ─── Relationships ───────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ─── Helpers ─────────────────────────────────────

    public function isText(): bool
    {
        return $this->type === 'text';
    }

    public function isImage(): bool
    {
        return $this->type === 'image';
    }

    public function getFullImageUrlAttribute(): ?string
    {
        return $this->image_path ? asset('storage/' . $this->image_path) : null;
    }
}
