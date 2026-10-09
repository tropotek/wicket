<?php

namespace App\Policies;

use App\Enums\Role;
use App\Enums\Visibility;
use App\Models\Secret;
use App\Models\User;

/**
 * Mirrors tkwiki's Secret canCreate/canView/canEdit (staff = editor). `Secret::scopeVisibleTo()`
 * is the query form of view() and must stay in step with it.
 */
class SecretPolicy
{
    public function view(User $user, Secret $secret): bool
    {
        if ($user->isAdmin() || $secret->author_id === $user->id) {
            return true;
        }

        $minimum = $secret->visibility->minimumRole();

        return $secret->publish && $minimum !== null && $user->hasRole($minimum);
    }

    /** The manager lists what the user can see; it's for those who can write secrets. */
    public function viewAny(User $user): bool
    {
        return $this->create($user);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(Role::Editor);
    }

    /** Admin, the author, or an editor for members / editors secrets. Never a member. */
    public function update(User $user, Secret $secret): bool
    {
        if (! $user->hasRole(Role::Editor)) {
            return false;
        }

        return $user->isAdmin()
            || $secret->author_id === $user->id
            || $secret->visibility !== Visibility::Private;
    }

    public function delete(User $user, Secret $secret): bool
    {
        return $this->update($user, $secret);
    }
}
