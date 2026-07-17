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

        // Profile set-up
        'nickname',
        'showcase_page',
        'city',
        'about',
        'profile_setup_media',

        // Identity & Location
        'dating_nickname',
        'dating_dob',
        'dating_full_name',
        'relationship_status',
        'dating_gender',
        'dating_email',
        'dating_location',
        'dating_country',
        'address_1',
        'address_2',
        'connections_view',

        // Appearance & Lifestyle
        'lifestyle_habits',
        'body_type',
        'ethnicity',
        'religious_beliefs',
        'languages',

        // Interests & Personality
        'hobbies',
        'personality_traits',
        'pet_preference',
        'political_views',
        'family_plans',
        'children_status',
        'prompt_question',
        'prompt_answer',

        // Visual info
        'visual_description',
    ];

    protected $casts = [
        'is_active'         => 'boolean',
        'relationship_goal' => 'string',
        'looking_for'       => 'string',
        'religion'          => 'string',
        'smoking'           => 'string',
        'drinking'          => 'string',

        'showcase_page'     => 'boolean',
        'dating_dob'        => 'date',
        'connections_view'  => 'boolean',
        'languages'         => 'array',
        'hobbies'           => 'array',
        'personality_traits' => 'array',
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
