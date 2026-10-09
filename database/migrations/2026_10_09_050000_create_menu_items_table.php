<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Navbar menu as an adjacency list. Deleting a page or a parent removes its items.
     */
    public function up(): void
    {
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('menu_items')->cascadeOnDelete();
            $table->foreignId('page_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort')->default(0);
            $table->string('type', 16)->default('item');
            $table->string('name')->default('');
            $table->timestamps();

            $table->index(['parent_id', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }
};
