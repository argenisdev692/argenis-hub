<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('studio_cv_audits', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->unsignedBigInteger('cv_id');
            $table->unsignedBigInteger('structure_id')->nullable();
            $table->unsignedBigInteger('profile_id')->nullable();
            $table->string('verdict', 16)->nullable();
            $table->json('verdict_reasons')->nullable();
            $table->json('structural_checks')->nullable();
            $table->decimal('parse_recovery_ratio', 5, 4)->nullable();
            $table->json('content_issues')->nullable();
            $table->string('target_job_title')->nullable();
            $table->json('strengths')->nullable();
            $table->json('improvements')->nullable();
            $table->json('keyword_gaps')->nullable();
            $table->json('xyz_gaps')->nullable();
            $table->unsignedSmallInteger('rules_version')->default(2);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['cv_id']);
            $table->index(['deleted_at', 'created_at']);
        });

        Schema::create('studio_metric_answers', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('audit_id')->constrained('studio_cv_audits')->cascadeOnUpdate()->cascadeOnDelete();
            $table->unsignedBigInteger('cv_id');
            $table->text('question');
            $table->text('answer')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['audit_id']);
            $table->index(['deleted_at', 'created_at']);
        });

        Schema::create('studio_cv_versions', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->unsignedBigInteger('cv_id');
            $table->unsignedBigInteger('structure_id')->nullable();
            $table->unsignedBigInteger('profile_id')->nullable();
            $table->unsignedBigInteger('posting_id')->nullable();
            $table->string('purpose', 16);
            $table->string('language', 8);
            $table->json('content')->nullable();
            $table->unsignedTinyInteger('page_count_estimate')->nullable();
            $table->unsignedBigInteger('parent_version_id')->nullable();
            $table->json('bullet_provenance')->nullable();
            $table->unsignedSmallInteger('rules_version')->default(2);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['cv_id', 'purpose']);
            $table->index(['posting_id']);
            $table->index(['deleted_at', 'created_at']);
        });

        Schema::create('studio_exports', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->unsignedBigInteger('cv_version_id')->nullable();
            $table->string('format', 8);
            $table->string('language', 8)->nullable();
            $table->string('disk', 16)->default('r2');
            $table->string('path');
            $table->unsignedBigInteger('bytes')->default(0);
            $table->boolean('text_extraction_verified')->default(false);
            $table->unsignedInteger('extracted_char_count')->default(0);
            $table->json('structural_checks')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['cv_version_id']);
            $table->index(['deleted_at', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('studio_exports');
        Schema::dropIfExists('studio_cv_versions');
        Schema::dropIfExists('studio_metric_answers');
        Schema::dropIfExists('studio_cv_audits');
    }
};
