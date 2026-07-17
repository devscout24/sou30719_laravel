<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class AiConversation extends Model
{
    protected $fillable = [
        'slug',
        'user_id',
        'workspace_id',
        'post_id',
        'status',
        'topic',
        'description',
        'short_description',
        'image_description',
        'tags',
        'images',
    ];

    protected $casts = [
        'status' => 'string',
        'images' => 'array',
        'tags'   => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (AiConversation $conversation) {
            if (blank($conversation->slug)) {
                $conversation->slug = static::generateUniqueSlug();
            }
        });
    }

    protected static function generateUniqueSlug(): string
    {
        do {
            $candidate = Str::lower(Str::random(10));
        } while (static::where('slug', $candidate)->exists());

        return $candidate;
    }

    // ─── Relationships ───────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AiMessage::class, 'conversation_id')->orderBy('created_at')->orderBy('id');
    }

    // ─── Helpers ─────────────────────────────────────

    public function isIdle(): bool
    {
        return $this->status === 'idle';
    }

    public function isCollecting(): bool
    {
        return $this->status === 'collecting';
    }

    public function isPreview(): bool
    {
        return $this->status === 'preview';
    }

    public function isAwaitingEditInstruction(): bool
    {
        return $this->status === 'awaiting_edit_instruction';
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function hasDescription(): bool
    {
        return filled($this->description);
    }

    public function hasImages(): bool
    {
        return !empty($this->images);
    }

    public function getImageUrlsAttribute(): array
    {
        return array_map(
            fn (string $path) => asset('storage/' . $path),
            $this->images ?? []
        );
    }
}
