<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('studio_profiles', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug', 64);
            $table->string('flow', 32)->default('fullstack');
            $table->boolean('is_active')->default(true);
            $table->string('base_city')->nullable();
            $table->string('base_country', 8)->nullable();
            $table->json('accepted_remote_scopes')->nullable();
            $table->json('stack_must')->nullable();
            $table->json('stack_reject')->nullable();
            $table->json('never_seed')->nullable();
            $table->json('search_languages')->nullable();
            $table->json('geography_prefer')->nullable();
            $table->json('geography_deny')->nullable();
            $table->unsignedSmallInteger('years_baseline')->nullable();
            $table->string('education_level', 64)->nullable();
            $table->json('language_levels')->nullable();
            $table->json('protected_block')->nullable();
            $table->json('seniority_band')->nullable();
            $table->string('tone', 32)->nullable();
            $table->json('rules')->nullable();
            $table->unsignedSmallInteger('rules_version')->default(2);
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['user_id', 'slug']);
            $table->index(['deleted_at', 'created_at']);
            $table->index(['user_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('studio_profiles');
    }
};
