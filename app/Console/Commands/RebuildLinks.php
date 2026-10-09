<?php

namespace App\Console\Commands;

use App\Models\Page;
use App\Support\LinkIndex;
use Illuminate\Console\Command;

class RebuildLinks extends Command
{
    protected $signature = 'wicket:links';

    protected $description = 'Rebuild the page link index from every page\'s current revision (e.g. after an import)';

    public function handle(LinkIndex $index): int
    {
        $count = 0;
        Page::withTrashed()->with('latestContent')->lazyById()->each(function (Page $page) use ($index, &$count) {
            $index->sync($page, $page->latestContent?->html);
            $count++;
        });

        $this->info("Indexed links for {$count} pages.");

        return self::SUCCESS;
    }
}
