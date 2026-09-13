<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Course Scripts aggregate (spec 002-course-scripts, plan §4).
 *
 * Project identifier convention: internal bigint `id` + public UUIDv7 `uuid`.
 * Soft deletes live on the aggregate root (`courses`) only; every child row
 * belongs to its course and cascades when the course is force-deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('title');
            $table->string('language', 8);
            $table->unsignedInteger('declared_duration_minutes')->nullable();
            $table->unsignedSmallInteger('default_video_minutes');
            $table->string('status', 30);
            $table->longText('course_notes')->nullable();
            $table->json('bible')->nullable();
            $table->string('bible_origin', 20)->nullable();
            $table->unsignedInteger('bible_revision')->default(0);
            $table->timestamp('prepared_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'created_at']);
            $table->index(['deleted_at', 'created_at']);
        });

        Schema::create('course_blocks', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnUpdate()->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->string('title');
            $table->unsignedInteger('declared_duration_minutes')->nullable();
            $table->unsignedInteger('position');
            $table->timestamps();

            $table->unique(['course_id', 'number']);
        });

        Schema::create('course_videos', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnUpdate()->cascadeOnDelete();
            // Blocks are optional (FR-2a).
            $table->foreignId('course_block_id')->nullable()->constrained('course_blocks')->cascadeOnUpdate()->nullOnDelete();
            $table->unsignedInteger('number');
            $table->string('title');
            $table->string('topic')->nullable();
            $table->unsignedInteger('declared_duration_minutes')->nullable();
            $table->text('objective')->nullable();
            $table->text('expected_result')->nullable();
            $table->json('learning_areas');
            $table->json('audience_objectives');
            $table->json('mandatory_content');
            $table->json('errors_to_avoid');
            $table->longText('notes')->nullable();
            $table->boolean('needs_review')->default(false);
            $table->unsignedInteger('brief_revision')->default(1);
            $table->string('script_status', 20);
            $table->timestamps();

            // Course order is the continuity contract (FR-17).
            $table->unique(['course_id', 'number']);
            $table->index(['course_id', 'script_status']);
            $table->index(['course_block_id', 'number']);
        });

        Schema::create('course_source_documents', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('course_video_id')->nullable()->constrained('course_videos')->cascadeOnUpdate()->nullOnDelete();
            $table->string('kind', 20);
            $table->string('original_name');
            $table->string('path');
            $table->string('mime', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->string('checksum', 64);
            $table->longText('extracted_text')->nullable();
            $table->timestamps();

            $table->index(['course_id', 'kind']);
        });

        Schema::create('course_research_findings', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnUpdate()->cascadeOnDelete();
            // Null = subject-level finding reused by every video (FR-13j).
            $table->foreignId('course_video_id')->nullable()->constrained('course_videos')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('provider', 32);
            $table->string('query', 500);
            $table->string('url', 2048);
            $table->string('title', 500);
            $table->text('content');
            $table->decimal('score', 5, 4)->nullable();
            $table->boolean('full_page_fetched')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('gathered_at');
            $table->timestamps();

            $table->index(['course_id', 'course_video_id']);
        });

        Schema::create('course_generation_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('kind', 20);
            $table->string('scope', 20);
            $table->foreignId('scoped_block_id')->nullable()->constrained('course_blocks')->cascadeOnUpdate()->nullOnDelete();
            $table->string('writer_provider', 32);
            $table->string('reviewer_provider', 32)->nullable();
            $table->boolean('with_review')->default(false);
            $table->boolean('reviewer_not_independent')->default(false);
            $table->string('status', 30);
            $table->string('batch_id')->nullable();
            $table->unsignedInteger('estimated_ai_write_calls')->default(0);
            $table->unsignedInteger('estimated_ai_review_calls')->default(0);
            $table->unsignedInteger('estimated_research_calls')->default(0);
            $table->unsignedInteger('ai_call_ceiling');
            $table->unsignedInteger('research_call_ceiling');
            $table->unsignedInteger('ai_write_calls_consumed')->default(0);
            $table->unsignedInteger('ai_review_calls_consumed')->default(0);
            $table->unsignedInteger('research_calls_consumed')->default(0);
            $table->unsignedInteger('videos_total')->default(0);
            $table->unsignedInteger('videos_completed')->default(0);
            $table->unsignedInteger('videos_failed')->default(0);
            $table->foreignId('current_video_id')->nullable()->constrained('course_videos')->cascadeOnUpdate()->nullOnDelete();
            $table->string('stop_reason', 100)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['course_id', 'status']);
            $table->index(['user_id', 'created_at']);
        });

        // One active run per course (FR-25, D8), enforced by the database so two
        // concurrent launches cannot race. Valid on PostgreSQL and SQLite.
        DB::statement(
            "CREATE UNIQUE INDEX course_generation_runs_one_active_per_course ON course_generation_runs (course_id) WHERE status IN ('queued', 'running')",
        );

        Schema::create('course_video_outcomes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_generation_run_id')->constrained('course_generation_runs')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('course_video_id')->constrained('course_videos')->cascadeOnUpdate()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->string('status', 20);
            // Sanitized reason code — never provider text (FR-55).
            $table->string('failure_reason', 500)->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('review_iterations')->default(0);
            $table->unsignedInteger('ai_write_calls')->default(0);
            $table->unsignedInteger('ai_review_calls')->default(0);
            $table->unsignedInteger('research_calls')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            // A retry updates an outcome, never appends a second one (FR-20).
            $table->unique(['course_generation_run_id', 'course_video_id']);
        });

        Schema::create('course_script_versions', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->foreignId('course_video_id')->constrained('course_videos')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('course_generation_run_id')->nullable()->constrained('course_generation_runs')->cascadeOnUpdate()->nullOnDelete();
            $table->unsignedInteger('version');
            $table->boolean('is_accepted')->default(false);
            $table->string('writer_provider', 32);
            $table->unsignedInteger('brief_revision');
            $table->unsignedInteger('bible_revision');
            $table->json('technical_header');
            $table->json('learning_objectives');
            $table->text('continuity_note');
            $table->boolean('continuity_is_provisional')->default(false);
            $table->json('continuity_source_video_ids');
            $table->boolean('continuity_stale')->default(false);
            $table->json('sections');
            $table->boolean('uses_tool')->default(false);
            $table->text('taught_summary');
            $table->json('summary_points');
            $table->json('next_video')->nullable();
            $table->json('recording_notes');
            $table->json('verification_checklist');
            $table->json('coverage_map');
            $table->json('errors_check');
            $table->boolean('is_grounded')->default(true);
            $table->json('notes_excerpt_ids');
            $table->string('prompts_sheet_reason')->nullable();
            $table->boolean('practice_warranted')->default(false);
            $table->text('practice_decision_reason')->nullable();
            $table->boolean('reviewed')->default(false);
            $table->string('reviewer_provider', 32)->nullable();
            $table->json('review_scores')->nullable();
            $table->json('review_objections')->nullable();
            $table->unsignedSmallInteger('review_iterations')->default(0);
            $table->boolean('passed_review')->nullable();
            $table->text('feedback_note')->nullable();
            $table->timestamps();

            $table->unique(['course_video_id', 'version']);
            $table->index(['course_video_id', 'is_accepted']);
        });

        Schema::create('course_script_sources', function (Blueprint $table): void {
            $table->foreignId('course_script_version_id')->constrained('course_script_versions')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('course_research_finding_id')->constrained('course_research_findings')->cascadeOnUpdate()->cascadeOnDelete();

            $table->primary(['course_script_version_id', 'course_research_finding_id']);
        });

        Schema::create('course_practice_versions', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->foreignId('course_script_version_id')->unique()->constrained('course_script_versions')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('decided_by', 20);
            $table->text('decision_reason');
            $table->string('document_name');
            $table->string('header_title', 500);
            $table->text('files_summary');
            $table->text('setup_instruction');
            $table->json('usage');
            $table->text('instructor_note');
            $table->json('designed_contrasts');
            $table->json('artifacts');
            $table->json('review_scores')->nullable();
            $table->json('review_objections')->nullable();
            $table->timestamps();
        });

        Schema::create('course_deliverables', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->foreignId('course_script_version_id')->constrained('course_script_versions')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('document_type', 20);
            // '' (not NULL) for non-file documents so the unique key stays effective.
            $table->string('artifact_file_name')->default('');
            $table->string('format', 5);
            $table->string('path');
            $table->unsignedBigInteger('size_bytes');
            $table->string('checksum', 64);
            $table->timestamps();

            $table->unique(['course_script_version_id', 'document_type', 'artifact_file_name', 'format'], 'course_deliverables_unique_document');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_deliverables');
        Schema::dropIfExists('course_practice_versions');
        Schema::dropIfExists('course_script_sources');
        Schema::dropIfExists('course_script_versions');
        Schema::dropIfExists('course_video_outcomes');
        Schema::dropIfExists('course_generation_runs');
        Schema::dropIfExists('course_research_findings');
        Schema::dropIfExists('course_source_documents');
        Schema::dropIfExists('course_videos');
        Schema::dropIfExists('course_blocks');
        Schema::dropIfExists('courses');
    }
};
