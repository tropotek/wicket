<?php

namespace App\Models;

use App\Enums\Visibility;
use Database\Factories\SecretFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A stored credential (login, 2FA seed, keys) shown inside pages. Visibility is members,
 * editors or private — never public. author_id is set explicitly, not mass-assigned.
 */
#[Fillable(['name', 'url', 'username', 'password', 'otp', 'keys', 'notes', 'visibility', 'publish'])]
class Secret extends Model
{
    /** @use HasFactory<SecretFactory> */
    use HasFactory;

    /** Never serialise the decrypted values by accident (e.g. JSON responses). */
    protected $hidden = ['url', 'username', 'password', 'otp', 'keys', 'notes'];

    protected $attributes = [
        'visibility' => 'private',
        'publish' => true,
    ];

    protected function casts(): array
    {
        return [
            'url' => 'encrypted',
            'username' => 'encrypted',
            'password' => 'encrypted',
            'otp' => 'encrypted',
            'keys' => 'encrypted',
            'notes' => 'encrypted',
            'visibility' => Visibility::class,
            'publish' => 'boolean',
        ];
    }

    /** Visibilities a secret may have. */
    public static function visibilities(): array
    {
        return array_values(array_filter(Visibility::cases(), fn (Visibility $v) => $v !== Visibility::Public));
    }

    /**
     * Secrets $user may read: the query form of SecretPolicy::view().
     *
     * @param  Builder<Secret>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, ?User $user): void
    {
        if ($user === null) {
            $query->whereRaw('1 = 0');

            return;
        }

        if ($user->isAdmin()) {
            return;
        }

        $byRole = collect(self::visibilities())
            ->filter(fn (Visibility $v) => ($min = $v->minimumRole()) && $user->hasRole($min))
            ->map->value;

        $query->where(fn (Builder $q) => $q
            ->where('author_id', $user->id)
            ->orWhere(fn (Builder $q) => $q->where('publish', true)->whereIn('visibility', $byRole->all())));
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
