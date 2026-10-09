<?php

namespace App\Console\Commands;

use App\Support\SearchIndex;
use Illuminate\Console\Command;

class Reindex extends Command
{
    protected $signature = 'wicket:reindex';

    protected $description = 'Rebuild the search index and the page link index from every page\'s current revision';

    public function handle(SearchIndex $search): int
    {
        if ($search->enabled()) {
            $this->info('Search: indexed '.$search->rebuild().' pages.');
        } else {
            $this->warn('Search: full-text index needs SQLite; skipped (search uses LIKE).');
        }

        return $this->call('wicket:links');
    }
}
