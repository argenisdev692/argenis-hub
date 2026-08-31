<?php

declare(strict_types=1);

namespace Modules\Post\Application\DTOs;

use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Input for topic ideation. The flow is category-first: the chosen blog
 * category IS the niche, so `categoryUuid` is required and its name +
 * description drive both the Tavily research queries and the agent prompt.
 * `topic` stays an optional extra steer that narrows the angle inside that
 * category — when blank the agent scans the whole category.
 */
#[MapInputName(SnakeCaseMapper::class)]
final class SuggestPostTopicsData extends Data
{
    public function __construct(
        public string $provider,
        public string $categoryUuid,
        public ?string $topic = null,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'provider' => ['required', 'string', Rule::in(['openai', 'anthropic', 'gemini'])],
            'category_uuid' => ['required', 'uuid', Rule::exists('blog_categories', 'uuid')->whereNull('deleted_at')],
            'topic' => ['nullable', 'string', 'max:255'],
        ];
    }
}
