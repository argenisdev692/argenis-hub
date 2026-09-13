<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Application\DTOs;

use Illuminate\Validation\Rule;
use Modules\CourseScripts\Domain\Enums\GenerationScope;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * A confirmed run request (US-9 · FR-14, FR-14a). The writer is chosen from an
 * allow-list; the model is never a request parameter (A2/D2). `with_review` is
 * the author's per-run choice of the independent second review (DEC-10).
 */
#[MapInputName(SnakeCaseMapper::class)]
final class StartGenerationRunData extends EstimateRunData
{
    /**
     * @param  list<string>|null  $videoUuids
     * @param  array{ai_write_calls: int, ai_review_calls: int, research_calls: int}  $confirmedEstimate
     */
    public function __construct(
        public string $writerProvider = '',
        public array $confirmedEstimate = [],
        GenerationScope $scope = GenerationScope::Selection,
        ?string $blockUuid = null,
        ?array $videoUuids = null,
        ?bool $withReview = null,
    ) {
        parent::__construct($scope, $blockUuid, $videoUuids, $withReview);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        return [
            ...parent::rules(),
            'writer_provider' => ['required', 'string', Rule::in((array) config('course-scripts.providers.selectable_writers'))],
            'confirmed_estimate' => ['required', 'array'],
            'confirmed_estimate.ai_write_calls' => ['required', 'integer', 'min:0'],
            'confirmed_estimate.ai_review_calls' => ['required', 'integer', 'min:0'],
            'confirmed_estimate.research_calls' => ['required', 'integer', 'min:0'],
        ];
    }
}
