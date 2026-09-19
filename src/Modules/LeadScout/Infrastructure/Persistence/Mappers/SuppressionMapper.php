<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Persistence\Mappers;

use Modules\LeadScout\Domain\Entities\Suppression;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutSuppressionEloquentModel;

final readonly class SuppressionMapper
{
    public static function toEntity(ScoutSuppressionEloquentModel $model): Suppression
    {
        return new Suppression(
            id: $model->id,
            uuid: $model->uuid,
            canonicalDomain: $model->canonical_domain,
            taxId: $model->tax_id,
            name: $model->name,
            source: $model->source,
            reason: $model->reason,
            listPeriod: $model->list_period,
            createdAt: $model->created_at?->toDateTimeImmutable(),
        );
    }
}
