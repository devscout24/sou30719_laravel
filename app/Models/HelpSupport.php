<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HelpSupport extends Model
{
    protected $table = 'help_supports';

    protected $fillable = [
        'customer_id',
        'subject',
        'message',
    ];

    protected $casts = [
        'customer_id' => 'integer',
        'subject'     => 'string',
        'message'     => 'string',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }
}
