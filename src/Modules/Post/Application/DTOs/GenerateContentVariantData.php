<?php

declare(strict_types=1);

namespace Modules\Post\Application\DTOs;

use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Shared input for the social-copy and reel-package generators — both need
 * only the chosen topic/angle/trend (from {@see PostTopicIdeaData} or a
 * freehand topic) plus the provider, same as {@see GeneratePostContentData}
 * minus the cover-image flag.
 */
#[MapInputName(SnakeCaseMapper::class)]
final class GenerateContentVariantData extends Data
{
    public function __construct(
        public string $topic,
        public string $provider,
        public ?string $angle = null,
        public ?string $keyTrend = null,
    ) {}

    /**
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'topic' => ['required', 'string', 'max:255'],
            'provider' => ['required', 'string', Rule::in(['openai', 'anthropic', 'gemini'])],
            'angle' => ['nullable', 'string', 'max:500'],
            'key_trend' => ['nullable', 'string', 'max:255'],
        ];
    }
}
