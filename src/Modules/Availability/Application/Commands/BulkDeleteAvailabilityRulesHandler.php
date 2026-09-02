<?php

declare(strict_types=1);

namespace Modules\Availability\Application\Commands;

use Illuminate\Support\Facades\DB;
use Modules\Availability\Domain\Ports\AvailabilityRuleRepositoryPort;
use Shared\Application\DTOs\BulkUuidsData;

/**
 * Soft-deletes a set of weekly availability rules by UUID. Authorization
 * (permission:BULK_DELETE_AVAILABILITY_RULES) is enforced at the route.
 */
final readonly class BulkDeleteAvailabilityRulesHandler
{
    public function __construct(private AvailabilityRuleRepositoryPort $rules) {}

    public function handle(BulkUuidsData $data): int
    {
        return DB::transaction(fn () => $this->rules->bulkSoftDeleteByUuid($data->uuids));
    }
}
