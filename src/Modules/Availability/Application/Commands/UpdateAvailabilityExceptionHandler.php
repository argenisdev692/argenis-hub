<?php

declare(strict_types=1);

namespace Modules\Availability\Application\Commands;

use Illuminate\Support\Facades\DB;
use Modules\Availability\Application\DTOs\AvailabilityExceptionData;
use Modules\Availability\Domain\Ports\AvailabilityExceptionRepositoryPort;
use Modules\Availability\Domain\ValueObjects\ExceptionSource;
use Modules\Availability\Infrastructure\Persistence\Eloquent\Models\AvailabilityExceptionEloquentModel;

/**
 * Updates an existing date exception. Switching to a closure clears the hours so
 * a stale open window never lingers. Any hand-edit also reclassifies the row as
 * `Manual` so a later holiday resync can no longer purge a user's changes.
 * Authorization (permission:UPDATE_AVAILABILITY_EXCEPTIONS) is enforced at the route.
 */
final readonly class UpdateAvailabilityExceptionHandler
{
    public function __construct(private AvailabilityExceptionRepositoryPort $exceptions) {}

    #[\NoDiscard]
    public function handle(AvailabilityExceptionEloquentModel $exception, AvailabilityExceptionData $data): AvailabilityExceptionEloquentModel
    {
        return DB::transaction(fn () => $this->exceptions->update($exception, [
            'date' => $data->date,
            'is_available' => $data->isAvailable,
            'start_time' => $data->isAvailable ? $data->startTime : null,
            'end_time' => $data->isAvailable ? $data->endTime : null,
            'reason' => $data->reason,
            'source' => ExceptionSource::Manual,
        ]));
    }
}
