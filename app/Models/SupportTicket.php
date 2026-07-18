<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportTicket extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'subject',
        'message',
        'attachment_path',
        'status',
    ];

    protected $casts = [
        'status' => 'string',
    ];

    /**
     * Ticket categories offered in the front-end ticket creation dropdown.
     */
    public const TYPES = [
        'payment'         => 'Payment',
        'subscription'    => 'Subscription',
        'refund'          => 'Refund',
        'custom_work'     => 'Custom Work',
        'service_package' => 'Service Package',
        'consultation'    => 'Consultation',
        'fractional_job'  => 'Fractional Job',
        'technical'       => 'Technical',
        'feedback'        => 'Feedback',
        'report_others'   => 'Report others',
    ];

    // ─── Relationships ───────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function replies(): HasMany
    {
        return $this->hasMany(SupportTicketReply::class)->oldest();
    }

    // ─── Helpers ─────────────────────────────────────

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function resolve(): void
    {
        $this->update(['status' => 'resolved']);
    }

    public function close(): void
    {
        $this->update(['status' => 'closed']);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? ($this->type ? ucfirst(str_replace('_', ' ', $this->type)) : 'General');
    }

    public function getFullAttachmentUrlAttribute(): ?string
    {
        return $this->attachment_path ? asset('storage/' . $this->attachment_path) : null;
    }

    // ─── Scopes ──────────────────────────────────────

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }
}
