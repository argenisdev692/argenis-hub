<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('blog_categories', function (Blueprint $table) {
            $table->id();

            $table->string('uuid')->unique();
            $table->string('blog_category_name')->nullable();
            $table->string('blog_category_description')->nullable();
            $table->string('blog_category_image')->nullable();

            $table->foreignId('user_id')->constrained('users')->onUpdate('cascade')->onDelete('cascade');
            $table->softDeletes();
            $table->timestamps();

            // Covers the canonical list filter pattern (status via deleted_at +
            // default ordering by created_at) — BACKEND-PHP §4.1.
            $table->index(['deleted_at', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blog_categories');
    }
};
