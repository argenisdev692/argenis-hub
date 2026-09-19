<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Persistence\Repositories;

use DateTimeImmutable;
use Modules\LeadScout\Domain\Entities\DecisionRule;
use Modules\LeadScout\Domain\Enums\DecisionOutcome;
use Modules\LeadScout\Domain\Ports\DecisionRuleRepositoryPort;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutDecisionRuleEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Mappers\DecisionRuleMapper;

final readonly class EloquentDecisionRuleRepository implements DecisionRuleRepositoryPort
{
    public function create(int $sampleSize, int $windowDays, array $thresholds): DecisionRule
    {
        return DecisionRuleMapper::toEntity(ScoutDecisionRuleEloquentModel::query()->create([
            'sample_size' => $sampleSize,
            'window_days' => $windowDays,
            'thresholds' => $thresholds,
        ]));
    }

    public function byUuid(string $uuid): ?DecisionRule
    {
        $model = ScoutDecisionRuleEloquentModel::query()->where('uuid', $uuid)->first();

        return $model === null ? null : DecisionRuleMapper::toEntity($model);
    }

    public function latestLocked(): ?DecisionRule
    {
        $model = ScoutDecisionRuleEloquentModel::query()->whereNotNull('locked_at')->orderByDesc('locked_at')->first();

        return $model === null ? null : DecisionRuleMapper::toEntity($model);
    }

    public function lock(
        DecisionRule $rule,
        DateTimeImmutable $lockedAt,
        DateTimeImmutable $periodEndsAt,
        DecisionOutcome $result,
    ): DecisionRule {
        $model = ScoutDecisionRuleEloquentModel::query()->findOrFail($rule->id);
        $model->update([
            'locked_at' => $lockedAt,
            'period_starts_at' => $lockedAt->format('Y-m-d'),
            'period_ends_at' => $periodEndsAt->format('Y-m-d'),
            'result' => $result->value,
        ]);

        return DecisionRuleMapper::toEntity($model->refresh());
    }
}
