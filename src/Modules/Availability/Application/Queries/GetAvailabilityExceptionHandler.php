<?php

declare(strict_types=1);

namespace Modules\Availability\Application\Queries;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\Availability\Domain\Ports\AvailabilityExceptionRepositoryPort;
use Modules\Availability\Infrastructure\Persistence\Eloquent\Models\AvailabilityExceptionEloquentModel;

final readonly class GetAvailabilityExceptionHandler
{
    public function __construct(private AvailabilityExceptionRepositoryPort $exceptions) {}

    public function handle(string $uuid): AvailabilityExceptionEloquentModel
    {
        return $this->exceptions->findByUuid($uuid)
            ?? throw (new ModelNotFoundException)->setModel(AvailabilityExceptionEloquentModel::class, [$uuid]);
    }
}
