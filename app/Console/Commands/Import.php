<?php

namespace App\Console\Commands;

use App\Import\ImportContext;
use App\Import\Importer;
use App\Import\LegacySource;
use App\Import\Passes\SecretsPass;
use App\Models\PageLink;
use Illuminate\Console\Command;
use InvalidArgumentException;
use RuntimeException;
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

        $mediaSrc = $this->option('media-src') ?: null;
        if ($mediaSrc !== null && ! is_dir($mediaSrc)) {
            $this->components->error("--media-src is not a directory: {$mediaSrc}");

            return self::FAILURE;
        }

        if (! $dryRun && ! $importer->targetIsEmpty() && ! $this->option('force')) {
            $this->components->error('Wicket already has users or pages. Import into a fresh install, or pass --force to empty them first.');

            return self::FAILURE;
        }

        $oldKey = $this->option('old-key');
        if ($oldKey === null && $counts['secret'] > 0 && $this->input->isInteractive() && ! $dryRun) {
            $oldKey = password("tkwiki's system.encrypt key (to decrypt {$counts['secret']} secrets; empty to skip them)") ?: null;
        }

        if ($oldKey && $counts['secret'] > 0) {
            try {
                SecretsPass::checkKey($source->db(), $oldKey);
            } catch (InvalidArgumentException|RuntimeException $e) {
                $this->components->error($e->getMessage());

                return self::FAILURE;
            }
        }

        if (! $dryRun && ! $importer->targetIsEmpty()) {
            $importer->wipeTarget();
            $this->components->warn('Emptied existing users, pages, menu, secrets and settings (--force).');
        }

        $context = new ImportContext($source, $oldKey ?: null, $mediaSrc, $dryRun);
        $report = $importer->run($context, fn (string $name) => $this->components->task($name));

        $this->table(['Pass', 'Found', $dryRun ? 'Would import' : 'Imported'], collect($report)
            ->map(fn ($r, $name) => [$name, $r['count'], $r['imported'] ?? $r['count']])->values()->all());

        foreach ($context->notes as $note) {
            $this->components->info($note);
        }

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
