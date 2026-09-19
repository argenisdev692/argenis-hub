<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\Queries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\CvJobStudio\Application\DTOs\StudioApplicationData;
use Modules\CvJobStudio\Application\DTOs\StudioApplicationPostingData;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioApplicationEloquentModel;

/** The owner's applications, most recently touched first (Applications board). */
final readonly class ListApplicationsHandler
{
    /** @return LengthAwarePaginator<int, StudioApplicationData> */
    #[\NoDiscard]
    public function handle(int $userId, int $perPage): LengthAwarePaginator
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
}
