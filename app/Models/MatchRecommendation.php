<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchRecommendation extends Model
{
    protected $fillable = [
        'user_id',
        'recommended_user_id',
        'compatibility_score',
        'reason',
        'status',
    ];

    protected $casts = [
        'compatibility_score' => 'integer',
        'status'              => 'string',
    ];

    // ─── Relationships ───────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function recommendedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recommended_user_id');
    }

    // ─── Helpers ─────────────────────────────────────

    public function markAsViewed(): void
    {
        $this->update(['status' => 'viewed']);
    }

    public function dismiss(): void
    {
        $this->update(['status' => 'dismissed']);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    // ─── Scopes ──────────────────────────────────────

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeViewed($query)
    {
        return $query->where('status', 'viewed');
    }

    public function scopeTopMatches($query, int $limit = 10)
    {
        return $query->orderByDesc('compatibility_score')->limit($limit);
    }
}
