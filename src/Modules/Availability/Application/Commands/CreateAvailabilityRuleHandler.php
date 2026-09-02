<?php

declare(strict_types=1);

namespace Modules\Availability\Application\Commands;

use Illuminate\Support\Facades\DB;
use Modules\Availability\Application\DTOs\AvailabilityRuleData;
use Modules\Availability\Domain\Ports\AvailabilityRuleRepositoryPort;
use Modules\Availability\Infrastructure\Persistence\Eloquent\Models\AvailabilityRuleEloquentModel;

/**
 * Persists a new weekly availability rule. Authorization
 * (permission:CREATE_AVAILABILITY_RULES) is enforced at the route.
 */
final readonly class CreateAvailabilityRuleHandler
{
    public function __construct(private AvailabilityRuleRepositoryPort $rules) {}

    #[\NoDiscard]
    public function handle(AvailabilityRuleData $data): AvailabilityRuleEloquentModel
    {
        return DB::transaction(fn () => $this->rules->create([
            'day_of_week' => $data->dayOfWeek,
            'start_time' => $data->startTime,
            'end_time' => $data->endTime,
            'is_available' => $data->isAvailable,
        ]));
    }
}
