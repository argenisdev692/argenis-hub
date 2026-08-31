<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('social_media_contents', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();

            // Step 1 selection — the topic the user picked from the 10 candidates.
            $table->string('niche')->nullable();
            $table->string('topic', 500);
            $table->string('angle', 500)->nullable();
            $table->string('hook', 500)->nullable();
            $table->string('key_trend')->nullable();
            $table->string('audience')->nullable();
            $table->string('business_goal', 20);
            $table->string('brand_voice', 20);
            $table->string('funnel_stage', 10);
            $table->string('language', 5);
            $table->string('provider', 20);

            $table->string('status', 20)->default('draft');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('published_at')->nullable();

            // Master content (platform-agnostic summary shown on the list/edit screen).
            $table->string('headline')->nullable();
            $table->longText('body')->nullable();
            $table->string('call_to_action', 500)->nullable();
            $table->json('hashtags')->nullable();

            // Per-platform copy + hashtags + image/voiceover paths+prompts (linkedin,
            // twitter, instagram, facebook, tiktok) — see PlatformContentData.
            $table->json('platforms')->nullable();

            $table->string('cover_image_path', 2048)->nullable();
            $table->string('cover_image_prompt', 2048)->nullable();

            // Quality-loop scoring (Domain\Services\ContentQualityEvaluator).
            $table->json('scores')->nullable();
            $table->unsignedTinyInteger('human_writing_index')->nullable();
            $table->unsignedTinyInteger('virality_score')->nullable();
            $table->unsignedTinyInteger('engagement_score')->nullable();
            $table->unsignedTinyInteger('roi_score')->nullable();
            $table->unsignedTinyInteger('trend_alignment')->nullable();
            $table->unsignedTinyInteger('overall_score_avg')->nullable();
            $table->boolean('all_scores_pass')->default(false);
            $table->unsignedTinyInteger('iterations_required')->nullable();
            $table->boolean('quality_warning')->default(false);
            $table->string('quality_warning_message', 500)->nullable();

            $table->json('eeat_analysis')->nullable();
            $table->json('optimization_suggestions')->nullable();
            $table->json('research_sources')->nullable();
            $table->json('tavily_data_used')->nullable();
            $table->json('ai_detection_risk')->nullable();

            $table->foreignId('created_by')->nullable()
                ->constrained('users')->onUpdate('cascade')->onDelete('set null');

            $table->softDeletes();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['deleted_at', 'created_at']);
            $table->index(['overall_score_avg']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('social_media_contents');
    }
};
