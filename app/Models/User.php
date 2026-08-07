<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'role', 'can_upload', 'google_id', 'avatar'];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = ['can_upload' => 'boolean', 'policy_accepted_at' => 'datetime'];
}
