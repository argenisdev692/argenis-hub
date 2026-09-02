<?php

declare(strict_types=1);

namespace Modules\Availability\Application\Commands;

use Illuminate\Support\Facades\DB;
use Modules\Availability\Domain\Ports\AvailabilityRuleRepositoryPort;

/**
 * Restores a soft-deleted weekly availability rule by UUID. Authorization
 * (permission:RESTORE_AVAILABILITY_RULES) is enforced at the route.
 */
final readonly class RestoreAvailabilityRuleHandler
{
    public function __construct(private AvailabilityRuleRepositoryPort $rules) {}

    public function handle(string $uuid): bool
    {
        return DB::transaction(fn () => $this->rules->restore($uuid));
    }
}
