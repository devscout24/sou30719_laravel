<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageAttachment extends Model
{
    protected $fillable = [
        'message_id',
        'file_path',
        'file_name',
        'mime_type',
        'file_size',
    ];

    protected $casts = [
        'file_size' => 'integer',
    ];

    // ─── Relationships ───────────────────────────────

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    // ─── Helpers ─────────────────────────────────────

    public function getFullUrlAttribute(): string
    {
        return asset('storage/' . $this->file_path);
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type ?? '', 'image/');
    }
}
