<?php

namespace App\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * Site-managed images under data/public (the `public_data` disk): the logo in site/ and profile
 * photos in avatars/. Served as static files at /data/public, apart from users' media.
 */
class PublicData
{
    public const URL = '/data/public';

    public function disk(): Filesystem
    {
        return Storage::disk('public_data');
    }

    public function url(string $path): string
    {
        return self::URL.'/'.collect(explode('/', ltrim($path, '/')))->map(fn ($s) => rawurlencode($s))->implode('/');
    }
}
