<?php

declare(strict_types=1);

namespace Modules\Availability\Application\Commands;

use Illuminate\Support\Facades\DB;
use Modules\Availability\Domain\Ports\AvailabilityRuleRepositoryPort;
use Shared\Application\DTOs\BulkUuidsData;

/**
 * Restores a set of soft-deleted weekly availability rules by UUID. Authorization
 * (permission:BULK_RESTORE_AVAILABILITY_RULES) is enforced at the route.
 */
final readonly class BulkRestoreAvailabilityRulesHandler
{
    public function __construct(private AvailabilityRuleRepositoryPort $rules) {}

    public function handle(BulkUuidsData $data): int
    {
        return DB::transaction(fn () => $this->rules->bulkRestoreByUuid($data->uuids));
    }
}
