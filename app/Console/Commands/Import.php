<?php

namespace App\Console\Commands;

use App\Import\ImportContext;
use App\Import\Importer;
use App\Import\LegacySource;
use App\Models\PageLink;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Throwable;

use function Laravel\Prompts\password;

class Import extends Command
{
    protected $signature = 'wicket:import
                            {--from= : Source database, mysql://user:password@host:port/database}
                            {--old-key= : tkwiki\'s system.encrypt key (decrypts its secrets)}
                            {--media-src= : tkwiki\'s data/media directory to copy}
                            {--dry-run : Report what would be imported; change nothing}
                            {--force : Empty Wicket\'s users, pages, menu, secrets and settings first}';

    protected $description = 'Import a tkwiki site: users, pages and revisions, menu, secrets, settings and media';

    public function handle(Importer $importer): int
    {
        $from = (string) $this->option('from');
        if ($from === '') {
            $this->components->error('Pass the source database: --from=mysql://user:password@host:port/database');

            return self::FAILURE;
        }

        $source = new LegacySource($from);
        try {
            $source->connect();
            $counts = $source->counts();
        } catch (InvalidArgumentException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        } catch (Throwable $e) {
            $this->components->error('Could not read the source database: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->components->info('tkwiki source');
        $this->table(['Table', 'Rows'], collect($counts)->map(fn ($n, $t) => [$t, $n])->values()->all());

        $dryRun = (bool) $this->option('dry-run');

        if (! $dryRun && ! $importer->targetIsEmpty()) {
            if (! $this->option('force')) {
                $this->components->error('Wicket already has users or pages. Import into a fresh install, or pass --force to empty them first.');

                return self::FAILURE;
            }
            $importer->wipeTarget();
            $this->components->warn('Emptied existing users, pages, menu, secrets and settings (--force).');
        }

        $oldKey = $this->option('old-key');
        if ($oldKey === null && $counts['secret'] > 0 && $this->input->isInteractive() && ! $dryRun) {
            $oldKey = password("tkwiki's system.encrypt key (to decrypt {$counts['secret']} secrets; empty to skip them)") ?: null;
        }

        $context = new ImportContext($source, $oldKey ?: null, $this->option('media-src') ?: null, $dryRun);
        $report = $importer->run($context, fn (string $name) => $this->components->task($name));

        $this->table(['Pass', 'Found', $dryRun ? 'Would import' : 'Imported'], collect($report)
            ->map(fn ($r, $name) => [$name, $r['count'], $r['imported'] ?? $r['count']])->values()->all());

        foreach ($context->warnings as $warning) {
            $this->components->warn($warning);
        }

        if ($dryRun) {
            $this->components->info('Dry run: nothing was changed.');
        } elseif ($report !== []) {
            $this->call('wicket:reindex');
            $this->components->info("Links: {$counts['links']} in tkwiki, ".PageLink::count().' rebuilt from page content.');
        }

        return self::SUCCESS;
    }
}
