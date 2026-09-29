<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Log extends Model
{
    protected $table = 'logs';

    protected $fillable = ['user_id', 'bluebook_id', 'user_name', 'email', 'action', 'document', 'timestamp', 'status'];

    /** Empty for a visitor with no account, or one since removed. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The paper this entry is about - empty when it is about none, or it was deleted. */
    public function bluebook(): BelongsTo
    {
        return $this->belongsTo(Bluebook::class);
    }
}
