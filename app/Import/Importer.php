<?php

namespace App\Import;

use App\Models\Page;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Runs the import passes in order against the legacy source. */
class Importer
{
    /** @var list<class-string<Pass>> in dependency order */
    public const PASSES = [
        Passes\UsersPass::class,
        Passes\PagesPass::class,
        Passes\MenuPass::class,
        Passes\SecretsPass::class,
        Passes\RevisionsPass::class,
        Passes\MediaPass::class,
    ];

    /** Wicket tables a forced re-import empties first (children before parents). */
    private const WIPE = ['page_links', 'page_redirects', 'menu_items', 'contents', 'page_locks', 'secrets', 'pages', 'users'];

    /** @return list<Pass> */
    public function passes(): array
    {
        return array_map(fn (string $class) => app($class), static::PASSES);
    }

    public function targetIsEmpty(): bool
    {
        return ! Page::withTrashed()->exists() && ! User::exists();
    }

    /** Remove Wicket content so a re-import starts clean (dev / retry only). */
    public function wipeTarget(): void
    {
        DB::transaction(function () {
            foreach (self::WIPE as $table) {
                DB::table($table)->delete();
            }
            DB::table('settings')->delete();
        });
    }

    /**
     * @return array<string, array{count: int, imported: ?int}>
     */
    public function run(ImportContext $context, ?callable $progress = null): array
    {
        // All or nothing: a pass failing part-way leaves no half import behind.
        return $context->dryRun ? $this->runPasses($context, $progress) : DB::transaction(fn () => $this->runPasses($context, $progress));
    }

    /**
     * @return array<string, array{count: int, imported: ?int}>
     */
    private function runPasses(ImportContext $context, ?callable $progress): array
    {
        $report = [];

        foreach ($this->passes() as $pass) {
            $count = $pass->count($context);
            $imported = $context->dryRun ? null : $pass->run($context);
            $report[$pass->name()] = ['count' => $count, 'imported' => $imported];
            $progress && $progress($pass->name(), $count, $imported);
        }

        return $report;
    }
}
