<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeedCategory extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'icon',
        'tag_keywords',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'tag_keywords' => 'array',
        'is_active'    => 'boolean',
        'sort_order'   => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
