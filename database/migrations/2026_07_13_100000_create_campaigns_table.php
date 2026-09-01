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
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();

            // Step 1 selection — the angle the user picked from the 10 candidates.
            $table->string('niche')->nullable();
            $table->string('topic', 500);
            $table->string('angle', 500)->nullable();
            $table->string('hook', 500)->nullable();
            $table->string('key_trend')->nullable();
            $table->string('audience')->nullable();
            $table->string('business_goal', 20);
            $table->string('brand_voice', 20);
            $table->string('funnel_stage', 10);
            $table->string('platform', 10);
            $table->string('ad_format', 20);
            $table->string('language', 5);
            $table->string('provider', 20);

            $table->string('status', 20)->default('draft');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('published_at')->nullable();

            // Master Meta Ads copy (platform-agnostic summary shown on the list/edit screen).
            $table->string('headline')->nullable();
            $table->longText('primary_text')->nullable();
            $table->string('description', 500)->nullable();
            $table->string('call_to_action', 100)->nullable();
            $table->json('hashtags')->nullable();
            $table->json('lead_form_questions')->nullable();
            $table->json('targeting_suggestions')->nullable();

            // Per-platform copy + hashtags + image path/prompt (facebook, instagram) —
            // see PlatformCampaignContentData.
            $table->json('platforms')->nullable();

            $table->string('cover_image_path', 2048)->nullable();
            $table->string('cover_image_prompt', 2048)->nullable();

            // Quality-loop scoring (Domain\Services\CampaignQualityEvaluator).
            $table->json('scores')->nullable();
            $table->unsignedTinyInteger('audience_fit_score')->nullable();
            $table->unsignedTinyInteger('virality_score')->nullable();
            $table->unsignedTinyInteger('roi_potential_score')->nullable();
            $table->unsignedTinyInteger('lead_quality_score')->nullable();
            $table->unsignedTinyInteger('trend_relevance_score')->nullable();
            $table->unsignedTinyInteger('overall_score_avg')->nullable();
            $table->string('success_probability_label', 20)->nullable();
            $table->boolean('all_scores_pass')->default(false);
            $table->unsignedTinyInteger('iterations_required')->nullable();
            $table->boolean('quality_warning')->default(false);
            $table->string('quality_warning_message', 500)->nullable();

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
        Schema::dropIfExists('campaigns');
    }
};
