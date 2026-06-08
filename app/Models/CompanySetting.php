<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanySetting extends Model
{
    protected $fillable = [

        // Company Info
        'company_name',
        'website',
        'email',
        'hotline',
        'address',
        'description',
        'logo',

        // App Links
        'play_store_link',
        'apple_store_link',

        // Social
        'facebook',
        'linkedin',
        'youtube',
        'twitter',
        'tiktok',
        'threads',
    ];
}
