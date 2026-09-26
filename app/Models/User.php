<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLE_ADMIN     = 'Admin';
    public const ROLE_SUB_ADMIN = 'Sub-Admin';

    /**
     * The admin privileges an Admin can give a Sub-Admin, one account at a
     * time. An Admin holds all of them; every Sub-Admin can also open the
     * admin dashboard and read the bluebooks, whatever is ticked here.
     */
    public const PERMISSIONS = [
        'review_bluebooks' => 'Review submissions: approve, reject with a reason, record waivers, mark Waiver Received',
        'manage_bluebooks' => 'Manage bluebooks: add, edit, delete, reprocess OCR',
        'manage_users'     => 'Manage student and faculty accounts, and their upload permission',
        'view_logs'        => 'View the access logs',
    ];

    protected $fillable = ['name', 'email', 'password', 'role', 'can_upload', 'permissions', 'google_id', 'avatar'];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = ['can_upload' => 'boolean', 'permissions' => 'array', 'policy_accepted_at' => 'datetime'];

    /** Admins and Sub-Admins: the people who use the admin side. */
    public static function isStaff(?string $role): bool
    {
        return $role === self::ROLE_ADMIN || $role === self::ROLE_SUB_ADMIN;
    }

    /**
     * Whether the signed-in user (the session array) holds a privilege. An
     * Admin holds every one; a Sub-Admin only those ticked on their account.
     */
    public static function allows(?array $user, string $permission): bool
    {
        $role = $user['role'] ?? null;

        if ($role === self::ROLE_ADMIN) {
            return true;
        }

        return $role === self::ROLE_SUB_ADMIN
            && in_array($permission, (array) ($user['permissions'] ?? []), true);
    }
}
