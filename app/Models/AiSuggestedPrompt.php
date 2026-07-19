<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiSuggestedPrompt extends Model
{
    public const CONTEXT_FEED_SEARCH = 'feed_search';
    public const CONTEXT_WORKSPACE_CONVERSATION = 'workspace_conversation';

    protected $fillable = [
        'context',
        'workspace_id',
        'label',
        'prompt',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active'  => 'boolean',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForContext($query, string $context)
    {
        return $query->where('context', $context);
    }
}
