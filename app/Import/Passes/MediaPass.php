<?php

namespace App\Import\Passes;

use App\Import\ImportContext;
use App\Import\Pass;
use FilesystemIterator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * tkwiki `data/media/` → the `media` disk, same relative paths, mtimes kept. Dot-prefixed entries
 * (elFinder's .tmb, .trash…) are left behind; Wicket makes its own thumbnails on demand.
 */
class MediaPass implements Pass
{
    /** @var ?array<string, SplFileInfo> relative path → source file */
    private ?array $files = null;

    public function name(): string
    {
        return 'Media';
    }

    public function count(ImportContext $context): int
    {
        return count($this->files($context));
    }

    public function run(ImportContext $context): int
    {
        $disk = Storage::disk('media');

        foreach ($this->files($context) as $path => $file) {
            $disk->makeDirectory(dirname($path));
            copy($file->getPathname(), $disk->path($path));
            touch($disk->path($path), $file->getMTime());
        }

        return count($this->files);
    }

    /** @return array<string, SplFileInfo> */
    private function files(ImportContext $context): array
    {
        if ($this->files !== null) {
            return $this->files;
        }

        $this->files = [];
        if ($context->mediaSrc === null) {
            return $this->files;
        }

        $root = rtrim($context->mediaSrc, '/');
        $allowed = config('wicket.media_extensions');
        $entries = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            fn (SplFileInfo $entry) => ! str_starts_with($entry->getFilename(), '.'),
        ));

        $bytes = 0;
        foreach ($entries as $entry) {
            $path = substr($entry->getPathname(), strlen($root) + 1);

            if ($entry->isLink()) {
                $context->warn("Media file \"{$path}\" is a link; skipped.");
            } elseif (! in_array(strtolower($entry->getExtension()), $allowed, true)) {
                $context->warn("Media file \"{$path}\" has a blocked file type; skipped.");
            } else {
                $this->files[$path] = $entry;
                $bytes += $entry->getSize();
            }
        }

        ksort($this->files);
        $context->note('Media: '.count($this->files).' '.Str::plural('file', count($this->files)).', '.Number::fileSize($bytes, 1)." from {$root}.");

        return $this->files;
    }
}
