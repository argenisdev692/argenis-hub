<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\Queries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\CvJobStudio\Application\DTOs\StudioCvVersionData;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioCvVersionEloquentModel;

/** The owner's generated CV versions, newest first (Versions page). */
final readonly class ListCvVersionsHandler
{
    /** @return LengthAwarePaginator<int, StudioCvVersionData> */
    #[\NoDiscard]
    public function handle(int $userId, int $perPage): LengthAwarePaginator
    {
        return StudioCvVersionEloquentModel::query()
            ->ownedBy($userId)
            ->select(['id', 'uuid', 'purpose', 'language', 'created_at'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(min(max($perPage, 1), 100))
            ->through(static fn (StudioCvVersionEloquentModel $version): StudioCvVersionData => new StudioCvVersionData(
                uuid: $version->uuid,
                purpose: $version->purpose,
                language: $version->language,
                createdAt: $version->created_at?->toIso8601String(),
            ));
    }
}
