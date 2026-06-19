<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserSubscription extends Model
{
    protected $fillable = [
        'user_id',
        'plan_id',
        'stripe_subscription_id',
        'start_date',
        'end_date',
        'status',
        'cancelled_at',
    ];

    protected $casts = [
        'start_date'   => 'datetime',
        'end_date'     => 'datetime',
        'cancelled_at' => 'datetime',
        'status'       => 'string',
    ];

    // ─── Relationships ───────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'subscription_id');
    }

    // ─── Helpers ─────────────────────────────────────

    public function isActive(): bool
    {
        return $this->status === 'active'
            && (is_null($this->end_date) || $this->end_date->isFuture());
    }

    public function cancel(): void
    {
        $this->update([
            'status'       => 'cancelled',
            'cancelled_at' => now(),
        ]);
    }

    // ─── Scopes ──────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
