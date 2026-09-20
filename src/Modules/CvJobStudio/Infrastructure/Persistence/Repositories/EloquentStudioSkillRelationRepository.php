<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Persistence\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Modules\CvJobStudio\Application\DTOs\StudioSkillRelationData;
use Modules\CvJobStudio\Domain\Ports\StudioSkillRelationRepositoryPort;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioSkillRelationEloquentModel;

final readonly class EloquentStudioSkillRelationRepository implements StudioSkillRelationRepositoryPort
{
    public function paginatePending(int $userId, int $perPage): LengthAwarePaginator
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

    /**
     * Instance update, never a mass query: a mass `update()` skips
     * `LogsActivity`, and confirmation is the audited business action (T-091).
     */
    public function setStatus(string $uuid, int $userId, string $status): bool
    {
        return DB::transaction(static fn (): bool => (bool) StudioSkillRelationEloquentModel::query()
            ->where('user_id', $userId)
            ->where('uuid', $uuid)
            ->lockForUpdate()
            ->first()
            ?->update(['status' => $status]));
    }
}
