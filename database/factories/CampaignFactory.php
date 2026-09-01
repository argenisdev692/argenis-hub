<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Campaigns\Domain\Enums\CampaignAdFormat;
use Modules\Campaigns\Domain\Enums\CampaignBrandVoice;
use Modules\Campaigns\Domain\Enums\CampaignBusinessGoal;
use Modules\Campaigns\Domain\Enums\CampaignFunnelStage;
use Modules\Campaigns\Domain\Enums\CampaignLanguage;
use Modules\Campaigns\Domain\Enums\CampaignPlatform;
use Modules\Campaigns\Domain\Enums\CampaignStatus;
use Modules\Campaigns\Infrastructure\Persistence\Eloquent\Models\CampaignEloquentModel;

/**
 * @extends Factory<CampaignEloquentModel>
 */
final class CampaignFactory extends Factory
{
    protected $model = CampaignEloquentModel::class;

    /**
     * A bare `draft` row — no AI output yet. Every scoring/platform column is
     * deliberately null so a test asserting on generation output cannot
     * accidentally pass on factory data (mirrors
     * {@see SocialMediaContentFactory}).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid7(),
            'niche' => fake()->words(2, true),
            'topic' => fake()->unique()->sentence(6),
            'angle' => fake()->sentence(8),
            'hook' => fake()->sentence(10),
            'key_trend' => fake()->words(3, true),
            'audience' => fake()->jobTitle(),
            'business_goal' => CampaignBusinessGoal::Leads->value,
            'brand_voice' => CampaignBrandVoice::Professional->value,
            'funnel_stage' => CampaignFunnelStage::Tofu->value,
            'platform' => CampaignPlatform::Both->value,
            'ad_format' => CampaignAdFormat::Feed->value,
            'language' => CampaignLanguage::English->value,
            'provider' => 'openai',
            'status' => CampaignStatus::Draft->value,
            'created_by' => null,
        ];
    }

    /**
     * A campaign the quality loop finished successfully: every score clears
     * its threshold, so `status` is `ready` and no warning is raised.
     */
    public function ready(): self
    {
        return $this->state(fn (): array => [
            'status' => CampaignStatus::Ready->value,
            'headline' => fake()->sentence(4),
            'primary_text' => fake()->paragraphs(2, true),
            'description' => fake()->sentence(3),
            'call_to_action' => 'GET_QUOTE',
            'hashtags' => ['#leads', '#metaads'],
            'lead_form_questions' => ['What is your project budget range?'],
            'targeting_suggestions' => ['Lookalike 1% of past converters'],
            'platforms' => [],
            'audience_fit_score' => 82,
            'virality_score' => 76,
            'roi_potential_score' => 78,
            'lead_quality_score' => 80,
            'trend_relevance_score' => 74,
            'overall_score_avg' => 78,
            'success_probability_label' => 'high',
            'all_scores_pass' => true,
            'iterations_required' => 1,
            'quality_warning' => false,
        ]);
    }

    /**
     * The loop ran out of iterations — best attempt kept for manual review.
     */
    public function needsReview(): self
    {
        return $this->state(fn (): array => [
            'status' => CampaignStatus::NeedsReview->value,
            'headline' => fake()->sentence(4),
            'primary_text' => fake()->paragraphs(2, true),
            'call_to_action' => 'LEARN_MORE',
            'overall_score_avg' => 62,
            'success_probability_label' => 'medium',
            'all_scores_pass' => false,
            'iterations_required' => 5,
            'quality_warning' => true,
            'quality_warning_message' => 'Maximum iterations reached — showing the best attempt for manual review.',
        ]);
    }

    public function published(): self
    {
        return $this->ready()->state(fn (): array => [
            'status' => CampaignStatus::Published->value,
            'published_at' => now()->subDays(fake()->numberBetween(1, 30)),
        ]);
    }

    public function scheduled(): self
    {
        return $this->ready()->state(fn (): array => [
            'status' => CampaignStatus::Scheduled->value,
            'scheduled_at' => now()->addDays(fake()->numberBetween(1, 14)),
        ]);
    }

    public function forUser(User $user): self
    {
        return $this->state(fn (): array => ['created_by' => $user->id]);
    }
}
