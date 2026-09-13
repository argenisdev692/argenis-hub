<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Application\DTOs;

use Modules\CourseScripts\Domain\Enums\BibleOrigin;
use Modules\CourseScripts\Domain\Enums\CourseStatus;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseBlockEloquentModel;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseEloquentModel;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseSourceDocumentEloquentModel;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseVideoEloquentModel;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * A course with its structure, notes, bible and files (US-2, US-10).
 * Requires `findOwnedWithStructure()` to have loaded the relations.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class CourseDetailData extends Data
{
    /**
     * @param  list<BlockData>  $blocks
     * @param  list<VideoBriefData>  $videos
     * @param  list<SourceDocumentData>  $documents
     */
    public function __construct(
        public string $uuid,
        public string $title,
        public string $language,
        public ?int $declaredDurationMinutes,
        public int $defaultVideoMinutes,
        public CourseStatus $status,
        public ?string $courseNotes,
        public ?CourseBibleData $bible,
        public ?BibleOrigin $bibleOrigin,
        public int $bibleRevision,
        public bool $prepared,
        public int $videosNeedingReview,
        public array $blocks,
        public array $videos,
        public array $documents,
        public string $createdAt,
    ) {}

    public static function fromModel(CourseEloquentModel $course): self
    {
        $blockUuids = $course->blocks->mapWithKeys(static fn (CourseBlockEloquentModel $block): array => [$block->id => $block->uuid])->all();
        $videoUuids = $course->videos->mapWithKeys(static fn (CourseVideoEloquentModel $video): array => [$video->id => $video->uuid])->all();

        return new self(
            uuid: $course->uuid,
            title: $course->title,
            language: $course->language,
            declaredDurationMinutes: $course->declared_duration_minutes,
            defaultVideoMinutes: $course->default_video_minutes,
            status: $course->status,
            courseNotes: $course->course_notes,
            bible: $course->bible === null ? null : CourseBibleData::from($course->bible),
            bibleOrigin: $course->bible_origin,
            bibleRevision: $course->bible_revision,
            prepared: $course->prepared_at !== null,
            videosNeedingReview: $course->videos->where('needs_review', true)->count(),
            blocks: $course->blocks->map(static fn (CourseBlockEloquentModel $block): BlockData => BlockData::fromModel($block))->values()->all(),
            videos: $course->videos
                ->map(static fn (CourseVideoEloquentModel $video): VideoBriefData => VideoBriefData::fromModel($video, $course->default_video_minutes, $blockUuids))
                ->values()
                ->all(),
            documents: $course->sourceDocuments
                ->map(static fn (CourseSourceDocumentEloquentModel $document): SourceDocumentData => SourceDocumentData::fromModel($document, $videoUuids))
                ->values()
                ->all(),
            createdAt: $course->created_at?->toIso8601String() ?? '',
        );
    }
}
