<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\Queries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\CvJobStudio\Application\DTOs\StudioApplicationData;
use Modules\CvJobStudio\Domain\Ports\StudioApplicationRepositoryPort;

/** The owner's applications, most recently touched first (Applications board). */
final readonly class ListApplicationsHandler
{
    public function __construct(private StudioApplicationRepositoryPort $applications) {}

    /** @return LengthAwarePaginator<int, StudioApplicationData> */
    #[\NoDiscard]
    public function handle(int $userId, int $perPage): LengthAwarePaginator
    {
        return $this->applications->paginate($userId, $perPage);
    }
}
