<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\Queries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\CvJobStudio\Domain\Ports\StudioProfileRepositoryPort;

final readonly class ListProfilesHandler
{
    public function __construct(private StudioProfileRepositoryPort $profiles) {}

    #[\NoDiscard]
    public function handle(int $userId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->profiles->paginate($userId, $perPage);
    }
}
