<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DatingPreference extends Model
{
    protected $fillable = [
        'user_id',
        'interested_in',
        'min_age',
        'max_age',
        'max_distance',
        'relationship_goal',
    ];

    protected $casts = [
        'min_age'           => 'integer',
        'max_age'           => 'integer',
        'max_distance'      => 'integer',
        'interested_in'     => 'string',
        'relationship_goal' => 'string',
    ];

    // ─── Relationships ───────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ─── Helpers ─────────────────────────────────────

    public function isWithinAgeRange(int $age): bool
    {
        return $age >= $this->min_age && $age <= $this->max_age;
    }

    public function isWithinDistance(float $distance): bool
    {
        return $distance <= $this->max_distance;
    }
}
