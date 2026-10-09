<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * role, active and last_login_at are deliberately not fillable: set them explicitly.
 */
#[Fillable(['username', 'name', 'email', 'password', 'timezone'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** Letters, digits, dot, dash, underscore. No "@", so a username never looks like an email. */
    public const USERNAME_PATTERN = '/^[A-Za-z0-9._-]+$/';

    /** Mirrors the column defaults so a freshly created model has them without a refresh. */
    protected $attributes = [
        'role' => 'member',
        'active' => true,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    /** True when the user's role is $role or higher. */
    public function hasRole(Role $role): bool
    {
        return $this->role->atLeast($role);
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    /** True when this is an active admin and no other active admin exists. */
    public function isLastActiveAdmin(): bool
    {
        return $this->isAdmin() && $this->active && ! static::query()
            ->where('role', Role::Admin)
            ->where('active', true)
            ->whereKeyNot($this->getKey())
            ->exists();
    }
}
