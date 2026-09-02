<?php

declare(strict_types=1);

namespace Modules\Availability\Application\Commands;

use Illuminate\Support\Facades\DB;
use Modules\Availability\Domain\Ports\AvailabilityExceptionRepositoryPort;
use Shared\Application\DTOs\BulkUuidsData;

/**
 * Soft-deletes a set of date exceptions by UUID. Authorization
 * (permission:BULK_DELETE_AVAILABILITY_EXCEPTIONS) is enforced at the route.
 */
final readonly class BulkDeleteAvailabilityExceptionsHandler
{
    public function __construct(private AvailabilityExceptionRepositoryPort $exceptions) {}

    public function handle(BulkUuidsData $data): int
    {
        return DB::transaction(fn () => $this->exceptions->bulkSoftDeleteByUuid($data->uuids));
    }
}
