<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportTicket extends Model
{
    protected $fillable = [
        'user_id',
        'subject',
        'message',
        'status',
    ];

    protected $casts = [
        'status' => 'string',
    ];

    // ─── Relationships ───────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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

    // ─── Scopes ──────────────────────────────────────

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }
}
