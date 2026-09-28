<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Log extends Model
{
    protected $table = 'logs';

    protected $fillable = ['user_id', 'user_name', 'email', 'action', 'document', 'timestamp', 'status'];

    /** Empty for a visitor with no account, or one since removed. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
