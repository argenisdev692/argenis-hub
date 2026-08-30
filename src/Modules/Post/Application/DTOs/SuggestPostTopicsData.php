<?php

declare(strict_types=1);

namespace Modules\Post\Application\DTOs;

use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;

/**
 * Input for topic ideation. `topic` is an optional steer — when blank the
 * agent leans entirely on the company profile + Tavily trend research; when
 * given, ideas are angled around that topic instead of a free niche scan.
 */
final class SuggestPostTopicsData extends Data
{
    public function __construct(
        public string $provider,
        public ?string $topic = null,
    ) {}

    /**
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'provider' => ['required', 'string', Rule::in(['openai', 'anthropic', 'gemini'])],
            'topic' => ['nullable', 'string', 'max:255'],
        ];
    }
}
