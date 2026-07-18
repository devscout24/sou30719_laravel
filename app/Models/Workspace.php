<?php

namespace App\Models;

use App\Enums\NavSection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workspace extends Model
{
    /**
     * Canonical workspace slugs. See database/seeders/WorkspaceSeeder.php.
     */
    public const SLUG_SOCIAL_POST = 'social_post';
    public const SLUG_MATCHES = 'matches';
    public const SLUG_MARKET_PLACE = 'market_place';
    public const SLUG_EVENT = 'event';
    public const SLUG_INTEREST_HUB = 'interest_hub';
    public const SLUG_PERSONAL_COURIER = 'personal_courier';

    protected $fillable = [
        'title',
        'description',
        'prompt',
        'slug',
        'is_supported',
        'status',
        'sort_order',
    ];

    protected $casts = [
        'is_supported' => 'boolean',
        'sort_order' => 'integer',
    ];

    // ─── Relationships ───────────────────────────────

    public function conversations(): HasMany
    {
        return $this->hasMany(AiConversation::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function navPermissions(): HasMany
    {
        return $this->hasMany(WorkspaceNavPermission::class);
    }

    // ─── Helpers ─────────────────────────────────────

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function hasNavAccess(string $navKey): bool
    {
        return $this->navPermissions->contains('nav_key', $navKey);
    }

    /**
     * Full ai_pal/discovery/friends/chat => bool map for frontend sidebar rendering.
     */
    public function navAccessMap(): array
    {
        $granted = $this->navPermissions->pluck('nav_key')->all();

        return collect(NavSection::values())
            ->mapWithKeys(fn (string $key) => [$key => in_array($key, $granted, true)])
            ->all();
    }

    // ─── Scopes ──────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
