<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Editor = 'editor';
    case Member = 'member';
    case Guest = 'guest';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function rank(): int
    {
        return match ($this) {
            self::Admin => 3,
            self::Editor => 2,
            self::Member => 1,
            self::Guest => 0,
        };
    }

    public function atLeast(self $role): bool
    {
        return $this->rank() >= $role->rank();
    }
}
