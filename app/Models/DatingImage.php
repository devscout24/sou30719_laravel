<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DatingImage extends Model
{
    protected $fillable = [
        'dating_profile_id',
        'image_path',
        'is_primary',
        'sort_order',
    ];

    protected $casts = [
        'is_primary'  => 'boolean',
        'sort_order'  => 'integer',
    ];

    // ─── Relationships ───────────────────────────────

    public function datingProfile(): BelongsTo
    {
        return $this->belongsTo(DatingProfile::class);
    }

    // ─── Helpers ─────────────────────────────────────

    public function getFullUrlAttribute(): string
    {
        return asset('storage/' . $this->image_path);
    }

    public function makePrimary(): void
    {
        // remove primary from all other images first
        $this->datingProfile->images()->update(['is_primary' => false]);
        $this->update(['is_primary' => true]);
    }
}
