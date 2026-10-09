<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Full-text index of pages (App\Support\SearchIndex), rowid = page id. SQLite FTS5 only;
     * other drivers skip it and search falls back to LIKE.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        DB::statement("CREATE VIRTUAL TABLE pages_fts USING fts5(title, category, body, meta, tokenize = 'porter unicode61 remove_diacritics 2')");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('DROP TABLE IF EXISTS pages_fts');
        }
    }
};
