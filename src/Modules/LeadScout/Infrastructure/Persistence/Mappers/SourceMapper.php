<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Persistence\Mappers;

use Modules\LeadScout\Domain\Entities\Source;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutSourceEloquentModel;

final readonly class SourceMapper
{
    public static function toEntity(ScoutSourceEloquentModel $model): Source
    {
        return new Source(
            id: $model->id,
            uuid: $model->uuid,
            name: $model->name,
            type: $model->type,
            country: $model->country,
            accessMethod: (string) $model->access_method,
            frequencyMinutes: (int) $model->frequency_minutes,
            priority: (int) $model->priority,
            status: $model->status,
            lastRunAt: $model->last_run_at?->toDateTimeImmutable(),
            lastCursor: $model->last_cursor,
            termsReviewedAt: $model->terms_reviewed_at?->toDateTimeImmutable(),
        );
    }
}
