<?php

namespace App\Policies;

use App\Models\User;

/**
 * No admin bypass: admins are deliberately barred from changing their own
 * role/active flag and from deleting themselves.
 */
class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isAdmin();
    }

    public function view(User $actor, User $target): bool
    {
        return $actor->isAdmin() || $actor->is($target);
    }

    public function create(User $actor): bool
    {
        return $actor->isAdmin();
    }

    public function update(User $actor, User $target): bool
    {
        return $actor->isAdmin() || $actor->is($target);
    }

    /** Change role or active flag. */
    public function changeAccess(User $actor, User $target): bool
    {
        return $actor->isAdmin() && ! $actor->is($target);
    }

    public function delete(User $actor, User $target): bool
    {
        return $this->changeAccess($actor, $target) && ! $target->isLastActiveAdmin();
    }
}
