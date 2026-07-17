<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiMessage extends Model
{
    protected $fillable = [
        'conversation_id',
        'sender',
        'type',
        'message',
        'attachments',
    ];

    protected $casts = [
        'sender'      => 'string',
        'type'        => 'string',
        'attachments' => 'array',
    ];

    // ─── Relationships ───────────────────────────────

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'conversation_id');
    }

    // ─── Helpers ─────────────────────────────────────

    /**
     * Decode the stored message, returning parsed JSON or the raw string.
     */
    public function decodedContent(): mixed
    {
        $decoded = json_decode($this->message, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $this->message;
    }
}
