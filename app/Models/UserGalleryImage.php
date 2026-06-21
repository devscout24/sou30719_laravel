<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserGalleryImage extends Model
{
    protected $fillable = ['user_id', 'image_path', 'sort_order'];

    protected $casts = ['sort_order' => 'integer'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getFullUrlAttribute(): string
    {
        return asset($this->image_path);
    }
}
