<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Jetstream\HasProfilePhoto;
use Spatie\Permission\Traits\HasRoles;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Wishlist;

class User extends Authenticatable implements JWTSubject
{
    use HasRoles;
    use HasApiTokens;
    use HasFactory;
    use HasProfilePhoto;
    use Notifiable;
    use SoftDeletes;

    protected $guarded = [
        'id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'dob' => 'date',
            'profile_completed' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }
    protected $casts = [
        'interests' => 'array',
    ];

    // implement 2 methods for token get
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    public function fcmTokens()
    {
        return $this->hasMany(FcmToken::class);
    }
    public function galleryImages()
    {
        return $this->hasMany(UserGalleryImage::class)->orderBy('sort_order');
    }

    public function datingProfile()
    {
        return $this->hasOne(DatingProfile::class);
    }

    public function datingPreference()
    {
        return $this->hasOne(DatingPreference::class);
    }

    public function posts()
    {
        return $this->hasMany(Post::class);
    }

    public function aiConversations()
    {
        return $this->hasMany(AiConversation::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(UserSubscription::class);
    }

    public function activeSubscription()
    {
        return $this->hasOne(UserSubscription::class)->where('status', 'active')->latestOfMany();
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function supportTickets()
    {
        return $this->hasMany(SupportTicket::class);
    }

    public function knowledgeBaseItems()
    {
        return $this->hasMany(KnowledgeBaseItem::class);
    }

    public function connectionsCount(): int
    {
        return UserConnection::where('user_one_id', $this->id)
            ->orWhere('user_two_id', $this->id)
            ->count();
    }

    /**
     * Whether this user's dating profile has enough information for the
     * Matches AI workspace to search for and be found by other daters.
     */
    public function hasCompletedDatingProfile(): bool
    {
        $profile = $this->datingProfile;

        return $profile !== null
            && filled($profile->dating_gender)
            && $this->datingPreference !== null
            && filled($this->datingPreference->interested_in);
    }
}
