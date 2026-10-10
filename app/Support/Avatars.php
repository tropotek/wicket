<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Format;
use Intervention\Image\ImageManager;

/**
 * Profile photos: re-encoded to a 256 px square WebP with metadata (EXIF, GPS) stripped, so the
 * served file is always a clean image. Stored in the media disk's hidden .avatars/ folder.
 */
class Avatars
{
    public const DIR = '.avatars';

    public const SIZE = 256;

    public function store(User $user, UploadedFile $file): void
    {
        $webp = (string) ImageManager::usingDriver(Driver::class, decodeAnimation: false, strip: true)
            ->decodePath($file->getRealPath())
            ->cover(self::SIZE, self::SIZE)
            ->encodeUsingFormat(Format::WEBP, quality: 85);

        $path = self::DIR.'/'.substr(sha1($webp), 0, 16).'.webp';
        Storage::disk('media')->put($path, $webp);

        if ($user->avatar !== $path) {
            $this->delete($user->avatar);
        }
        $user->forceFill(['avatar' => $path])->save();
    }

    public function remove(User $user): void
    {
        $this->delete($user->avatar);
        $user->forceFill(['avatar' => null])->save();
    }

    private function delete(?string $path): void
    {
        if ($path && str_starts_with($path, self::DIR.'/')) {
            Storage::disk('media')->delete($path);
        }
    }
}
