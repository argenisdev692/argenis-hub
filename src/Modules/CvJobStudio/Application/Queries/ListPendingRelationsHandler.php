<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\Queries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\CvJobStudio\Application\DTOs\StudioSkillRelationData;
use Modules\CvJobStudio\Domain\Ports\StudioSkillRelationRepositoryPort;

/** Proposed skill relations awaiting the owner's confirm/reject (relations inbox). */
final readonly class ListPendingRelationsHandler
{
    public function __construct(private StudioSkillRelationRepositoryPort $relations) {}

    /** @return LengthAwarePaginator<int, StudioSkillRelationData> */
    #[\NoDiscard]
    public function handle(int $userId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->relations->paginatePending($userId, min(max($perPage, 1), 100));
    }
}
