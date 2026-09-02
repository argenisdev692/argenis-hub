<?php

declare(strict_types=1);

namespace Modules\Availability\Application\Queries;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\Availability\Domain\Ports\AvailabilityRuleRepositoryPort;
use Modules\Availability\Infrastructure\Persistence\Eloquent\Models\AvailabilityRuleEloquentModel;

final readonly class GetAvailabilityRuleHandler
{
    public function __construct(private AvailabilityRuleRepositoryPort $rules) {}

    public function handle(string $uuid): AvailabilityRuleEloquentModel
    {
        return $this->rules->findByUuid($uuid)
            ?? throw (new ModelNotFoundException)->setModel(AvailabilityRuleEloquentModel::class, [$uuid]);
    }
}
