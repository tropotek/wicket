<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static mixed get(string $key, mixed $default = null)
 * @method static array<string, mixed> all()
 * @method static void set(array $values)
 *
 * @see \App\Support\Settings
 */
class Settings extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \App\Support\Settings::class;
    }
}
