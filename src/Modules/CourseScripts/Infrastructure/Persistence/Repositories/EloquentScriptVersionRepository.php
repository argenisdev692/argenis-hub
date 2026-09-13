<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Infrastructure\Persistence\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Modules\CourseScripts\Domain\Enums\DeliverableDocumentType;
use Modules\CourseScripts\Domain\Enums\DeliverableFormat;
use Modules\CourseScripts\Domain\Ports\ScriptVersionRepositoryPort;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseDeliverableEloquentModel;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CoursePracticeVersionEloquentModel;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseScriptVersionEloquentModel;

final readonly class EloquentScriptVersionRepository implements ScriptVersionRepositoryPort
{
    public function acceptedSummaries(int $courseId): array
    {
        return CourseScriptVersionEloquentModel::query()
            ->where('is_accepted', true)
            ->whereHas('video', static fn (Builder $query) => $query->where('course_id', $courseId))
            ->pluck('taught_summary', 'course_video_id')
            ->map(static fn (mixed $summary): string => (string) $summary)
            ->all();
    }

    public function nextVersionNumber(int $videoId): int
    {
        return (int) CourseScriptVersionEloquentModel::query()->where('course_video_id', $videoId)->max('version') + 1;
    }

    public function record(array $attributes, ?array $practice, array $findingIds, bool $accept): CourseScriptVersionEloquentModel
    {
        return DB::transaction(function () use ($attributes, $practice, $findingIds, $accept): CourseScriptVersionEloquentModel {
            if ($accept) {
                CourseScriptVersionEloquentModel::query()
                    ->where('course_video_id', $attributes['course_video_id'])
                    ->where('is_accepted', true)
                    ->update(['is_accepted' => false]);
            }

            $version = CourseScriptVersionEloquentModel::query()->create([
                ...$attributes,
                'version' => $this->nextVersionNumber((int) $attributes['course_video_id']),
                'is_accepted' => $accept,
            ]);

            if ($practice !== null) {
                CoursePracticeVersionEloquentModel::query()->create([
                    ...$practice,
                    'course_script_version_id' => $version->id,
                ]);
            }

            if ($findingIds !== []) {
                $version->sources()->sync(array_values(array_unique($findingIds)));
            }

            return $version;
        });
    }

    public function findById(int $id): ?CourseScriptVersionEloquentModel
    {
        return CourseScriptVersionEloquentModel::query()->with('practice')->find($id);
    }

    public function findAccepted(int $videoId): ?CourseScriptVersionEloquentModel
    {
        return CourseScriptVersionEloquentModel::query()
            ->where('course_video_id', $videoId)
            ->where('is_accepted', true)
            ->with('practice')
            ->first();
    }

    public function findOwned(string $courseUuid, string $videoUuid, int $userId, ?string $versionUuid = null): ?CourseScriptVersionEloquentModel
    {
        return CourseScriptVersionEloquentModel::query()
            ->whereHas('video', static fn (Builder $query) => $query
                ->where('uuid', $videoUuid)
                ->whereHas('course', static fn (Builder $course) => $course->where('uuid', $courseUuid)->where('user_id', $userId)))
            ->when(
                $versionUuid,
                static fn (Builder $query, string $uuid): Builder => $query->where('uuid', $uuid),
                static fn (Builder $query): Builder => $query->where('is_accepted', true),
            )
            ->with([
                'practice',
                'deliverables:id,uuid,course_script_version_id,document_type,artifact_file_name,format,size_bytes',
                'sources:course_research_findings.id,course_research_findings.url,course_research_findings.title,course_research_findings.provider,course_research_findings.full_page_fetched',
                'video:id,uuid,course_id,number,title,course_block_id',
            ])
            ->first();
    }

    public function listForVideo(int $videoId): array
    {
        return CourseScriptVersionEloquentModel::query()
            ->where('course_video_id', $videoId)
            ->select(['id', 'uuid', 'course_video_id', 'version', 'is_accepted', 'writer_provider', 'reviewed', 'passed_review', 'review_scores', 'is_grounded', 'continuity_stale', 'feedback_note', 'created_at'])
            ->orderByDesc('version')
            ->get()
            ->all();
    }

    public function accept(CourseScriptVersionEloquentModel $version): CourseScriptVersionEloquentModel
    {
        DB::transaction(static function () use ($version): void {
            CourseScriptVersionEloquentModel::query()
                ->where('course_video_id', $version->course_video_id)
                ->where('id', '!=', $version->id)
                ->update(['is_accepted' => false]);

            $version->is_accepted = true;
            $version->save();
        });

        return $version;
    }

    public function saveDeliverable(int $versionId, DeliverableDocumentType $type, string $artifactFileName, DeliverableFormat $format, string $path, int $sizeBytes, string $checksum): ?string
    {
        $existing = CourseDeliverableEloquentModel::query()
            ->where('course_script_version_id', $versionId)
            ->where('document_type', $type->value)
            ->where('artifact_file_name', $artifactFileName)
            ->where('format', $format->value)
            ->first();

        $previousPath = $existing?->path;

        $deliverable = $existing ?? new CourseDeliverableEloquentModel([
            'course_script_version_id' => $versionId,
            'document_type' => $type,
            'artifact_file_name' => $artifactFileName,
            'format' => $format,
        ]);

        $deliverable->fill(['path' => $path, 'size_bytes' => $sizeBytes, 'checksum' => $checksum])->save();

        return $previousPath !== null && $previousPath !== $path ? $previousPath : null;
    }

    public function findOwnedDeliverable(string $deliverableUuid, int $userId): ?CourseDeliverableEloquentModel
    {
        return CourseDeliverableEloquentModel::query()
            ->where('uuid', $deliverableUuid)
            ->whereHas('scriptVersion.video.course', static fn (Builder $query) => $query->where('user_id', $userId))
            ->first();
    }

    public function acceptedForCourse(int $courseId, ?int $videoId = null): array
    {
        return CourseScriptVersionEloquentModel::query()
            ->where('is_accepted', true)
            ->whereHas('video', static fn (Builder $query) => $query->where('course_id', $courseId)->when($videoId, static fn (Builder $video, int $id) => $video->whereKey($id)))
            ->with([
                'video:id,uuid,course_id,course_block_id,number,title',
                'video.block:id,number,title',
                'practice:id,course_script_version_id,document_name',
                'deliverables:id,uuid,course_script_version_id,document_type,artifact_file_name,format,path,size_bytes',
            ])
            ->get()
            ->sortBy(static fn (CourseScriptVersionEloquentModel $version): int => $version->video->number)
            ->values()
            ->all();
    }

    public function markContinuityStale(int $courseId, int $changedVideoId): int
    {
        $stale = 0;

        CourseScriptVersionEloquentModel::query()
            ->where('is_accepted', true)
            ->where('course_video_id', '!=', $changedVideoId)
            ->whereHas('video', static fn (Builder $query) => $query->where('course_id', $courseId))
            ->select(['id', 'continuity_source_video_ids', 'continuity_stale'])
            ->chunkById(200, static function ($versions) use ($changedVideoId, &$stale): void {
                foreach ($versions as $version) {
                    if (in_array($changedVideoId, array_map(intval(...), (array) $version->continuity_source_video_ids), true) && ! $version->continuity_stale) {
                        $version->continuity_stale = true;
                        $version->save();
                        $stale++;
                    }
                }
            });

        return $stale;
    }
}
