<?php

declare(strict_types=1);

namespace Modules\Campaigns\Application\DTOs;

use Illuminate\Validation\Rule;
use Modules\Campaigns\Domain\Enums\CampaignBusinessGoal;
use Modules\Campaigns\Domain\Enums\CampaignLanguage;
use Modules\SocialMedia\Application\DTOs\SuggestSocialMediaTopicsData;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Input for Step 1 (10 Meta Ads lead-gen angle candidates). `niche` is an
 * optional steer — when blank the agent leans entirely on the company
 * profile + Tavily trend research, mirroring
 * {@see SuggestSocialMediaTopicsData}.
 */
#[MapInputName(SnakeCaseMapper::class)]
final class SuggestCampaignTopicsData extends Data
{
    public function __construct(
        public string $provider,
        public string $language,
        public ?string $niche = null,
        public ?string $audience = null,
        public ?string $businessGoal = null,
        public ?string $city = null,
        public ?string $state = null,
        public ?string $country = null,
        public ?string $location = null,
    ) {}

    /**
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'provider' => ['required', 'string', Rule::in(['openai', 'anthropic', 'gemini'])],
            'language' => ['required', 'string', Rule::enum(CampaignLanguage::class)],
            'niche' => ['nullable', 'string', 'max:255'],
            'audience' => ['nullable', 'string', 'max:255'],
            'business_goal' => ['nullable', 'string', Rule::enum(CampaignBusinessGoal::class)],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:120'],
            'location' => ['nullable', 'string', 'max:255'],
        ];
    }
}
