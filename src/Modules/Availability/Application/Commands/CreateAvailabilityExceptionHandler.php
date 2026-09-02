<?php

declare(strict_types=1);

namespace Modules\Availability\Application\Commands;

use Illuminate\Support\Facades\DB;
use Modules\Availability\Application\DTOs\AvailabilityExceptionData;
use Modules\Availability\Domain\Ports\AvailabilityExceptionRepositoryPort;
use Modules\Availability\Infrastructure\Persistence\Eloquent\Models\AvailabilityExceptionEloquentModel;

/**
 * Persists a new date exception. Hours are stored only for a forced-open day; a
 * closure keeps them null. Authorization
 * (permission:CREATE_AVAILABILITY_EXCEPTIONS) is enforced at the route.
 */
final readonly class CreateAvailabilityExceptionHandler
{
    public function __construct(private AvailabilityExceptionRepositoryPort $exceptions) {}

    #[\NoDiscard]
    public function handle(AvailabilityExceptionData $data): AvailabilityExceptionEloquentModel
    {
        return DB::transaction(fn () => $this->exceptions->create($this->attributes($data)));
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(AvailabilityExceptionData $data): array
    {
        return [
            'date' => $data->date,
            'is_available' => $data->isAvailable,
            'start_time' => $data->isAvailable ? $data->startTime : null,
            'end_time' => $data->isAvailable ? $data->endTime : null,
            'reason' => $data->reason,
        ];
    }
}
