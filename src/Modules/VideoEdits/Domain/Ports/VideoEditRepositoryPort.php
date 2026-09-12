<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\Ports;

use Closure;
use DateTimeInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\VideoEdits\Application\DTOs\VideoEditFilterData;
use Modules\VideoEdits\Application\DTOs\VideoEditListItemData;
use Modules\VideoEdits\Domain\Enums\ProcessingStage;
use Modules\VideoEdits\Domain\Enums\VideoEditMode;
use Modules\VideoEdits\Domain\Enums\VideoEditStatus;
use Modules\VideoEdits\Domain\Exceptions\VideoEditNotFoundException;
use Modules\VideoEdits\Domain\Exceptions\VideoEditStateConflictException;
use Modules\VideoEdits\Domain\ValueObjects\ContentFingerprint;
use Modules\VideoEdits\Domain\ValueObjects\CutPlan;
use Modules\VideoEdits\Domain\ValueObjects\MediaProbe;
use Modules\VideoEdits\Infrastructure\Persistence\Eloquent\Models\VideoEditEloquentModel;

/**
 * Persistence of the video edit aggregate. Returns the Eloquent model, following
 * the project precedent set by InvoiceRepositoryPort (plan AD-10).
 */
interface VideoEditRepositoryPort
{
    public function findByUuid(string $uuid): ?VideoEditEloquentModel;

    /**
     * Owner-scoped lookup: another user's edit is indistinguishable from a missing one (OWASP §11).
     */
    public function findOwnedByUuid(string $uuid, int $userId): ?VideoEditEloquentModel;

    /**
     * Owner-scoped lookup with sources, applied cuts, decisions and the re-edit origin loaded.
     */
    public function findOwnedWithDetails(string $uuid, int $userId): ?VideoEditEloquentModel;

    /**
     * Worker-side lookup (no owner scope) with details and the owner's uuid loaded.
     */
    public function findWithDetails(string $uuid): ?VideoEditEloquentModel;

    /**
     * The owner's history, newest first, drafts excluded (US-6).
     *
     * @return LengthAwarePaginator<int, VideoEditListItemData>
     */
    public function paginateOwned(int $userId, VideoEditFilterData $filters): LengthAwarePaginator;

    /**
     * Creates a draft edit with its source rows — and, for a V3 AI edit, its
     * script row and consent timestamp — in one transaction, returned with
     * details loaded.
     *
     * @param  array<string, mixed>  $parameters
     * @param  list<array<string, mixed>>  $sources  source column values
     * @param  array<string, mixed>|null  $script  script column values (EX-9)
     */
    public function createDraft(
        string $uuid,
        int $userId,
        ?int $previousEditId,
        VideoEditMode $mode,
        array $parameters,
        array $sources,
        ?array $script = null,
        ?DateTimeInterface $consentedAt = null,
    ): VideoEditEloquentModel;

    /**
     * @param  array<string, int>  $sizesBySourceUuid
     */
    public function recordVerifiedSourceSizes(array $sizesBySourceUuid): void;

    public function recordSourceProbe(string $sourceUuid, MediaProbe $probe, ContentFingerprint $fingerprint): void;

    /**
     * Stores the text pulled out of an attached script (V3 · US-12), so a retry
     * does not parse the same PDF twice.
     */
    public function recordScriptText(string $scriptUuid, string $text): void;

    /**
     * Atomic compare-and-set on `status` (AD-9): succeeds only when the row is
     * still in one of `$from`, so races between submit, retry, delete and the
     * worker are settled by the database.
     *
     * @param  list<VideoEditStatus>  $from
     * @param  array<string, mixed>  $attributes  extra columns written in the same statement
     *
     * @throws VideoEditStateConflictException when the move would give the owner a second active edit
     */
    public function transitionStatus(string $uuid, array $from, VideoEditStatus $to, array $attributes = []): bool;

    public function recordAttempt(string $uuid, int $attempt): void;

    /**
     * Progress writes only land while the edit is processing.
     */
    public function updateProgress(string $uuid, int $percent, ProcessingStage $stage): void;

    /**
     * In one transaction: replaces decisions and applied cuts, stores totals and
     * the result, and moves `processing → completed`. Returns false when the edit
     * is no longer processing.
     *
     * @param  array<string, mixed>  $effectiveSettings
     * @param  list<string>  $warnings
     */
    public function completeWithPlan(
        string $uuid,
        CutPlan $plan,
        string $resultPath,
        int $resultSizeBytes,
        array $effectiveSettings,
        array $warnings,
    ): bool;

    /**
     * Forgets the object paths of sources whose files were deleted (FR-9 / FR-10).
     *
     * @param  list<string>  $sourceUuids
     */
    public function markSourcesPurged(string $uuid, array $sourceUuids): void;

    /**
     * Hard delete in one transaction (US-9 · D14): locks the owner's edit, refuses
     * while it is processing, runs `$beforeDelete` (object cleanup — an exception
     * there rolls everything back) and deletes the rows. A worker waiting on the
     * lock then finds nothing to process.
     *
     * @param  Closure(VideoEditEloquentModel): void  $beforeDelete
     *
     * @throws VideoEditNotFoundException
     * @throws VideoEditStateConflictException
     */
    public function deleteOwned(string $uuid, int $userId, Closure $beforeDelete): VideoEditEloquentModel;

    /**
     * Failed edits whose retry window has closed but whose sources are still stored (FR-10).
     *
     * @return list<string>
     */
    public function failedWithExpiredSourcesUuids(DateTimeInterface $now, int $limit): array;

    /**
     * Completed edits with source files that could not be deleted at publish time (FR-9).
     *
     * @return list<string>
     */
    public function completedWithRetainedSourcesUuids(int $limit): array;

    /**
     * Processing edits with no progress write since `$lastActivityBefore` (AD-14).
     *
     * @return list<string>
     */
    public function staleProcessingUuids(DateTimeInterface $lastActivityBefore, int $limit): array;

    /**
     * Drafts never submitted (D17).
     *
     * @return list<string>
     */
    public function expiredDraftUuids(DateTimeInterface $createdBefore, int $limit): array;

    /**
     * Deletes a draft (with `$beforeDelete` cleaning up its objects) only if it is
     * still a draft — a submit racing the cleanup wins.
     *
     * @param  Closure(VideoEditEloquentModel): void  $beforeDelete
     */
    public function deleteDraft(string $uuid, Closure $beforeDelete): bool;
}
