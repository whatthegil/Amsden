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

    protected $fillable = ['name', 'email', 'password', 'role', 'can_upload', 'google_id', 'avatar'];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = ['can_upload' => 'boolean', 'policy_accepted_at' => 'datetime', 'password_set_at' => 'datetime'];

    /**
     * Whether the owner knows this account's password, and so can sign in
     * with email and password. Accounts made by Google sign-in start with a
     * random one until the owner sets their own (password_set_at).
     */
    public function hasKnownPassword(): bool
    {
        return $this->password_set_at !== null || !$this->google_id;
    }

    /**
     * Passwords are only ever stored as a bcrypt hash. The places that set one
     * hash it already; this catches any that forget, so a plain password can
     * never reach the database. A value that is already a hash is kept as is.
     */
    public function setPasswordAttribute(string $value): void
    {
        $this->attributes['password'] = password_get_info($value)['algoName'] === 'unknown'
            ? \Illuminate\Support\Facades\Hash::make($value)
            : $value;
    }

    /** A password the owner or an administrator chose. */
    public function setKnownPassword(string $plain): void
    {
        $this->forceFill(['password' => \Illuminate\Support\Facades\Hash::make($plain), 'password_set_at' => now()]);
    }

    /** Admins and Sub-Admins: the people who use the admin side. */
    public static function isStaff(?string $role): bool
    {
        return $role === self::ROLE_ADMIN || $role === self::ROLE_SUB_ADMIN;
    }

    /**
     * Privileges only the Admin holds. Approving an upload is what lets it
     * reach readers, so that stays with the Admin; a Sub-Admin keeps the
     * archive running when the Admin is away but cannot publish on their own.
     */
    public const ADMIN_ONLY = ['approve_bluebooks'];

    /**
     * Whether the signed-in user (the session array) holds a privilege. An
     * Admin holds every one; a Sub-Admin every one outside ADMIN_ONLY. The
     * other thing that sets an Admin apart is managing Admin and Sub-Admin
     * accounts (AdminController::mayManage).
     */
    public static function allows(?array $user, string $permission): bool
    {
        $role = $user['role'] ?? null;

        return in_array($permission, self::ADMIN_ONLY, true)
            ? $role === self::ROLE_ADMIN
            : self::isStaff($role);
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
