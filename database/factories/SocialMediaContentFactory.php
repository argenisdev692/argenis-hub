<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\SocialMedia\Domain\Enums\BrandVoice;
use Modules\SocialMedia\Domain\Enums\BusinessGoal;
use Modules\SocialMedia\Domain\Enums\ContentLanguage;
use Modules\SocialMedia\Domain\Enums\FunnelStage;
use Modules\SocialMedia\Domain\Enums\SocialMediaContentStatus;
use Modules\SocialMedia\Infrastructure\Persistence\Eloquent\Models\SocialMediaContentEloquentModel;

/**
 * @extends Factory<SocialMediaContentEloquentModel>
 */
final class SocialMediaContentFactory extends Factory
{
    protected $model = SocialMediaContentEloquentModel::class;

    /**
     * A bare `draft` row — no AI output yet. Every scoring/platform column is
     * deliberately null so a test that asserts on generation output cannot
     * accidentally pass on factory data.
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
            'business_goal' => BusinessGoal::Awareness->value,
            'brand_voice' => BrandVoice::Professional->value,
            'funnel_stage' => FunnelStage::Tofu->value,
            'language' => ContentLanguage::English->value,
            'provider' => 'openai',
            'status' => SocialMediaContentStatus::Draft->value,
            'created_by' => null,
        ];
    }

    /**
     * A package the quality loop finished successfully: every score clears its
     * threshold, so `status` is `ready` and no warning is raised.
     */
    public function ready(): self
    {
        return $this->state(fn (): array => [
            'status' => SocialMediaContentStatus::Ready->value,
            'headline' => fake()->sentence(6),
            'body' => fake()->paragraphs(2, true),
            'call_to_action' => fake()->sentence(4),
            'hashtags' => ['#tech', '#ai'],
            'platforms' => [],
            'human_writing_index' => 82,
            'virality_score' => 76,
            'engagement_score' => 78,
            'roi_score' => 74,
            'trend_alignment' => 79,
            'overall_score_avg' => 78,
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
            'status' => SocialMediaContentStatus::NeedsReview->value,
            'headline' => fake()->sentence(6),
            'body' => fake()->paragraphs(2, true),
            'call_to_action' => fake()->sentence(4),
            'overall_score_avg' => 62,
            'all_scores_pass' => false,
            'iterations_required' => 5,
            'quality_warning' => true,
            'quality_warning_message' => 'Maximum iterations reached — showing the best attempt for manual review.',
        ]);
    }

    public function published(): self
    {
        return $this->ready()->state(fn (): array => [
            'status' => SocialMediaContentStatus::Published->value,
            'published_at' => now()->subDays(fake()->numberBetween(1, 30)),
        ]);
    }

    public function scheduled(): self
    {
        return $this->ready()->state(fn (): array => [
            'status' => SocialMediaContentStatus::Scheduled->value,
            'scheduled_at' => now()->addDays(fake()->numberBetween(1, 14)),
        ]);
    }

    public function forUser(User $user): self
    {
        return $this->state(fn (): array => ['created_by' => $user->id]);
    }
}
