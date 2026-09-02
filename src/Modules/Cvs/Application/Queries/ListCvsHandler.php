<?php

declare(strict_types=1);

namespace Modules\Cvs\Application\Queries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Cvs\Application\DTOs\CvFilterData;
use Modules\Cvs\Domain\Ports\CvRepositoryPort;

final readonly class ListCvsHandler
{
    public function __construct(private CvRepositoryPort $cvs) {}

    #[\NoDiscard]
    public function handle(CvFilterData $filters, int $userId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->cvs->paginate($filters, $perPage, $userId);
    }
}
