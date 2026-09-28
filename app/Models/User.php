<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const ROLE_ADMIN     = 'Admin';
    public const ROLE_SUB_ADMIN = 'Sub-Admin';

    protected $fillable = ['name', 'email', 'password', 'role', 'can_upload', 'permissions', 'google_id', 'avatar'];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = ['can_upload' => 'boolean', 'permissions' => 'array', 'policy_accepted_at' => 'datetime'];

    /** Admins and Sub-Admins: the people who use the admin side. */
    public static function isStaff(?string $role): bool
    {
        return $role === self::ROLE_ADMIN || $role === self::ROLE_SUB_ADMIN;
    }

    /**
     * Whether the signed-in user (the session array) holds a privilege. Admins
     * and Sub-Admins hold every one; what sets an Admin apart is managing
     * Admin and Sub-Admin accounts (AdminController::mayManage).
     */
    public static function allows(?array $user, string $permission): bool
    {
        return self::isStaff($user['role'] ?? null);
    }

    /**
     * Whether an account may submit papers. Only Students upload, and only once
     * an admin has enabled it; Faculty browse and read the archive.
     */
    public static function mayUpload(?string $role, bool $canUpload): bool
    {
        return $role === 'Student' && $canUpload;
    }

    /** Papers this account submitted (bluebooks.uploaded_by). */
    public function bluebooks(): HasMany
    {
        return $this->hasMany(Bluebook::class, 'uploaded_by', 'email');
    }

    /** Papers this account saved (bookmarks.user_email). */
    public function bookmarks(): HasMany
    {
        return $this->hasMany(Bookmark::class, 'user_email', 'email');
    }

    /** What this account did, from the access log. */
    public function logs(): HasMany
    {
        return $this->hasMany(Log::class);
    }
}
