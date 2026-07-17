<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'user_id',
        'subscription_id',
        'stripe_payment_intent_id',
        'amount',
        'currency',
        'status',
        'invoice_url',
        'receipt_url',
        'paid_at',
    ];

    protected $casts = [
        'amount'  => 'decimal:2',
        'status'  => 'string',
        'paid_at' => 'datetime',
    ];

    // ─── Relationships ───────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(UserSubscription::class, 'subscription_id');
    }

    // ─── Helpers ─────────────────────────────────────

    public function markAsPaid(): void
    {
        $this->update([
            'status'  => 'paid',
            'paid_at' => now(),
        ]);
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    // ─── Scopes ──────────────────────────────────────

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }
}
