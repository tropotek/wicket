<?php

namespace App\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ExceptionInterface;

/**
 * Files under data/media (the `media` disk), served as static files at /data/media.
 * Thumbnails mirror the file's path under .thumbs/.
 */
class MediaStore
{
    public const URL = '/data/media';

    public const THUMB_DIR = '.thumbs';

    public const THUMB_SIZE = 400;

    /**
     * Store an uploaded image byte-for-byte in uploads/YYYY/MM/ and make its thumbnail.
     *
     * @return array{location: string, thumbnail: string, name: string}
     */
    public function storeImage(UploadedFile $file): array
    {
        $dir = 'uploads/'.now()->format('Y/m');
        $name = $this->freeName($dir, Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'image', $file->extension());
        $path = $this->disk()->putFileAs($dir, $file, $name);

        try {
            $this->makeThumbnail($path);
        } catch (ExceptionInterface) {
            $this->disk()->delete($path);

            throw ValidationException::withMessages(['file' => __('The image could not be read.')]);
        }

        return [
            'location' => $this->url($path),
            'thumbnail' => $this->url($this->thumbnailPath($path)),
            'name' => $name,
        ];
    }

    /** Scaled-down copy (never upscaled), metadata stripped. */
    public function makeThumbnail(string $path): void
    {
        $thumb = $this->thumbnailPath($path);
        $this->disk()->makeDirectory(dirname($thumb));

        ImageManager::usingDriver(Driver::class, decodeAnimation: false, strip: true)
            ->decodePath($this->disk()->path($path))
            ->scaleDown(self::THUMB_SIZE, self::THUMB_SIZE)
            ->save($this->disk()->path($thumb));
    }

    public function thumbnailPath(string $path): string
    {
        return self::THUMB_DIR.'/'.ltrim($path, '/');
    }

    public function url(string $path): string
    {
        return self::URL.'/'.ltrim($path, '/');
    }

    private function disk(): Filesystem
    {
        return Storage::disk('media');
    }

    /** $base.$ext, or $base-2.$ext, $base-3.$ext… whichever is free in $dir. */
    private function freeName(string $dir, string $base, string $ext): string
    {
        $name = "{$base}.{$ext}";

        for ($n = 2; $this->disk()->exists("{$dir}/{$name}"); $n++) {
            $name = "{$base}-{$n}.{$ext}";
        }

        return $name;
    }
}
