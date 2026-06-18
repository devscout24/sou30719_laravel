<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DatingProfile extends Model
{
    protected $fillable = [
        'user_id',
        'about_me',
        'occupation',
        'education',
        'relationship_goal',
        'looking_for',
        'height',
        'religion',
        'smoking',
        'drinking',
        'is_active',
    ];

    protected $casts = [
        'height'            => 'integer',
        'is_active'         => 'boolean',
        'relationship_goal' => 'string',
        'looking_for'       => 'string',
        'religion'          => 'string',
        'smoking'           => 'string',
        'drinking'          => 'string',
    ];

    // ─── Relationships ───────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(DatingImage::class)->orderBy('sort_order');
    }

    public function preferences(): HasMany
    {
        return $this->hasMany(DatingPreference::class);
    }

    // ─── Helpers ─────────────────────────────────────

    public function primaryImage(): ?DatingImage
    {
        return $this->images()->where('is_primary', true)->first();
    }

    public function isActive(): bool
    {
        return $this->is_active === true;
    }

    // ─── Scopes ──────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
