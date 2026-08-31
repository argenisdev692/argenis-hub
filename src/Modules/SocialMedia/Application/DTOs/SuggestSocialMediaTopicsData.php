<?php

declare(strict_types=1);

namespace Modules\SocialMedia\Application\DTOs;

use Illuminate\Validation\Rule;
use Modules\Post\Application\DTOs\SuggestPostTopicsData;
use Modules\SocialMedia\Domain\Enums\BusinessGoal;
use Modules\SocialMedia\Domain\Enums\ContentLanguage;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Input for Step 1 (10 viral topics). `niche` is an optional steer — when
 * blank the agent leans entirely on the company profile + Tavily trend
 * research, mirroring {@see SuggestPostTopicsData}.
 */
#[MapInputName(SnakeCaseMapper::class)]
final class SuggestSocialMediaTopicsData extends Data
{
    public function __construct(
        public string $provider,
        public string $language,
        public ?string $niche = null,
        public ?string $audience = null,
        public ?string $businessGoal = null,
    ) {}

    /**
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'provider' => ['required', 'string', Rule::in(['openai', 'anthropic', 'gemini'])],
            'language' => ['required', 'string', Rule::enum(ContentLanguage::class)],
            'niche' => ['nullable', 'string', 'max:255'],
            'audience' => ['nullable', 'string', 'max:255'],
            'business_goal' => ['nullable', 'string', Rule::enum(BusinessGoal::class)],
        ];
    }
}
