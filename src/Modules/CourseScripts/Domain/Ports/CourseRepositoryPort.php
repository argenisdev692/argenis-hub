<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Domain\Ports;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\CourseScripts\Application\DTOs\CourseFilterData;
use Modules\CourseScripts\Domain\Enums\BibleOrigin;
use Modules\CourseScripts\Domain\Enums\CourseStatus;
use Modules\CourseScripts\Domain\Enums\SourceDocumentKind;
use Modules\CourseScripts\Domain\Enums\VideoScriptStatus;
use Modules\CourseScripts\Domain\ValueObjects\ParsedIndex;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseEloquentModel;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseSourceDocumentEloquentModel;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseVideoEloquentModel;

/**
 * Persistence of the course aggregate: course, blocks, videos and source
 * documents. Returns Eloquent models, following the project precedent
 * (InvoiceRepositoryPort, VideoEditRepositoryPort).
 *
 * Every `findOwned*` method is owner-scoped: another user's course is
 * indistinguishable from a missing one (FR-53, OWASP §11).
 */
interface CourseRepositoryPort
{
    /**
     * Creates the course with its blocks, videos and source documents in one
     * transaction.
     *
     * @param  list<array{uuid: string, kind: SourceDocumentKind, original_name: string, path: string, mime: string, size_bytes: int, checksum: string, extracted_text: ?string, video_number: ?int}>  $documents
     */
    public function createFromIndex(
        string $uuid,
        int $userId,
        string $title,
        ParsedIndex $index,
        int $defaultVideoMinutes,
        array $documents,
    ): CourseEloquentModel;

    public function findOwned(string $uuid, int $userId): ?CourseEloquentModel;

    /**
     * With blocks, videos and source documents loaded, in course order.
     */
    public function findOwnedWithStructure(string $uuid, int $userId): ?CourseEloquentModel;

    /**
     * Worker-side lookup, no owner scope.
     */
    public function findById(int $id): ?CourseEloquentModel;

    public function findOwnedVideo(string $courseUuid, string $videoUuid, int $userId): ?CourseVideoEloquentModel;

    /**
     * @param  array<string, mixed>  $attributes  brief columns
     */
    public function updateVideoBrief(CourseVideoEloquentModel $video, array $attributes): CourseVideoEloquentModel;

    public function updateCourseNotes(CourseEloquentModel $course, ?string $notes): CourseEloquentModel;

    /**
     * @param  array{uuid: string, kind: SourceDocumentKind, original_name: string, path: string, mime: string, size_bytes: int, checksum: string, extracted_text: ?string, video_number: ?int}  $document
     */
    public function addDocument(CourseEloquentModel $course, array $document): CourseSourceDocumentEloquentModel;

    public function findOwnedDocument(string $courseUuid, string $documentUuid, int $userId): ?CourseSourceDocumentEloquentModel;

    public function deleteDocument(CourseSourceDocumentEloquentModel $document): void;

    public function countDocuments(CourseEloquentModel $course, SourceDocumentKind $kind): int;

    /**
     * @return LengthAwarePaginator<int, CourseEloquentModel>
     */
    public function paginateOwned(int $userId, CourseFilterData $filters): LengthAwarePaginator;

    public function softDelete(CourseEloquentModel $course): void;

    /**
     * Stores the bible and bumps its revision (FR-13 anchor).
     *
     * @param  array<string, mixed>  $bible
     */
    public function saveBible(CourseEloquentModel $course, array $bible, BibleOrigin $origin): CourseEloquentModel;

    public function markPrepared(CourseEloquentModel $course): void;

    /**
     * Accepted scripts written against an older bible revision (FR-13).
     */
    public function countScriptsBeforeBibleRevision(CourseEloquentModel $course): int;

    public function updateStatus(CourseEloquentModel $course, CourseStatus $status): void;

    public function updateVideoStatus(int $videoId, VideoScriptStatus $status): void;

    /**
     * Derives the course status from its videos once no run is active.
     */
    public function refreshStatus(int $courseId): void;
}
