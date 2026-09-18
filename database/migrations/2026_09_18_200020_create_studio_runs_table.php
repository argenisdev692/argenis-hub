<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('studio_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('profile_id')->constrained('studio_profiles')->cascadeOnUpdate()->cascadeOnDelete();
            $table->unsignedBigInteger('cv_id')->nullable();
            $table->unsignedBigInteger('structure_id')->nullable();
            $table->string('status', 32)->default('queued');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->json('queries')->nullable();
            $table->unsignedInteger('candidates_count')->default(0);
            $table->unsignedInteger('gate_passed_count')->default(0);
            $table->unsignedInteger('extracted_count')->default(0);
            $table->unsignedInteger('scored_count')->default(0);
            $table->unsignedInteger('new_matches_count')->default(0);
            $table->unsignedInteger('excluded_count')->default(0);
            $table->unsignedBigInteger('spend_micros')->default(0);
            $table->json('refresh_flags')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedSmallInteger('rules_version')->default(2);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['profile_id', 'status']);
            $table->index(['deleted_at', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('studio_runs');
    }
};
