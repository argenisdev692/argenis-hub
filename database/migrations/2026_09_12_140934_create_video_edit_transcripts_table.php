<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * V2 transcript store (spec 001-video-edit US-10 / US-11).
 *
 * One row per edit, not a global content-addressed cache. A transcript is the
 * user's speech verbatim, so it is personal data: keeping it on the edit means
 * a hard delete (Q5) removes it with everything else through the cascade,
 * instead of leaving orphaned speech behind in a shared cache.
 *
 * Reuse (US-11) therefore reads a PREVIOUS EDIT OF THE SAME USER whose ordered
 * source fingerprints match, and copies the transcript forward — never across
 * user boundaries.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_edit_transcripts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('video_edit_id')
                ->unique()
                ->constrained('video_edits')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // SHA-256 over the ordered per-source fingerprints (EX-6, R5): the
            // key that says "these are the same recordings in the same order".
            $table->string('source_fingerprint', 64);
            $table->string('provider', 40);
            $table->string('model', 60);
            $table->string('language', 12)->nullable();
            $table->unsignedInteger('word_count')->default(0);
            $table->json('payload');
            $table->timestamps();

            // The reuse lookup: same owner, same sources, newest first.
            $table->index(['source_fingerprint', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_edit_transcripts');
    }
};
