<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bookmark extends Model
{
    protected $fillable = ['user_email', 'user_name', 'bluebook_id', 'added_at'];
}
