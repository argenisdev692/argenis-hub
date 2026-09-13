<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Application\DTOs;

use Modules\CourseScripts\Domain\Enums\VideoScriptStatus;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseVideoEloquentModel;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * A video's brief and notes as the author sees and edits them (US-2).
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class VideoBriefData extends Data
{
    /**
     * @param  list<string>  $learningAreas
     * @param  list<string>  $audienceObjectives
     * @param  list<string>  $mandatoryContent
     * @param  list<string>  $errorsToAvoid
     */
    public function __construct(
        public string $uuid,
        public ?string $blockUuid,
        public int $number,
        public string $title,
        public ?string $topic,
        public ?int $declaredDurationMinutes,
        public int $effectiveDurationMinutes,
        public ?string $objective,
        public array $learningAreas,
        public array $audienceObjectives,
        public array $mandatoryContent,
        public array $errorsToAvoid,
        public ?string $expectedResult,
        public ?string $notes,
        public bool $needsReview,
        public int $briefRevision,
        public VideoScriptStatus $scriptStatus,
    ) {}

    /**
     * @param  array<int, string>  $blockUuidsById
     */
    public static function fromModel(CourseVideoEloquentModel $video, int $defaultMinutes, array $blockUuidsById = []): self
    {
        return new self(
            uuid: $video->uuid,
            blockUuid: $video->course_block_id !== null ? ($blockUuidsById[$video->course_block_id] ?? null) : null,
            number: $video->number,
            title: $video->title,
            topic: $video->topic,
            declaredDurationMinutes: $video->declared_duration_minutes,
            effectiveDurationMinutes: $video->declared_duration_minutes ?? $defaultMinutes,
            objective: $video->objective,
            learningAreas: $video->learning_areas,
            audienceObjectives: $video->audience_objectives,
            mandatoryContent: $video->mandatory_content,
            errorsToAvoid: $video->errors_to_avoid,
            expectedResult: $video->expected_result,
            notes: $video->notes,
            needsReview: $video->needs_review,
            briefRevision: $video->brief_revision,
            scriptStatus: $video->script_status,
        );
    }
}
