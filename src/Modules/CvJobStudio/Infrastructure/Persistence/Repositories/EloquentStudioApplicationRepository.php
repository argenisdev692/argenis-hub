<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Persistence\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\CvJobStudio\Application\DTOs\StudioApplicationData;
use Modules\CvJobStudio\Application\DTOs\StudioApplicationPostingData;
use Modules\CvJobStudio\Domain\Ports\StudioApplicationRepositoryPort;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioApplicationEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioChannelBaselineEloquentModel;

/**
 * Outcomes and baselines are written through model instances so the
 * `LogsActivity` trail on both aggregates records every change.
 */
final readonly class EloquentStudioApplicationRepository implements StudioApplicationRepositoryPort
{
    public function paginate(int $userId, int $perPage): LengthAwarePaginator
    {
        return StudioApplicationEloquentModel::query()
            ->ownedBy($userId)
            ->with(['posting:id,uuid,title,employer_name,status'])
            ->select(['id', 'uuid', 'posting_id', 'status', 'applied_at', 'outcome', 'outcome_at'])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate(min(max($perPage, 1), 100))
            ->through(static fn (StudioApplicationEloquentModel $application): StudioApplicationData => new StudioApplicationData(
                uuid: $application->uuid,
                status: $application->status,
                outcome: $application->outcome,
                appliedAt: $application->applied_at?->toIso8601String(),
                outcomeAt: $application->outcome_at?->toIso8601String(),
                posting: $application->posting === null ? null : new StudioApplicationPostingData(
                    uuid: $application->posting->uuid,
                    title: $application->posting->title,
                    employerName: $application->posting->employer_name,
                    status: $application->posting->status,
                ),
            ));
    }

    public function recordOutcome(int $postingId, int $userId, string $outcome, ?string $note): void
    {
        StudioApplicationEloquentModel::query()->updateOrCreate(
            ['posting_id' => $postingId, 'user_id' => $userId],
            ['outcome' => $outcome, 'outcome_at' => now(), 'outcome_note' => $note],
        );
    }

    public function channelOutcomes(int $userId): array
    {
        return StudioApplicationEloquentModel::query()
            ->ownedBy($userId)
            ->with('posting:id,discovery_channel')
            ->get(['id', 'posting_id', 'outcome'])
            ->map(static fn (StudioApplicationEloquentModel $application): array => [
                'bucket' => $application->posting?->discovery_channel,
                'outcome' => $application->outcome,
            ])
            ->values()
            ->all();
    }

    public function requirementOutcomes(int $userId): array
    {
        return StudioApplicationEloquentModel::query()
            ->ownedBy($userId)
            ->with(['posting:id', 'posting.requirements:id,posting_id,canonical_name'])
            ->get(['id', 'posting_id', 'outcome'])
            ->flatMap(static fn (StudioApplicationEloquentModel $application) => ($application->posting?->requirements ?? collect())->map(
                static fn ($requirement): array => [
                    'requirement' => (string) $requirement->canonical_name,
                    'outcome' => $application->outcome,
                ],
            ))
            ->values()
            ->all();
    }

    public function lockChannelBaselines(int $userId, array $buckets): array
    {
        if ($buckets === []) {
            return [];
        }

        return StudioChannelBaselineEloquentModel::query()
            ->where('user_id', $userId)
            ->where('bucket_kind', 'channel')
            ->whereIn('bucket', $buckets)
            ->lockForUpdate()
            ->pluck('policy_value', 'bucket')
            ->map(static fn (mixed $value): float => (float) $value)
            ->all();
    }

    public function updateChannelBaseline(int $userId, string $bucket, int $positives, int $trials, bool $gatePassed): void
    {
        $baseline = StudioChannelBaselineEloquentModel::query()
            ->where('user_id', $userId)
            ->where('bucket_kind', 'channel')
            ->where('bucket', $bucket)
            ->first();

        $baseline?->update([
            'positives' => $positives,
            'trials' => $trials,
            'applied_from' => $gatePassed ? now() : $baseline->applied_from,
            'gate_passed_at' => $gatePassed ? now() : $baseline->gate_passed_at,
        ]);
    }
}
