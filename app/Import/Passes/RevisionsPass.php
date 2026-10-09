<?php

namespace App\Import\Passes;

use App\Html\Sanitizer;
use App\Import\ImportContext;
use App\Import\LegacyHtml;
use App\Import\Pass;
use App\Support\RenderCache;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * tkwiki `content` → contents, oldest first so the newest revision is each page's current one.
 * Legacy links are rewritten, then the HTML is sanitised (what Content::saving would do).
 */
class RevisionsPass implements Pass
{
    public function __construct(private Sanitizer $sanitizer, private RenderCache $cache) {}

    public function name(): string
    {
        return 'Revisions';
    }

    public function count(ImportContext $context): int
    {
        return $context->source->db()->table('content')->count();
    }

    public function run(ImportContext $context): int
    {
        $html = new LegacyHtml(array_filter($context->slugs, fn ($new, $old) => $new !== (string) $old, ARRAY_FILTER_USE_BOTH));
        $orphans = [];
        $imported = 0;

        $context->source->db()->table('content')
            ->chunkById(200, function ($rows) use ($context, $html, &$orphans, &$imported) {
                $insert = [];
                foreach ($rows as $row) {
                    $pageId = $context->newId('page', (int) $row->page_id);
                    if ($pageId === null) {
                        $orphans[$row->page_id] = ($orphans[$row->page_id] ?? 0) + 1;

                        continue;
                    }

                    $insert[] = [
                        'page_id' => $pageId,
                        'user_id' => $context->newId('user', $row->user_id === null ? null : (int) $row->user_id),
                        'html' => $this->sanitizer->clean($html->rewrite((string) $row->html)),
                        'keywords' => (string) $row->keywords,
                        'description' => (string) $row->description,
                        'css' => $row->css,
                        'js' => $row->js,
                        'created_at' => $row->created ?? Carbon::now(),
                    ];
                }
                DB::table('contents')->insert($insert);
                $imported += count($insert);
            }, 'content_id');

        foreach ($orphans as $pageId => $n) {
            $context->warn("Skipped {$n} revision(s) of page {$pageId}, which doesn't exist in tkwiki.");
        }

        $this->cache->flush();

        return $imported;
    }
}
