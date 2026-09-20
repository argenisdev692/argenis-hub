<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\Queries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\CvJobStudio\Application\DTOs\StudioCvVersionData;
use Modules\CvJobStudio\Domain\Ports\StudioCvRepositoryPort;

/** The owner's generated CV versions, newest first (Versions page). */
final readonly class ListCvVersionsHandler
{
    public function __construct(private StudioCvRepositoryPort $cvs) {}

    /** @return LengthAwarePaginator<int, StudioCvVersionData> */
    #[\NoDiscard]
    public function handle(int $userId, int $perPage): LengthAwarePaginator
    {
        return $this->cvs->paginateVersions($userId, $perPage);
    }
}
