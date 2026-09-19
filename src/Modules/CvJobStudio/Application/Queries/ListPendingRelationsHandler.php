<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\Queries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\CvJobStudio\Application\DTOs\StudioSkillRelationData;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioSkillRelationEloquentModel;

/** Proposed skill relations awaiting the owner's confirm/reject (relations inbox). */
final readonly class ListPendingRelationsHandler
{
    /** @return LengthAwarePaginator<int, StudioSkillRelationData> */
    #[\NoDiscard]
    public function handle(int $userId, int $perPage = 15): LengthAwarePaginator
    {
        return StudioSkillRelationEloquentModel::query()
            ->where('user_id', $userId)
            ->where('status', 'pending')
            ->select(['id', 'uuid', 'from_skill', 'to_skill', 'kind', 'origin', 'status', 'created_at'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->through(static fn (StudioSkillRelationEloquentModel $relation): StudioSkillRelationData => new StudioSkillRelationData(
                uuid: $relation->uuid,
                fromSkill: $relation->from_skill,
                toSkill: $relation->to_skill,
                kind: $relation->kind,
                origin: $relation->origin,
                status: $relation->status,
                createdAt: $relation->created_at?->toIso8601String(),
            ));
    }
}
