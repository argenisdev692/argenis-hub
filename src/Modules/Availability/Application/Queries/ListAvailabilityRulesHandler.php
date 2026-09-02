<?php

declare(strict_types=1);

namespace Modules\Availability\Application\Queries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Availability\Application\DTOs\AvailabilityRuleFilterData;
use Modules\Availability\Domain\Ports\AvailabilityRuleRepositoryPort;

final readonly class ListAvailabilityRulesHandler
{
    public function __construct(private AvailabilityRuleRepositoryPort $rules) {}

    public function handle(AvailabilityRuleFilterData $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->rules->paginate($filters, $perPage);
    }
}
