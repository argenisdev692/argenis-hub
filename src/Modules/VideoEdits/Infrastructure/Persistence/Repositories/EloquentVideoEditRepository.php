<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Infrastructure\Persistence\Repositories;

use BackedEnum;
use Closure;
use DateTimeInterface;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\VideoEdits\Application\DTOs\VideoEditFilterData;
use Modules\VideoEdits\Application\DTOs\VideoEditListItemData;
use Modules\VideoEdits\Domain\Enums\ProcessingStage;
use Modules\VideoEdits\Domain\Enums\VideoEditMode;
use Modules\VideoEdits\Domain\Enums\VideoEditStatus;
use Modules\VideoEdits\Domain\Exceptions\VideoEditNotFoundException;
use Modules\VideoEdits\Domain\Exceptions\VideoEditStateConflictException;
use Modules\VideoEdits\Domain\Ports\VideoEditRepositoryPort;
use Modules\VideoEdits\Domain\ValueObjects\AppliedCut;
use Modules\VideoEdits\Domain\ValueObjects\ContentFingerprint;
use Modules\VideoEdits\Domain\ValueObjects\CutPlan;
use Modules\VideoEdits\Domain\ValueObjects\MediaProbe;
use Modules\VideoEdits\Domain\ValueObjects\ValidatedDecision;
use Modules\VideoEdits\Infrastructure\Persistence\Eloquent\Models\VideoEditAppliedCutEloquentModel;
use Modules\VideoEdits\Infrastructure\Persistence\Eloquent\Models\VideoEditCutDecisionEloquentModel;
use Modules\VideoEdits\Infrastructure\Persistence\Eloquent\Models\VideoEditEloquentModel;
use Modules\VideoEdits\Infrastructure\Persistence\Eloquent\Models\VideoEditScriptEloquentModel;
use Modules\VideoEdits\Infrastructure\Persistence\Eloquent\Models\VideoEditSourceEloquentModel;

final readonly class EloquentVideoEditRepository implements VideoEditRepositoryPort
{
    private const array SOURCE_COLUMNS = [
        'id', 'uuid', 'video_edit_id', 'position', 'original_name', 'extension', 'declared_mime',
        'declared_size_bytes', 'storage_path', 'size_bytes', 'sha256', 'duration_ms', 'width',
        'height', 'frame_rate', 'has_audio', 'container', 'video_codec', 'audio_codec',
    ];

    private const array LIST_COLUMNS = [
        'id', 'uuid', 'user_id', 'mode', 'status', 'progress_percent', 'final_duration_ms',
        'applied_cut_count', 'created_at', 'completed_at',
    ];

    private const int INSERT_CHUNK = 500;

    public function findByUuid(string $uuid): ?VideoEditEloquentModel
    {
        return VideoEditEloquentModel::query()->where('uuid', $uuid)->first();
    }

    public function findOwnedByUuid(string $uuid, int $userId): ?VideoEditEloquentModel
    {
        return VideoEditEloquentModel::query()
            ->where('uuid', $uuid)
            ->where('user_id', $userId)
            ->first();
    }

    public function findOwnedWithDetails(string $uuid, int $userId): ?VideoEditEloquentModel
    {
        return VideoEditEloquentModel::query()
            ->with(self::detailRelations())
            ->where('uuid', $uuid)
            ->where('user_id', $userId)
            ->first();
    }

    public function findWithDetails(string $uuid): ?VideoEditEloquentModel
    {
        return VideoEditEloquentModel::query()
            ->with([...self::detailRelations(), 'user:id,uuid'])
            ->where('uuid', $uuid)
            ->first();
    }

    public function paginateOwned(int $userId, VideoEditFilterData $filters): LengthAwarePaginator
    {
        return VideoEditEloquentModel::query()
            ->select(self::LIST_COLUMNS)
            ->withCount('sources')
            ->ownedBy($userId)
            ->applyFilters($filters)
            ->paginate(perPage: $filters->perPage, page: $filters->page)
            ->through(static fn (VideoEditEloquentModel $edit): VideoEditListItemData => VideoEditListItemData::fromModel($edit));
    }

    public function createDraft(
        string $uuid,
        int $userId,
        ?int $previousEditId,
        VideoEditMode $mode,
        array $parameters,
        array $sources,
        ?array $script = null,
        ?DateTimeInterface $consentedAt = null,
    ): VideoEditEloquentModel {
        return DB::transaction(function () use ($uuid, $userId, $previousEditId, $mode, $parameters, $sources, $script, $consentedAt): VideoEditEloquentModel {
            $edit = VideoEditEloquentModel::query()->create([
                'uuid' => $uuid,
                'user_id' => $userId,
                'previous_edit_id' => $previousEditId,
                'mode' => $mode,
                'status' => VideoEditStatus::Draft,
                'parameters' => $parameters,
                'ai_consent_at' => $consentedAt,
            ]);

            $edit->sources()->createMany($sources);

            if ($script !== null) {
                $edit->script()->create($script);
            }

            return $edit->load(self::detailRelations());
        });
    }

    public function recordVerifiedSourceSizes(array $sizesBySourceUuid): void
    {
        foreach ($sizesBySourceUuid as $sourceUuid => $sizeBytes) {
            VideoEditSourceEloquentModel::query()
                ->where('uuid', $sourceUuid)
                ->update(['size_bytes' => $sizeBytes, 'updated_at' => now()]);
        }
    }

    public function recordScriptText(string $scriptUuid, string $text): void
    {
        VideoEditScriptEloquentModel::query()
            ->where('uuid', $scriptUuid)
            ->update(['extracted_text' => $text, 'updated_at' => now()]);
    }

    public function recordSourceProbe(string $sourceUuid, MediaProbe $probe, ContentFingerprint $fingerprint): void
    {
        VideoEditSourceEloquentModel::query()
            ->where('uuid', $sourceUuid)
            ->update([
                'sha256' => $fingerprint->sha256,
                'duration_ms' => $probe->durationMs,
                'width' => $probe->width,
                'height' => $probe->height,
                'frame_rate' => $probe->frameRate,
                'has_audio' => $probe->hasAudio,
                'container' => mb_substr($probe->container, 0, 50),
                'video_codec' => $probe->videoCodec === null ? null : mb_substr($probe->videoCodec, 0, 30),
                'audio_codec' => $probe->audioCodec === null ? null : mb_substr($probe->audioCodec, 0, 30),
                'updated_at' => now(),
            ]);
    }

    public function transitionStatus(string $uuid, array $from, VideoEditStatus $to, array $attributes = []): bool
    {
        foreach ($from as $status) {
            if (! $status->canTransitionTo($to)) {
                throw new InvalidArgumentException("A video edit cannot move from {$status->value} to {$to->value}.");
            }
        }

        try {
            $updated = VideoEditEloquentModel::query()
                ->where('uuid', $uuid)
                ->whereIn('status', array_map(static fn (VideoEditStatus $status): string => $status->value, $from))
                ->update([
                    ...array_map(self::toColumnValue(...), $attributes),
                    'status' => $to->value,
                    'updated_at' => now(),
                ]);
        } catch (UniqueConstraintViolationException $exception) {
            throw VideoEditStateConflictException::alreadyActive($exception);
        }

        return $updated === 1;
    }

    public function recordAttempt(string $uuid, int $attempt): void
    {
        VideoEditEloquentModel::query()
            ->where('uuid', $uuid)
            ->update(['attempts' => $attempt, 'updated_at' => now()]);
    }

    public function updateProgress(string $uuid, int $percent, ProcessingStage $stage): void
    {
        VideoEditEloquentModel::query()
            ->where('uuid', $uuid)
            ->where('status', VideoEditStatus::Processing->value)
            ->update([
                'progress_percent' => max(0, min(100, $percent)),
                'current_stage' => $stage->value,
                'updated_at' => now(),
            ]);
    }

    public function completeWithPlan(
        string $uuid,
        CutPlan $plan,
        string $resultPath,
        int $resultSizeBytes,
        array $effectiveSettings,
        array $warnings,
    ): bool {
        return DB::transaction(function () use ($uuid, $plan, $resultPath, $resultSizeBytes, $effectiveSettings, $warnings): bool {
            $edit = VideoEditEloquentModel::query()->where('uuid', $uuid)->lockForUpdate()->first();

            if ($edit === null || $edit->status !== VideoEditStatus::Processing) {
                return false;
            }

            $now = now();

            // A retried attempt replaces whatever an earlier attempt managed to write.
            VideoEditCutDecisionEloquentModel::query()->where('video_edit_id', $edit->id)->delete();
            VideoEditAppliedCutEloquentModel::query()->where('video_edit_id', $edit->id)->delete();

            foreach (array_chunk(array_map(
                static fn (ValidatedDecision $decision): array => self::decisionRow($edit->id, $decision, $now),
                $plan->decisions,
            ), self::INSERT_CHUNK) as $rows) {
                VideoEditCutDecisionEloquentModel::query()->insert($rows);
            }

            foreach (array_chunk(array_map(
                static fn (AppliedCut $cut): array => self::appliedCutRow($edit->id, $cut, $now),
                $plan->appliedCuts,
            ), self::INSERT_CHUNK) as $rows) {
                VideoEditAppliedCutEloquentModel::query()->insert($rows);
            }

            $edit->forceFill([
                'status' => VideoEditStatus::Completed,
                'progress_percent' => 100,
                'current_stage' => ProcessingStage::Publish,
                'original_duration_ms' => $plan->originalDurationMs,
                'final_duration_ms' => $plan->finalDurationMs,
                'removed_duration_ms' => $plan->removedDurationMs(),
                'applied_cut_count' => count($plan->appliedCuts),
                'rejected_decision_count' => $plan->rejectedDecisionCount(),
                'effective_settings' => $effectiveSettings,
                'warnings' => $warnings === [] ? null : $warnings,
                'result_path' => $resultPath,
                'result_size_bytes' => $resultSizeBytes,
                'failure_code' => null,
                'failure_message' => null,
                'failure_details' => null,
                'completed_at' => $now,
            ])->save();

            return true;
        });
    }

    public function markSourcesPurged(string $uuid, array $sourceUuids): void
    {
        if ($sourceUuids === []) {
            return;
        }

        DB::transaction(function () use ($uuid, $sourceUuids): void {
            $edit = VideoEditEloquentModel::query()->where('uuid', $uuid)->first(['id']);

            if ($edit === null) {
                return;
            }

            VideoEditSourceEloquentModel::query()
                ->where('video_edit_id', $edit->id)
                ->whereIn('uuid', $sourceUuids)
                ->update(['storage_path' => null, 'updated_at' => now()]);

            $remaining = VideoEditSourceEloquentModel::query()
                ->where('video_edit_id', $edit->id)
                ->whereNotNull('storage_path')
                ->exists();

            if (! $remaining) {
                VideoEditEloquentModel::query()->whereKey($edit->id)->update(['sources_purged_at' => now()]);
            }
        });
    }

    public function deleteOwned(string $uuid, int $userId, Closure $beforeDelete): VideoEditEloquentModel
    {
        return DB::transaction(function () use ($uuid, $userId, $beforeDelete): VideoEditEloquentModel {
            $edit = VideoEditEloquentModel::query()
                ->where('uuid', $uuid)
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->first();

            if ($edit === null) {
                throw VideoEditNotFoundException::forUuid($uuid);
            }

            if (! $edit->status->isDeletable()) {
                throw VideoEditStateConflictException::invalidState($edit->status);
            }

            $edit->load(['sources' => static fn (Builder $query) => $query->select(['id', 'video_edit_id', 'storage_path'])]);

            $beforeDelete($edit);
            $edit->delete();

            return $edit;
        });
    }

    public function failedWithExpiredSourcesUuids(DateTimeInterface $now, int $limit): array
    {
        return VideoEditEloquentModel::query()
            ->where('status', VideoEditStatus::Failed->value)
            ->whereNull('sources_purged_at')
            ->where('sources_expire_at', '<=', $now)
            ->orderBy('sources_expire_at')
            ->limit($limit)
            ->pluck('uuid')
            ->all();
    }

    public function completedWithRetainedSourcesUuids(int $limit): array
    {
        return VideoEditEloquentModel::query()
            ->where('status', VideoEditStatus::Completed->value)
            ->whereNull('sources_purged_at')
            ->whereHas('sources', static fn (Builder $query) => $query->whereNotNull('storage_path'))
            ->orderBy('completed_at')
            ->limit($limit)
            ->pluck('uuid')
            ->all();
    }

    public function staleProcessingUuids(DateTimeInterface $lastActivityBefore, int $limit): array
    {
        return VideoEditEloquentModel::query()
            ->where('status', VideoEditStatus::Processing->value)
            ->where('updated_at', '<', $lastActivityBefore)
            ->orderBy('updated_at')
            ->limit($limit)
            ->pluck('uuid')
            ->all();
    }

    public function expiredDraftUuids(DateTimeInterface $createdBefore, int $limit): array
    {
        return VideoEditEloquentModel::query()
            ->where('status', VideoEditStatus::Draft->value)
            ->where('created_at', '<', $createdBefore)
            ->orderBy('created_at')
            ->limit($limit)
            ->pluck('uuid')
            ->all();
    }

    public function deleteDraft(string $uuid, Closure $beforeDelete): bool
    {
        return DB::transaction(function () use ($uuid, $beforeDelete): bool {
            $edit = VideoEditEloquentModel::query()->where('uuid', $uuid)->lockForUpdate()->first();

            if ($edit === null || $edit->status !== VideoEditStatus::Draft) {
                return false;
            }

            $edit->load(['sources' => static fn (Builder $query) => $query->select(['id', 'video_edit_id', 'storage_path'])]);

            $beforeDelete($edit);
            $edit->delete();

            return true;
        });
    }

    /**
     * Relations needed by VideoEditDetailData and the pipeline, with explicit
     * columns and ordering (BACKEND-PHP §4.1).
     *
     * @return array<string, Closure|string>
     */
    private static function detailRelations(): array
    {
        return [
            'sources' => static fn (Builder $query) => $query->select(self::SOURCE_COLUMNS)->orderBy('position'),
            // Without the text: it can be 120 000 characters, and the only
            // consumer that wants it reads it through ScriptProviderPort. The
            // derived flag is what the pipeline needs — "has this been parsed
            // already" — without dragging the payload into every detail read.
            'script' => static fn (Builder $query) => $query
                ->select([
                    'id', 'video_edit_id', 'uuid', 'original_name', 'extension', 'declared_mime',
                    'declared_size_bytes', 'storage_path',
                ])
                ->selectRaw('(extracted_text IS NOT NULL) as has_extracted_text'),
            'appliedCuts' => static fn (Builder $query) => $query
                ->select(['id', 'video_edit_id', 'sequence', 'start_ms', 'end_ms', 'reasons', 'origins'])
                ->orderBy('sequence'),
            'cutDecisions' => static fn (Builder $query) => $query
                ->select(['id', 'video_edit_id', 'producer', 'reason', 'origin', 'start_ms', 'end_ms', 'confidence', 'evidence', 'outcome', 'rejection_reason', 'applied_cut_sequence'])
                ->orderBy('start_ms')
                ->orderBy('id'),
            'previousEdit' => static fn (Builder $query) => $query->select(['id', 'uuid']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function decisionRow(int $editId, ValidatedDecision $validated, mixed $now): array
    {
        $decision = $validated->decision;

        return [
            'video_edit_id' => $editId,
            'producer' => $decision->producer,
            'reason' => $decision->reason->value,
            'origin' => $decision->origin->value,
            'start_ms' => $decision->startMs,
            'end_ms' => $decision->endMs,
            'confidence' => $decision->confidence,
            'evidence' => $decision->evidence === null ? null : json_encode($decision->evidence, JSON_THROW_ON_ERROR),
            'outcome' => $validated->outcome->value,
            'rejection_reason' => $validated->rejectionReason?->value,
            'applied_cut_sequence' => $validated->appliedCutSequence,
            'created_at' => $now,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function appliedCutRow(int $editId, AppliedCut $cut, mixed $now): array
    {
        return [
            'video_edit_id' => $editId,
            'sequence' => $cut->sequence,
            'start_ms' => $cut->range->startMs,
            'end_ms' => $cut->range->endMs,
            'reasons' => json_encode(array_map(static fn (BackedEnum $reason): string => (string) $reason->value, $cut->reasons), JSON_THROW_ON_ERROR),
            'origins' => json_encode(array_map(static fn (BackedEnum $origin): string => (string) $origin->value, $cut->origins), JSON_THROW_ON_ERROR),
            'created_at' => $now,
        ];
    }

    /**
     * Query-builder updates bypass model casts, so encode casted shapes here.
     */
    private static function toColumnValue(mixed $value): mixed
    {
        return match (true) {
            $value instanceof BackedEnum => $value->value,
            is_array($value) => json_encode($value, JSON_THROW_ON_ERROR),
            default => $value,
        };
    }
}
