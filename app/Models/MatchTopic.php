<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchTopic extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'icon',
        'tag_keywords',
        'sort_order',
        'is_fixed',
        'is_active',
    ];

    protected $casts = [
        'tag_keywords' => 'array',
        'sort_order'   => 'integer',
        'is_fixed'     => 'boolean',
        'is_active'    => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
