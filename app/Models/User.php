<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Role;
use App\Support\MediaStore;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * role, active, last_login_at and avatar are deliberately not fillable: set them explicitly.
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
    /** Public URL of the profile photo, or null to show initials. */
    public function avatarUrl(): ?string
    {
        return $this->avatar ? app(MediaStore::class)->url($this->avatar) : null;
    }

    /** Up to two initials from the name, for the avatar circle when there's no photo. */
    public function initials(): string
    {
        return collect(preg_split('/\s+/', trim($this->name)))->filter()->take(2)
            ->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
    }

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
