<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Application\DTOs;

use Illuminate\Validation\Rule;
use Modules\CourseScripts\Domain\Enums\GenerationScope;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * What a run would cover and whether it uses the second review (US-9, US-12).
 */
#[MapInputName(SnakeCaseMapper::class)]
class EstimateRunData extends Data
{
    /**
     * @param  list<string>|null  $videoUuids
     */
    public function __construct(
        public GenerationScope $scope = GenerationScope::Selection,
        public ?string $blockUuid = null,
        public ?array $videoUuids = null,
        public ?bool $withReview = null,
    ) {}

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        return [
            'scope' => ['required', 'string', Rule::in(array_column(GenerationScope::cases(), 'value'))],
            'block_uuid' => ['nullable', 'required_if:scope,block', 'uuid'],
            'video_uuids' => ['nullable', 'required_if:scope,selection', 'array', 'min:1', 'max:200'],
            'video_uuids.*' => ['uuid'],
            'with_review' => ['nullable', 'boolean'],
        ];
    }

    public function reviewRequested(): bool
    {
        return $this->withReview ?? (bool) config('course-scripts.review.default_with_review', false);
    }
}
