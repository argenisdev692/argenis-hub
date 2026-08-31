<?php

declare(strict_types=1);

namespace Modules\Post\Application\DTOs;

use Illuminate\Validation\Rule;
use Modules\Post\Domain\Enums\PostImageMode;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Input for full draft generation: either a title picked from
 * {@see PostTopicIdeaData} or a freehand topic the user typed directly.
 *
 * `imageMode` decides how much cover artwork is actually rendered against the
 * brand palette — `full` composites background + subject + title, `base`
 * renders only the palette background plate for the user to composite on, and
 * `none` bills no image call. The layered prompts come back in all three
 * cases, so `none` still leaves the user able to generate externally.
 */
#[MapInputName(SnakeCaseMapper::class)]
final class GeneratePostContentData extends Data
{
    public function __construct(
        public string $topic,
        public string $provider,
        public ?string $angle = null,
        public ?string $keyTrend = null,
        public PostImageMode $imageMode = PostImageMode::Full,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'topic' => ['required', 'string', 'max:255'],
            'provider' => ['required', 'string', Rule::in(['openai', 'anthropic', 'gemini'])],
            'angle' => ['nullable', 'string', 'max:500'],
            'key_trend' => ['nullable', 'string', 'max:255'],
            'image_mode' => ['nullable', Rule::enum(PostImageMode::class)],
        ];
    }
}
