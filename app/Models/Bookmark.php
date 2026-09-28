<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bookmark extends Model
{
    protected $fillable = ['user_email', 'user_name', 'bluebook_id', 'added_at'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_email', 'email');
    }

    public function bluebook(): BelongsTo
    {
        return $this->belongsTo(Bluebook::class);
    }
}
