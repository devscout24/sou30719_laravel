<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Post extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'type',
        'created_by',
        'title',
        'content',
        'price',
        'location',
        'category',
        'event_date',
        'event_location',
        'visibility',
        'status',
    ];

    protected $casts = [
        'price'      => 'decimal:2',
        'event_date' => 'datetime',
        'type'       => 'string',
        'created_by' => 'string',
        'visibility' => 'string',
        'status'     => 'string',
    ];

    // ─── Relationships ───────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(PostImage::class)->orderBy('sort_order');
    }

    public function likes(): HasMany
    {
        return $this->hasMany(PostLike::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(PostComment::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(PostReport::class);
    }

    public function savedByUsers(): HasMany
    {
        return $this->hasMany(SavedPost::class);
    }

    // ─── Helpers ─────────────────────────────────────

    public function isAd(): bool
    {
        return $this->type === 'ad';
    }

    public function isEvent(): bool
    {
        return $this->type === 'event';
    }

    public function isRegular(): bool
    {
        return $this->type === 'regular';
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    // ─── Scopes ──────────────────────────────────────

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopeVisible($query)
    {
        return $query->where('visibility', 'public');
    }

    public function scopeAds($query)
    {
        return $query->where('type', 'ad');
    }

    public function scopeEvents($query)
    {
        return $query->where('type', 'event');
    }

    public function scopeRegular($query)
    {
        return $query->where('type', 'regular');
    }
}
