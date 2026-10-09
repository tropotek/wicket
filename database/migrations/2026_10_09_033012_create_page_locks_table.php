<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Edit locks: at most one per page (the primary key makes acquiring race-safe).
     */
    public function up(): void
    {
        Schema::create('page_locks', function (Blueprint $table) {
            $table->foreignId('page_id')->primary()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_locks');
    }
};
