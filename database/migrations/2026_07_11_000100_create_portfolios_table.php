<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portfolios', function (Blueprint $table) {
            $table->id();

            $table->string('uuid')->unique();
            $table->string('title');
            $table->string('client_name');
            $table->string('project_type', 50);
            // Tech badges for Astro (e.g. React, Next.js, PostgreSQL, Stripe).
            $table->json('tech_stack')->nullable();
            $table->string('live_url', 500)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->boolean('is_public')->default(true);
            $table->string('cover_path', 500)->nullable();
            $table->string('video_path', 500)->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);

            $table->foreignId('user_id')->constrained('users')->onUpdate('cascade')->onDelete('cascade');
            $table->softDeletes();
            $table->timestamps();

            // Canonical list filter pattern (status via deleted_at + default
            // ordering by created_at) — BACKEND-PHP §4.1.
            $table->index(['deleted_at', 'created_at']);
            // Per-user card ordering in the admin list.
            $table->index(['user_id', 'sort_order']);
            // Public landing-page feed: is_public filter + card ordering.
            $table->index(['is_public', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portfolios');
    }
};
