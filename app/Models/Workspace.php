<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workspace extends Model
{
    protected $fillable = [
        'title',
        'description',
        'prompt',
        'slug',
        'is_supported',
        'status',
        'sort_order',
    ];

    protected $casts = [
        'is_supported' => 'boolean',
        'sort_order' => 'integer',
    ];

    // ─── Relationships ───────────────────────────────

    public function conversations(): HasMany
    {
        return $this->hasMany(AiConversation::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    // ─── Helpers ─────────────────────────────────────

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    // ─── Scopes ──────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
