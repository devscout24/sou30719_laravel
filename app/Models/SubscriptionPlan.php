<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'billing_cycle',
        'price',
        'max_posts_per_day',
        'max_matches_per_day',
        'max_ai_requests_per_day',
        'is_active',
    ];

    protected $casts = [
        'billing_cycle' => 'string',
        'price'         => 'decimal:2',
        'is_active'     => 'boolean',
    ];

    // ─── Relationships ───────────────────────────────

    public function userSubscriptions(): HasMany
    {
        return $this->hasMany(UserSubscription::class, 'plan_id');
    }

    // ─── Helpers ─────────────────────────────────────

    public function hasUnlimitedPosts(): bool
    {
        return is_null($this->max_posts_per_day);
    }

    public function hasUnlimitedMatches(): bool
    {
        return is_null($this->max_matches_per_day);
    }

    // ─── Scopes ──────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
