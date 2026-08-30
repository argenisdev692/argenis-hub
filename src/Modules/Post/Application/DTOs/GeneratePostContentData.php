<?php

declare(strict_types=1);

namespace Modules\Post\Application\DTOs;

use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Input for full draft generation: either a title picked from
 * {@see PostTopicIdeaData} or a freehand topic the user typed directly.
 */
#[MapInputName(SnakeCaseMapper::class)]
final class GeneratePostContentData extends Data
{
    public function __construct(
        public string $topic,
        public string $provider,
        public ?string $angle = null,
        public ?string $keyTrend = null,
        public bool $generateCoverImage = true,
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
            'generate_cover_image' => ['boolean'],
        ];
    }
}
