<?php

namespace App\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ExceptionInterface;

/**
 * Files under data/media (the `media` disk), served as static files at /data/media.
 * Thumbnails mirror an image's path under .thumbs/. Dot-prefixed entries are internal:
 * never listed and never reachable through safePath().
 */
class MediaStore
{
    public const URL = '/data/media';

    public const THUMB_DIR = '.thumbs';

    public const THUMB_SIZE = 400;

    public const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    /** Video and audio TinyMCE's media dialog can embed. */
    public const MEDIA_EXTENSIONS = ['mp4', 'webm', 'ogv', 'mp3', 'm4a', 'ogg', 'wav'];

    /**
     * Normalise a user-supplied path relative to the media root ('' is the root).
     * Rejects absolute, empty, dot-prefixed (incl. `..`) and NUL/backslash segments, and anything
     * whose real path (symlinks resolved) leaves the root. Does not require the path to exist.
     */
    public function safePath(?string $path): string
    {
        $path = trim((string) $path, '/');

        if ($path === '') {
            return '';
        }

        abort_if(str_contains($path, "\0") || str_contains($path, '\\'), 404);

        foreach (explode('/', $path) as $segment) {
            abort_if($segment === '' || str_starts_with($segment, '.'), 404);
        }

        $root = realpath($this->disk()->path(''));
        $real = realpath($this->disk()->path($path));
        abort_if($real !== false && $real !== $root && ! str_starts_with($real, $root.DIRECTORY_SEPARATOR), 404);

        return $path;
    }

    public function isDirectory(string $path): bool
    {
        return is_dir($this->disk()->path($path));
    }

    public function isFile(string $path): bool
    {
        return $path !== '' && is_file($this->disk()->path($path));
    }

    /**
     * Visible folders, then files, in $dir, each sorted by name.
     *
     * @return Collection<int, array{type: string, path: string, name: string, size: ?int, modified: int, image: bool, media: bool, url: ?string}>
     */
    public function list(string $dir): Collection
    {
        $entry = fn (string $path, string $type) => [
            'type' => $type,
            'path' => $path,
            'name' => basename($path),
            'size' => $type === 'file' ? $this->disk()->size($path) : null,
            'modified' => $this->disk()->lastModified($path),
            'image' => $type === 'file' && $this->isImage($path),
            'media' => $type === 'file' && in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::MEDIA_EXTENSIONS, true),
            'url' => $type === 'file' ? $this->url($path) : null,
        ];
        $visible = fn (string $path) => ! str_starts_with(basename($path), '.');
        $byName = fn (array $a, array $b) => strnatcasecmp($a['name'], $b['name']);

        return collect($this->disk()->directories($dir))->filter($visible)->map(fn ($p) => $entry($p, 'dir'))->sort($byName)
            ->concat(collect($this->disk()->files($dir))->filter($visible)->map(fn ($p) => $entry($p, 'file'))->sort($byName))
            ->values();
    }

    /** Visible files under $path (itself, for a file). */
    public function countFiles(string $path): int
    {
        if ($this->isFile($path)) {
            return 1;
        }

        return collect($this->disk()->allFiles($path))
            ->reject(fn (string $p) => collect(explode('/', $p))->contains(fn ($s) => str_starts_with($s, '.')))
            ->count();
    }

    /**
     * Store an editor-pasted image in uploads/YYYY/MM/.
     *
     * @return array{location: string, thumbnail: string, name: string}
     */
    public function storeImage(UploadedFile $file): array
    {
        $path = $this->store($file, 'uploads/'.now()->format('Y/m'), 'image');

        return [
            'location' => $this->url($path),
            'thumbnail' => $this->url($this->thumbnailPath($path)),
            'name' => basename($path),
        ];
    }

    /**
     * Store a file byte-for-byte in $dir under a slugged, unused name; images get a thumbnail.
     * Returns the stored path.
     */
    public function store(UploadedFile $file, string $dir, string $fallback = 'file'): string
    {
        $ext = strtolower($file->getClientOriginalExtension());
        $name = $this->freeName($dir, $this->slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME), $fallback), $ext);
        $path = $this->disk()->putFileAs($dir, $file, $name);

        if ($this->isImage($path)) {
            try {
                $this->makeThumbnail($path);
            } catch (ExceptionInterface) {
                $this->disk()->delete($path);

                throw ValidationException::withMessages(['file' => __(':name could not be read as an image.', ['name' => $file->getClientOriginalName()])]);
            }
        }

        return $path;
    }

    /** Create a slugged folder in $dir. Returns its path. */
    public function makeFolder(string $dir, string $name): string
    {
        $path = ltrim($dir.'/'.$this->slug($name, 'folder'), '/');

        if ($this->disk()->exists($path)) {
            throw ValidationException::withMessages(['name' => __('":name" already exists here.', ['name' => basename($path)])]);
        }

        $this->disk()->makeDirectory($path);

        return $path;
    }

    /**
     * Rename a file or folder in place. A file keeps its extension (typing it is optional);
     * its thumbnail, or a folder's thumbnail folder, moves with it. Returns the new path.
     */
    public function rename(string $path, string $name): string
    {
        $isFile = $this->isFile($path);
        $ext = $isFile ? strtolower(pathinfo($path, PATHINFO_EXTENSION)) : '';

        if ($ext !== '' && str_ends_with(strtolower($name), '.'.$ext)) {
            $name = substr($name, 0, -strlen($ext) - 1);
        }

        $base = $this->slug($name, $isFile ? 'file' : 'folder');
        $parent = dirname($path) === '.' ? '' : dirname($path);
        $target = ltrim($parent.'/'.$base.($ext !== '' ? '.'.$ext : ''), '/');

        if ($target === $path) {
            return $path;
        }

        if ($this->disk()->exists($target)) {
            throw ValidationException::withMessages(['name' => __('":name" already exists here.', ['name' => basename($target)])]);
        }

        $this->disk()->move($path, $target);

        if ($this->disk()->exists($this->thumbnailPath($path))) {
            $this->disk()->makeDirectory(dirname($this->thumbnailPath($target)));
            $this->disk()->move($this->thumbnailPath($path), $this->thumbnailPath($target));
        }

        return $target;
    }

    /** Delete a file or a whole folder, with thumbnails. */
    public function delete(string $path): void
    {
        if ($this->isFile($path)) {
            $this->disk()->delete([$path, $this->thumbnailPath($path)]);

            return;
        }

        $this->disk()->deleteDirectory($path);
        $this->disk()->deleteDirectory($this->thumbnailPath($path));
    }

    /** Absolute path of $path's thumbnail, made now if missing (e.g. imported files). */
    public function thumbnailFile(string $path): string
    {
        if (! $this->disk()->exists($this->thumbnailPath($path))) {
            $this->makeThumbnail($path);
        }

        return $this->disk()->path($this->thumbnailPath($path));
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

    public function isImage(string $path): bool
    {
        return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::IMAGE_EXTENSIONS, true);
    }

    /** Public URL; segments are encoded since imported names may hold spaces etc. */
    public function url(string $path): string
    {
        return self::URL.'/'.collect(explode('/', ltrim($path, '/')))->map(fn ($s) => rawurlencode($s))->implode('/');
    }

    private function disk(): Filesystem
    {
        return Storage::disk('media');
    }

    private function slug(string $name, string $fallback): string
    {
        return Str::slug($name) ?: $fallback;
    }

    /** $base.$ext, or $base-2.$ext, $base-3.$ext… whichever is free in $dir. */
    private function freeName(string $dir, string $base, string $ext): string
    {
        $suffix = $ext !== '' ? '.'.$ext : '';
        $name = $base.$suffix;

        for ($n = 2; $this->disk()->exists(ltrim("{$dir}/{$name}", '/')); $n++) {
            $name = "{$base}-{$n}{$suffix}";
        }

        return $name;
    }
}
