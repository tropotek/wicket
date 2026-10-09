<?php

namespace App\Enums;

/**
 * Who can read a page. Authors and admins can always read their pages.
 */
enum Visibility: string
{
    case Public = 'public';     // everyone, including logged-out visitors
    case Members = 'members';   // member role and above
    case Editors = 'editors';   // editor role and above
    case Private = 'private';   // author and admins only

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /** Lowest role that can read this page, or null when no role is enough (private) or none is needed (public). */
    public function minimumRole(): ?Role
    {
        return match ($this) {
            self::Public, self::Private => null,
            self::Members => Role::Member,
            self::Editors => Role::Editor,
        };
    }
}
