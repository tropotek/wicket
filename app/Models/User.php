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
}
