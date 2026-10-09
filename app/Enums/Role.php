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
}
