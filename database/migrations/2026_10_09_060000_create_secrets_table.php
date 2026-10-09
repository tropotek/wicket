<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Credentials embedded in pages. Everything but the name is encrypted with APP_KEY (model casts).
     */
    public function up(): void
    {
        Schema::create('secrets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->text('url')->nullable();
            $table->text('username')->nullable();
            $table->text('password')->nullable();
            $table->text('otp')->nullable();
            $table->text('keys')->nullable();
            $table->text('notes')->nullable();
            $table->string('visibility', 16)->default('private');
            $table->boolean('publish')->default(true);
            $table->timestamps();

            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('secrets');
    }
};
