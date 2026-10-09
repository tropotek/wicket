<?php

namespace App\Console\Commands;

use App\Models\PageLock;
use App\Support\MediaStore;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class Cron extends Command
{
    public const LAST_RUN_KEY = 'wicket:cron.last_run';

    protected $signature = 'wicket:cron';

    protected $description = 'Hourly housekeeping: expired edit locks, orphan thumbnails, expired password resets';

    public function handle(): int
    {
        $locks = PageLock::where('expires_at', '<=', now())->delete();
        $this->line("Expired edit locks removed: {$locks}");

        $this->line('Orphan thumbnails removed: '.$this->orphanThumbnails());

        $this->call('auth:clear-resets');

        Cache::forever(self::LAST_RUN_KEY, now()->timestamp);

        return self::SUCCESS;
    }

    /** Thumbnails whose original was deleted outside the app (the media browser removes both). */
    private function orphanThumbnails(): int
    {
        $disk = Storage::disk('media');
        $prefix = MediaStore::THUMB_DIR.'/';
        $removed = 0;

        foreach ($disk->allFiles(MediaStore::THUMB_DIR) as $thumb) {
            if (! $disk->exists(substr($thumb, strlen($prefix)))) {
                $disk->delete($thumb);
                $removed++;
            }
        }

        return $removed;
    }
}
