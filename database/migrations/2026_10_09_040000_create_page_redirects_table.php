<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Old slugs of renamed pages. Each points at the page, not at a slug, so there are no chains.
     */
    public function up(): void
    {
        Schema::create('page_redirects', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->foreignId('page_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_redirects');
    }
};
