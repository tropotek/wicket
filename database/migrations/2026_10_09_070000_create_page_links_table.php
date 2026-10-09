<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Page slugs linked from each page's current revision (App\Support\LinkIndex). Broken links and
     * backlinks are queries over this; nothing here goes stale when target pages change.
     */
    public function up(): void
    {
        Schema::create('page_links', function (Blueprint $table) {
            $table->foreignId('page_id')->constrained()->cascadeOnDelete();
            $table->string('slug');

            $table->primary(['page_id', 'slug']);
            $table->index('slug');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_links');
    }
};
