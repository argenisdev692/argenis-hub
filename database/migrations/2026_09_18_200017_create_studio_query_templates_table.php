<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('studio_query_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('profile_id')->constrained('studio_profiles')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('country', 8)->nullable();
            $table->string('language', 8)->nullable();
            $table->string('title_variant')->nullable();
            $table->string('stack_synonym')->nullable();
            $table->string('seniority')->nullable();
            $table->string('modality')->nullable();
            $table->string('regional_term')->nullable();
            $table->string('template');
            $table->softDeletes();
            $table->timestamps();

            $table->index(['profile_id', 'country', 'language']);
            $table->index(['deleted_at', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('studio_query_templates');
    }
};
