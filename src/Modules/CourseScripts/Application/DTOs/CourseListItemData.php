<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Application\DTOs;

use Modules\CourseScripts\Domain\Enums\CourseStatus;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseEloquentModel;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * A row of the course list (US-10). Counts come from `withCount()`.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class CourseListItemData extends Data
{
    public function __construct(
        public string $uuid,
        public string $title,
        public string $language,
        public CourseStatus $status,
        public int $videosCount,
        public int $generatedVideosCount,
        public string $createdAt,
        public string $updatedAt,
    ) {}

    public static function fromModel(CourseEloquentModel $course): self
    {
        return new self(
            uuid: $course->uuid,
            title: $course->title,
            language: $course->language,
            status: $course->status,
            videosCount: (int) ($course->getAttribute('videos_count') ?? 0),
            generatedVideosCount: (int) ($course->getAttribute('generated_videos_count') ?? 0),
            createdAt: $course->created_at?->toIso8601String() ?? '',
            updatedAt: $course->updated_at?->toIso8601String() ?? '',
        );
    }
}
