<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Domain\Ports;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\CvJobStudio\Application\DTOs\StudioSkillRelationData;

/** The relations inbox (T-124, FR-44): proposals only earn credit once confirmed. */
interface StudioSkillRelationRepositoryPort
{
    /** @return LengthAwarePaginator<int, StudioSkillRelationData> */
    public function paginatePending(int $userId, int $perPage): LengthAwarePaginator;

    /**
     * Audited status change (T-091) — `confirmed` or `rejected`.
     *
     * @return bool false when the relation is not the owner's
     */
    public function setStatus(string $uuid, int $userId, string $status): bool;
}
