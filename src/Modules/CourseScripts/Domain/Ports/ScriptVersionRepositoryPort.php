<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Domain\Ports;

use Modules\CourseScripts\Domain\Enums\DeliverableDocumentType;
use Modules\CourseScripts\Domain\Enums\DeliverableFormat;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseDeliverableEloquentModel;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseScriptVersionEloquentModel;

/**
 * Persistence of script versions, their practice packs and research sources
 * (US-5, US-7, US-14 · FR-49, FR-13f).
 */
interface ScriptVersionRepositoryPort
{
    /**
     * `taught_summary` of every accepted script of the course, keyed by video id
     * (continuity input, FR-33a).
     *
     * @return array<int, string>
     */
    public function acceptedSummaries(int $courseId): array;

    public function nextVersionNumber(int $videoId): int;

    /**
     * Stores a version with its practice pack and sources in one transaction.
     * When `$accept` is true, the previous accepted version is un-accepted.
     *
     * @param  array<string, mixed>  $attributes  script version columns
     * @param  array<string, mixed>|null  $practice  practice version columns
     * @param  list<int>  $findingIds
     */
    public function record(array $attributes, ?array $practice, array $findingIds, bool $accept): CourseScriptVersionEloquentModel;

    public function findById(int $id): ?CourseScriptVersionEloquentModel;

    public function findAccepted(int $videoId): ?CourseScriptVersionEloquentModel;

    /**
     * Owner-scoped: the accepted version, or the given version of the video.
     */
    public function findOwned(string $courseUuid, string $videoUuid, int $userId, ?string $versionUuid = null): ?CourseScriptVersionEloquentModel;

    /**
     * @return list<CourseScriptVersionEloquentModel> newest first
     */
    public function listForVideo(int $videoId): array;

    public function accept(CourseScriptVersionEloquentModel $version): CourseScriptVersionEloquentModel;

    /**
     * Flags accepted scripts whose continuity used this video (FR-34, D9).
     */
    public function markContinuityStale(int $courseId, int $changedVideoId): int;

    /**
     * Inserts or replaces one rendered file (FR-45, FR-50).
     *
     * @return string|null the path it replaced, for cleanup
     */
    public function saveDeliverable(int $versionId, DeliverableDocumentType $type, string $artifactFileName, DeliverableFormat $format, string $path, int $sizeBytes, string $checksum): ?string;

    /**
     * Owner-scoped single deliverable (FR-51).
     */
    public function findOwnedDeliverable(string $deliverableUuid, int $userId): ?CourseDeliverableEloquentModel;

    /**
     * Accepted versions of a course with practice and deliverables, in course order.
     *
     * @return list<CourseScriptVersionEloquentModel>
     */
    public function acceptedForCourse(int $courseId, ?int $videoId = null): array;
}
