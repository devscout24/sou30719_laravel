<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DynamicPage extends Model
{
    // Mass assignable attributes
    protected $fillable = [
        'page_name',
        'content',
        'is_active',
    ];
}
