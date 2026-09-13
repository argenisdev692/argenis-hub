<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Infrastructure\Persistence\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Modules\CourseScripts\Application\DTOs\CourseFilterData;
use Modules\CourseScripts\Domain\Enums\BibleOrigin;
use Modules\CourseScripts\Domain\Enums\CourseStatus;
use Modules\CourseScripts\Domain\Enums\SourceDocumentKind;
use Modules\CourseScripts\Domain\Enums\VideoScriptStatus;
use Modules\CourseScripts\Domain\Ports\CourseRepositoryPort;
use Modules\CourseScripts\Domain\ValueObjects\ParsedIndex;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseBlockEloquentModel;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseEloquentModel;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseScriptVersionEloquentModel;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseSourceDocumentEloquentModel;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseVideoEloquentModel;

final readonly class EloquentCourseRepository implements CourseRepositoryPort
{
    /**
     * Notes shorter than this do not lift a thin brief out of review (FR-5).
     */
    private const int SUBSTANTIAL_NOTES_CHARS = 200;

    public function createFromIndex(
        string $uuid,
        int $userId,
        string $title,
        ParsedIndex $index,
        int $defaultVideoMinutes,
        array $documents,
    ): CourseEloquentModel {
        return DB::transaction(function () use ($uuid, $userId, $title, $index, $defaultVideoMinutes, $documents): CourseEloquentModel {
            $course = CourseEloquentModel::query()->create([
                'uuid' => $uuid,
                'user_id' => $userId,
                'title' => $title,
                'language' => $index->language,
                'declared_duration_minutes' => $index->declaredTotalMinutes,
                'default_video_minutes' => $defaultVideoMinutes,
                'status' => CourseStatus::Ready,
                'course_notes' => $index->courseNotes,
            ]);

            $blockIds = [];

            foreach ($index->groups as $group) {
                // An implicit group is the parser's convenience, not the author's
                // structure — it is not persisted (FR-2a).
                if ($group->isImplicit) {
                    continue;
                }

                $blockIds[$group->number] = CourseBlockEloquentModel::query()->create([
                    'course_id' => $course->id,
                    'number' => $group->number,
                    'title' => $group->title,
                    'declared_duration_minutes' => $group->declaredDurationMinutes,
                    'position' => $group->position,
                ])->id;
            }

            $videoIds = [];

            foreach ($index->points as $point) {
                $videoIds[$point->position] = CourseVideoEloquentModel::query()->create([
                    'course_id' => $course->id,
                    'course_block_id' => $point->groupNumber !== null ? ($blockIds[$point->groupNumber] ?? null) : null,
                    'number' => $point->position,
                    'title' => $point->title,
                    'topic' => $point->topic,
                    'declared_duration_minutes' => $point->declaredDurationMinutes > 0 ? $point->declaredDurationMinutes : null,
                    'objective' => $point->objective,
                    'expected_result' => $point->expectedResult,
                    'learning_areas' => $point->learningAreas,
                    'audience_objectives' => $point->audienceObjectives,
                    'mandatory_content' => $point->mandatoryContent,
                    'errors_to_avoid' => $point->errorsToAvoid,
                    'notes' => $point->notes,
                    'needs_review' => $point->needsReview(self::SUBSTANTIAL_NOTES_CHARS),
                    'script_status' => VideoScriptStatus::NotStarted,
                ])->id;
            }

            foreach ($documents as $document) {
                CourseSourceDocumentEloquentModel::query()->create([
                    'uuid' => $document['uuid'],
                    'course_id' => $course->id,
                    'course_video_id' => $document['video_number'] !== null ? ($videoIds[$document['video_number']] ?? null) : null,
                    'kind' => $document['kind'],
                    'original_name' => $document['original_name'],
                    'path' => $document['path'],
                    'mime' => $document['mime'],
                    'size_bytes' => $document['size_bytes'],
                    'checksum' => $document['checksum'],
                    'extracted_text' => $document['extracted_text'],
                ]);
            }

            return $course;
        });
    }

    public function findOwned(string $uuid, int $userId): ?CourseEloquentModel
    {
        return CourseEloquentModel::query()->ownedBy($userId)->where('uuid', $uuid)->first();
    }

    public function findOwnedWithStructure(string $uuid, int $userId): ?CourseEloquentModel
    {
        return CourseEloquentModel::query()
            ->ownedBy($userId)
            ->where('uuid', $uuid)
            ->with([
                'blocks' => static fn ($query) => $query->orderBy('number'),
                'videos' => static fn ($query) => $query->orderBy('number'),
                'sourceDocuments' => static fn ($query) => $query
                    ->select(['id', 'uuid', 'course_id', 'course_video_id', 'kind', 'original_name', 'mime', 'size_bytes', 'created_at'])
                    ->orderBy('id'),
            ])
            ->first();
    }

    public function findById(int $id): ?CourseEloquentModel
    {
        return CourseEloquentModel::query()->find($id);
    }

    public function findOwnedVideo(string $courseUuid, string $videoUuid, int $userId): ?CourseVideoEloquentModel
    {
        return CourseVideoEloquentModel::query()
            ->where('uuid', $videoUuid)
            ->whereHas('course', static fn (Builder $query) => $query->where('uuid', $courseUuid)->where('user_id', $userId))
            ->first();
    }

    public function updateVideoBrief(CourseVideoEloquentModel $video, array $attributes): CourseVideoEloquentModel
    {
        $video->fill($attributes);

        if ($video->isDirty()) {
            $video->brief_revision++;
            $video->save();
        }

        return $video;
    }

    public function updateCourseNotes(CourseEloquentModel $course, ?string $notes): CourseEloquentModel
    {
        $course->course_notes = $notes;
        $course->save();

        return $course;
    }

    public function addDocument(CourseEloquentModel $course, array $document): CourseSourceDocumentEloquentModel
    {
        $videoId = $document['video_number'] === null
            ? null
            : CourseVideoEloquentModel::query()->where('course_id', $course->id)->where('number', $document['video_number'])->value('id');

        return CourseSourceDocumentEloquentModel::query()->create([
            'uuid' => $document['uuid'],
            'course_id' => $course->id,
            'course_video_id' => $videoId,
            'kind' => $document['kind'],
            'original_name' => $document['original_name'],
            'path' => $document['path'],
            'mime' => $document['mime'],
            'size_bytes' => $document['size_bytes'],
            'checksum' => $document['checksum'],
            'extracted_text' => $document['extracted_text'],
        ]);
    }

    public function findOwnedDocument(string $courseUuid, string $documentUuid, int $userId): ?CourseSourceDocumentEloquentModel
    {
        return CourseSourceDocumentEloquentModel::query()
            ->where('uuid', $documentUuid)
            ->whereHas('course', static fn (Builder $query) => $query->where('uuid', $courseUuid)->where('user_id', $userId))
            ->first();
    }

    public function deleteDocument(CourseSourceDocumentEloquentModel $document): void
    {
        $document->delete();
    }

    public function countDocuments(CourseEloquentModel $course, SourceDocumentKind $kind): int
    {
        return CourseSourceDocumentEloquentModel::query()
            ->where('course_id', $course->id)
            ->where('kind', $kind->value)
            ->count();
    }

    public function paginateOwned(int $userId, CourseFilterData $filters): LengthAwarePaginator
    {
        return CourseEloquentModel::query()
            ->ownedBy($userId)
            ->applyFilters($filters)
            ->withCount([
                'videos',
                'videos as generated_videos_count' => static fn (Builder $query) => $query->where('script_status', VideoScriptStatus::Generated->value),
            ])
            ->paginate(perPage: min($filters->perPage, 100), page: $filters->page);
    }

    public function softDelete(CourseEloquentModel $course): void
    {
        $course->delete();
    }

    public function saveBible(CourseEloquentModel $course, array $bible, BibleOrigin $origin): CourseEloquentModel
    {
        $course->bible = $bible;
        $course->bible_origin = $origin;
        $course->bible_revision++;
        $course->save();

        return $course;
    }

    public function markPrepared(CourseEloquentModel $course): void
    {
        $course->prepared_at = now();
        $course->save();
    }

    public function countScriptsBeforeBibleRevision(CourseEloquentModel $course): int
    {
        return CourseScriptVersionEloquentModel::query()
            ->where('is_accepted', true)
            ->where('bible_revision', '<', $course->bible_revision)
            ->whereHas('video', static fn (Builder $query) => $query->where('course_id', $course->id))
            ->count();
    }

    public function updateStatus(CourseEloquentModel $course, CourseStatus $status): void
    {
        $course->status = $status;
        $course->save();
    }

    public function updateVideoStatus(int $videoId, VideoScriptStatus $status): void
    {
        CourseVideoEloquentModel::query()->whereKey($videoId)->update(['script_status' => $status->value]);
    }

    public function refreshStatus(int $courseId): void
    {
        $course = CourseEloquentModel::query()->find($courseId);

        if ($course === null) {
            return;
        }

        $total = CourseVideoEloquentModel::query()->where('course_id', $courseId)->count();
        $generated = CourseVideoEloquentModel::query()->where('course_id', $courseId)->where('script_status', VideoScriptStatus::Generated->value)->count();

        $course->status = match (true) {
            $total > 0 && $generated === $total => CourseStatus::Completed,
            $generated > 0 => CourseStatus::PartiallyGenerated,
            default => CourseStatus::Ready,
        };
        $course->save();
    }
}
