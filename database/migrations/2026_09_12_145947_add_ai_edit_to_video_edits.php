<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * V3 AI edit storage (spec 001-video-edit US-12/13/14).
 *
 * Two deliberate omissions, both from the recorded decisions:
 *
 * - **No raw AI response column** (R10). Only the validated recommendations and
 *   the conclusion are kept, so a hard delete leaves nothing of the user's
 *   speech or script behind. The cut decisions themselves — applied and
 *   rejected — already live in `video_edit_cut_decisions`.
 * - **Consent is a timestamp, not a boolean** (R9). "When did they agree" is
 *   the auditable fact; a bare flag cannot answer it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('video_edits', function (Blueprint $table): void {
            $table->timestamp('ai_consent_at')->nullable()->after('effective_settings');
            $table->json('ai_report')->nullable()->after('warnings');
        });

        Schema::create('video_edit_scripts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('video_edit_id')
                ->unique()
                ->constrained('video_edits')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->string('uuid')->unique();
            $table->string('original_name');
            $table->string('extension', 10);
            $table->string('declared_mime', 120);
            $table->unsignedBigInteger('declared_size_bytes');
            $table->string('storage_path');

            // Extracted text, not the file: the model only ever sees the words,
            // and this is what the report is written against.
            $table->longText('extracted_text')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_edit_scripts');

        Schema::table('video_edits', function (Blueprint $table): void {
            $table->dropColumn(['ai_consent_at', 'ai_report']);
        });
    }
};
