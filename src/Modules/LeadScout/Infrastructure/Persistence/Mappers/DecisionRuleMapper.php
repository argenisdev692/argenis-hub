<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Persistence\Mappers;

use Modules\LeadScout\Domain\Entities\DecisionRule;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutDecisionRuleEloquentModel;

final readonly class DecisionRuleMapper
{
    public static function toEntity(ScoutDecisionRuleEloquentModel $model): DecisionRule
    {
        return new DecisionRule(
            id: $model->id,
            uuid: $model->uuid,
            sampleSize: (int) $model->sample_size,
            windowDays: (int) $model->window_days,
            thresholds: $model->thresholds ?? ['scale_at' => 0.05, 'stop_below' => 0.01],
            lockedAt: $model->locked_at?->toDateTimeImmutable(),
            result: $model->result,
        );
    }
}
