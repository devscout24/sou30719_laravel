<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavedProfile extends Model
{
    protected $fillable = [
        'user_id',
        'saved_user_id',
    ];

    // ─── Relationships ───────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function savedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'saved_user_id');
    }
}
