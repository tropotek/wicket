<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class Backup extends Command
{
    protected $signature = 'wicket:backup {--keep=7 : How many snapshots to keep}';

    protected $description = 'Write a consistent SQLite snapshot to data/backups (safe while the site runs)';

    public function handle(): int
    {
        if (DB::getDriverName() !== 'sqlite') {
            $this->components->error('wicket:backup snapshots SQLite only; back up other databases with their own tools.');

            return self::FAILURE;
        }

        $dir = base_path('data/backups');
        if (! is_dir($dir)) {
            mkdir($dir, 0700, true);
        }

        $file = $dir.'/wiki-'.now()->format('Ymd-His').'.sqlite';
        // VACUUM INTO writes a transactionally consistent copy without stopping writers.
        DB::statement('VACUUM INTO ?', [$file]);
        chmod($file, 0600);

        $keep = max(1, (int) $this->option('keep'));
        $snapshots = glob($dir.'/wiki-*.sqlite') ?: [];
        rsort($snapshots);
        foreach (array_slice($snapshots, $keep) as $old) {
            unlink($old);
        }

        $this->components->info("Snapshot written: {$file}");
        $this->line('Back up the whole data/ directory (snapshot, media, private/.app-key) — secrets need the key.');

        return self::SUCCESS;
    }
}
