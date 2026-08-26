<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();

            $table->string('uuid')->unique();
            $table->string('name');
            $table->string('slug', 100);
            $table->string('description', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);

            $table->foreignId('user_id')->constrained('users')->onUpdate('cascade')->onDelete('cascade');
            $table->softDeletes();
            $table->timestamps();

            // Canonical list filter pattern (status via deleted_at + default
            // ordering by created_at) — BACKEND-PHP §4.1.
            $table->index(['deleted_at', 'created_at']);
            // Public select-input feed: active-only + display ordering.
            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
