<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The transcript reuse lookup (US-11) became four columns wide.
 *
 * `EloquentTranscriptStore::findReusable()` matches the owner, the ordered
 * source fingerprints AND the provider/model that produced the timings, because
 * word timings are not portable between models: a whisper-1 transcript handed
 * back to a gemini run moves every cut in the video. The old index covered
 * `source_fingerprint` + `created_at`, which still serves the `whereHas` on the
 * owner but no longer describes the whole predicate.
 *
 * Dropped and recreated rather than extended: adding provider/model onto the end
 * of the existing key would only help if the planner could seek on all four, and
 * the owner filter is a join through `video_edits` rather than a column here, so
 * it can never be part of this index. The column order follows the lookup:
 * fingerprint is far more selective than provider, and `created_at` sorts the
 * `latest()`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('video_edit_transcripts', function (Blueprint $table): void {
            $table->dropIndex(['source_fingerprint', 'created_at']);
            $table->index(['source_fingerprint', 'provider', 'model', 'created_at'], 'video_edit_transcripts_reuse_lookup');
        });
    }

    public function down(): void
    {
        Schema::table('video_edit_transcripts', function (Blueprint $table): void {
            $table->dropIndex('video_edit_transcripts_reuse_lookup');
            $table->index(['source_fingerprint', 'created_at']);
        });
    }
};
