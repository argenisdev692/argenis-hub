<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Video Edits aggregate (spec 001-video-edit, plan §4).
 *
 * Rows are HARD-deleted by design (decisions Q2/Q5): there is no `deleted_at`,
 * and every child table cascades with its edit. Times are integer milliseconds
 * (frame-accurate at 60 fps without float drift).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_edits', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            // Re-edit lineage (US-7): the original may be deleted independently.
            $table->foreignId('previous_edit_id')->nullable()->constrained('video_edits')->cascadeOnUpdate()->nullOnDelete();

            // VideoEditMode / VideoEditStatus enum values.
            $table->string('mode', 20);
            $table->string('status', 20);

            // Requested settings, reused to prefill a re-edit (US-7, EX-9).
            $table->json('parameters');
            // Effective system settings snapshot (noise floor, padding, output profile).
            $table->json('effective_settings')->nullable();

            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->string('current_stage', 30)->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);

            // User-safe failure data only — never commands, paths or stderr (FR-20).
            $table->string('failure_code', 50)->nullable();
            $table->string('failure_message', 255)->nullable();
            $table->json('failure_details')->nullable();

            $table->unsignedBigInteger('original_duration_ms')->nullable();
            $table->unsignedBigInteger('final_duration_ms')->nullable();
            $table->unsignedBigInteger('removed_duration_ms')->nullable();
            $table->unsignedInteger('applied_cut_count')->default(0);
            $table->unsignedInteger('rejected_decision_count')->default(0);
            $table->json('warnings')->nullable();

            $table->string('result_path')->nullable();
            $table->unsignedBigInteger('result_size_bytes')->nullable();

            // Failed edits keep their sources for a retry window (FR-10).
            $table->timestamp('sources_expire_at')->nullable();
            $table->timestamp('sources_purged_at')->nullable();

            $table->timestamp('queued_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'updated_at']);
            $table->index(['status', 'sources_expire_at']);
            $table->index(['status', 'created_at']);
        });

        // One queued/processing edit per user (FR-16), enforced by the database so
        // concurrent submits and retries cannot race. Valid on PostgreSQL and SQLite.
        DB::statement(
            "CREATE UNIQUE INDEX video_edits_one_active_per_user ON video_edits (user_id) WHERE status IN ('queued', 'processing')",
        );

        Schema::create('video_edit_sources', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->foreignId('video_edit_id')->constrained('video_edits')->cascadeOnUpdate()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');

            $table->string('original_name', 255);
            $table->string('extension', 10);
            $table->string('declared_mime', 100);
            $table->unsignedBigInteger('declared_size_bytes');

            // NULL once the object is purged (FR-9 / FR-10).
            $table->string('storage_path')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            // Content fingerprint — the V2 transcript reuse key (EX-6).
            $table->char('sha256', 64)->nullable();

            $table->unsignedBigInteger('duration_ms')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->decimal('frame_rate', 7, 3)->nullable();
            $table->boolean('has_audio')->nullable();
            $table->string('container', 50)->nullable();
            $table->string('video_codec', 30)->nullable();
            $table->string('audio_codec', 30)->nullable();
            $table->timestamps();

            $table->unique(['video_edit_id', 'position']);
            $table->index('sha256');
        });

        // Every decision any producer proposed — applied AND rejected (EX-1, EX-8).
        Schema::create('video_edit_cut_decisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('video_edit_id')->constrained('video_edits')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('producer', 50);
            $table->string('reason', 40);
            $table->string('origin', 30);
            $table->unsignedBigInteger('start_ms');
            $table->unsignedBigInteger('end_ms');
            $table->decimal('confidence', 4, 3)->nullable();
            $table->json('evidence')->nullable();
            $table->string('outcome', 10);
            $table->string('rejection_reason', 60)->nullable();
            $table->unsignedInteger('applied_cut_sequence')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['video_edit_id', 'outcome']);
        });

        // The normalized intervals actually removed from the result (FR-5).
        Schema::create('video_edit_applied_cuts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('video_edit_id')->constrained('video_edits')->cascadeOnUpdate()->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->unsignedBigInteger('start_ms');
            $table->unsignedBigInteger('end_ms');
            $table->json('reasons');
            $table->json('origins');
            $table->timestamp('created_at')->nullable();

            $table->unique(['video_edit_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_edit_applied_cuts');
        Schema::dropIfExists('video_edit_cut_decisions');
        Schema::dropIfExists('video_edit_sources');
        Schema::dropIfExists('video_edits');
    }
};
