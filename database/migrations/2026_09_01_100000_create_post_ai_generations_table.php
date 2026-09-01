<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per background draft generation.
     *
     * Deliberately NOT the `posts` table. The AI panel runs on Create (where
     * no post exists yet) as well as Edit, and the user still has to review
     * and submit the draft — "generating is not saving". Persisting an
     * in-flight generation as a post would litter the content table with
     * abandoned rows, and `failed` is not a state a post can be in.
     */
    public function up(): void
    {
        Schema::create('post_ai_generations', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();

            // The brief, kept so a run can be traced or re-issued verbatim.
            $table->string('topic', 500);
            $table->string('angle', 500)->nullable();
            $table->string('key_trend')->nullable();
            $table->string('provider', 20);
            $table->string('image_mode', 10);

            $table->string('status', 20)->default('draft');

            // Live progress, overwritten on every phase transition.
            $table->string('stage_message', 500)->nullable();
            $table->unsignedTinyInteger('progress')->default(0);
            $table->unsignedTinyInteger('iteration')->default(0);

            // The finished GeneratedPostContentData, or null until `completed`.
            $table->json('result')->nullable();
            $table->string('error_message', 500)->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();

            $table->foreignId('created_by')->nullable()
                ->constrained('users')->onUpdate('cascade')->onDelete('set null');

            $table->timestamps();

            // The wizard polls `where uuid = ? and created_by = ?`; the list of
            // a user's own recent runs is ordered by recency.
            $table->index(['created_by', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('post_ai_generations');
    }
};
