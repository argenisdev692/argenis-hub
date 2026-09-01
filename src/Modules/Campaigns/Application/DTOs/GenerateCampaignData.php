<?php

declare(strict_types=1);

namespace Modules\Campaigns\Application\DTOs;

use Illuminate\Validation\Rule;
use Modules\Campaigns\Domain\Enums\CampaignAdFormat;
use Modules\Campaigns\Domain\Enums\CampaignBrandVoice;
use Modules\Campaigns\Domain\Enums\CampaignBusinessGoal;
use Modules\Campaigns\Domain\Enums\CampaignFunnelStage;
use Modules\Campaigns\Domain\Enums\CampaignLanguage;
use Modules\Campaigns\Domain\Enums\CampaignPlatform;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Input for Step 2 (quality-loop campaign generation). One instance is
 * reused across every iteration of the Infrastructure quality-loop Job;
 * only the previous-scores/weaknesses feedback (passed separately to the
 * port) changes between iterations.
 */
#[MapInputName(SnakeCaseMapper::class)]
final class GenerateCampaignData extends Data
{
    public function __construct(
        public string $topic,
        public string $provider,
        public string $language,
        public string $businessGoal,
        public string $brandVoice,
        public string $funnelStage,
        public string $platform,
        public string $adFormat,
        public ?string $angle = null,
        public ?string $hook = null,
        public ?string $keyTrend = null,
        public ?string $niche = null,
        public ?string $audience = null,
        public bool $generateImages = true,
        public ?string $city = null,
        public ?string $state = null,
        public ?string $country = null,
        public ?string $location = null,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'topic' => ['required', 'string', 'max:255'],
            'provider' => ['required', 'string', Rule::in(['openai', 'anthropic', 'gemini'])],
            'language' => ['required', 'string', Rule::enum(CampaignLanguage::class)],
            'business_goal' => ['required', 'string', Rule::enum(CampaignBusinessGoal::class)],
            'brand_voice' => ['required', 'string', Rule::enum(CampaignBrandVoice::class)],
            'funnel_stage' => ['required', 'string', Rule::enum(CampaignFunnelStage::class)],
            'platform' => ['required', 'string', Rule::enum(CampaignPlatform::class)],
            'ad_format' => ['required', 'string', Rule::enum(CampaignAdFormat::class)],
            'angle' => ['nullable', 'string', 'max:500'],
            'hook' => ['nullable', 'string', 'max:500'],
            'key_trend' => ['nullable', 'string', 'max:255'],
            'niche' => ['nullable', 'string', 'max:255'],
            'audience' => ['nullable', 'string', 'max:255'],
            'generate_images' => ['boolean'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:120'],
            'location' => ['nullable', 'string', 'max:255'],
        ];
    }
}
