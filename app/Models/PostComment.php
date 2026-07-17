<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PostComment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'post_id',
        'user_id',
        'parent_id',
        'comment',
    ];

    protected $casts = [
        'parent_id' => 'integer',
    ];

    // ─── Relationships ───────────────────────────────

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // parent comment
    public function parent(): BelongsTo
    {
        return $this->belongsTo(PostComment::class, 'parent_id');
    }

    // child replies
    public function replies(): HasMany
    {
        return $this->hasMany(PostComment::class, 'parent_id')->orderBy('created_at');
    }

    // ─── Helpers ─────────────────────────────────────

    public function isReply(): bool
    {
        return !is_null($this->parent_id);
    }
}
