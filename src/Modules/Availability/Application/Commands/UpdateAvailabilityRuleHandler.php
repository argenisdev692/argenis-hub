<?php

declare(strict_types=1);

namespace Modules\Availability\Application\Commands;

use Illuminate\Support\Facades\DB;
use Modules\Availability\Application\DTOs\AvailabilityRuleData;
use Modules\Availability\Domain\Ports\AvailabilityRuleRepositoryPort;
use Modules\Availability\Infrastructure\Persistence\Eloquent\Models\AvailabilityRuleEloquentModel;

/**
 * Updates an existing weekly availability rule. Authorization
 * (permission:UPDATE_AVAILABILITY_RULES) is enforced at the route.
 */
final readonly class UpdateAvailabilityRuleHandler
{
    public function __construct(private AvailabilityRuleRepositoryPort $rules) {}

    #[\NoDiscard]
    public function handle(AvailabilityRuleEloquentModel $rule, AvailabilityRuleData $data): AvailabilityRuleEloquentModel
    {
        return DB::transaction(fn () => $this->rules->update($rule, [
            'day_of_week' => $data->dayOfWeek,
            'start_time' => $data->startTime,
            'end_time' => $data->endTime,
            'is_available' => $data->isAvailable,
        ]));
    }
}
