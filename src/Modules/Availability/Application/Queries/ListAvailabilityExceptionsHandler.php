<?php

declare(strict_types=1);

namespace Modules\Availability\Application\Queries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Availability\Application\DTOs\AvailabilityExceptionFilterData;
use Modules\Availability\Domain\Ports\AvailabilityExceptionRepositoryPort;

final readonly class ListAvailabilityExceptionsHandler
{
    public function __construct(private AvailabilityExceptionRepositoryPort $exceptions) {}

    public function handle(AvailabilityExceptionFilterData $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->exceptions->paginate($filters, $perPage);
    }
}
