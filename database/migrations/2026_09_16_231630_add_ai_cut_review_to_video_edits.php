<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Human review of AI-proposed cuts (spec 001-video-edit V3, OWASP LLM06).
 *
 * - `ai_review` holds the proposed cuts and the owner's decision. A JSON column
 *   for the same reason as `ai_report`: always read whole, and removed with the
 *   edit on a hard delete (it contains transcript excerpts).
 * - `review_expires_at` is when an unanswered review resolves itself as "keep
 *   everything" and the edit renders without AI cuts.
 * - `awaiting_review` joins the one-active-edit-per-user index, so approving
 *   the cuts can never collide with an edit started in the meantime.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('video_edits', function (Blueprint $table): void {
            $table->json('ai_review')->nullable()->after('ai_report');
            $table->timestamp('review_expires_at')->nullable()->after('ai_review');

            $table->index(['status', 'review_expires_at']);
        });

        DB::statement('DROP INDEX IF EXISTS video_edits_one_active_per_user');
        DB::statement(
            "CREATE UNIQUE INDEX video_edits_one_active_per_user ON video_edits (user_id) WHERE status IN ('queued', 'processing', 'awaiting_review')",
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS video_edits_one_active_per_user');
        DB::statement(
            "CREATE UNIQUE INDEX video_edits_one_active_per_user ON video_edits (user_id) WHERE status IN ('queued', 'processing')",
        );

        Schema::table('video_edits', function (Blueprint $table): void {
            $table->dropIndex(['status', 'review_expires_at']);
            $table->dropColumn(['ai_review', 'review_expires_at']);
        });
    }
};
