<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('studio_insight_reports', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('run_id')->constrained('studio_runs')->cascadeOnUpdate()->cascadeOnDelete();
            $table->unsignedInteger('jds_analyzed')->default(0);
            $table->string('sample_note')->nullable();
            $table->json('requirements')->nullable();
            $table->json('reach_table')->nullable();
            $table->json('recommendations')->nullable();
            $table->json('outcome_correlation')->nullable();
            $table->unsignedSmallInteger('rules_version')->default(2);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['run_id']);
            $table->index(['deleted_at', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('studio_insight_reports');
    }
};
