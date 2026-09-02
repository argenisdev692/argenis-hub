<?php

declare(strict_types=1);

namespace Modules\Availability\Application\Commands;

use Illuminate\Support\Facades\DB;
use Modules\Availability\Domain\Ports\AvailabilityExceptionRepositoryPort;
use Shared\Application\DTOs\BulkUuidsData;

/**
 * Restores a set of soft-deleted date exceptions by UUID. Authorization
 * (permission:BULK_RESTORE_AVAILABILITY_EXCEPTIONS) is enforced at the route.
 */
final readonly class BulkRestoreAvailabilityExceptionsHandler
{
    public function __construct(private AvailabilityExceptionRepositoryPort $exceptions) {}

    public function handle(BulkUuidsData $data): int
    {
        return DB::transaction(fn () => $this->exceptions->bulkRestoreByUuid($data->uuids));
    }
}
