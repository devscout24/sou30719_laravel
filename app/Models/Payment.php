<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'user_id',
        'subscription_id',
        'context',
        'stripe_payment_intent_id',
        'amount',
        'tax',
        'currency',
        'status',
        'payment_method',
        'invoice_url',
        'receipt_url',
        'paid_at',
    ];

    protected $casts = [
        'amount'  => 'decimal:2',
        'tax'     => 'decimal:2',
        'status'  => 'string',
        'paid_at' => 'datetime',
    ];

    /**
     * App modules a transaction can originate from.
     */
    public const CONTEXTS = [
        'subscription' => 'Subscription',
        'social'       => 'Social',
        'marketplace'  => 'Marketplace',
        'matches'      => 'Matches',
        'interest_hub' => 'Interest Hub',
        'courier'      => 'Courier',
        'events'       => 'Events',
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

    public function contextLabel(): string
    {
        return self::CONTEXTS[$this->context] ?? ucfirst(str_replace('_', ' ', $this->context ?? 'subscription'));
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'paid'     => 'Successful',
            'pending'  => 'Pending',
            'failed'   => 'Failed',
            'refunded' => 'Refunded',
            default    => ucfirst($this->status),
        };
    }

    public function methodLabel(): string
    {
        return $this->payment_method ? ucfirst($this->payment_method) : '—';
    }

    public function getTotalAttribute(): string
    {
        return number_format($this->amount + $this->tax, 2);
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
