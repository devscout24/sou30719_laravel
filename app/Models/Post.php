<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Post extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'slug',
        'user_id',
        'workspace_id',
        'topic',
        'type',
        'created_by',
        'title',
        'content',
        'short_description',
        'image_description',
        'tags',
        'price',
        'location',
        'category',
        'event_date',
        'event_location',
        'visibility',
        'status',
        'published_at',
    ];

    protected $casts = [
        'price'             => 'decimal:2',
        'event_date'        => 'datetime',
        'published_at'      => 'datetime',
        'tags'              => 'array',
        'type'              => 'string',
        'created_by'        => 'string',
        'visibility'        => 'string',
        'status'            => 'string',
    ];

    protected static function booted(): void
    {
        static::creating(function (Post $post) {
            if (blank($post->slug)) {
                $post->slug = static::generateUniqueSlug($post->topic ?: $post->title ?: 'post');
            }
        });
    }

    protected static function generateUniqueSlug(string $base): string
    {
        $slug = Str::slug($base) ?: 'post';

        do {
            $candidate = $slug . '-' . Str::lower(Str::random(6));
        } while (static::withTrashed()->where('slug', $candidate)->exists());

        return $candidate;
    }

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

    public function shares(): HasMany
    {
        return $this->hasMany(PostShare::class);
    }

    public function aiConversation(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(AiConversation::class);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
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

    public function isUserCreated(): bool
    {
        return $this->created_by === 'user';
    }

    public function deactivate(): void
    {
        $this->update(['status' => 'removed']);
    }

    public function activate(): void
    {
        $this->update(['status' => 'published']);
    }

    public function displayTitle(): string
    {
        return $this->topic ?: $this->title ?: '(untitled)';
    }

    // ─── Scopes ──────────────────────────────────────

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopeUserCreated($query)
    {
        return $query->where('created_by', 'user');
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

    public function scopeWorkspace($query, int $workspaceId)
    {
        return $query->where('workspace_id', $workspaceId);
    }
}
