<?php

namespace App\Policies;

use App\Enums\Role;
use App\Enums\Visibility;
use App\Models\Page;
use App\Models\User;

/**
 * Mirrors tkwiki's canView/canEdit/canCreate. `Page::scopeVisibleTo()` is
 * the query form of view() and must stay in step with it.
 */
class PagePolicy
{
    public function view(?User $user, Page $page): bool
    {
        if ($page->visibility === Visibility::Public) {
            return true;
        }

        if ($user === null) {
            return false;
        }

        if ($user->isAdmin() || $page->author_id === $user->id) {
            return true;
        }

        $minimum = $page->visibility->minimumRole();

        return $minimum !== null && $user->hasRole($minimum);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(Role::Editor);
    }

    public function update(User $user, Page $page): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if (! $user->hasRole(Role::Editor)) {
            return false;
        }

        return $page->visibility !== Visibility::Private || $page->author_id === $user->id;
    }

    public function delete(User $user, Page $page): bool
    {
        return $user->isAdmin() || ($user->hasRole(Role::Editor) && $page->author_id === $user->id);
    }
}
