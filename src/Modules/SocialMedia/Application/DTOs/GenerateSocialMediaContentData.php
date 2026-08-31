<?php

declare(strict_types=1);

namespace Modules\SocialMedia\Application\DTOs;

use Illuminate\Validation\Rule;
use Modules\SocialMedia\Domain\Enums\BrandVoice;
use Modules\SocialMedia\Domain\Enums\BusinessGoal;
use Modules\SocialMedia\Domain\Enums\ContentLanguage;
use Modules\SocialMedia\Domain\Enums\FunnelStage;
use Modules\SocialMedia\Domain\Enums\SocialMediaImageMode;
use Modules\SocialMedia\Infrastructure\Queue\GenerateSocialMediaContentJob;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Input for Step 2 (quality-loop content generation). One instance is reused
 * across every iteration of {@see GenerateSocialMediaContentJob};
 * only the previous-scores/weaknesses feedback (passed separately to the
 * port) changes between iterations.
 *
 * `imageMode` decides how much artwork is actually rendered against the theme
 * palette — `full` (composite cover + 5 platform graphics), `base` (palette
 * background plates only, to composite elsewhere) or `none` (prompts only, no
 * billed image call). It defaults to `full` so an omitted field keeps the
 * previous behaviour.
 */
#[MapInputName(SnakeCaseMapper::class)]
final class GenerateSocialMediaContentData extends Data
{
    public function __construct(
        public string $topic,
        public string $provider,
        public string $language,
        public string $businessGoal,
        public string $brandVoice,
        public string $funnelStage,
        public ?string $angle = null,
        public ?string $hook = null,
        public ?string $keyTrend = null,
        public ?string $niche = null,
        public ?string $audience = null,
        public SocialMediaImageMode $imageMode = SocialMediaImageMode::Full,
        public bool $generateVoiceover = true,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'topic' => ['required', 'string', 'max:255'],
            'provider' => ['required', 'string', Rule::in(['openai', 'anthropic', 'gemini'])],
            'language' => ['required', 'string', Rule::enum(ContentLanguage::class)],
            'business_goal' => ['required', 'string', Rule::enum(BusinessGoal::class)],
            'brand_voice' => ['required', 'string', Rule::enum(BrandVoice::class)],
            'funnel_stage' => ['required', 'string', Rule::enum(FunnelStage::class)],
            'angle' => ['nullable', 'string', 'max:500'],
            'hook' => ['nullable', 'string', 'max:500'],
            'key_trend' => ['nullable', 'string', 'max:255'],
            'niche' => ['nullable', 'string', 'max:255'],
            'audience' => ['nullable', 'string', 'max:255'],
            'image_mode' => ['nullable', Rule::enum(SocialMediaImageMode::class)],
            'generate_voiceover' => ['boolean'],
        ];
    }
}
