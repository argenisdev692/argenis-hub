<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Application\DTOs;

use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseScriptVersionEloquentModel;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * One row of a video's version history (US-14): metadata only, the script
 * body is fetched through {@see ScriptVersionData}.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class ScriptVersionSummaryData extends Data
{
    /**
     * @param  array<string, mixed>|null  $reviewScores
     */
    public function __construct(
        public string $uuid,
        public int $version,
        public bool $isAccepted,
        public string $writerProvider,
        public bool $reviewed,
        public ?bool $passedReview,
        public ?array $reviewScores,
        public bool $isGrounded,
        public bool $continuityStale,
        public ?string $feedbackNote,
        public ?string $createdAt,
    ) {}

    public static function fromModel(CourseScriptVersionEloquentModel $version): self
    {
        return new self(
            uuid: $version->uuid,
            version: $version->version,
            isAccepted: $version->is_accepted,
            writerProvider: $version->writer_provider,
            reviewed: $version->reviewed,
            passedReview: $version->passed_review,
            reviewScores: $version->review_scores,
            isGrounded: $version->is_grounded,
            continuityStale: $version->continuity_stale,
            feedbackNote: $version->feedback_note,
            createdAt: $version->created_at?->toIso8601String(),
        );
    }
}
